<?php

namespace App\Actions\Fortify;

use App\Http\Middleware\ProtectDemoAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        // Demo accounts are shared, so a reset link must not let one person lock the others out.
        if ($user->is_demo) {
            throw ValidationException::withMessages([
                'email' => ProtectDemoAccount::MESSAGE,
            ]);
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'uses_default_password' => false,
        ])->save();
    }
}
