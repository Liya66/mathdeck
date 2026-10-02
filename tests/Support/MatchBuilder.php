<?php

declare(strict_types=1);

namespace MathDeck\Tests\Support;

use MathDeck\Engine\Card\Card;
use MathDeck\Engine\Card\Operator;
use MathDeck\Engine\State\Board;
use MathDeck\Engine\State\DeckRules;
use MathDeck\Engine\State\MatchState;
use MathDeck\Engine\State\Phase;
use MathDeck\Engine\State\PlayerState;

/**
 * Builds a match with an exactly known hand and target. Engine tests should never
 * depend on what the shuffle happened to deal.
 */
final class MatchBuilder
{
    private string $hand = '3 + 4';
    private string $opponentHand = '1 + 1';
    private int $target = 7;
    /** @var list<int> */
    private array $targetQueue = [5, 9];
    private int $drawPileSize = 10;
    private Phase $phase = Phase::AwaitingPlay;
    private int $currentPlayerIndex = 0;
    private DeckRules $rules;
    private string $turnStartedAt = '2026-01-15 09:00:00.000000';

    public function __construct()
    {
        $this->rules = DeckRules::default();
    }

    public static function new(): self
    {
        return new self();
    }

    public function withHand(string $hand): self
    {
        $this->hand = $hand;

        return $this;
    }

    public function withTarget(int $target): self
    {
        $this->target = $target;

        return $this;
    }

    /** @param list<int> $targetQueue */
    public function withTargetQueue(array $targetQueue): self
    {
        $this->targetQueue = $targetQueue;

        return $this;
    }

    public function withDrawPileSize(int $size): self
    {
        $this->drawPileSize = $size;

        return $this;
    }

    public function withRules(DeckRules $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    public function endedMatch(): self
    {
        $this->phase = Phase::Ended;

        return $this;
    }

    public function withOpponentToPlay(): self
    {
        $this->currentPlayerIndex = 1;

        return $this;
    }

    public function turnStartedAt(string $at): self
    {
        $this->turnStartedAt = $at;

        return $this;
    }

    /** @return list<Card> */
    public function handCards(): array
    {
        return Cards::parse($this->hand);
    }

    public function build(): MatchState
    {
        return new MatchState(
            matchId: 'match-1',
            deckVersionId: 'deck-v1',
            seed: 42,
            rules: $this->rules,
            phase: $this->phase,
            seq: 0,
            players: [
                new PlayerState('alice', $this->handCards()),
                new PlayerState('bob', Cards::parse($this->opponentHand, 'o')),
            ],
            currentPlayerIndex: $this->currentPlayerIndex,
            board: new Board(
                target: $this->target,
                drawPile: $this->drawPile(),
                targetQueue: $this->targetQueue,
            ),
            turnStartedAt: new \DateTimeImmutable($this->turnStartedAt, new \DateTimeZone('UTC')),
        );
    }

    /** @return list<Card> */
    private function drawPile(): array
    {
        $pile = [];

        for ($index = 0; $index < $this->drawPileSize; ++$index) {
            $pile[] = $index % 3 === 2
                ? Card::operator(sprintf('p%d', $index), Operator::Add)
                : Card::operand(sprintf('p%d', $index), $index + 1);
        }

        return $pile;
    }
}
