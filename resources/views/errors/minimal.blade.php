{{--
    The page shown when something is refused, missing or broken.

    Written to stand on its own — no app layout, no compiled stylesheet, no
    query — because the moment it is needed may be the moment those are what
    failed. It says what happened in plain Arabic and always offers the way
    back, since the person reading it is as likely a parent on a phone as
    anyone who would know what a status code is.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">

        <title>@yield('title') - {{ config('brand.name', config('app.name')) }}</title>

        <style>
            :root {
                --page: #f7f5f0;
                --card: #ffffff;
                --ink: #1c1917;
                --muted: #57534e;
                --line: #e7e5e4;
                --accent: #8b6f3d;
                --accent-ink: #ffffff;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --page: #0c0a09;
                    --card: #1c1917;
                    --ink: #fafaf9;
                    --muted: #a8a29e;
                    --line: #292524;
                    --accent: #c9a96a;
                    --accent-ink: #1c1917;
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: var(--page);
                color: var(--ink);
                font-family: system-ui, -apple-system, "Segoe UI", Tahoma, "Noto Sans Arabic", sans-serif;
                line-height: 1.7;
            }

            .card {
                width: 100%;
                max-width: 28rem;
                padding: 2rem 1.5rem;
                text-align: center;
                background: var(--card);
                border: 1px solid var(--line);
                border-radius: 1.25rem;
            }

            .code {
                margin: 0;
                font-size: .875rem;
                font-weight: 600;
                letter-spacing: .1em;
                color: var(--muted);
            }

            h1 {
                margin: .5rem 0 0;
                font-size: 1.375rem;
                line-height: 1.5;
            }

            .message {
                margin: .75rem 0 0;
                color: var(--muted);
            }

            .detail {
                margin: 1rem 0 0;
                padding: .75rem 1rem;
                border-radius: .75rem;
                background: var(--page);
                font-size: .9375rem;
            }

            .actions {
                display: flex;
                flex-direction: column;
                gap: .5rem;
                margin-top: 1.5rem;
            }

            .actions a {
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 2.75rem;
                padding: .5rem 1rem;
                border-radius: .75rem;
                font-weight: 600;
                text-decoration: none;
            }

            .primary {
                background: var(--accent);
                color: var(--accent-ink);
            }

            .secondary {
                border: 1px solid var(--line);
                color: var(--ink);
            }

            .actions a:focus-visible {
                outline: 3px solid var(--accent);
                outline-offset: 2px;
            }
        </style>
    </head>
    <body>
        <main class="card" role="main">
            <p class="code">@yield('code')</p>
            <h1>@yield('title')</h1>
            <p class="message">@yield('message')</p>

            @hasSection('detail')
                <p class="detail">@yield('detail')</p>
            @endif

            <div class="actions">
                @hasSection('primary')
                    @yield('primary')
                @else
                    <a class="primary" href="{{ url('/dashboard') }}">العودة إلى الرئيسية</a>
                @endif
                <a class="secondary" href="javascript:history.back()">الرجوع للصفحة السابقة</a>
            </div>
        </main>
    </body>
</html>
