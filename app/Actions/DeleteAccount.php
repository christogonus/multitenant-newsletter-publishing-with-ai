<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Erases an account with its publications, readers and files.
 */
class DeleteAccount
{
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Delete publications one by one so their model events remove uploaded files.
            $user->newsletters()->each(fn ($newsletter) => $newsletter->delete());
            $user->delete();
        });
    }
}
