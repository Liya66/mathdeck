<?php

declare(strict_types=1);

namespace MathDeck\Authoring;

use MathDeck\Authoring\Exception\DeckNotEditable;

final readonly class DeckVersion
{
    public function __construct(
        public string $deckId,
        public int $version,
        public string $authorId,
        public DeckStatus $status,
        public DeckDocument $document,
        public \DateTimeImmutable $updatedAt,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {
    }

    public static function idFor(string $deckId, int $version): string
    {
        return sprintf('%s@%d', $deckId, $version);
    }

    public function id(): string
    {
        return self::idFor($this->deckId, $this->version);
    }

    public function name(): string
    {
        return $this->document->name();
    }

    /** @throws DeckNotEditable */
    public function withDocument(DeckDocument $document, \DateTimeImmutable $at): self
    {
        if (!$this->status->isEditable()) {
            throw DeckNotEditable::because($this->id(), $this->status);
        }

        return new self($this->deckId, $this->version, $this->authorId, $this->status, $document, $at);
    }

    /** @throws DeckNotEditable */
    public function published(\DateTimeImmutable $at): self
    {
        if (!$this->status->isEditable()) {
            throw DeckNotEditable::because($this->id(), $this->status);
        }

        return new self(
            $this->deckId,
            $this->version,
            $this->authorId,
            DeckStatus::Published,
            $this->document,
            $at,
            $at,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'deckVersionId' => $this->id(),
            'deckId' => $this->deckId,
            'version' => $this->version,
            'name' => $this->name(),
            'authorId' => $this->authorId,
            'status' => $this->status->value,
            'updatedAt' => $this->updatedAt->format('Y-m-d\TH:i:s.up'),
            'publishedAt' => $this->publishedAt?->format('Y-m-d\TH:i:s.up'),
            'definition' => $this->document->toArray(),
        ];
    }
}
