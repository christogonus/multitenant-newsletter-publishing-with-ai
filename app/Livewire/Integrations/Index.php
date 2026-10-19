<?php

namespace App\Livewire\Integrations;

use App\Enums\ConnectionStatus;
use App\Enums\IssueStatus;
use App\Enums\Platform;
use App\Integrations\PlatformException;
use App\Integrations\PlatformManager;
use App\Jobs\PushIssueJob;
use App\Jobs\SyncSubscribersJob;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\IssueDelivery;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Email services')]
class Index extends Component
{
    use InteractsWithPublication;

    public ?string $editing = null;

    /** @var array<string, string> */
    public array $credentials = [];

    public function edit(string $platform): void
    {
        $platform = Platform::from($platform);
        $this->editing = $platform->value;
        $this->resetValidation();

        $existing = $this->connectionFor($platform);
        $this->credentials = collect(app(PlatformManager::class)->for($platform)->fields())
            ->mapWithKeys(fn (array $field) => [$field['key'] => $field['secret'] ? '' : (string) $existing?->credential($field['key'])])
            ->all();
    }

    public function cancel(): void
    {
        $this->reset('editing', 'credentials');
    }

    public function connect(PlatformManager $platforms): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $platform = Platform::from((string) $this->editing);
        $existing = $this->connectionFor($platform);

        if (! $existing && ! $this->canAddConnection()) {
            $this->toast('Connecting email services is part of Delivery and Platform Integrations.', 'warning');

            return;
        }

        $driver = $platforms->for($platform);

        $rules = collect($driver->fields())->mapWithKeys(function (array $field) use ($existing) {
            $keepSecret = $field['secret'] && $existing?->credential($field['key']);

            return ['credentials.'.$field['key'] => [
                $field['required'] && ! $keepSecret ? 'required' : 'nullable',
                'string',
                'max:500',
                ...($field['key'] === 'reply_to' || $field['key'] === 'from_email' ? ['email'] : []),
                ...($field['key'] === 'site_url' ? ['url:https'] : []),
            ]];
        })->all();

        $this->validate($rules, [
            'credentials.*.required' => 'Please fill this in.',
            'credentials.*.url' => 'Enter the full address starting with https://',
        ]);

        $values = collect($this->credentials)->map(fn ($value) => trim((string) $value));
        $merged = array_merge($existing?->credentials ?? [], $values->filter(fn ($value) => $value !== '')->all());

        $connection = $existing ?? new PlatformConnection([
            'user_id' => $this->user()->id,
            'newsletter_id' => $newsletter->id,
            'platform' => $platform,
        ]);
        $connection->credentials = $merged;

        try {
            $driver->test($connection);
        } catch (PlatformException $exception) {
            $this->addError('connection', $exception->getMessage().' '.$exception->fix);

            return;
        }

        $saved = DB::transaction(function () use ($connection) {
            // Lock the owner row and re-check the plan limit, so two tabs cannot both add a connection.
            User::query()->whereKey($this->user()->id)->lockForUpdate()->first();

            if (! $connection->exists && ! $this->canAddConnection()) {
                return false;
            }

            $connection->fill(['status' => ConnectionStatus::Connected, 'last_error' => null])->save();

            return true;
        });

        if (! $saved) {
            $this->toast('Connecting email services is part of Delivery and Platform Integrations.', 'warning');

            return;
        }
        SyncSubscribersJob::dispatch($newsletter);

        $this->reset('editing', 'credentials');
        $this->toast($platform->label().' is connected. We are copying your readers across now.');
    }

    public function test(int $connectionId, PlatformManager $platforms): void
    {
        $connection = $this->ownedConnection($connectionId);

        try {
            $platforms->for($connection->platform)->test($connection);
            $connection->update(['status' => ConnectionStatus::Connected, 'last_error' => null]);
            $this->toast('Connection works.');
        } catch (PlatformException $exception) {
            $connection->update(['status' => ConnectionStatus::Error, 'last_error' => $exception->getMessage().' '.$exception->fix]);
            $this->toast($exception->getMessage(), 'danger');
        }
    }

    public function syncNow(int $connectionId): void
    {
        $this->ownedConnection($connectionId);
        SyncSubscribersJob::dispatch($this->newsletter);
        $this->toast('Copying your readers now.');
    }

    public function sendLatest(int $connectionId): void
    {
        $connection = $this->ownedConnection($connectionId);
        $issue = $this->newsletter->issues()->where('status', IssueStatus::Published)->latest('published_at')->first();

        if (! $issue) {
            $this->toast('Publish an issue first, then send it from here.', 'warning');

            return;
        }

        PushIssueJob::dispatch($issue, $connection);
        $this->toast('Sending “'.$issue->title.'” to '.$connection->platform->label().'. It arrives there as a draft for you to check and send.');
    }

    public function disconnect(int $connectionId): void
    {
        $connection = $this->ownedConnection($connectionId);
        $this->authorize('delete', $connection);
        $connection->delete();
        $this->toast('Disconnected.');
    }

    public function render(PlatformManager $platforms): View
    {
        $connections = $this->newsletter->platformConnections()->get()->keyBy(fn (PlatformConnection $connection) => $connection->platform->value);
        $limit = $this->entitlements()->connectionLimit($this->user());

        return view('livewire.integrations.index', [
            'platforms' => collect(Platform::cases())->map(fn (Platform $platform) => [
                'platform' => $platform,
                'driver' => $platforms->for($platform),
                'connection' => $connections->get($platform->value),
            ]),
            'limit' => $limit,
            'used' => $connections->count(),
            'canAdd' => $this->canAddConnection(),
            'recentDeliveries' => IssueDelivery::query()
                ->whereIn('platform_connection_id', $connections->pluck('id'))
                ->with('issue:id,title', 'platformConnection:id,platform')
                ->latest()
                ->limit(5)
                ->get(),
        ]);
    }

    private function connectionFor(Platform $platform): ?PlatformConnection
    {
        return $this->newsletter->platformConnections()->where('platform', $platform)->first();
    }

    private function ownedConnection(int $connectionId): PlatformConnection
    {
        $connection = $this->newsletter->platformConnections()->findOrFail($connectionId);
        $this->authorize('update', $connection);

        return $connection;
    }

    private function canAddConnection(): bool
    {
        $limit = $this->entitlements()->connectionLimit($this->user());

        return $limit === null || $this->newsletter->platformConnections()->count() < $limit;
    }
}
