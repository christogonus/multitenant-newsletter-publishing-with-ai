<?php

namespace App\Policies;

use App\Models\Newsletter;
use App\Models\User;

class NewsletterPolicy
{
    public function view(User $user, Newsletter $newsletter): bool
    {
        return $newsletter->user_id === $user->id;
    }

    public function update(User $user, Newsletter $newsletter): bool
    {
        return $newsletter->user_id === $user->id;
    }

    public function delete(User $user, Newsletter $newsletter): bool
    {
        return $newsletter->user_id === $user->id;
    }
}
