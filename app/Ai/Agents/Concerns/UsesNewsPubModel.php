<?php

namespace App\Ai\Agents\Concerns;

use Laravel\Ai\AiManager;
use Laravel\Ai\Enums\Lab;

/**
 * Reads the provider(s) and model for every NewsPub agent from config/newspub.php.
 *
 * NEWSPUB_AI_PROVIDER may list several providers ("anthropic,openai"): the SDK tries them in order
 * and fails over when one is down. NEWSPUB_AI_MODEL applies to the first provider only.
 * An agent may override model(); IssueWriter does, so Gold buyers get the premium model.
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

        $model = $this->model();

        return collect($providers)
            ->mapWithKeys(fn (string $provider, int $index) => [$provider => $index === 0 ? $model : null])
            ->all();
    }

    public function model(): ?string
    {
        return config('newspub.ai.model') ?: null;
    }

    /**
     * OpenAI reasoning models (gpt-5, gpt-6, o-series) think at NEWSPUB_AI_REASONING_EFFORT. Other models and
     * providers reject the setting, so it is only sent where it is understood.
     *
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $provider = $provider instanceof Lab ? $provider->value : $provider;
        $effort = config('newspub.ai.reasoning_effort');

        if ($provider !== Lab::OpenAI->value || blank($effort)) {
            return [];
        }

        // The configured model applies to the first provider only; a fail-over provider uses its default.
        $model = (self::configuredProviders()[0] === $provider ? $this->model() : null)
            ?? app(AiManager::class)->textProvider($provider)->defaultTextModel();

        return preg_match('/^(gpt-5(?!-chat)|gpt-6|o\d)/', $model) === 1
            ? ['reasoning' => ['effort' => $effort]]
            : [];
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
