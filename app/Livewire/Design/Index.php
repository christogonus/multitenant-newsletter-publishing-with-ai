<?php

namespace App\Livewire\Design;

use App\Enums\Product;
use App\Enums\TlsStatus;
use App\Jobs\RefreshDomainCertificateJob;
use App\Jobs\RequestDomainCertificateJob;
use App\Livewire\Concerns\InteractsWithPublication;
use App\Models\Domain;
use App\Support\DomainVerifier;
use App\Support\Hostnames;
use App\Support\PublicPageCache;
use App\Support\Theme;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Design')]
class Index extends Component
{
    use InteractsWithPublication, WithFileUploads;

    public string $accentColor = '#4f46e5';

    public string $font = '';

    /** @var TemporaryUploadedFile|null */
    public $logo = null;

    public string $hostname = '';

    public function mount(): void
    {
        $brand = $this->newsletter->brand();
        $this->accentColor = $brand['accent_color'];
        $this->font = (string) ($this->newsletter->branding['font'] ?? '');
        $this->hostname = (string) $this->newsletter->domain?->hostname;
    }

    public function chooseTemplate(string $key): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        abort_unless(array_key_exists($key, Theme::templates()), 404);

        if (Theme::isPremium($key) && ! $this->user()->owns(Product::Oto8Premium)) {
            $this->toast('That design is part of Premium Designs. Upgrade to use it.', 'warning');

            return;
        }

        $newsletter->update(['template' => $key]);
        PublicPageCache::bust($newsletter);
        unset($this->newsletter);
        $this->toast('Design updated. Your page and emails now use '.Theme::templates()[$key]['name'].'.');
    }

    public function saveBranding(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);
        abort_unless($this->user()->owns(Product::Oto8Premium), 403);

        $this->validate([
            'accentColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['nullable', Rule::in(array_keys(config('templates.fonts')))],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
        ], [
            'accentColor.regex' => 'Pick a colour from the colour picker.',
            'logo.max' => 'The logo must be smaller than 1 MB.',
        ]);

        $branding = $newsletter->branding ?? [];
        $branding['accent_color'] = strtolower($this->accentColor);
        $branding['font'] = $this->font ?: null;

        if ($this->logo) {
            if (! empty($branding['logo_path'])) {
                Storage::disk('public')->delete($branding['logo_path']);
            }
            $branding['logo_path'] = $this->logo->store('logos/'.$newsletter->id, 'public');
            $this->logo = null;
        }

        $newsletter->update(['branding' => $branding]);
        PublicPageCache::bust($newsletter);
        unset($this->newsletter);
        $this->toast('Branding saved.');
    }

    public function removeLogo(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);

        $branding = $newsletter->branding ?? [];

        if (! empty($branding['logo_path'])) {
            Storage::disk('public')->delete($branding['logo_path']);
        }

        unset($branding['logo_path']);
        $newsletter->update(['branding' => $branding]);
        PublicPageCache::bust($newsletter);
        unset($this->newsletter);
    }

    public function saveDomain(): void
    {
        $newsletter = $this->newsletter;
        $this->authorize('update', $newsletter);
        abort_unless($this->user()->owns(Product::Oto8Premium), 403);

        // theirsite.com and www.theirsite.com are the same choice: the bare domain is primary, www forwards to it.
        $this->hostname = Hostnames::normalize($this->hostname);

        $this->validate([
            'hostname' => [
                'required',
                'max:253',
                'regex:/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',
                'not_regex:/'.preg_quote(config('newspub.public_root'), '/').'$/',
                Rule::unique('domains', 'hostname')->whereNotNull('verified_at')->ignore($newsletter->domain?->id),
            ],
        ], [
            'hostname.regex' => 'Enter an address like news.yourname.com.',
            'hostname.not_regex' => 'Use your own web address, not ours.',
            'hostname.unique' => 'That address is already used by another newsletter.',
        ]);

        $domain = $newsletter->domain;

        if ($domain && $domain->hostname === $this->hostname) {
            return;
        }

        $domain?->delete();

        $domain = $newsletter->domain()->create([
            'hostname' => $this->hostname,
            'is_apex' => Hostnames::isApex($this->hostname),
            'verification_token' => Str::lower(Str::random(32)),
        ]);

        unset($this->newsletter);
        $this->toast('Address saved. Add the '.count($domain->setupRecords()).' records below, then press Check.');
    }

    public function verifyDomain(DomainVerifier $verifier): void
    {
        $domain = $this->newsletter->domain;
        abort_unless($domain, 404);
        $this->authorize('update', $domain);

        if ($verifier->verify($domain)) {
            PublicPageCache::bust($this->newsletter);
            RequestDomainCertificateJob::dispatch($domain);
            $this->toast('Your address is connected. We are setting up the secure connection now, which usually takes a few minutes.');
        } else {
            $this->toast('Not every record is showing yet (see the ticks below). Changes can take up to an hour to show up, so try again soon.', 'warning');
        }

        unset($this->newsletter);
    }

    /**
     * Asks for a new https certificate, after a failure or when a request has been stuck.
     */
    public function requestCertificate(): void
    {
        $domain = $this->newsletter->domain;
        abort_unless($domain, 404);
        $this->authorize('update', $domain);

        if (! $domain->canRequestCertificate()) {
            $this->toast('Your secure connection is already being set up. Give it a few more minutes.', 'info');

            return;
        }

        $key = 'certificate-request:'.$domain->id;

        if (RateLimiter::tooManyAttempts($key, Domain::MAX_REQUESTS_PER_HOUR)) {
            $this->toast('You have asked several times already. Please wait an hour, or contact support if it keeps failing.', 'warning');

            return;
        }

        RateLimiter::hit($key, 3600);
        $domain->update(['tls_status' => TlsStatus::Pending, 'tls_error' => null]);
        RequestDomainCertificateJob::dispatch($domain);

        unset($this->newsletter);
        $this->toast('Requested. This usually takes a few minutes.');
    }

    /**
     * Checks the secure connection right now, so the owner sees the real status.
     */
    public function checkCertificate(): void
    {
        $domain = $this->newsletter->domain;
        abort_unless($domain, 404);
        $this->authorize('update', $domain);

        if (RateLimiter::tooManyAttempts('certificate-check:'.$domain->id, 20)) {
            return;
        }

        RateLimiter::hit('certificate-check:'.$domain->id, 3600);
        RefreshDomainCertificateJob::dispatchSync($domain);

        unset($this->newsletter);
    }

    public function removeDomain(): void
    {
        $domain = $this->newsletter->domain;

        if ($domain) {
            $this->authorize('delete', $domain);
            $domain->delete();
        }

        $this->hostname = '';
        unset($this->newsletter);
        $this->toast('Custom address removed. Your page uses your free address again.');
    }

    public function render(): View
    {
        return view('livewire.design.index', [
            'templates' => Theme::templates(),
            'current' => Theme::for($this->newsletter)['key'],
            'fonts' => collect(config('templates.fonts'))->map(fn (array $font) => $font['label'])->all(),
            'hasPremium' => $this->user()->owns(Product::Oto8Premium),
            'premiumProduct' => Product::Oto8Premium,
            'domain' => $this->newsletter->domain,
            'serverIpMissing' => config('newspub.certificates.server_ips') === [],
        ]);
    }
}
