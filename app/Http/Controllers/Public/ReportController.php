<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The report-abuse link on every public page. Admins review reports and can take a page offline.
 */
class ReportController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $newsletter = $request->attributes->get('newsletter');

        $validated = $request->validate([
            'reason' => ['required', 'in:spam,harmful,copyright,other'],
            'details' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
            'issue' => ['nullable', 'integer'],
        ]);

        if (! filled($request->input('website'))) {
            $issueId = isset($validated['issue'])
                ? Issue::query()->whereBelongsTo($newsletter)->whereKey($validated['issue'])->value('id')
                : null;

            $newsletter->reports()->create([
                'issue_id' => $issueId,
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
                'reporter_email' => $validated['email'] ?? null,
                'reporter_ip' => $request->ip(),
            ]);
        }

        return back()->with('reported', true);
    }
}
