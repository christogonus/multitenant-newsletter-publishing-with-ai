<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared by every public sign-up form: honeypot, time trap and email validation.
 */
class SubscribeRequest extends FormRequest
{
    public const MINIMUM_SECONDS = 2;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:120'],
            'ref' => ['nullable', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Please enter your email address.',
            'email.email' => 'That email address does not look right. Please check it.',
        ];
    }

    /**
     * Bots fill the hidden field or submit faster than a person can type.
     */
    public function looksLikeABot(): bool
    {
        if (filled($this->input('website'))) {
            return true;
        }

        if (! $this->filled('started_at')) {
            return false;
        }

        try {
            $startedAt = (int) decrypt((string) $this->input('started_at'));
        } catch (DecryptException) {
            return true;
        }

        return now()->timestamp - $startedAt < self::MINIMUM_SECONDS;
    }
}
