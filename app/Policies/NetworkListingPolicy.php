<?php

namespace App\Policies;

use App\Models\NetworkListing;
use App\Models\User;

class NetworkListingPolicy
{
    public function view(User $user, NetworkListing $networkListing): bool
    {
        return $networkListing->newsletter()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, NetworkListing $networkListing): bool
    {
        return $networkListing->newsletter()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, NetworkListing $networkListing): bool
    {
        return $networkListing->newsletter()->where('user_id', $user->id)->exists();
    }
}
