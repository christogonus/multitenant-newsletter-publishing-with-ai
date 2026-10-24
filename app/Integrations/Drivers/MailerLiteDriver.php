<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Support\IssueRenderer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

class MailerLiteDriver extends HttpDriver
{
    public const BASE_URL = 'https://connect.mailerlite.com/api';

    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'Secret key', 'hint' => 'From Integrations in MailerLite.', 'secret' => true, 'required' => true],
            ['key' => 'group_id', 'label' => 'Group ID', 'hint' => 'The group new readers join.', 'secret' => false, 'required' => true],
            ['key' => 'from_name', 'label' => 'Sender name', 'hint' => 'The name readers see in their inbox.', 'secret' => false, 'required' => true],
            ['key' => 'from_email', 'label' => 'Sender email', 'hint' => 'Must be an email you have confirmed in MailerLite.', 'secret' => false, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to MailerLite and open Integrations.',
            'Generate a new key, name it '.config('app.name').' and copy it.',
            'Open Subscribers, then Groups, pick a group and copy its ID from the web address.',
            'Paste them below with your sender details, then press Test connection.',
        ];
    }

    public function test(PlatformConnection $connection): void
    {
        $group = $this->required($connection, 'group_id', 'Group ID');
        $this->send($connection, fn (PendingRequest $http) => $http->get("groups/{$group}"));
    }

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        $group = $this->required($connection, 'group_id', 'Group ID');

        foreach ($subscribers as $subscriber) {
            $this->send($connection, fn (PendingRequest $http) => $http->post('subscribers', array_filter([
                'email' => $subscriber->email,
                'fields' => $subscriber->name ? ['name' => $subscriber->name] : null,
                'groups' => [$group],
                'status' => 'unconfirmed',
            ])));
        }

        return $subscribers->count();
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        $group = $this->required($connection, 'group_id', 'Group ID');

        $response = $this->send($connection, fn (PendingRequest $http) => $http->post('campaigns', [
            'name' => mb_substr($issue->title, 0, 255),
            'type' => 'regular',
            'emails' => [[
                'subject' => $issue->subject_line ?: $issue->title,
                'from_name' => $this->required($connection, 'from_name', 'sender name'),
                'from' => $this->required($connection, 'from_email', 'sender email'),
                'content' => IssueRenderer::emailHtml($issue),
            ]],
            'groups' => [$group],
        ]));

        return (string) $response->json('data.id') ?: null;
    }

    protected function platform(): Platform
    {
        return Platform::MailerLite;
    }

    protected function client(PlatformConnection $connection): PendingRequest
    {
        return $this->http()->baseUrl(self::BASE_URL)->withToken($this->required($connection, 'api_key', 'Secret key'));
    }
}
