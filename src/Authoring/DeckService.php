<?php

declare(strict_types=1);

namespace MathDeck\Authoring;

use MathDeck\Authoring\Exception\DeckNotFound;
use MathDeck\Authoring\Exception\DeckRejected;
use MathDeck\Authoring\Lint\DeckLinter;
use MathDeck\Authoring\Lint\LintReport;
use MathDeck\Authoring\Port\DeckIdFactory;
use MathDeck\Authoring\Port\DeckStore;
use MathDeck\Engine\Clock;

/**
 * Authoring use cases.
 *
 * The rule that shapes all of them: a published version is frozen. Drafts are
 * mutable, publishing freezes, and changing a published deck means forking a new
 * draft at the next version number. Matches store the version they were created
 * with, so history stays true no matter what a teacher does next.
 */
final readonly class DeckService
{
    public function __construct(
        private DeckStore $decks,
        private DeckLinter $linter,
        private DeckIdFactory $ids,
        private Clock $clock,
    ) {
    }

    /** @param array<string, mixed> $definition */
    public function createDraft(string $authorId, array $definition): DeckVersion
    {
        $name = is_string($definition['name'] ?? null) ? $definition['name'] : 'Untitled deck';

        $draft = new DeckVersion(
            deckId: $this->ids->newDeckId($name),
            version: 1,
            authorId: $authorId,
            status: DeckStatus::Draft,
            document: DeckDocument::fromArray($definition),
            updatedAt: $this->clock->now(),
        );

        $this->decks->save($draft);

        return $draft;
    }

    /** @param array<string, mixed> $definition */
    public function updateDraft(string $deckVersionId, array $definition): DeckVersion
    {
        $updated = $this->require($deckVersionId)
            ->withDocument(DeckDocument::fromArray($definition), $this->clock->now());

        $this->decks->save($updated);

        return $updated;
    }

    public function lint(string $deckVersionId): LintReport
    {
        return $this->linter->lint($this->require($deckVersionId)->document->toArray());
    }

    /** @param array<string, mixed> $definition */
    public function lintDefinition(array $definition): LintReport
    {
        return $this->linter->lint($definition);
    }

    /** @throws DeckRejected when the deck would not be playable */
    public function publish(string $deckVersionId): DeckVersion
    {
        $draft = $this->require($deckVersionId);
        $report = $this->linter->lint($draft->document->toArray());

        if (!$report->isPublishable()) {
            throw DeckRejected::from($report);
        }

        $published = $draft->published($this->clock->now());
        $this->decks->save($published);

        return $published;
    }

    /**
     * Editing a published deck means starting a new version of it.
     */
    public function fork(string $deckVersionId, string $authorId): DeckVersion
    {
        $source = $this->require($deckVersionId);

        $draft = new DeckVersion(
            deckId: $source->deckId,
            version: $this->decks->nextVersion($source->deckId),
            authorId: $authorId,
            status: DeckStatus::Draft,
            document: $source->document,
            updatedAt: $this->clock->now(),
        );

        $this->decks->save($draft);

        return $draft;
    }

    public function get(string $deckVersionId): DeckVersion
    {
        return $this->require($deckVersionId);
    }

    /** @return list<DeckVersion> */
    public function all(): array
    {
        return $this->decks->all();
    }

    private function require(string $deckVersionId): DeckVersion
    {
        return $this->decks->find($deckVersionId) ?? throw DeckNotFound::withId($deckVersionId);
    }
}
