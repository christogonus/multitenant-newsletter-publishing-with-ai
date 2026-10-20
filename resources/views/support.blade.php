@auth
    <x-layouts::app title="Help">
        <div class="space-y-6">
            <x-ui.page-header title="Help" description="Quick answers, and a real person if you need one." icon="question" />
            @include('partials.support')
        </div>
    </x-layouts::app>
@else
    <x-layouts::page title="Support">
        <div class="space-y-6">
            <div class="py-4 text-center">
                <h1 class="text-2xl font-bold text-ink sm:text-3xl">Welcome to NewsPub AI</h1>
                <p class="mx-auto mt-2 max-w-xl text-muted">Your login details, a quick setup guide, and how to reach us if you get stuck.</p>
            </div>
            @include('partials.support')
        </div>
    </x-layouts::page>
@endauth
