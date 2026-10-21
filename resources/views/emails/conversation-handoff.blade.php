@php
    $emailPrimary = trim((string) ($brand['primary_color'] ?? ''));
    if ($emailPrimary === '' || strtolower($emailPrimary) === '#3b82f6') {
        $emailPrimary = '#00A548';
    }
    $emailPrimaryText = $brand['primary_text_color'] ?? '#0f1f19';
    $data = $notification->data ?? [];
@endphp
<x-emails.billing.layout title="Chat needs human attention">
    <h1 style="margin:0 0 10px;font-size:22px;line-height:1.3;color:#17231f;">Chat needs human attention</h1>
    <p style="margin:0 0 14px;color:#53645c;line-height:1.6;">
        {{ $notification->body ?: 'A customer chat was handed off to a human agent.' }}
    </p>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:18px 0;border:1px solid #e2ebe5;border-radius:8px;overflow:hidden;">
        <tr>
            <td style="padding:12px 14px;background:#f7faf8;color:#6b7b73;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">Workspace</td>
            <td style="padding:12px 14px;color:#17231f;font-weight:700;text-align:right;">{{ $account?->name ?: 'Workspace' }}</td>
        </tr>
        <tr>
            <td style="padding:12px 14px;border-top:1px solid #e2ebe5;color:#6b7b73;font-size:12px;text-transform:uppercase;letter-spacing:.04em;">Reason</td>
            <td style="padding:12px 14px;border-top:1px solid #e2ebe5;color:#17231f;text-align:right;">{{ $data['reason'] ?? $notification->body ?? 'Human handoff requested' }}</td>
        </tr>
    </table>
    <p style="margin:22px 0 0;">
        <a href="{{ $actionUrl }}" style="display:inline-block;background:{{ $emailPrimary }};color:{{ $emailPrimaryText }};text-decoration:none;font-weight:700;padding:11px 16px;border-radius:7px;">Open chat</a>
    </p>
</x-emails.billing.layout>
