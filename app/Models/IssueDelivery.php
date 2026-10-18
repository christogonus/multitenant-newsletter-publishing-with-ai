<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['issue_id', 'platform_connection_id', 'status', 'remote_id', 'error'])]
class IssueDelivery extends Model
{
    /**
     * @return BelongsTo<Issue, $this>
     */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    /**
     * @return BelongsTo<PlatformConnection, $this>
     */
    public function platformConnection(): BelongsTo
    {
        return $this->belongsTo(PlatformConnection::class);
    }
}
