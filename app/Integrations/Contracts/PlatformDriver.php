<?php

namespace App\Integrations\Contracts;

use App\Integrations\PlatformException;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Models\Subscriber;
use Illuminate\Support\Collection;

/**
 * One driver per email platform (OTO 1). The platform sends the email; NewsPub AI never does.
 */
interface PlatformDriver
{
    /**
     * Credential fields the connect form asks for.
     *
     * @return list<array{key: string, label: string, hint: string, secret: bool, required: bool}>
     */
    public function fields(): array;

    /**
     * Plain-English steps for finding those credentials.
     *
     * @return list<string>
     */
    public function steps(): array;

    /**
     * @throws PlatformException
     */
    public function test(PlatformConnection $connection): void;

    /**
     * @param  Collection<int, Subscriber>  $subscribers
     *
     * @throws PlatformException
     */
    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int;

    /**
     * Creates the issue on the platform as a draft or campaign and returns its remote ID.
     *
     * @throws PlatformException
     */
    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string;
}
