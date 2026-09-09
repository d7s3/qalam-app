{{--
    The letter a person receives when an account has been made for him.

    It carries no password, and never will: a password mailed is a password
    sitting in an inbox for years. It carries a link that lets him choose his
    own, once, and it says plainly when that link stops working — a person who
    opens his mail on Sunday should not find a dead link and no explanation.
--}}
<x-mail.layout :heading="__('أُنشئ لك حساب في :name', ['name' => config('brand.name')])"
    :preview="__('اضبط كلمة مرورك لتدخل حسابك.')">

    <p style="margin:0 0 14px; font-size:15px; line-height:2; color:#404040;">
        {{ __('أهلاً :name،', ['name' => $recipientName]) }}
    </p>

    <p style="margin:0 0 18px; font-size:15px; line-height:2; color:#404040;">
        {{ __('أنشأ لك :inviter حساباً بصفة :role. تدخل ببريدك وبالرمز المبدئي أدناه، ويُطلب منك اختيار كلمةٍ تخصّك فور دخولك.', [
            'inviter' => $inviterName,
            'role' => $roleLabel,
        ]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="margin:0 0 22px; background-color:#fdfaf3; border:1px solid #e6cd99; border-radius:12px;">
        <tr>
            <td style="padding:18px 20px;" dir="rtl" align="right">
                <div style="font-size:12px; color:#85807c;">{{ __('البريد') }}</div>
                <div dir="ltr" style="font-size:15px; font-weight:bold; color:#521f1e; margin-top:2px; text-align:right;">{{ $email }}</div>

                <div style="font-size:12px; color:#85807c; margin-top:14px;">{{ __('الرمز المبدئي') }}</div>
                <div dir="ltr" style="font-size:24px; font-weight:bold; color:#7a2727; letter-spacing:4px; margin-top:2px; text-align:right;">{{ $code }}</div>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0;">
        <tr>
            <td align="center" style="background-color:#7a2727; border-radius:10px;">
                <a href="{{ $loginUrl }}"
                    style="display:inline-block; padding:14px 34px; color:#ffffff; font-size:15px; font-weight:bold; text-decoration:none;">
                    {{ __('ادخل إلى حسابك') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px; font-size:13px; line-height:1.9; color:#85807c;">
        {{ __('الرمز المبدئي يعرفه غيرك، فلا يبقى معك: أوّل دخولٍ يطلب منك تغييره قبل أن ترى شيئاً.') }}
    </p>

    <p style="margin:0 0 14px; font-size:13px; line-height:1.9; color:#85807c;">
        {{ __('أو اضبط كلمتك مباشرةً من هذا الرابط، وهو صالح :days أيام:', ['days' => $days]) }}<br>
        <a href="{{ $url }}" dir="ltr" style="color:#7a2727; word-break:break-all;">{{ $url }}</a>
    </p>

    <x-slot:footer>
        {{ __('إن لم تكن تتوقّع هذه الرسالة فتجاهلها — لن يُفتح الحساب حتى تضبط كلمته بنفسك.') }}
    </x-slot:footer>
</x-mail.layout>
