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

    /**
     * Teachers provision their class. Note what this does not permit: creating
     * another teacher. There is no path through the API by which an account grants
     * its own level of access to someone else — teacher accounts are made with
     * bin/create-account, on the machine.
     */
    public function mayManageAccounts(): bool
    {
        return $this === self::Teacher;
    }
}
