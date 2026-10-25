<?php

namespace App\Exceptions;

use App\Models\User;
use App\Support\Entitlements;
use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public static function forUser(User $user, Entitlements $entitlements): self
    {
        if ($entitlements->hasUnlimitedIssues($user)) {
            return new self('You have written a lot this week. New newsletters unlock again in a few days under our fair-use policy.');
        }

        return new self('You have used all '.$entitlements->weeklyIssueLimit($user).' newsletters for this publication this week. More unlock as the week rolls on, or upgrade to Unlimited Creation.');
    }
}
