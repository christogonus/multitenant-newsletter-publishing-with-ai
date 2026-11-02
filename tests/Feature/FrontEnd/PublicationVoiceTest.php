<?php

use App\Livewire\Publication\Settings;
use App\Models\Newsletter;
use Livewire\Livewire;

test('a voice at the limit is saved', function () {
    $user = buyer();
    $newsletter = Newsletter::factory()->for($user)->create();
    $voice = str_repeat('a', Newsletter::VOICE_MAX_LENGTH);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('voice', $voice)
        ->call('save')
        ->assertHasNoErrors();

    expect($newsletter->refresh()->voice)->toBe($voice);
});

test('a voice over the limit is rejected', function () {
    $user = buyer();
    $newsletter = Newsletter::factory()->for($user)->create(['voice' => 'Warm']);

    Livewire::actingAs($user)->test(Settings::class)
        ->set('voice', str_repeat('a', Newsletter::VOICE_MAX_LENGTH + 1))
        ->call('save')
        ->assertHasErrors(['voice' => 'max']);

    expect($newsletter->refresh()->voice)->toBe('Warm');
});

test('the voice box stops typing at the limit and shows a counter', function () {
    $user = buyer();
    Newsletter::factory()->for($user)->create();

    Livewire::actingAs($user)->test(Settings::class)
        ->assertSeeHtml('maxlength="'.Newsletter::VOICE_MAX_LENGTH.'"')
        ->assertSeeHtml('/ '.Newsletter::VOICE_MAX_LENGTH);
});
