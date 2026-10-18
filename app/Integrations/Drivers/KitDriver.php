<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Support\IssueRenderer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

class KitDriver extends HttpDriver
{
    public const BASE_URL = 'https://api.kit.com/v4';

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'Secret key', 'hint' => 'From Kit Settings, then Developer.', 'secret' => true, 'required' => true],
            ['key' => 'form_id', 'label' => 'Form ID', 'hint' => 'Optional. New readers join this form so Kit can ask them to confirm.', 'secret' => false, 'required' => false],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to Kit and open Settings, then Developer.',
            'Create a new key, name it NewsPub AI and copy it.',
            'Optional: open a form in Kit and copy the number from its web address. Turn on "double opt-in" for that form so readers confirm.',
            'Paste below and press Test connection.',
        ];
    }

    public function test(PlatformConnection $connection): void
    {
        $this->send($connection, fn (PendingRequest $http) => $http->get('account'));
    }

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        $formId = trim((string) $connection->credential('form_id'));

        foreach ($subscribers as $subscriber) {
            $this->send($connection, fn (PendingRequest $http) => $http->post('subscribers', array_filter([
                'email_address' => $subscriber->email,
                'first_name' => $subscriber->name,
            ])));

            if ($formId !== '') {
                $this->send($connection, fn (PendingRequest $http) => $http->post("forms/{$formId}/subscribers", [
                    'email_address' => $subscriber->email,
                ]));
            }
        }

        return $subscribers->count();
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        $response = $this->send($connection, fn (PendingRequest $http) => $http->post('broadcasts', [
            'subject' => $issue->subject_line ?: $issue->title,
            'preview_text' => $issue->preheader,
            'description' => $issue->title,
            'content' => IssueRenderer::emailHtml($issue),
            'public' => false,
            'send_at' => null,
        ]));

        return (string) $response->json('broadcast.id') ?: null;
    }

    protected function platform(): Platform
    {
        return Platform::Kit;
    }

    protected function client(PlatformConnection $connection): PendingRequest
    {
        return $this->http()->baseUrl(self::BASE_URL)->withHeaders(['X-Kit-Api-Key' => $this->required($connection, 'api_key', 'Secret key')]);
    }
}
