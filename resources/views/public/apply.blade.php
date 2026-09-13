<!DOCTYPE html>
<html lang="ar" dir="rtl" class="antialiased">
<head>
    @include('partials.head')
    <title>{{ $form->title }}</title>
</head>
<body class="min-h-screen text-zinc-900" style="background:#FDF7EF">
    <livewire:public.apply :token="$form->public_token" />
</body>
</html>
