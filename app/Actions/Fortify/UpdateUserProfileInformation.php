<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
        ])->validateWithBag('updateProfileInformation');

        // Moving the email hands over the account, so it only happens through the confirmed steps in ChangeEmail.
        if (isset($input['email']) && Str::lower(trim($input['email'])) !== Str::lower($user->email)) {
            throw ValidationException::withMessages([
                'email' => $user->uses_default_password
                    ? 'Choose your own password first, then you can change your email.'
                    : 'Use Change email below to change your email.',
            ])->errorBag('updateProfileInformation');
        }

        $user->forceFill(['name' => $input['name']])->save();
    }
}
