<?php

namespace App\Models;

use App\Enums\ConnectionStatus;
use App\Enums\PaymentProvider;
use Database\Factories\PaymentAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An owner's own Stripe or PayPal account, used to charge paying readers of one newsletter (OTO 4).
 */
#[Fillable(['newsletter_id', 'provider', 'credentials', 'details', 'status', 'last_error'])]
#[Hidden(['credentials'])]
class PaymentAccount extends Model
{
    /** @use HasFactory<PaymentAccountFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'provider' => PaymentProvider::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'details' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Newsletter, $this>
     */
    public function newsletter(): BelongsTo
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function credential(string $key, ?string $default = null): ?string
    {
        return $this->credentials[$key] ?? $default;
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return data_get($this->details ?? [], $key, $default);
    }
}
