<?php

declare(strict_types=1);

/**
 * The composition root.
 *
 * This is the only file that knows both the ports and the concrete adapters behind
 * them, which is why it lives outside src/ — deptrac scans src/, and a composition
 * root is precisely the place where the layer rules stop applying. Nothing in
 * src/Http imports anything from src/Infrastructure.
 */

use DI\ContainerBuilder;
use MathDeck\Application\EventSerializer;
use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ProjectionCursors;
use MathDeck\Analytics\Port\PseudonymResolver;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Application\Port\DeckCatalog;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\Port\MatchIdentityFactory;
use MathDeck\Application\Port\MatchStore;
use MathDeck\Authoring\Lint\BalanceLinter;
use MathDeck\Authoring\Lint\DeckLinter;
use MathDeck\Authoring\Lint\SchemaValidator;
use MathDeck\Authoring\Port\DeckIdFactory;
use MathDeck\Authoring\Port\DeckStore;
use MathDeck\Engine\Clock;
use MathDeck\Engine\SystemClock;
use MathDeck\Identity\Port\AccountStore;
use MathDeck\Identity\Port\SignInAttempts;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Infrastructure\Analytics\AccountPseudonymResolver;
use MathDeck\Infrastructure\Deck\PublishedDeckCatalog;
use MathDeck\Infrastructure\Deck\SlugDeckIdFactory;
use MathDeck\Infrastructure\Mysql\Connection;
use MathDeck\Infrastructure\Mysql\MysqlAccountStore;
use MathDeck\Infrastructure\Mysql\MysqlAttemptStore;
use MathDeck\Infrastructure\Mysql\MysqlDeckStore;
use MathDeck\Infrastructure\Mysql\MysqlEventStore;
use MathDeck\Infrastructure\Mysql\MysqlMatchStore;
use MathDeck\Infrastructure\Mysql\MysqlProjectionCursors;
use MathDeck\Infrastructure\Mysql\MysqlSignInAttempts;
use MathDeck\Infrastructure\Mysql\MysqlReportQueries;
use MathDeck\Infrastructure\Random\RandomMatchIdentityFactory;
use Psr\Container\ContainerInterface;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    Clock::class => DI\autowire(SystemClock::class),
    MatchIdentityFactory::class => DI\autowire(RandomMatchIdentityFactory::class),
    DeckIdFactory::class => DI\autowire(SlugDeckIdFactory::class),
    SchemaValidator::class => static fn (): SchemaValidator => SchemaValidator::default(),
    BalanceLinter::class => static fn (): BalanceLinter => new BalanceLinter(),

    // Matches read decks through the authoring store; a draft can never start one.
    DeckCatalog::class => static fn (ContainerInterface $c): DeckCatalog => new PublishedDeckCatalog(
        $c->get(DeckStore::class),
    ),

    DeckStore::class => static fn (ContainerInterface $c): DeckStore => new MysqlDeckStore($c->get(PDO::class)),

    AttemptStore::class => static fn (ContainerInterface $c): AttemptStore => new MysqlAttemptStore(
        $c->get(PDO::class),
    ),
    ReportQueries::class => static fn (ContainerInterface $c): ReportQueries => new MysqlReportQueries(
        $c->get(PDO::class),
    ),
    ProjectionCursors::class => static fn (ContainerInterface $c): ProjectionCursors => new MysqlProjectionCursors(
        $c->get(PDO::class),
    ),
    AccountStore::class => static fn (ContainerInterface $c): AccountStore => new MysqlAccountStore(
        $c->get(PDO::class),
    ),
    SignInAttempts::class => static fn (ContainerInterface $c): SignInAttempts => new MysqlSignInAttempts(
        $c->get(PDO::class),
    ),
    PseudonymResolver::class => static fn (ContainerInterface $c): PseudonymResolver => new AccountPseudonymResolver(
        $c->get(AccountStore::class),
    ),

    // No default secret on purpose: a deployment that forgot to set one must fail
    // at boot rather than sign every token with the empty string.
    TokenIssuer::class => static function (ContainerInterface $c): TokenIssuer {
        $secret = getenv('MATCHDECK_TOKEN_SECRET');

        if (!is_string($secret) || $secret === '') {
            throw new RuntimeException('MATCHDECK_TOKEN_SECRET is not configured.');
        }

        return new TokenIssuer($secret, $c->get(Clock::class));
    },

    PDO::class => static function (): PDO {
        $connection = Connection::fromEnvironment();

        if ($connection === null) {
            throw new RuntimeException('MATCHDECK_DSN is not configured.');
        }

        return $connection;
    },

    EventStore::class => static fn (ContainerInterface $c): EventStore => new MysqlEventStore(
        $c->get(PDO::class),
        $c->get(EventSerializer::class),
    ),

    MatchStore::class => static fn (ContainerInterface $c): MatchStore => new MysqlMatchStore(
        $c->get(PDO::class),
    ),
]);

return $builder->build();
