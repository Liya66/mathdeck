<?php

declare(strict_types=1);

namespace MathDeck\Http;

use MathDeck\Http\Action\ChangeOwnPasscodeAction;
use MathDeck\Http\Action\CreateAccountAction;
use MathDeck\Http\Action\CreateDeckAction;
use MathDeck\Http\Action\CreateMatchAction;
use MathDeck\Http\Action\ForkDeckAction;
use MathDeck\Http\Action\GetDeckAction;
use MathDeck\Http\Action\GetDeckSchemaAction;
use MathDeck\Http\Action\GetEventsAction;
use MathDeck\Http\Action\GetMatchAction;
use MathDeck\Http\Action\IssueTokenAction;
use MathDeck\Http\Action\LintDeckAction;
use MathDeck\Http\Action\ListAccountsAction;
use MathDeck\Http\Action\ListDecksAction;
use MathDeck\Http\Action\PublishDeckAction;
use MathDeck\Http\Action\ResetPasscodeAction;
use MathDeck\Http\Action\ErrorsReportAction;
use MathDeck\Http\Action\LatencyReportAction;
use MathDeck\Http\Action\OverviewReportAction;
use MathDeck\Http\Action\PostCommandAction;
use MathDeck\Http\Action\ProgressionReportAction;
use MathDeck\Http\Action\UpdateDeckAction;
use MathDeck\Http\Middleware\IdempotencyKeyMiddleware;
use MathDeck\Http\Middleware\IdentityMiddleware;
use MathDeck\Http\Middleware\ProblemDetailsMiddleware;
use MathDeck\Http\Middleware\SecurityHeadersMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final readonly class AppFactory
{
    /** @return App<ContainerInterface|null> Slim types the container slot as nullable. */
    public static function create(ContainerInterface $container, bool $debug = false): App
    {
        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        // The one public route. Declared before the group so it is never shadowed.
        $app->post('/v1/tokens', IssueTokenAction::class);

        $app->group('/v1', function (\Slim\Routing\RouteCollectorProxy $group): void {
            $group->post('/matches', CreateMatchAction::class)
                ->add(new IdempotencyKeyMiddleware());
            $group->get('/matches/{matchId}', GetMatchAction::class);
            $group->post('/matches/{matchId}/commands', PostCommandAction::class)
                ->add(new IdempotencyKeyMiddleware());
            $group->get('/matches/{matchId}/events', GetEventsAction::class);

            // Authoring. `lint` is declared before the id route so a deck can never
            // be named "lint" and shadow it.
            $group->get('/deck-schema', GetDeckSchemaAction::class);
            $group->post('/decks/lint', LintDeckAction::class);
            $group->get('/decks', ListDecksAction::class);
            $group->post('/decks', CreateDeckAction::class);
            $group->get('/decks/{deckVersionId}', GetDeckAction::class);
            $group->put('/decks/{deckVersionId}', UpdateDeckAction::class);
            $group->post('/decks/{deckVersionId}/publish', PublishDeckAction::class);
            $group->post('/decks/{deckVersionId}/fork', ForkDeckAction::class);

            // Accounts. A teacher provisions the class; everyone may change their
            // own passcode. `me` is declared first so no player id can shadow it.
            $group->put('/accounts/me/passcode', ChangeOwnPasscodeAction::class);
            $group->get('/accounts', ListAccountsAction::class);
            $group->post('/accounts', CreateAccountAction::class);
            $group->post('/accounts/{playerId}/passcode', ResetPasscodeAction::class);

            // Teacher reports. Read-only, and read only from the projections.
            $group->get('/reports/overview', OverviewReportAction::class);
            $group->get('/reports/progression', ProgressionReportAction::class);
            $group->get('/reports/errors', ErrorsReportAction::class);
            $group->get('/reports/latency', LatencyReportAction::class);
        })->add($container->get(IdentityMiddleware::class));

        // Slim runs middleware in reverse order of addition, so the last one added
        // is the outermost. Read this list bottom-up.
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        $app->add(new ProblemDetailsMiddleware($app->getResponseFactory(), debug: $debug));
        // Outermost, so error responses carry the headers too.
        $app->add(new SecurityHeadersMiddleware());

        return $app;
    }
}
