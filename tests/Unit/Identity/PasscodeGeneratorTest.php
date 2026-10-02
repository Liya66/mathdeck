<?php

declare(strict_types=1);

namespace MathDeck\Tests\Unit\Identity;

use MathDeck\Identity\PasscodeGenerator;
use PHPUnit\Framework\TestCase;

final class PasscodeGeneratorTest extends TestCase
{
    public function testPasscodesReadTheWayAChildWouldTypeThem(): void
    {
        $generator = new PasscodeGenerator();

        for ($i = 0; $i < 200; ++$i) {
            self::assertMatchesRegularExpression('/^[a-z]+-[a-z]+-\d{3}$/', $generator->generate());
        }
    }

    /**
     * The space is small on its own. It is enough only because sign-in is
     * throttled — if that ever goes, this has to grow with it.
     */
    public function testTheSpaceIsLargeEnoughToBeWorthThrottling(): void
    {
        self::assertGreaterThan(1_000_000, PasscodeGenerator::combinations());
    }

    public function testTwoPasscodesAreUnlikelyToCollide(): void
    {
        $generator = new PasscodeGenerator();
        $seen = [];

        for ($i = 0; $i < 300; ++$i) {
            $seen[$generator->generate()] = true;
        }

        // Birthday-bound: 300 draws from 1M should collide only rarely.
        self::assertGreaterThan(290, count($seen));
    }

    /**
     * The words are drawn from a small fixed vocabulary, which is the whole reason
     * this is readable: a letter like `l` is unambiguous inside "glad" and would not
     * be in a random string. Asserting "no ambiguous characters" would be testing
     * for a problem that using words is what solves.
     */
    public function testPasscodesAreBuiltFromAShortFixedVocabulary(): void
    {
        $generator = new PasscodeGenerator();
        $words = [];

        for ($i = 0; $i < 500; ++$i) {
            $passcode = $generator->generate();

            self::assertSame(strtolower($passcode), $passcode);
            self::assertStringNotContainsString(' ', $passcode);

            [$adjective, $noun] = explode('-', $passcode);

            foreach ([$adjective, $noun] as $word) {
                self::assertMatchesRegularExpression('/^[a-z]{3,}$/', $word);
                $words[$word] = true;
            }
        }

        self::assertLessThanOrEqual(
            64,
            count($words),
            'Five hundred draws should exhaust a small word list, not reveal a large one.',
        );
    }
}
