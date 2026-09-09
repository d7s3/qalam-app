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

    <p style="margin:0 0 14px; font-size:15px; line-height:2; color:#404040;">
        {{ __('أنشأ لك :inviter حساباً بصفة :role. لم تُوضع لك كلمة مرور — تختارها أنت من الزرّ أدناه.', [
            'inviter' => $inviterName,
            'role' => $roleLabel,
        ]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0;">
        <tr>
            <td align="center" style="background-color:#7a2727; border-radius:10px;">
                <a href="{{ $url }}"
                    style="display:inline-block; padding:14px 34px; color:#ffffff; font-size:15px; font-weight:bold; text-decoration:none;">
                    {{ __('اضبط كلمة المرور') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 14px; font-size:13px; line-height:1.9; color:#85807c;">
        {{ __('الرابط صالح :days أيام، ويعمل مرّةً واحدة. فإن انتهى فاطلب من :inviter إعادة إرساله.', [
            'days' => $days,
            'inviter' => $inviterName,
        ]) }}
    </p>

    <p style="margin:0; font-size:12px; line-height:1.9; color:#a3a3a3; word-break:break-all;">
        {{ __('وإن لم يعمل الزرّ، انسخ هذا العنوان إلى متصفّحك:') }}<br>
        <span dir="ltr">{{ $url }}</span>
    </p>

    <x-slot:footer>
        {{ __('إن لم تكن تتوقّع هذه الرسالة فتجاهلها — لن يُفتح الحساب حتى تضبط كلمته بنفسك.') }}
    </x-slot:footer>
</x-mail.layout>
