<?php

/**
 * Reseller license content.
 *
 * Promotional emails, a sales page, social posts and reseller rules.
 */
$appName = env('APP_NAME', 'NewsletterPub AI');

return [

    'emails' => [
        [
            'title' => 'Introduction',
            'subject' => 'A simpler way to start your own newsletter',
            'body' => implode("\n\n", [
                'Hi,',
                'If you have ever wanted to start an email newsletter but felt stuck on what to write or how to set it up, I wanted to share a tool I have been using called '.$appName.'.',
                'It helps you pick a topic, write each issue with AI, and publish it to your own sign-up page. You stay in control of every word, but you do not have to start from a blank page.',
                'You can get access for {price} here: {link}',
                'It will not do the work of building an audience for you, but it does make the writing and publishing part much easier.',
                'Thanks,',
                '{your_name}',
            ]),
        ],
        [
            'title' => 'How it works',
            'subject' => 'How '.$appName.' works, in three steps',
            'body' => implode("\n\n", [
                'Hi,',
                'A few people asked how '.$appName.' actually works, so here is the short version.',
                'First, you choose your topic and writing style. Second, you ask the AI to draft an issue, then edit it until it sounds like you. Third, you publish it to your own page and share the link with people who might enjoy it.',
                'There are also ready-made topic ideas and starter templates if you are not sure where to begin.',
                'If that sounds useful, you can get access for {price} here: {link}',
                'Any questions, just reply and I will do my best to help.',
                '{your_name}',
            ]),
        ],
        [
            'title' => 'Who it is for',
            'subject' => 'Is '.$appName.' a good fit for you?',
            'body' => implode("\n\n", [
                'Hi,',
                'I want to be honest about who '.$appName.' suits, so you can decide if it is right for you.',
                'It is a good fit if you want to share helpful content with an audience, you are new to newsletters, or you find writing regularly hard to keep up with. It works for hobbies, small businesses, local groups and personal projects.',
                'It is probably not for you if you are looking for a quick way to make money without putting in effort. Growing a newsletter still takes time, consistency and genuinely useful content.',
                'If it sounds like a fit, here is the link: {link}. Access is {price}.',
                'Thanks for reading,',
                '{your_name}',
            ]),
        ],
        [
            'title' => 'Friendly reminder',
            'subject' => 'A quick reminder about '.$appName,
            'body' => implode("\n\n", [
                'Hi,',
                'Just a short note in case you missed my earlier emails about '.$appName.'.',
                'If you have been meaning to start a newsletter, it can help you plan topics, draft issues and publish them to your own page, all in one place.',
                'You can find all the details and get access for {price} here: {link}',
                'No pressure at all. If it is not for you right now, that is completely fine, and I will not keep emailing about it.',
                'All the best,',
                '{your_name}',
            ]),
        ],
    ],

    'sales_page' => [
        'headline' => 'Start your own email newsletter, even if you have never written one before',
        'subheadline' => $appName.' helps you choose a topic, write each issue with AI and publish it to your own sign-up page, all in one simple place.',
        'bullets' => [
            'Pick a niche and writing style that suits you, with guided suggestions.',
            'Draft full newsletter issues with AI, then edit them in your own words.',
            'Publish issues to your own public page that readers can browse.',
            'Share a sign-up page link so new readers can join easily.',
            'Use ready-made topic ideas and templates when you feel stuck.',
            'Built for complete beginners, with no technical setup needed.',
        ],
        'faq' => [
            ['q' => 'Do I need any technical skills?', 'a' => 'No. '.$appName.' is designed for beginners. If you can write an email and copy a link, you can use it.'],
            ['q' => 'Will the AI write everything for me?', 'a' => 'It writes a first draft for each issue, which you can edit and approve. You stay in control of what gets published.'],
            ['q' => 'Is there a guarantee I will make money?', 'a' => 'No. '.$appName.' is a tool to help you write and publish. Results depend on your topic, your effort and your audience.'],
            ['q' => 'How do I access my account after buying?', 'a' => 'You will receive an access code. Enter it when you create your '.$appName.' account to unlock your access. Each code can be used once.'],
            ['q' => 'Who do I contact if I need help?', 'a' => 'Reply to the email you received with your access code, or contact the person you bought from, and they will point you in the right direction.'],
        ],
    ],

    'social_posts' => [
        'Thinking about starting a newsletter but stuck on what to write? '.$appName.' helps you plan topics and draft issues with AI. Take a look: {link}',
        'I have been using '.$appName.' to write and publish my newsletter. It turns a blank page into a first draft in minutes. Details here: {link}',
        'Want to share what you know with people who care about the same things? A simple newsletter is a great place to start. {link}',
        'Small business owners: a regular email to your customers can keep you top of mind. '.$appName.' makes it easier to write one. {link}',
        'Beginner friendly, no technical setup, and you stay in control of every word. Start your own newsletter with '.$appName.': {link}',
    ],

    'rules' => [
        'Only email people who have agreed to hear from you. Never send spam or buy email lists.',
        'Do not make income claims or promise that buyers will earn money.',
        'Each access code is single use. Give one code per buyer and never share codes publicly.',
        'Do not resell, share or offer the '.$appName.' source code, and do not offer self-hosted copies.',
        'Describe the product honestly and do not invent features, reviews or results.',
        'Use your own checkout and handle your own customer questions and refunds politely.',
    ],

];
