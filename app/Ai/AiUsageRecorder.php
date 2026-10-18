<?php

namespace App\Ai;

use App\Enums\AiUsageKind;
use App\Models\AiUsage;
use App\Models\Newsletter;
use Laravel\Ai\Responses\TextResponse;

/**
 * Writes one ai_usage row per generation. Feeds the quota service, the admin spend page and the spend alert.
 */
class AiUsageRecorder
{
    public function record(TextResponse $response, AiUsageKind $kind, ?Newsletter $newsletter = null, ?int $userId = null): AiUsage
    {
        $inputTokens = $response->usage->inputTokens;
        $outputTokens = $response->usage->outputTokens;

        return AiUsage::create([
            'user_id' => $userId ?? $newsletter?->user_id,
            'newsletter_id' => $newsletter?->id,
            'kind' => $kind,
            'provider' => $response->meta->provider,
            'model' => $response->meta->model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cost' => self::cost($inputTokens, $outputTokens),
            'created_at' => now(),
        ]);
    }

    public static function cost(int $inputTokens, int $outputTokens): float
    {
        return round(
            $inputTokens / 1_000_000 * (float) config('newspub.ai.price_per_million_input')
            + $outputTokens / 1_000_000 * (float) config('newspub.ai.price_per_million_output'),
            6,
        );
    }
}
