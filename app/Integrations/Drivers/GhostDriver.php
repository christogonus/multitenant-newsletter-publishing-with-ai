<?php

namespace App\Integrations\Drivers;

use App\Enums\Platform;
use App\Integrations\PlatformException;
use App\Models\Issue;
use App\Models\PlatformConnection;
use App\Support\IssueRenderer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

class GhostDriver extends HttpDriver
{
    public function fields(): array
    {
        return [
            ['key' => 'site_url', 'label' => 'Your Ghost site address', 'hint' => 'For example https://yourname.ghost.io', 'secret' => false, 'required' => true],
            ['key' => 'admin_key', 'label' => 'Admin key', 'hint' => 'Looks like two parts joined by a colon.', 'secret' => true, 'required' => true],
        ];
    }

    public function steps(): array
    {
        return [
            'Sign in to Ghost and open Settings, then Integrations.',
            'Press "Add custom integration" and name it NewsPub AI.',
            'Copy the Admin key and your site address.',
            'Paste both below and press Test connection.',
        ];
    }

    public function test(PlatformConnection $connection): void
    {
        $this->send($connection, fn (PendingRequest $http) => $http->get('site/'));
    }

    public function syncSubscribers(PlatformConnection $connection, Collection $subscribers): int
    {
        foreach ($subscribers as $subscriber) {
            $this->send($connection, fn (PendingRequest $http) => $http->post('members/', [
                'members' => [['email' => $subscriber->email, 'name' => $subscriber->name]],
            ]), acceptable: [422]);
        }

        return $subscribers->count();
    }

    public function pushIssue(PlatformConnection $connection, Issue $issue): ?string
    {
        $response = $this->send($connection, fn (PendingRequest $http) => $http->post('posts/?source=html', [
            'posts' => [[
                'title' => $issue->title,
                'html' => IssueRenderer::contentHtml($issue),
                'custom_excerpt' => $issue->preheader ? mb_substr($issue->preheader, 0, 300) : null,
                'status' => 'draft',
            ]],
        ]));

        return $response->json('posts.0.id');
    }

    /**
     * Ghost admin tokens are short-lived HS256 JWTs signed with the hex secret half of the admin key.
     */
    public static function token(string $adminKey, ?int $issuedAt = null): string
    {
        [$id, $secret] = array_pad(explode(':', $adminKey, 2), 2, '');

        if ($id === '' || $secret === '' || ! ctype_xdigit($secret)) {
            throw new PlatformException(
                'That Ghost admin key does not look right.',
                'Copy the whole Admin key from Ghost. It has two parts joined by a colon.',
                credentialsInvalid: true,
            );
        }

        $issuedAt ??= time();
        $encode = fn (array $part) => rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');

        $header = $encode(['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $id]);
        $payload = $encode(['iat' => $issuedAt, 'exp' => $issuedAt + 300, 'aud' => '/admin/']);
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", hex2bin($secret), true)), '+/', '-_'), '=');

        return "{$header}.{$payload}.{$signature}";
    }

    protected function platform(): Platform
    {
        return Platform::Ghost;
    }

    protected function client(PlatformConnection $connection): PendingRequest
    {
        $site = rtrim($this->required($connection, 'site_url', 'site address'), '/');

        return $this->http()
            ->baseUrl($site.'/ghost/api/admin')
            ->withHeaders([
                'Authorization' => 'Ghost '.self::token($this->required($connection, 'admin_key', 'Admin key')),
                'Accept-Version' => 'v5.0',
            ]);
    }
}
