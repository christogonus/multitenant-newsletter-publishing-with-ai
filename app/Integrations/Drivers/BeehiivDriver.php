<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Support\IssueRenderer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

class BeehiivDriver extends HttpDriver
{
    public const BASE_URL = 'https://api.beehiiv.com/v2';

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'Secret key', 'hint' => 'Starts with a long string of letters and numbers.', 'secret' => true, 'required' => true],
            ['key' => 'publication_id', 'label' => 'Publication ID', 'hint' => 'Starts with pub_', 'secret' => false, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to beehiiv and open Settings, then Integrations.',
            'Under the developer keys section, create a new key and copy it.',
            'On the same page, copy your Publication ID (it starts with pub_).',
            'Paste both below and press Test connection.',
        ];
    }

    public function test(PlatformConnection $connection): void
    {
        $publication = $this->required($connection, 'publication_id', 'Publication ID');

        $this->send($connection, fn (PendingRequest $http) => $http->get("publications/{$publication}"));
    }

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        $publication = $this->required($connection, 'publication_id', 'Publication ID');

        foreach ($subscribers as $subscriber) {
            $this->send($connection, fn (PendingRequest $http) => $http->post("publications/{$publication}/subscriptions", [
                'email' => $subscriber->email,
                'reactivate_existing' => false,
                'send_welcome_email' => true,
                'double_opt_override' => 'on',
                'utm_source' => 'newspub',
            ]));
        }

        return $subscribers->count();
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        $publication = $this->required($connection, 'publication_id', 'Publication ID');

        $response = $this->send($connection, fn (PendingRequest $http) => $http->post("publications/{$publication}/posts", [
            'title' => $issue->title,
            'subtitle' => $issue->preheader,
            'body_content' => IssueRenderer::contentHtml($issue),
            'status' => 'draft',
            'email_settings' => ['email_subject_line' => $issue->subject_line ?: $issue->title, 'email_preview_text' => $issue->preheader],
        ]));

        return $response->json('data.id');
    }

    protected function platform(): Platform
    {
        return Platform::Beehiiv;
    }

    protected function client(PlatformConnection $connection): PendingRequest
    {
        return $this->http()->baseUrl(self::BASE_URL)->withToken($this->required($connection, 'api_key', 'Secret key'));
    }
}
