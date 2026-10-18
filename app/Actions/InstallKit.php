<?php

namespace App\Actions;

use App\Enums\IssueStatus;
use App\Models\Kit;
use App\Models\Newsletter;
use App\Models\User;
use App\Support\Blocks;
use Illuminate\Support\Facades\DB;

/**
 * Installs a done-for-you kit (OTO 5) as a new publication with its voice, topic pipeline and seed issues as drafts.
 */
class InstallKit
{
    public function __construct(private CreateNewsletter $createNewsletter) {}

    public function handle(User $user, Kit $kit): Newsletter
    {
        return DB::transaction(function () use ($user, $kit) {
            $newsletter = $this->createNewsletter->handle($user, [
                'name' => $kit->name,
                'niche' => $kit->niche,
                'tagline' => $kit->tagline,
                'audience' => $kit->audience,
                'voice' => $kit->voice,
                'frequency' => $kit->frequency->value,
                'kit_key' => $kit->key,
                'topic_pipeline' => $kit->topics,
            ]);

            foreach ($kit->seed_issues as $seed) {
                $newsletter->issues()->create([
                    'title' => $seed['title'],
                    'topic' => $seed['title'],
                    'subject_line' => $seed['subject_line'] ?? $seed['title'],
                    'preheader' => $seed['preheader'] ?? null,
                    'blocks' => Blocks::normalize($seed['blocks']),
                    'status' => IssueStatus::Draft,
                    'is_ai_generated' => false,
                ]);
            }

            return $newsletter;
        });
    }
}
