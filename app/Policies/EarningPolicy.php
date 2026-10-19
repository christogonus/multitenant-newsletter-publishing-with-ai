<?php

namespace App\Policies;

use App\Models\Earning;
use App\Models\User;

class EarningPolicy
{
    public function delete(User $user, Earning $earning): bool
    {
        return $earning->newsletter()->where('user_id', $user->id)->exists();
    }
}
