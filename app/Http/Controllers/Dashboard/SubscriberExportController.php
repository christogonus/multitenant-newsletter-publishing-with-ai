<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV of active readers with their consent record, for importing anywhere.
 */
class SubscriberExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $newsletter = $request->user()->activeNewsletter();
        $this->authorize('view', $newsletter);

        return response()->streamDownload(function () use ($newsletter) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'name', 'source', 'consent_at', 'consent_ip', 'subscribed_at']);

            $newsletter->subscribers()->active()->orderBy('id')->chunk(500, function ($subscribers) use ($handle) {
                foreach ($subscribers as $subscriber) {
                    fputcsv($handle, [
                        $subscriber->email,
                        $this->safeCell($subscriber->name),
                        $subscriber->source,
                        $subscriber->consent_at?->toIso8601String(),
                        $subscriber->consent_ip,
                        $subscriber->created_at->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, Str::slug($newsletter->name).'-readers.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * Stops spreadsheet formula injection from reader-supplied names.
     */
    private function safeCell(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
