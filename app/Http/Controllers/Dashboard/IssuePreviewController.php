<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Support\IssueRenderer;
use Illuminate\Http\Response;

/**
 * The editor's live preview: the issue exactly as the email will look.
 */
class IssuePreviewController extends Controller
{
    public function __invoke(Issue $issue): Response
    {
        $this->authorize('view', $issue);

        return response(IssueRenderer::emailHtml($issue, complete: true))
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Content-Security-Policy', "default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; frame-ancestors 'self'");
    }
}
