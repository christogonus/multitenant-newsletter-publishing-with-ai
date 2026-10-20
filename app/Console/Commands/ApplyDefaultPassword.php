<?php

namespace App\Console\Commands;

use App\Enums\PurchaseSource;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Moves buyers who never chose a password onto NEWSPUB_DEFAULT_PASSWORD, so the public /support page works for them too.
 * Only accounts the IPN created and nobody has signed in to are touched: never admins, demo accounts,
 * or accounts made with a reseller code, whose owners chose their own password.
 */
#[Signature('newspub:apply-default-password {--dry-run : Only count the accounts that would change}')]
#[Description('Give buyers who never set a password the default password')]
class ApplyDefaultPassword extends Command
{
    public function handle(): int
    {
        $defaultPassword = (string) config('newspub.default_password');

        if ($defaultPassword === '') {
            $this->components->error('Set NEWSPUB_DEFAULT_PASSWORD first.');

            return self::FAILURE;
        }

        $accounts = User::query()
            ->whereNull('email_verified_at')
            ->whereNull('terms_accepted_at')
            ->where('uses_default_password', false)
            ->where('is_admin', false)
            ->where('is_demo', false)
            ->whereHas('purchases', fn (Builder $purchases) => $purchases->where('source', PurchaseSource::LaunchpadJv));

        $count = $accounts->count();

        if ($this->option('dry-run')) {
            $this->components->info("{$count} ".str('account')->plural($count).' would get the default password.');

            return self::SUCCESS;
        }

        $accounts->update(['password' => Hash::make($defaultPassword), 'uses_default_password' => true]);
        $this->components->info("{$count} ".str('account')->plural($count).' now use the default password.');

        return self::SUCCESS;
    }
}
