<?php

namespace App\Ai\Agents\Concerns;

/**
 * Reads the provider(s) and model for every NewsPub agent from config/newspub.php.
 *
 * NEWSPUB_AI_PROVIDER may list several providers ("anthropic,openai"): the SDK tries them in order
 * and fails over when one is down. NEWSPUB_AI_MODEL applies to the first provider only.
 */
trait UsesNewsPubModel
{
    /**
     * @return string|array<string, string|null>
     */
    public function provider(): string|array
    {
        $providers = self::configuredProviders();

        if (count($providers) === 1) {
            return $providers[0];
        }

        $model = config('newspub.ai.model') ?: null;

        return collect($providers)
            ->mapWithKeys(fn (string $provider, int $index) => [$provider => $index === 0 ? $model : null])
            ->all();
    }

    public function model(): ?string
    {
        return config('newspub.ai.model') ?: null;
    }

    public function timeout(): int
    {
        return 120;
    }

    /**
     * @return list<string>
     */
    public static function configuredProviders(): array
    {
        $providers = array_values(array_filter(array_map('trim', explode(',', (string) config('newspub.ai.provider')))));

        return $providers === [] ? ['anthropic'] : $providers;
    }
}
