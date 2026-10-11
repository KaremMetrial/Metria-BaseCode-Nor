<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page['title'] }} — {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="bg-slate-50 text-slate-800 antialiased">
    <main class="mx-auto max-w-3xl px-6 py-12 sm:py-20">
        <nav class="mb-10 flex items-center justify-between gap-4" aria-label="{{ app()->getLocale() === 'ar' ? 'اللغة' : 'Language' }}">
            <span class="font-semibold">{{ config('app.name') }}</span>
            <a class="text-blue-700 underline underline-offset-4" href="{{ route('pages.show', ['slug' => $page['slug'], 'locale' => app()->getLocale() === 'ar' ? 'en' : 'ar']) }}">{{ app()->getLocale() === 'ar' ? 'English' : 'العربية' }}</a>
        </nav>
        <article class="rounded-2xl border border-slate-200 bg-white p-6 sm:p-10">
            <h1 class="mb-8 text-3xl font-bold tracking-tight">{{ $page['title'] }}</h1>
            <div class="space-y-5 break-words leading-8 [&_a]:text-blue-700 [&_a]:underline [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ms-6 [&_ol]:list-decimal [&_ul]:list-disc [&_img]:max-w-full">
                {!! $page['content_html'] !!}
            </div>
        </article>

        @if ($contact !== null)
            @php($arabic = app()->getLocale() === 'ar')
            <section class="mt-8 flex flex-col gap-6 rounded-2xl border border-slate-200 bg-white p-6 sm:p-10">
                @if ($contact['intro'] ?? null)<p>{{ $contact['intro'] }}</p>@endif
                @if ($contact['phone'])<a class="text-blue-700 underline" dir="ltr" href="tel:{{ $contact['phone'] }}">{{ $contact['phone'] }}</a>@endif
                @if ($contact['whatsapp'])<a class="text-blue-700 underline" href="https://wa.me/{{ ltrim($contact['whatsapp'], '+') }}">WhatsApp</a>@endif
                @if ($contact['address'] ?? null)<p>{{ $contact['address'] }}</p>@endif
                @if ($contact['working_hours'] ?? null)<p>{{ $contact['working_hours'] }}</p>@endif
                @foreach ($contact['social_links'] as $link)<a class="text-blue-700 underline" rel="noopener noreferrer" href="{{ $link['url'] }}">{{ $link['label'] }}</a>@endforeach
                @if (session('contact_reference'))
                    <p role="status" class="rounded-lg bg-emerald-50 p-4 text-emerald-900">{{ __('content.contact_received') }} <span dir="ltr">{{ session('contact_reference') }}</span></p>
                @endif
                @if ($errors->any())
                    <ul role="alert" class="list-inside list-disc text-red-700">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                @endif
                <h2 class="text-xl font-semibold">{{ $arabic ? 'أرسل رسالة' : 'Send a message' }}</h2>
                <form method="POST" action="{{ route('contact.store', ['locale' => app()->getLocale()]) }}" class="flex flex-col gap-5">
                    @csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() }}">
                    <input type="hidden" name="request_id" value="{{ old('request_id', (string) \Illuminate\Support\Str::uuid()) }}">
                    <label class="flex flex-col gap-2">{{ $arabic ? 'الاسم' : 'Name' }}<input class="rounded-lg border border-slate-300 p-3" name="name" autocomplete="name" maxlength="255" required value="{{ old('name') }}"></label>
                    <label class="flex flex-col gap-2">{{ $arabic ? 'الدولة' : 'Country' }}<select class="rounded-lg border border-slate-300 p-3" name="country_id" required>
                        <option value="">{{ $arabic ? 'اختر الدولة' : 'Choose a country' }}</option>
                        @foreach ($countries as $country)<option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>{{ $country->name }} ({{ $country->calling_code }})</option>@endforeach
                    </select></label>
                    <label class="flex flex-col gap-2">{{ $arabic ? 'رقم الهاتف' : 'Phone number' }}<input class="rounded-lg border border-slate-300 p-3" name="phone" type="tel" autocomplete="tel" maxlength="40" required value="{{ old('phone') }}"></label>
                    <label class="flex flex-col gap-2">{{ $arabic ? 'الموضوع' : 'Subject' }}<input class="rounded-lg border border-slate-300 p-3" name="subject" maxlength="200" required value="{{ old('subject') }}"></label>
                    <label class="flex flex-col gap-2">{{ $arabic ? 'الرسالة' : 'Message' }}<textarea class="rounded-lg border border-slate-300 p-3" name="message" rows="5" maxlength="5000" required>{{ old('message') }}</textarea></label>
                    <button class="rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-700" type="submit">{{ $arabic ? 'إرسال الرسالة' : 'Send message' }}</button>
                </form>
            </section>
        @endif
    </main>
</body>
</html>
