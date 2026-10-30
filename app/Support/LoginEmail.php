<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * What an account's new sign-in email must satisfy, whoever is changing it.
 */
class LoginEmail
{
    /**
     * @return array<int, mixed>
     */
    public static function rules(User $user): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            Rule::notIn([Str::lower($user->email)]),
            Rule::unique('users', 'email')->ignore($user->id),
            // Purchases are matched on the email they were paid with, so it must keep pointing at the account that paid.
            Rule::unique('purchases', 'buyer_email')->ignore($user->id, 'user_id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $attribute): array
    {
        return [
            $attribute.'.not_in' => 'That is already the email on this account.',
            $attribute.'.unique' => 'That email is already used by another account.',
        ];
    }
}
