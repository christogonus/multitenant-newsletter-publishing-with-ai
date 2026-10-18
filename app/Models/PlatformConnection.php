<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Enums\Platform;
use Database\Factories\PlatformConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'newsletter_id', 'platform', 'credentials', 'status', 'last_error', 'last_pushed_at', 'last_synced_at'])]
#[Hidden(['credentials'])]
class PlatformConnection extends Model
{
    /** @use HasFactory<PlatformConnectionFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'last_pushed_at' => 'datetime',
            'last_synced_at' => 'datetime',
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

    /**
     * @return HasMany<IssueDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(IssueDelivery::class);
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        return $this->credentials[$key] ?? $default;
    }
}
