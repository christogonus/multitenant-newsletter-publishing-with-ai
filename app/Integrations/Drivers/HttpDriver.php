<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Integrations\Contracts\PlatformDriver;
use App\Integrations\PlatformException;
use App\Models\PlatformConnection;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Shared HTTP plumbing for drivers that talk to a platform over the network.
 */
abstract class HttpDriver implements PlatformDriver
{
    abstract protected function platform(): Platform;

    abstract protected function client(PlatformConnection $connection): PendingRequest;

    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout(20)
            ->connectTimeout(10)
            ->withUserAgent('NewsPubAI/1.0')
            ->withOptions(['allow_redirects' => false]);
    }

    /**
     * Runs a request and converts failures into a PlatformException with a plain-English fix.
     *
     * @param  Closure(PendingRequest): Response  $request
     * @param  list<int>  $acceptable  Extra status codes that count as success (for example "already exists").
     *
     * @throws PlatformException
     */
    protected function send(PlatformConnection $connection, Closure $request, array $acceptable = []): Response
    {
        try {
            $response = $request($this->client($connection));
        } catch (ConnectionException $exception) {
            throw PlatformException::fromConnectionError($this->platform(), $exception);
        }

        if ($response->successful() || in_array($response->status(), $acceptable, true)) {
            return $response;
        }

        throw PlatformException::fromResponse($this->platform(), $response);
    }

    /**
     * @throws PlatformException
     */
    protected function required(PlatformConnection $connection, string $key, string $label): string
    {
        $value = trim((string) $connection->credential($key));

        if ($value === '') {
            throw PlatformException::missing($this->platform(), $label);
        }

        return $value;
    }
}
