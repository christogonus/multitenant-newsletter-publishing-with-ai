<?php

namespace App\Actions;

use App\Models\Newsletter;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateNewsletter
{
    /**
     * @return array<int, mixed>
     */
    public static function subdomainRules(?Newsletter $ignore = null): array
    {
        return [
            'required',
            'string',
            'min:3',
            'max:40',
            'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            Rule::notIn(config('newspub.reserved_subdomains')),
            Rule::unique('newsletters', 'subdomain')->ignore($ignore),
        ];
    }

    /**
     * @param  array{niche: string, name: string, audience?: ?string, frequency: string, subdomain?: string, tagline?: ?string, voice?: ?string, kit_key?: ?string, topic_pipeline?: ?array<int, string>}  $attributes
     */
    public function handle(User $user, array $attributes): Newsletter
    {
        $newsletter = $user->newsletters()->create([
            'name' => $attributes['name'],
            'niche' => $attributes['niche'],
            'audience' => $attributes['audience'] ?? null,
            'frequency' => $attributes['frequency'],
            'tagline' => $attributes['tagline'] ?? null,
            'voice' => $attributes['voice'] ?? null,
            'subdomain' => $attributes['subdomain'] ?? $this->availableSubdomain($attributes['name']),
            'kit_key' => $attributes['kit_key'] ?? null,
            'topic_pipeline' => $attributes['topic_pipeline'] ?? null,
            'template' => 'classic',
        ]);

        $user->forceFill(['current_newsletter_id' => $newsletter->id])->save();

        return $newsletter;
    }

    public function availableSubdomain(string $name): string
    {
        $base = Str::limit(Str::slug($name), 34, '') ?: 'newsletter';
        $base = in_array($base, config('newspub.reserved_subdomains'), true) ? $base.'-news' : $base;
        $candidate = $base;
        $suffix = 2;

        while (Newsletter::query()->where('subdomain', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
