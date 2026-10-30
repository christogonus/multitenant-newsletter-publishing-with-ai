<?php

use App\Actions\CreateNewsletter;
use App\Livewire\Admin\Subdomains;
use App\Models\Newsletter;
use App\Models\ReservedSubdomain;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;

/**
 * @return array<string, list<string>>
 */
function subdomainErrors(string $subdomain, ?Newsletter $ignore = null): array
{
    return Validator::make(['subdomain' => $subdomain], ['subdomain' => CreateNewsletter::subdomainRules($ignore)])->errors()->toArray();
}

test('a new web address needs at least five characters', function () {
    expect(subdomainErrors('news')['subdomain'] ?? [])->toBe(['Use at least 5 characters.'])
        ->and(subdomainErrors('daily'))->toBe([]);
});

test('built-in and admin-added names cannot be claimed', function (string $subdomain) {
    ReservedSubdomain::factory()->create(['name' => 'partners']);

    expect(subdomainErrors($subdomain)['subdomain'] ?? [])->toBe(['That web address is reserved. Try another.']);
})->with(['support', 'webmail', 'noreply', 'cpanel', 'partners']);

test('a publication keeps a short or reserved address it already has, but cannot switch to one', function () {
    $newsletter = Newsletter::factory()->create(['subdomain' => 'ai']);

    expect(subdomainErrors('ai', $newsletter))->toBe([])
        ->and(subdomainErrors('cpanel', $newsletter))->not->toBe([]);
});

test('an address made from a short or reserved name gets a suffix', function (string $name, string $subdomain) {
    expect(app(CreateNewsletter::class)->availableSubdomain($name))->toBe($subdomain);
})->with([
    ['AI', 'ai-news'],
    ['Support', 'support-news'],
    ['Daily Brief', 'daily-brief'],
]);

test('an admin reserves and releases an address', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Subdomains::class)
        ->set('name', ' Partners ')
        ->call('add')
        ->assertHasNoErrors()
        ->set('name', 'partners')
        ->call('add')
        ->assertHasErrors(['name' => 'not_in'])
        ->set('name', 'webmail')
        ->call('add')
        ->assertHasErrors(['name' => 'not_in']);

    $reserved = ReservedSubdomain::sole();
    expect($reserved->name)->toBe('partners');

    Livewire::test(Subdomains::class)->call('remove', $reserved->id);

    expect(ReservedSubdomain::count())->toBe(0);
});

test('only admins can open the page or reserve addresses', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.subdomains'))->assertForbidden();
    Livewire::test(Subdomains::class)->set('name', 'partners')->call('add')->assertForbidden();
});
