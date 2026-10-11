<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('languages.supported.'.app()->getLocale().'.rtl') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $link['title'] ?? __('links.unavailable') }} — {{ config('app.name') }}</title>
    <meta name="description" content="{{ $link['description'] ?? __('links.unavailable_help') }}">
    @if ($link)
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $link['title'] }}">
        <meta property="og:description" content="{{ $link['description'] }}">
        <meta property="og:url" content="{{ $link['url'] }}">
    @endif
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="mx-auto max-w-xl px-6 py-12 sm:py-20">
        <p class="mb-8 text-sm font-semibold tracking-wide text-blue-700">{{ config('app.name') }}</p>
        <article class="flex flex-col gap-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-10">
            <h1 class="text-3xl font-bold leading-tight">{{ $link['title'] ?? __('links.unavailable') }}</h1>
            <p class="leading-7 text-slate-600">{{ $link['description'] ?? __('links.unavailable_help') }}</p>
            @if ($link)
                <a class="rounded-xl bg-blue-700 px-5 py-3 text-center font-semibold text-white hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700" href="{{ $link['url'] }}">{{ __('links.open_app') }}</a>
                @if ($link['web_url'])
                    <a class="text-center text-blue-700 underline underline-offset-4" href="{{ $link['web_url'] }}">{{ __('links.read_page') }}</a>
                @endif
                @if ($link['referral_code'])
                    <section class="rounded-xl bg-slate-50 p-5">
                        <h2 class="font-semibold">{{ __('links.invitation') }}</h2>
                        <p class="my-3 select-all break-all font-mono text-xl tracking-wider" dir="ltr">{{ $link['referral_code'] }}</p>
                        <p class="text-sm leading-6 text-slate-600">{{ __('links.invitation_help') }}</p>
                    </section>
                @endif
                <section class="flex flex-col gap-3 border-t border-slate-100 pt-6">
                    <h2 class="font-semibold">{{ __('links.download') }}</h2>
                    @foreach (['android', 'ios'] as $platform)
                        @if ($link['stores'][$platform])
                            <a class="rounded-xl border border-slate-300 px-4 py-3 text-center font-medium hover:bg-slate-50" rel="noopener noreferrer" href="{{ $link['stores'][$platform] }}">{{ __('links.'.$platform) }}</a>
                        @endif
                    @endforeach
                    @if (! $link['stores']['android'] && ! $link['stores']['ios'])
                        <p class="text-sm text-slate-500">{{ __('links.unavailable_stores') }}</p>
                    @endif
                </section>
                <p class="text-sm leading-6 text-slate-500">{{ __('links.after_install') }}</p>
                <p class="text-sm leading-6 text-slate-500">{{ __('links.browser_help') }}</p>
            @endif
        </article>
    </main>
</body>
</html>
