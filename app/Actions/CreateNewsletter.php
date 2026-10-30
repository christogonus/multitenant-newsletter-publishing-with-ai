<?php

namespace App\Actions;

use App\Exceptions\PublicationLimitReachedException;
use App\Models\Newsletter;
use App\Models\ReservedSubdomain;
use App\Models\User;
use App\Support\Entitlements;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateNewsletter
{
    public function __construct(private Entitlements $entitlements) {}

    public const SUBDOMAIN_MIN_LENGTH = 5;

    /**
     * The length and reserved-name checks only apply to a new address, so a publication
     * that already has a short or now-reserved subdomain can keep it.
     *
     * @return array<int, mixed>
     */
    public static function subdomainRules(?Newsletter $ignore = null): array
    {
        return [
            'required',
            'string',
            'max:40',
            'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
            function (string $attribute, mixed $value, Closure $fail) use ($ignore): void {
                if ($ignore && $value === $ignore->subdomain) {
                    return;
                }

                if (mb_strlen((string) $value) < self::SUBDOMAIN_MIN_LENGTH) {
                    $fail('Use at least '.self::SUBDOMAIN_MIN_LENGTH.' characters.');
                } elseif (in_array($value, ReservedSubdomain::names(), true)) {
                    $fail('That web address is reserved. Try another.');
                }
            },
            Rule::unique('newsletters', 'subdomain')->ignore($ignore),
        ];
    }

    /**
     * @param  array{niche: string, name: string, audience?: ?string, frequency: string, subdomain?: string, tagline?: ?string, voice?: ?string, kit_key?: ?string, topic_pipeline?: ?array<int, string>}  $attributes
     */
    public function handle(User $user, array $attributes): Newsletter
    {
        return DB::transaction(function () use ($user, $attributes) {
            // Lock the owner row and re-check the limit, so parallel requests cannot exceed it.
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            if (! $this->entitlements->canCreatePublication($user)) {
                throw new PublicationLimitReachedException('You have reached your publication limit.');
            }

            return $this->create($user, $attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(User $user, array $attributes): Newsletter
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
        $base = mb_strlen($base) < self::SUBDOMAIN_MIN_LENGTH || in_array($base, ReservedSubdomain::names(), true) ? $base.'-news' : $base;
        $candidate = $base;
        $suffix = 2;

        while (Newsletter::query()->where('subdomain', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix++;
        }

        return $candidate;
    }
}
