<?php

namespace App\Policies;

use App\Models\PlatformConnection;
use App\Models\User;

class PlatformConnectionPolicy
{
    public function view(User $user, PlatformConnection $platformConnection): bool
    {
        return $platformConnection->user_id === $user->id;
    }

    public function update(User $user, PlatformConnection $platformConnection): bool
    {
        return $platformConnection->user_id === $user->id;
    }

    public function delete(User $user, PlatformConnection $platformConnection): bool
    {
        return $platformConnection->user_id === $user->id;
    }
}
