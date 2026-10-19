<?php

namespace App\Support;

use App\Models\Issue;
use Illuminate\Support\Facades\Blade;

/**
 * Renders an issue's blocks into email-safe HTML. The same output feeds HTML export and every platform driver.
 */
class IssueRenderer
{
    /**
     * A locked issue (OTO 4) only carries its free opening and a link to read the rest, because emails reach
     * every reader. Pass $complete for the owner's own preview.
     */
    public static function emailHtml(Issue $issue, bool $complete = false): string
    {
        $newsletter = $issue->newsletter;
        $locked = ! $complete && $issue->isLocked();

        return view('email.issue', [
            'issue' => $issue,
            'newsletter' => $newsletter,
            'theme' => Theme::for($newsletter),
            'blocks' => $locked ? $issue->teaserBlocks() : $issue->blockList(),
            'locked' => $locked,
        ])->render();
    }

    /**
     * Just the body content (no html/head), for platforms that wrap content in their own template and for the feed.
     */
    public static function contentHtml(Issue $issue): string
    {
        $html = Blade::render('<x-public.blocks :blocks="$blocks" />', ['blocks' => $issue->publicBlocks()]);

        if ($issue->isLocked() && $issue->public_slug) {
            $html .= '<p><strong>The rest of this issue is for paying readers.</strong> <a href="'.e($issue->publicUrl()).'">Continue reading on the web &rarr;</a></p>';
        }

        return $html;
    }
}
