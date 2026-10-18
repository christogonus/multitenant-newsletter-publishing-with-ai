<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['newsletter_id', 'slug', 'headline', 'subheadline', 'bullets', 'button_label', 'magnet_title', 'magnet_blocks', 'views', 'is_live'])]
class LeadPage extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'bullets' => 'array',
            'magnet_blocks' => 'array',
            'is_live' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    /**
     * @return HasMany<Subscriber, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function publicUrl(): string
    {
        return $this->newsletter->publicUrl('join/'.$this->slug);
    }
}
