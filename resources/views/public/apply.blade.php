{{--
    A public form's own page, owing nothing to the organisation hosting it.

    This page is given to strangers — fathers who have never heard of the
    association and are answering for a programme, not for it. So it carries the
    form's name in the tab, the form's colour as the browser's, and the form's
    words in whatever preview a messaging app draws of the link. The association
    keeps its head partial, its favicon and its palette for the pages its own
    people sign into.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="antialiased">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>{{ $form->title }}</title>
    <meta name="description" content="{{ Str::limit(strtok((string) $form->public_intro, "\n"), 160) }}" />

    {{-- What WhatsApp draws when the link is forwarded, which is how most of
         these links travel. --}}
    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ $form->title }}" />
    <meta property="og:description" content="{{ Str::limit(strtok((string) $form->public_intro, "\n"), 160) }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta name="twitter:card" content="summary" />
    @if ($form->header_image_path)
        <meta property="og:image" content="{{ asset('storage/'.$form->header_image_path) }}" />
    @endif

    {{-- The tab's mark: the form's initial in the form's own colour, drawn
         inline so the page asks the association's server for no icon of its. --}}
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        .'<rect width="64" height="64" rx="14" fill="'.($form->color ?: '#1B9A8F').'"/>'
        .'<text x="32" y="45" font-size="38" font-family="system-ui,sans-serif" font-weight="bold"'
        .' text-anchor="middle" fill="#fff">'.e(mb_substr($form->title, 0, 1)).'</text></svg>'
    ) }}" type="image/svg+xml">

    <meta name="theme-color" content="{{ $form->color ?: '#1B9A8F' }}" />

    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen text-zinc-900" style="background:#FDF7EF">
    <livewire:public.apply :token="$form->public_token" />
</body>
</html>
