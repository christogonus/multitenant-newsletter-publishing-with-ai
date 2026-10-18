<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Makes an account an admin (creating it when needed), so the first admin can be set up on a fresh server.
 */
#[Signature('newspub:make-admin {email : The account to make an admin} {--name= : Name for a new account} {--revoke : Remove admin access instead}')]
#[Description('Make an account an admin, creating it if it does not exist')]
class MakeAdmin extends Command
{
    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('That is not a valid email address.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($this->option('revoke')) {
            if (! $user?->is_admin) {
                $this->components->warn("{$email} is not an admin.");

                return self::SUCCESS;
            }

            $user->forceFill(['is_admin' => false])->save();
            $this->components->info("{$email} is no longer an admin.");

            return self::SUCCESS;
        }

        if ($user) {
            $user->forceFill(['is_admin' => true])->save();
            $this->components->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        $password = Str::password(16, symbols: false);

        $user = User::create([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
            'terms_accepted_at' => now(),
        ]);
        $user->forceFill(['is_admin' => true, 'email_verified_at' => now()])->save();

        $this->components->info("Created admin {$email}.");
        $this->components->twoColumnDetail('Password (shown once, change it after signing in)', $password);

        return self::SUCCESS;
    }
}
