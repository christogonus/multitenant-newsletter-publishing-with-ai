<?php

namespace App\Console\Commands;

use App\Actions\RecordPurchase;
use App\Http\Controllers\Internal\LaunchpadIpnController;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Sends every IPN saved in storage/logs/launchpad.log back through the IPN controller, oldest first,
 * so sales that were turned away (or never processed) create their accounts after all.
 * Sales already recorded come back as duplicates, so running it twice is safe. Replays are
 * logged again with an X-Replayed-From-Log header, and later replays skip those lines.
 */
#[Signature('launchpad:replay {path? : The log file, storage/logs/launchpad.log by default} {--dry-run : Only list the IPNs that would be replayed}')]
#[Description('Replay the LaunchpadJV IPNs saved in launchpad.log')]
class ReplayLaunchpadLog extends Command
{
    public function handle(LaunchpadIpnController $controller, RecordPurchase $recordPurchase): int
    {
        $path = (string) ($this->argument('path') ?? storage_path('logs/launchpad.log'));
        $key = (string) config('products.launchpad.webhook_key');

        if (! is_file($path)) {
            $this->components->error("No log at {$path}.");

            return self::FAILURE;
        }

        if ($key === '') {
            $this->components->error('Set LAUNCHPAD_WEBHOOK_KEY first.');

            return self::FAILURE;
        }

        // Read the whole file first, since every replay appends a line to it.
        $entries = $this->entries((array) file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
        $totals = [];

        foreach ($entries as $entry) {
            $sale = json_decode($entry['body'], true);
            $label = "{$entry['time']} ".($sale['action'] ?? '?').' '.($sale['user']['email'] ?? $sale['checkout_email'] ?? '?').' product '.($sale['product_id'] ?? '?');

            if ($this->option('dry-run')) {
                $this->line("  {$label}");

                continue;
            }

            $request = Request::create(route('ipn.launchpad', ['key' => $key]), 'POST', server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_REPLAYED_FROM_LOG' => $entry['time'],
            ], content: $entry['body']);

            $response = $controller($request, $recordPurchase);
            $outcome = $response->getStatusCode().' '.$response->getContent();
            $totals[$outcome] = ($totals[$outcome] ?? 0) + 1;

            $this->line("  {$label}: {$outcome}");
        }

        if ($this->option('dry-run')) {
            $this->components->info(count($entries).' '.(count($entries) === 1 ? 'IPN' : 'IPNs').' would be replayed.');

            return self::SUCCESS;
        }

        $this->components->info('Replayed '.count($entries).' '.(count($entries) === 1 ? 'IPN' : 'IPNs').'.');

        foreach ($totals as $outcome => $count) {
            $this->components->twoColumnDetail($outcome, (string) $count);
        }

        return self::SUCCESS;
    }

    /**
     * The "IPN received" lines of the log, leaving out earlier replays.
     *
     * @param  list<string>  $lines
     * @return list<array{time: string, body: string}>
     */
    private function entries(array $lines): array
    {
        $entries = [];

        foreach ($lines as $line) {
            if (! preg_match('/^\[([^\]]+)\] \w+\.INFO: IPN received (\{.*\})\s*$/', $line, $matches)) {
                continue;
            }

            $context = json_decode($matches[2], true);

            if (! is_array($context) || ! is_string($context['body'] ?? null) || isset($context['headers']['x-replayed-from-log'])) {
                continue;
            }

            $entries[] = ['time' => $matches[1], 'body' => $context['body']];
        }

        return $entries;
    }
}
