<?php

namespace App\Ai\Agents\Concerns;

/**
 * Reads the provider and model for every NewsPub agent from config/newspub.php.
 */
trait UsesNewsPubModel
{
    public function provider(): string
    {
        return (string) config('newspub.ai.provider');
    }

    public function model(): ?string
    {
        return config('newspub.ai.model') ?: null;
    }

    public function timeout(): int
    {
        return 120;
    }
}
