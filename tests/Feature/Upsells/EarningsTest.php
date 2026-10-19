<?php

use App\Enums\EarningSource;
use App\Enums\Product;
use App\Livewire\Publications\Index as Publications;
use App\Models\Earning;
use App\Models\Newsletter;
use Livewire\Livewire;

test('a manager buyer logs earnings and sees them per newsletter, this month and all time', function () {
    $user = buyer(Product::Oto9Multi);
    [$cooking, $travel] = Newsletter::factory()->for($user)->count(2)->sequence(['name' => 'Cooking Weekly'], ['name' => 'Travel Daily'])->create();
    Earning::factory()->for($cooking)->create(['amount' => 100, 'earned_on' => now()->subMonths(2)->toDateString()]);
    $this->actingAs($user);

    Livewire::test(Publications::class)
        ->call('openEarningForm', $cooking->id)
        ->set('earningSource', 'paid_subscriptions')
        ->set('earningDescription', 'October subscribers')
        ->set('earningAmount', '250.50')
        ->call('logEarning')
        ->assertHasNoErrors()
        ->call('openEarningForm', $travel->id)
        ->set('earningDescription', 'Acme Luggage')
        ->set('earningAmount', '400')
        ->call('logEarning')
        ->assertSee('$650.50')
        ->assertSee('$750.50')
        ->assertSee('$350.50')
        ->assertSee('Acme Luggage');

    expect($cooking->earnings()->latest('id')->first())
        ->source->toBe(EarningSource::PaidSubscriptions)
        ->amount->toBe('250.50');
});

test('earnings need an amount above zero, who paid, and a date that has passed', function () {
    $user = buyer(Product::Oto9Multi);
    $newsletter = Newsletter::factory()->for($user)->create();
    $this->actingAs($user);

    Livewire::test(Publications::class)
        ->call('openEarningForm', $newsletter->id)
        ->set('earningAmount', '0')
        ->set('earningDate', now()->addDay()->toDateString())
        ->call('logEarning')
        ->assertHasErrors(['earningAmount', 'earningDescription', 'earningDate']);

    expect(Earning::count())->toBe(0);
});

test('nobody can log earnings for, or remove earnings from, someone else\'s newsletter', function () {
    $user = buyer(Product::Oto9Multi);
    Newsletter::factory()->for($user)->create();
    $strangersNewsletter = Newsletter::factory()->create();
    $strangersEarning = Earning::factory()->for($strangersNewsletter)->create();
    $this->actingAs($user);

    Livewire::test(Publications::class)
        ->set('earningNewsletterId', $strangersNewsletter->id)
        ->set('earningDescription', 'Sneaky')
        ->set('earningAmount', '10')
        ->set('earningDate', now()->toDateString())
        ->call('logEarning')
        ->assertNotFound();

    Livewire::test(Publications::class)->call('deleteEarning', $strangersEarning->id)->assertNotFound();

    expect(Earning::count())->toBe(1)->and($strangersNewsletter->earnings()->count())->toBe(1);
});

test('a buyer removes an entry they logged by mistake', function () {
    $user = buyer(Product::Oto9Multi);
    $earning = Earning::factory()->for(Newsletter::factory()->for($user))->create();
    $this->actingAs($user);

    Livewire::test(Publications::class)->call('deleteEarning', $earning->id);

    expect(Earning::count())->toBe(0);
});
