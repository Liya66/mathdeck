<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Authoring;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\DeckService;
use MathDeck\Authoring\DeckStatus;
use MathDeck\Authoring\Exception\DeckNotEditable;
use MathDeck\Authoring\Exception\DeckNotFound;
use MathDeck\Authoring\Exception\DeckRejected;
use MathDeck\Authoring\Lint\DeckLinter;
use MathDeck\Infrastructure\InMemory\InMemoryDeckStore;
use MathDeck\Tests\Support\FixedDeckIdFactory;
use MathDeck\Tests\Support\FrozenClock;
use PHPUnit\Framework\TestCase;

final class DeckServiceTest extends TestCase
{
    private InMemoryDeckStore $store;
    private DeckService $service;

    protected function setUp(): void
    {
        $this->store = new InMemoryDeckStore();
        $this->service = new DeckService(
            $this->store,
            DeckLinter::default(),
            new FixedDeckIdFactory(),
            new FrozenClock(),
        );
    }

    public function testADraftStartsAtVersionOne(): void
    {
        $draft = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());

        self::assertSame('deck-1@1', $draft->id());
        self::assertSame(DeckStatus::Draft, $draft->status);
        self::assertNull($draft->publishedAt);
        self::assertSame('miss-lee', $draft->authorId);
    }

    public function testADraftCanBeEditedAndPublished(): void
    {
        $draft = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());

        $renamed = DeckDocument::starter()->toArray();
        $renamed['name'] = 'Halving and doubling';
        $this->service->updateDraft($draft->id(), $renamed);

        $published = $this->service->publish($draft->id());

        self::assertSame('Halving and doubling', $published->name());
        self::assertSame(DeckStatus::Published, $published->status);
        self::assertNotNull($published->publishedAt);
    }

    /**
     * The rule the whole authoring model rests on. Matches record the version they
     * were played with; editing one after the fact would rewrite results already
     * collected, silently.
     */
    public function testAPublishedDeckCannotBeEdited(): void
    {
        $draft = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());
        $this->service->publish($draft->id());

        $this->expectException(DeckNotEditable::class);

        $this->service->updateDraft($draft->id(), DeckDocument::starter()->toArray());
    }

    public function testPublishingTwiceIsRefused(): void
    {
        $draft = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());
        $this->service->publish($draft->id());

        $this->expectException(DeckNotEditable::class);

        $this->service->publish($draft->id());
    }

    public function testEditingAPublishedDeckMeansForkingTheNextVersion(): void
    {
        $first = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());
        $this->service->publish($first->id());

        $fork = $this->service->fork($first->id(), 'mr-adeyemi');

        self::assertSame('deck-1@2', $fork->id());
        self::assertSame(DeckStatus::Draft, $fork->status);
        self::assertSame('mr-adeyemi', $fork->authorId);
        self::assertEquals($first->document->toArray(), $fork->document->toArray(), 'A fork starts as a copy.');

        // And the published version is untouched.
        self::assertSame(DeckStatus::Published, $this->service->get($first->id())->status);
    }

    public function testAnUnplayableDeckCannotBePublished(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = [2, 4];
        $definition['operators']['symbols'] = ['+'];
        $definition['targets'] = [7];

        $draft = $this->service->createDraft('miss-lee', $definition);

        try {
            $this->service->publish($draft->id());
            self::fail('Expected the deck to be rejected.');
        } catch (DeckRejected $rejected) {
            self::assertContains('TARGET_UNREACHABLE', array_column($rejected->report->errors, 'code'));
            self::assertSame(DeckStatus::Draft, $this->service->get($draft->id())->status, 'It stays a draft.');
        }
    }

    public function testDraftsAreLintedWithoutPublishing(): void
    {
        $draft = $this->service->createDraft('miss-lee', DeckDocument::starter()->toArray());

        $report = $this->service->lint($draft->id());

        self::assertTrue($report->isPublishable());
        self::assertSame(DeckStatus::Draft, $this->service->get($draft->id())->status);
    }

    public function testAnUnknownDeckIsReported(): void
    {
        $this->expectException(DeckNotFound::class);

        $this->service->get('nope@1');
    }
}
