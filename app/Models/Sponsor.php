<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'kind', 'website', 'description', 'niches', 'minimum_audience', 'verified_at', 'is_active'])]
class Sponsor extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'niches' => 'array',
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
