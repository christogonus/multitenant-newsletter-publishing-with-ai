<?php

namespace App\Support;

/**
 * The first-time setup guide, shared by the welcome email and the public /support page so they never disagree.
 */
class GettingStarted
{
    /**
     * @return list<array{title: string, body: string}>
     */
    public static function steps(): array
    {
        return [
            [
                'title' => 'Sign in',
                'body' => 'Go to '.route('login').' and sign in with the email you used at checkout and your password.',
            ],
            [
                'title' => 'Change your password',
                'body' => 'Open the account menu at the top right, choose Account, and press Email me a code. Type the code from your inbox with your new password. Do this first: the starting password is the same for every new member.',
            ],
            [
                'title' => 'Start your newsletter',
                'body' => 'On Home, press Start My Newsletter. Pick a topic or type your own, then give your newsletter a name, say who it is for, choose how often it goes out and pick its web address.',
            ],
            [
                'title' => 'Let the AI write your first issue',
                'body' => 'Press Create and write my first issue. The AI writes a complete issue in about a minute.',
            ],
            [
                'title' => 'Read it, edit it, publish it',
                'body' => 'Open the issue and change any heading, paragraph or list you like. Changes save on their own. When you are happy, press Publish.',
            ],
            [
                'title' => 'Share your page',
                'body' => 'Press View my page at the top of the screen to get your newsletter\'s link. Share it in your bio, your posts and with friends so readers can sign up.',
            ],
        ];
    }
}
