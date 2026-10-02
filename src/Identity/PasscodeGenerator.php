<?php

declare(strict_types=1);

namespace MathDeck\Identity;

/**
 * Passcodes a nine-year-old can read off a card and type correctly.
 *
 * Two words and three digits, from short, concrete, unambiguous words. No `l`/`1`
 * or `O`/`0` confusion, nothing that needs spelling out, nothing embarrassing to
 * read aloud in a classroom.
 *
 * Roughly 2.3 million combinations. That is not much on its own — it is enough
 * because sign-in is throttled: at ten tries per five minutes per account, the
 * expected search takes centuries. The two defences only work together, which is
 * why neither should be removed without the other being reconsidered.
 */
final readonly class PasscodeGenerator
{
    private const ADJECTIVES = [
        'brave', 'bright', 'calm', 'clever', 'cosy', 'eager', 'fair', 'fast',
        'gentle', 'glad', 'happy', 'jolly', 'keen', 'kind', 'lucky', 'merry',
        'neat', 'proud', 'quick', 'quiet', 'ready', 'sharp', 'shiny', 'smooth',
        'snug', 'sunny', 'swift', 'tidy', 'warm', 'wise', 'witty', 'zesty',
    ];

    private const NOUNS = [
        'acorn', 'badger', 'cedar', 'comet', 'crane', 'dolphin', 'ember', 'falcon',
        'ferry', 'garden', 'harbour', 'heron', 'island', 'kettle', 'lantern', 'meadow',
        'otter', 'pebble', 'pepper', 'puffin', 'river', 'robin', 'salmon', 'sparrow',
        'thistle', 'thunder', 'tulip', 'violet', 'walnut', 'willow', 'window', 'yarrow',
    ];

    public function generate(): string
    {
        return sprintf(
            '%s-%s-%03d',
            self::ADJECTIVES[random_int(0, count(self::ADJECTIVES) - 1)],
            self::NOUNS[random_int(0, count(self::NOUNS) - 1)],
            random_int(0, 999),
        );
    }

    /** The size of the space these are drawn from, for anyone auditing the choice. */
    public static function combinations(): int
    {
        return count(self::ADJECTIVES) * count(self::NOUNS) * 1000;
    }
}
