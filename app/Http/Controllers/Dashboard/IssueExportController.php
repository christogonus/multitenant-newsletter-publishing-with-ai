<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Issue;
use App\Support\IssueRenderer;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTML export (OTO 1): a ready-to-paste email file for Substack and any other service.
 */
class IssueExportController extends Controller
{
    public function __invoke(Issue $issue): Response
    {
        $this->authorize('view', $issue);

        $filename = Str::slug($issue->title ?: 'issue').'.html';

        return response()->streamDownload(
            fn () => print (IssueRenderer::emailHtml($issue)),
            $filename,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
