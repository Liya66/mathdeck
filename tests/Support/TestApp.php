<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use DI\ContainerBuilder;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;
use MathDeck\Analytics\AttemptProjector;
use MathDeck\Analytics\Port\AttemptStore;
use MathDeck\Analytics\Port\ProjectionCursors;
use MathDeck\Analytics\Port\PseudonymResolver;
use MathDeck\Analytics\Port\ReportQueries;
use MathDeck\Analytics\ProjectionRunner;
use MathDeck\Application\Port\DeckCatalog;
use MathDeck\Application\Port\EventStore;
use MathDeck\Application\Port\MatchIdentityFactory;
use MathDeck\Application\Port\MatchStore;
use MathDeck\Authoring\Lint\BalanceLinter;
use MathDeck\Authoring\Lint\SchemaValidator;
use MathDeck\Authoring\Port\DeckIdFactory;
use MathDeck\Authoring\Port\DeckStore;
use MathDeck\Engine\Clock;
use MathDeck\Identity\AccountFactory;
use MathDeck\Identity\PasswordHasher;
use MathDeck\Identity\Port\AccountStore;
use MathDeck\Identity\Port\SignInAttempts;
use MathDeck\Identity\Role;
use MathDeck\Identity\SignInThrottle;
use MathDeck\Identity\TokenIssuer;
use MathDeck\Http\AppFactory;
use MathDeck\Infrastructure\InMemory\InMemoryEventStore;
use MathDeck\Infrastructure\Analytics\AccountPseudonymResolver;
use MathDeck\Infrastructure\Deck\PublishedDeckCatalog;
use MathDeck\Infrastructure\InMemory\InMemoryAttemptStore;
use MathDeck\Infrastructure\InMemory\InMemoryProjectionCursors;
use MathDeck\Infrastructure\InMemory\InMemorySignInAttempts;
use MathDeck\Infrastructure\InMemory\InMemoryAccountStore;
use MathDeck\Infrastructure\InMemory\InMemoryDeckStore;
use MathDeck\Infrastructure\InMemory\InMemoryMatchStore;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Runs the real application in-process: same routes, same middleware, same
 * container wiring, no socket and no server.
 *
 * Every request and response is validated against openapi.yaml as a side effect, so
 * the spec cannot drift from the implementation without a test going red. A spec
 * written and then forgotten is documentation; a spec in the test path is a
 * contract.
 */
final class TestApp
{
    private const ROUTES = [
        '#^/v1/matches$#' => '/v1/matches',
        '#^/v1/matches/[^/]+$#' => '/v1/matches/{matchId}',
        '#^/v1/matches/[^/]+/commands$#' => '/v1/matches/{matchId}/commands',
        '#^/v1/matches/[^/]+/events$#' => '/v1/matches/{matchId}/events',
        '#^/v1/deck-schema$#' => '/v1/deck-schema',
        '#^/v1/decks$#' => '/v1/decks',
        '#^/v1/decks/lint$#' => '/v1/decks/lint',
        '#^/v1/decks/[^/]+/publish$#' => '/v1/decks/{deckVersionId}/publish',
        '#^/v1/decks/[^/]+/fork$#' => '/v1/decks/{deckVersionId}/fork',
        '#^/v1/decks/[^/]+$#' => '/v1/decks/{deckVersionId}',
        '#^/v1/tokens$#' => '/v1/tokens',
        '#^/v1/reports/overview#' => '/v1/reports/overview',
        '#^/v1/reports/progression#' => '/v1/reports/progression',
        '#^/v1/reports/errors#' => '/v1/reports/errors',
        '#^/v1/reports/latency#' => '/v1/reports/latency',
    ];

    /** @var App<\Psr\Container\ContainerInterface|null> */
    private App $app;
    private InMemoryProjectionCursors $cursors;
    public readonly InMemoryAccountStore $accounts;
    public readonly InMemorySignInAttempts $signInAttempts;
    private TokenIssuer $tokens;
    private AccountFactory $people;
    private ValidatorBuilder $validator;

    public function __construct(
        public readonly FrozenClock $clock = new FrozenClock(),
        public readonly InMemoryEventStore $events = new InMemoryEventStore(),
        public readonly InMemoryMatchStore $matches = new InMemoryMatchStore(),
        public readonly MatchIdentityFactory $identity = new FixedMatchIdentityFactory(),
        public readonly InMemoryDeckStore $decks = new InMemoryDeckStore(),
        public readonly InMemoryAttemptStore $attempts = new InMemoryAttemptStore(),
        /** @var list<string> */
        public readonly array $teachers = ['miss-lee', 'mr-adeyemi'],
        // Small enough that a test can reach the limit without paying for a dozen
        // argon2 verifications.
        public readonly int $maxSignInFailures = 3,
    ) {
        $this->cursors = new InMemoryProjectionCursors();
        $this->accounts = new InMemoryAccountStore();
        $this->signInAttempts = new InMemorySignInAttempts();
        $this->tokens = new TokenIssuer(str_repeat('test-secret-', 4), $this->clock);
        $this->people = new AccountFactory(new PasswordHasher(), $this->clock);

        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            Clock::class => $this->clock,
            EventStore::class => $this->events,
            MatchStore::class => $this->matches,
            DeckStore::class => $this->decks,
            DeckCatalog::class => new PublishedDeckCatalog($this->decks),
            DeckIdFactory::class => new FixedDeckIdFactory(),
            SchemaValidator::class => SchemaValidator::default(),
            BalanceLinter::class => new BalanceLinter(),
            AttemptStore::class => $this->attempts,
            ReportQueries::class => $this->attempts,
            ProjectionCursors::class => $this->cursors,
            AccountStore::class => $this->accounts,
            PseudonymResolver::class => new AccountPseudonymResolver($this->accounts),
            TokenIssuer::class => $this->tokens,
            SignInAttempts::class => $this->signInAttempts,
            SignInThrottle::class => new SignInThrottle(
                $this->signInAttempts,
                $this->clock,
                maxPerAccount: $this->maxSignInFailures,
                maxPerAddress: $this->maxSignInFailures * 3,
            ),
            MatchIdentityFactory::class => $this->identity,
        ]);

        // Every app under test starts with the starter deck published, the same way
        // a fresh database does after its migrations.
        $this->decks->seedStarterDeck();

        $this->app = AppFactory::create($builder->build(), debug: true);
        $this->validator = (new ValidatorBuilder())->fromYamlFile(dirname(__DIR__, 2) . '/openapi.yaml');
    }

    /**
     * A signed token for a player, creating the account on first use.
     *
     * Every functional test goes through the real token path — there is no test-only
     * bypass of authentication, because a bypass is exactly the thing that lets an
     * authentication bug ship green.
     *
     * @return array<string, string>
     */
    public function authAs(string $playerId): array
    {
        if ($this->accounts->find($playerId) === null) {
            $this->accounts->save($this->people->create(
                $playerId,
                ucfirst($playerId),
                in_array($playerId, $this->teachers, strict: true) ? Role::Teacher : Role::Student,
                'passcode-' . $playerId,
            ));
        }

        $account = $this->accounts->find($playerId);

        if ($account === null) {
            throw new \LogicException('Account vanished.');
        }

        return ['Authorization' => 'Bearer ' . $this->tokens->issue($account)->value];
    }

    public function passcodeFor(string $playerId): string
    {
        $this->authAs($playerId);

        return 'passcode-' . $playerId;
    }

    /**
     * Runs the projection worker, the same way the real one does. Lets a test play
     * a match and then read the reports it produced.
     *
     * @return array{matches: int, attempts: int}
     */
    public function project(): array
    {
        return (new ProjectionRunner(
            $this->matches,
            $this->events,
            $this->attempts,
            $this->cursors,
            new AttemptProjector(new AccountPseudonymResolver($this->accounts)),
        ))->run();
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>     $headers
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $headers = [],
        bool $validateRequest = true,
    ): ResponseInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        // Deliberately malformed requests are validated only on the way out: the
        // spec describes what a correct client sends, and these are the cases where
        // the client is not correct.
        if ($validateRequest) {
            $this->validator->getServerRequestValidator()->validate($request);
        }

        $response = $this->app->handle($request);

        $this->validateResponse($method, $path, $response);

        return $response;
    }

    /** @return array<string, mixed> */
    public function json(ResponseInterface $response): array
    {
        $decoded = json_decode($this->bodyOf($response), associative: true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($decoded)) {
            throw new \RuntimeException('Response body is not a JSON object.');
        }

        /** @var array<string, mixed> */
        return $decoded;
    }

    public function bodyOf(ResponseInterface $response): string
    {
        $response->getBody()->rewind();

        return $response->getBody()->getContents();
    }

    private function validateResponse(string $method, string $path, ResponseInterface $response): void
    {
        $template = null;

        $withoutQuery = strtok($path, '?');

        foreach (self::ROUTES as $pattern => $candidate) {
            if (preg_match($pattern, $withoutQuery === false ? $path : $withoutQuery) === 1) {
                $template = $candidate;

                break;
            }
        }

        if ($template === null) {
            return;
        }

        $response->getBody()->rewind();

        $this->validator->getResponseValidator()->validate(
            new OperationAddress($template, strtolower($method)),
            $response,
        );
    }
}
