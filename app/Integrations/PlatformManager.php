<?php

namespace App\Integrations;

use App\Enums\Platform;
use App\Integrations\Contracts\PlatformDriver;
use App\Integrations\Drivers\BeehiivDriver;
use App\Integrations\Drivers\GhostDriver;
use App\Integrations\Drivers\HtmlExportDriver;
use App\Integrations\Drivers\KitDriver;
use App\Integrations\Drivers\MailchimpDriver;
use App\Integrations\Drivers\MailerLiteDriver;
use Illuminate\Support\Manager;

/**
 * Driver-per-platform integration layer.
 *
 * @method PlatformDriver driver(string|null $driver = null)
 */
class PlatformManager extends Manager
{
    public function for(Platform $platform): PlatformDriver
    {
        return $this->driver($platform->value);
    }

    public function getDefaultDriver(): string
    {
        return Platform::HtmlExport->value;
    }

    protected function createBeehiivDriver(): PlatformDriver
    {
        return new BeehiivDriver;
    }

    protected function createGhostDriver(): PlatformDriver
    {
        return new GhostDriver;
    }

    protected function createKitDriver(): PlatformDriver
    {
        return new KitDriver;
    }

    protected function createMailchimpDriver(): PlatformDriver
    {
        return new MailchimpDriver;
    }

    protected function createMailerliteDriver(): PlatformDriver
    {
        return new MailerLiteDriver;
    }

    protected function createHtmlExportDriver(): PlatformDriver
    {
        return new HtmlExportDriver;
    }
}
