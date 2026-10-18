<?php

namespace App\Policies;

use App\Models\User;

class LicenseCodePolicy
{
    public function view(User $user, LicenseCode $licenseCode): bool
    {
        return $licenseCode->reseller_id === $user->id;
    }

    public function update(User $user, LicenseCode $licenseCode): bool
    {
        return $licenseCode->reseller_id === $user->id;
    }

    public function delete(User $user, LicenseCode $licenseCode): bool
    {
        return $licenseCode->reseller_id === $user->id;
    }
}
