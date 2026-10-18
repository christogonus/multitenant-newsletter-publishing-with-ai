<?php

namespace App\Models;

use App\Enums\Frequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A done-for-you newsletter kit (OTO 5): niche, voice, topic pipeline and seed issues.
 */
#[Fillable(['key', 'name', 'niche', 'tagline', 'audience', 'voice', 'frequency', 'topics', 'seed_issues'])]
class Kit extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'frequency' => Frequency::class,
            'topics' => 'array',
            'seed_issues' => 'array',
        ];
    }
}
