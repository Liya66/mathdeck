<?php

declare(strict_types=1);

namespace MathDeck\Identity;

enum Role: string
{
    case Student = 'student';
    case Teacher = 'teacher';

    /** Class reports show one person's performance to another. */
    public function mayReadClassReports(): bool
    {
        return $this === self::Teacher;
    }
}
