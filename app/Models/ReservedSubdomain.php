<?php

namespace App\Models;

use Database\Factories\ReservedSubdomainFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A subdomain an admin has kept back from publications, on top of the built-in list in newspub.reserved_subdomains.
 */
#[Fillable(['name'])]
class ReservedSubdomain extends Model
{
    /** @use HasFactory<ReservedSubdomainFactory> */
    use HasFactory;

    /**
     * Every subdomain no publication may claim: the built-in list plus those added under Admin.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return collect(config('newspub.reserved_subdomains'))
            ->merge(self::query()->pluck('name'))
            ->unique()
            ->values()
            ->all();
    }
}
