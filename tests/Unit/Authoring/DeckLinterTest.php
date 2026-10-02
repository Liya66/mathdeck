<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Authoring;

use MathDeck\Authoring\DeckDocument;
use MathDeck\Authoring\Lint\DeckLinter;
use PHPUnit\Framework\TestCase;

final class DeckLinterTest extends TestCase
{
    public function testTheStarterDeckPublishesCleanly(): void
    {
        $report = DeckLinter::default()->lint(DeckDocument::starter()->toArray());

        self::assertTrue($report->isPublishable());
        self::assertSame([], $report->errors);
        self::assertSame([], $report->warnings);
        self::assertCount(24, $report->targets);
    }

    /**
     * Every bad field at once, each with the path the editor can highlight. Fixing
     * a deck one error per save would be miserable.
     */
    public function testEveryBadFieldIsReportedWithItsPath(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operators']['symbols'] = ['%'];
        $definition['play']['handSize'] = 99;
        $definition['targets'] = [];

        $report = DeckLinter::default()->lint($definition);

        self::assertFalse($report->isPublishable());
        self::assertSame(['SCHEMA'], array_unique(array_column($report->errors, 'code')));
        self::assertSame(
            ['/operators/symbols/0', '/targets', '/play/handSize'],
            array_column($report->errors, 'path'),
        );
    }

    /**
     * The one case that does not report everything: Opis short-circuits sibling
     * keywords when `required` fails, so a missing key comes back on its own.
     */
    public function testAMissingRequiredPropertyIsReportedOnItsOwn(): void
    {
        $definition = DeckDocument::starter()->toArray();
        unset($definition['name']);

        $report = DeckLinter::default()->lint($definition);

        self::assertFalse($report->isPublishable());
        self::assertCount(1, $report->errors);
        self::assertStringContainsString('name', $report->errors[0]['detail']);
    }

    /**
     * Balance is not checked until the shape is right — asking whether a target is
     * reachable in a deck whose operands are not numbers is meaningless.
     */
    public function testBalanceIsNotAttemptedOnAStructurallyInvalidDeck(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = 'all of them';

        $report = DeckLinter::default()->lint($definition);

        self::assertFalse($report->isPublishable());
        self::assertSame([], $report->targets);
    }

    public function testAnUnreachableTargetBlocksPublication(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = [2, 4, 6];
        $definition['operators']['symbols'] = ['+'];
        $definition['targets'] = [7];

        $report = DeckLinter::default()->lint($definition);

        self::assertFalse($report->isPublishable());
        self::assertSame('TARGET_UNREACHABLE', $report->errors[0]['code']);
        self::assertStringContainsString('makes 7', $report->errors[0]['detail']);
    }

    public function testAHandTooSmallForTheLargestPlayIsRefused(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['play']['handSize'] = 5;
        $definition['play']['maximumCards'] = 7;

        $report = DeckLinter::default()->lint($definition);

        self::assertFalse($report->isPublishable());
        self::assertContains('HAND_TOO_SMALL', array_column($report->errors, 'code'));
    }

    /**
     * Hard is the teacher's business. Impossible is not.
     */
    public function testAMerelyHardTargetIsAWarningAndStillPublishes(): void
    {
        $definition = DeckDocument::starter()->toArray();
        $definition['operands']['values'] = [2, 3];
        $definition['operators']['symbols'] = ['+'];
        $definition['targets'] = [5, 8];

        $report = DeckLinter::default()->lint($definition);

        self::assertTrue($report->isPublishable(), 'A deck of hard targets is still a deck.');
        self::assertContains('TARGET_HARD', array_column($report->warnings, 'code'));
    }
}
