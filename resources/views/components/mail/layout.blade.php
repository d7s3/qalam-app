{{--
    The frame every message this application sends is written in.

    Mail is not a web page: a good half of the clients that will open this
    understand tables and inline styles and very little else, and none of them
    load a stylesheet. So the layout is a table, the styles sit on the elements,
    and the brand's colours are written out rather than named — there is no
    `text-maroon` inside an inbox.

    Right to left is set on the html element itself, because a client that
    ignores `dir` on a div will still honour it there.
--}}
@props(['heading' => null, 'preview' => null])

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading ?? config('brand.name') }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f7efe0; font-family:'Tajawal','Segoe UI',Arial,sans-serif; color:#262626;">

    {{-- The line a mail client shows beside the subject, and never displays. --}}
    @if ($preview)
        <div style="display:none; max-height:0; overflow:hidden; opacity:0;">{{ $preview }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7efe0; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                    style="max-width:560px; background-color:#ffffff; border-radius:16px; overflow:hidden; border:1px solid #ecccc8;">

                    {{-- الترويسة --}}
                    <tr>
                        <td align="center" style="background-color:#7a2727; padding:28px 24px;">
                            <div style="color:#ffffff; font-size:19px; font-weight:bold; letter-spacing:-0.2px;">
                                {{ config('brand.name') }}
                            </div>
                            <div style="color:#e6cd99; font-size:12px; margin-top:6px;">
                                {{ config('brand.tagline') }}
                            </div>
                        </td>
                    </tr>

                    {{-- خيط ذهبي --}}
                    <tr><td style="height:3px; background-color:#c9a063; line-height:3px; font-size:0;">&nbsp;</td></tr>

                    <tr>
                        <td style="padding:32px 28px;" dir="rtl" align="right">
                            @if ($heading)
                                <h1 style="margin:0 0 16px; font-size:20px; font-weight:bold; color:#521f1e;">{{ $heading }}</h1>
                            @endif

                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- التذييل --}}
                    <tr>
                        <td style="padding:20px 28px; background-color:#fdfaf3; border-top:1px solid #f6e6e4;"
                            dir="rtl" align="right">
                            <div style="font-size:12px; color:#85807c; line-height:1.8;">
                                {{ $footer ?? __('وصلتك هذه الرسالة لأنّ لك حساباً في :name.', ['name' => config('brand.name')]) }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="max-width:560px; margin-top:16px; font-size:11px; color:#a3a3a3;">
                    {{ config('brand.name') }}
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
