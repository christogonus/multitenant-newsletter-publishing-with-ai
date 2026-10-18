<?php

namespace App\Models;

use Database\Factories\BumpOfferFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;

/**
 * One downloadable file of an order bump, kept on the private disk and only served to its buyers.
 */
#[Fillable(['bump_offer_id', 'name', 'path', 'mime_type', 'size', 'sort'])]
class BumpOfferFile extends Model
{
    /** @use HasFactory<BumpOfferFileFactory> */
    use HasFactory;

    public const DISK = 'local';

    protected static function booted(): void
    {
        static::deleted(fn (BumpOfferFile $file) => Storage::disk(self::DISK)->delete($file->path));
    }

    /**
     * @return BelongsTo<BumpOffer, $this>
     */
    public function bumpOffer(): BelongsTo
    {
        return $this->belongsTo(BumpOffer::class);
    }

    public function isImage(): bool
    {
        return in_array($this->mime_type, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true);
    }

    public function humanSize(): string
    {
        return Number::fileSize($this->size, precision: 1);
    }
}
