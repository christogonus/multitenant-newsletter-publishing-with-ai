@php
    $highlights = match ($product) {
        \App\Enums\Product::FrontEnd => ['AI writes your issues', 'A hosted page with a sign-up box', 'Run up to 3 newsletters'],
        \App\Enums\Product::BumpVault => ['Niches ranked by demand and sponsor money', '100 proven subject lines', 'A day-by-day 30-day launch plan'],
        \App\Enums\Product::Oto1Delivery => ['Send through beehiiv, Ghost, Kit, Mailchimp or MailerLite', 'Connect every service you use, and send automatically when you publish', 'Download a ready email for Substack'],
        \App\Enums\Product::Oto2Silver => ['No 3-newsletter cap', 'No weekly limit on issues'],
        \App\Enums\Product::Oto2Gold => ['Everything in Silver', 'Your issues are written first, even at busy times', 'Our richest, most premium writing style'],
        \App\Enums\Product::Oto3Growth => ['AI-made sign-up pages with a free gift', 'Readers invite friends and earn rewards', 'The 1,000-reader playbook'],
        \App\Enums\Product::Oto4Sponsor => ['A directory of sponsors and ad networks', 'An AI-made media kit', 'Pitch emails and a price guide'],
        \App\Enums\Product::Oto5Dfy => ['20+ ready-made newsletters to install', 'Topics and first issues already done', 'Run up to 5 publications'],
        \App\Enums\Product::Oto6Social => ['X threads and LinkedIn posts from every issue', 'Short video scripts', 'One-click copy'],
        \App\Enums\Product::Oto7Autopilot => ['Plan a month of topics in one click', 'Write them all at once', 'Issues go live on schedule'],
        \App\Enums\Product::Oto8Premium => ['4 premium designs', 'Your logo, colours and fonts', 'Your own web address'],
        \App\Enums\Product::Oto9Multi => ['Every newsletter on one dashboard', 'Readers and earnings for each, side by side', 'Switch between them in one click'],
        \App\Enums\Product::Oto10Network => ['A directory of newsletters to team up with', 'Recommend each other on your pages', 'Active members get shown first'],
        \App\Enums\Product::Oto11Agency => ['Run newsletters for paying clients', 'Ready packages and prices', 'Contract templates'],
        \App\Enums\Product::Oto12Reseller => ['Sell '.config('app.name').' accounts as your own', 'Create access codes in one click', 'Sales pages, emails and banners included'],
    };
@endphp

<ul class="grid gap-2 sm:grid-cols-3">
    @foreach ($highlights as $highlight)
        <li class="flex items-start gap-2"><x-ui.icon name="check" size="4" class="mt-0.5 text-success" />{{ $highlight }}</li>
    @endforeach
</ul>
