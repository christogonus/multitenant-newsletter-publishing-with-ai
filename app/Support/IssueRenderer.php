<?php

namespace App\Support;

use App\Models\Issue;
use Illuminate\Support\Facades\Blade;

/**
 * Renders an issue's blocks into email-safe HTML. The same output feeds HTML export and every platform driver.
 */
class IssueRenderer
{
    public static function emailHtml(Issue $issue): string
    {
        $newsletter = $issue->newsletter;

        return view('email.issue', [
            'issue' => $issue,
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
        ])->render();
    }

    /**
     * Just the body content (no html/head), for platforms that wrap content in their own template.
     */
    public static function contentHtml(Issue $issue): string
    {
        return Blade::render('<x-public.blocks :blocks="$blocks" />', ['blocks' => $issue->blockList()]);
    }
}
