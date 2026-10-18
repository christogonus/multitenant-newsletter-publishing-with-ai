<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['newsletter_id', 'hostname', 'verification_token', 'verified_at', 'tls_status', 'last_checked_at'])]
class Domain extends Model
{
    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
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
     * The TXT record name the owner adds to prove they own the domain.
     */
    public function verificationRecordName(): string
    {
        return '_newspub.'.$this->hostname;
    }

    public function verificationRecordValue(): string
    {
        return 'newspub-verify='.$this->verification_token;
    }
}
