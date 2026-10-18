<?php

namespace App\Policies;

use App\Models\Subscriber;
use App\Models\User;

class SubscriberPolicy
{
    public function view(User $user, Subscriber $subscriber): bool
    {
        return $subscriber->newsletter()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Subscriber $subscriber): bool
    {
        return $subscriber->newsletter()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Subscriber $subscriber): bool
    {
        return $subscriber->newsletter()->where('user_id', $user->id)->exists();
    }
}
