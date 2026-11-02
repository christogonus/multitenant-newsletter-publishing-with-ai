<?php

use App\Models\Newsletter;
use Laravel\Dusk\Browser;

test('the voice counter follows the text and typing stops at the limit', function () {
    $user = buyer();
    $newsletter = Newsletter::factory()->for($user)->create(['voice' => 'Warm']);
    $limit = Newsletter::VOICE_MAX_LENGTH;

    $this->browse(function (Browser $browser) use ($user, $limit) {
        $browser->loginAs($user)
            ->visit('/settings/publication')
            ->waitForText("4 / {$limit}")
            ->type('#field-voice', 'Warm and funny')
            ->waitForText("14 / {$limit}")
            ->press('Save settings')
            ->waitForText('Settings saved.')
            ->assertSee("14 / {$limit}")
            ->type('#field-voice', str_repeat('a', $limit + 5))
            ->waitForText("{$limit} / {$limit}");

        expect(mb_strlen($browser->value('#field-voice')))->toBe($limit);
    });

    expect($newsletter->refresh()->voice)->toBe('Warm and funny');
});
