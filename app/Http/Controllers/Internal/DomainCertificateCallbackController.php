<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshDomainCertificateJob;
use App\Models\Domain;
use Illuminate\Http\JsonResponse;

/**
 * Called by the hosting service (Ploi webhook) when a certificate request finishes. The payload is not
 * trusted or needed: the signed URL identifies the domain and we check https ourselves.
 */
class DomainCertificateCallbackController extends Controller
{
    public function __invoke(Domain $domain): JsonResponse
    {
        RefreshDomainCertificateJob::dispatch($domain)->delay(now()->addSeconds(20));

        return response()->json(['ok' => true]);
    }
}
