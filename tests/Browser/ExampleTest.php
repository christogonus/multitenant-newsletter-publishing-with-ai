<?php

use Laravel\Dusk\Browser;

test('the sign-in page loads in a real browser', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
            ->assertSee('Welcome back');
    });
});
