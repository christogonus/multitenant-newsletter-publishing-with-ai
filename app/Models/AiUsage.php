<?php

namespace App\Models;

use App\Enums\AiUsageKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'ai_usage', timestamps: false)]
#[Fillable(['user_id', 'newsletter_id', 'kind', 'provider', 'model', 'input_tokens', 'output_tokens', 'cost', 'created_at'])]
class AiUsage extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'kind' => AiUsageKind::class,
            'cost' => 'decimal:6',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }
}
