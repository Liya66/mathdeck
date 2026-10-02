<?php

declare(strict_types=1);

namespace MathDeck\Tests\Functional;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Tests\Support\TestApp;
use PHPUnit\Framework\TestCase;

final class DeckAuthoringTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void
    {
        $this->app = new TestApp();
    }

    public function testTheSeededStarterDeckIsListedAndPublished(): void
    {
        $body = $this->app->json($this->app->request('GET', '/v1/decks', null, $this->app->authAs('miss-lee')));

        self::assertSame([
            [
                'deckVersionId' => 'starter@1',
                'deckId' => 'starter',
                'version' => 1,
                'name' => 'Starter deck',
                'authorId' => 'system',
                'status' => 'published',
                'updatedAt' => '2026-01-01T00:00:00.000000Z',
            ],
        ], $body['decks']);
    }

    public function testADraftIsCreatedUnvalidated(): void
    {
        // Deliberately broken: a teacher halfway through authoring must be able to
        // save without being told off.
        $response = $this->app->request('POST', '/v1/decks', [
            'definition' => ['schemaVersion' => 1, 'name' => 'Work in progress'],
        ], $this->app->authAs('miss-lee'));

        $body = $this->app->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('draft', $body['status']);
        self::assertSame('deck-1@1', $body['deckVersionId']);
        self::assertNull($body['publishedAt']);
        self::assertSame('/v1/decks/deck-1@1', $response->getHeaderLine('Location'));
    }

    public function testAnotherTeachersDraftIsNotVisible(): void
    {
        $this->createDraft('miss-lee');

        $mine = $this->app->request('GET', '/v1/decks/deck-1@1', null, $this->app->authAs('miss-lee'));
        $theirs = $this->app->request('GET', '/v1/decks/deck-1@1', null, $this->app->authAs('mr-adeyemi'));

        self::assertSame(200, $mine->getStatusCode());
        self::assertSame(403, $theirs->getStatusCode());
    }

    public function testLintingReportsProblemsWithoutSaving(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = [2, 4, 6];
        $definition['operators']['symbols'] = ['+'];
        $definition['targets'] = [7, 8];

        $response = $this->app->request('POST', '/v1/decks/lint', [
            'definition' => $definition,
        ], $this->app->authAs('miss-lee'));
        $report = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode(), 'Analysing a broken deck is a success.');
        self::assertFalse($report['publishable']);
        self::assertSame(['TARGET_UNREACHABLE'], array_column($report['errors'], 'code'));

        $targets = array_column($report['targets'], null, 'target');
        self::assertFalse($targets[7]['reachable']);
        self::assertTrue($targets[8]['reachable']);
        self::assertSame('2 + 6', $targets[8]['example']);
    }

    public function testPublishingAGoodDeckFreezesIt(): void
    {
        $this->createDraft('miss-lee');

        $published = $this->app->json(
            $this->app->request('POST', '/v1/decks/deck-1@1/publish', null, $this->app->authAs('miss-lee')),
        );

        self::assertSame('published', $published['status']);
        self::assertNotNull($published['publishedAt']);

        $edit = $this->app->request('PUT', '/v1/decks/deck-1@1', [
            'definition' => DeckDocument::starter()->toArray(),
        ], $this->app->authAs('miss-lee'));

        self::assertSame(409, $edit->getStatusCode());
        self::assertSame('DECK_NOT_EDITABLE', $this->app->json($edit)['reason']);
    }

    /**
     * The gate that justifies the whole linter: a deck with a target nobody can
     * reach never gets in front of a class.
     */
    public function testAnUnplayableDeckIsRefusedWithTheFullReport(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = [2, 4];
        $definition['operators']['symbols'] = ['+'];
        $definition['targets'] = [7];

        $this->app->request('POST', '/v1/decks', ['definition' => $definition], $this->app->authAs('miss-lee'));

        $response = $this->app->request('POST', '/v1/decks/deck-1@1/publish', null, $this->app->authAs('miss-lee'));
        $problem = $this->app->json($response);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        self::assertContains('TARGET_UNREACHABLE', array_column($problem['report']['errors'], 'code'));
        self::assertStringContainsString('makes 7', $problem['detail']);

        $still = $this->app->json($this->app->request('GET', '/v1/decks/deck-1@1', null, $this->app->authAs('miss-lee')));
        self::assertSame('draft', $still['status'], 'A refused publish leaves the draft alone.');
    }

    public function testEditingAPublishedDeckMeansForkingIt(): void
    {
        $response = $this->app->request('POST', '/v1/decks/starter@1/fork', null, $this->app->authAs('mr-adeyemi'));
        $fork = $this->app->json($response);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('starter@2', $fork['deckVersionId']);
        self::assertSame('draft', $fork['status']);
        self::assertSame('mr-adeyemi', $fork['authorId']);
        self::assertSame(
            DeckDocument::starter()->toArray()['targets'],
            $fork['definition']['targets'],
            'A fork starts as a copy.',
        );
    }

    public function testOnlyTheAuthorCanPublish(): void
    {
        $this->createDraft('miss-lee');

        $response = $this->app->request('POST', '/v1/decks/deck-1@1/publish', null, $this->app->authAs('mr-adeyemi'));

        self::assertSame(403, $response->getStatusCode());
    }

    /**
     * The cross-domain rule. An unpublished deck is usually a broken one, and a
     * broken deck is exactly what must not reach a classroom.
     */
    public function testADraftCannotStartAMatch(): void
    {
        $this->createDraft('miss-lee');

        $response = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'deck-1@1',
            'playerIds' => ['miss-lee', 'bob'],
        ], $this->app->authAs('miss-lee') + ['Idempotency-Key' => 'create-DeckAuthoringTest-166']);

        self::assertSame(404, $response->getStatusCode());

        $this->app->request('POST', '/v1/decks/deck-1@1/publish', null, $this->app->authAs('miss-lee'));

        $afterPublishing = $this->app->request('POST', '/v1/matches', [
            'deckVersionId' => 'deck-1@1',
            'playerIds' => ['miss-lee', 'bob'],
        ], $this->app->authAs('miss-lee') + ['Idempotency-Key' => 'create-DeckAuthoringTest-175']);

        self::assertSame(201, $afterPublishing->getStatusCode());
    }

    public function testTheSchemaIsServedFromTheSameFileTheServerValidatesAgainst(): void
    {
        $response = $this->app->request('GET', '/v1/deck-schema', null, $this->app->authAs('miss-lee'));
        $schema = $this->app->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/schema+json', $response->getHeaderLine('Content-Type'));
        self::assertSame('https://mathdeck.dev/schema/deck-v1.schema.json', $schema['$id']);
        self::assertArrayHasKey('operands', $schema['properties']);
    }

    private function createDraft(string $authorId): void
    {
        $this->app->request('POST', '/v1/decks', [
            'definition' => DeckDocument::starter()->toArray(),
        ], $this->app->authAs($authorId));
    }

}
