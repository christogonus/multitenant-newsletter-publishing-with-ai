<?php

namespace App\Models;

use App\Enums\ContractStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An agency's paying client (OTO 11).
 */
#[Fillable(['user_id', 'name', 'contact_email', 'package', 'monthly_fee', 'contract_status', 'notes'])]
class Client extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'contract_status' => ContractStatus::class,
            'monthly_fee' => 'decimal:2',
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
     * @return BelongsToMany<Newsletter, $this>
     */
    public function newsletters(): BelongsToMany
    {
        return $this->belongsToMany(Newsletter::class, 'client_newsletters')->withTimestamps();
    }
}
