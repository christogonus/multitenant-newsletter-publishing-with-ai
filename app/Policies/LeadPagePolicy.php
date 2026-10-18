<?php

namespace App\Policies;

use App\Models\User;

class LeadPagePolicy
{
    public function view(User $user, LeadPage $leadPage): bool
    {
        return $leadPage->newsletter()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, LeadPage $leadPage): bool
    {
        return $leadPage->newsletter()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, LeadPage $leadPage): bool
    {
        return $leadPage->newsletter()->where('user_id', $user->id)->exists();
    }
}
