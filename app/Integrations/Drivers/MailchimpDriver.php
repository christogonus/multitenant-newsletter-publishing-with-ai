<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Integrations\PlatformException;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Support\IssueRenderer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

class MailchimpDriver extends HttpDriver
{
    public function fields(): array
    {
        return [
            ['key' => 'api_key', 'label' => 'Secret key', 'hint' => 'Ends with something like -us21.', 'secret' => true, 'required' => true],
            ['key' => 'audience_id', 'label' => 'Audience ID', 'hint' => 'From Audience, then Settings, then Audience name and defaults.', 'secret' => false, 'required' => true],
            ['key' => 'from_name', 'label' => 'Sender name', 'hint' => 'The name readers see in their inbox.', 'secret' => false, 'required' => true],
            ['key' => 'reply_to', 'label' => 'Reply-to email', 'hint' => 'Must be an email you have confirmed in Mailchimp.', 'secret' => false, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to Mailchimp, open your profile, then Extras, then the keys page.',
            'Create a key and copy it.',
            'Open Audience, then Settings, then "Audience name and defaults" and copy the Audience ID.',
            'Paste them below with your sender name and reply-to email, then press Test connection.',
        ];
    }

    public function test(PlatformConnection $connection): void
    {
        $this->send($connection, fn (PendingRequest $http) => $http->get('ping'));
        $audience = $this->required($connection, 'audience_id', 'Audience ID');
        $this->send($connection, fn (PendingRequest $http) => $http->get("lists/{$audience}", ['fields' => 'id,name']));
    }

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        $audience = $this->required($connection, 'audience_id', 'Audience ID');

        foreach ($subscribers as $subscriber) {
            $hash = md5(strtolower($subscriber->email));

            $this->send($connection, fn (PendingRequest $http) => $http->put("lists/{$audience}/members/{$hash}", [
                'email_address' => $subscriber->email,
                'status_if_new' => 'pending',
                'merge_fields' => array_filter(['FNAME' => $subscriber->name]) ?: new \stdClass,
            ]));
        }

        return $subscribers->count();
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        $audience = $this->required($connection, 'audience_id', 'Audience ID');

        $campaign = $this->send($connection, fn (PendingRequest $http) => $http->post('campaigns', [
            'type' => 'regular',
            'recipients' => ['list_id' => $audience],
            'settings' => [
                'subject_line' => $issue->subject_line ?: $issue->title,
                'preview_text' => (string) $issue->preheader,
                'title' => $issue->title,
                'from_name' => $this->required($connection, 'from_name', 'sender name'),
                'reply_to' => $this->required($connection, 'reply_to', 'reply-to email'),
            ],
        ]));

        $campaignId = (string) $campaign->json('id');

        $this->send($connection, fn (PendingRequest $http) => $http->put("campaigns/{$campaignId}/content", [
            'html' => IssueRenderer::emailHtml($issue),
        ]));

        return $campaignId;
    }

    /**
     * Mailchimp keys end with the data-centre the account lives in, e.g. "-us21".
     */
    public static function dataCenter(string $apiKey): string
    {
        $dataCenter = str_contains($apiKey, '-') ? substr($apiKey, strrpos($apiKey, '-') + 1) : '';

        if (! preg_match('/^[a-z]{2}\d{1,3}$/', $dataCenter)) {
            throw new PlatformException(
                'That Mailchimp key does not look complete.',
                'Copy the whole key from Mailchimp, including the ending such as -us21.',
                credentialsInvalid: true,
            );
        }

        return $dataCenter;
    }

    protected function platform(): Platform
    {
        return Platform::Mailchimp;
    }

    protected function client(PlatformConnection $connection): PendingRequest
    {
        $apiKey = $this->required($connection, 'api_key', 'Secret key');

        return $this->http()
            ->baseUrl('https://'.self::dataCenter($apiKey).'.api.mailchimp.com/3.0')
            ->withBasicAuth('newspub', $apiKey);
    }
}
