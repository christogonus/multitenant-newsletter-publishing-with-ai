<?php

namespace App\Integrations\Drivers;

use App\Integrations\Contracts\PlatformDriver;
use App\Models\Issue;
use App\Models\PlatformConnection;
use Illuminate\Support\Collection;

/**
 * For Substack and any service without a connection: the owner downloads the email file and pastes it in.
 */
class HtmlExportDriver implements PlatformDriver
{
    public function fields(): array
    {
        return [];
    }

    public function steps(): array
    {
        return [
            'Open any newsletter and choose More, then "Download email file".',
            'In Substack (or your email service), start a new post.',
            'Open the file in your browser, select everything and copy it.',
            'Paste it into the new post, check it and send.',
        ];
    }

    public function test(PlatformConnection $connection): void {}

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        return 0;
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        return null;
    }
}
