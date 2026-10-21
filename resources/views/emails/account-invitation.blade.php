@php
    $accountName = $invitation->account?->name ?? 'an account';
    $inviterName = $invitation->inviter?->name ?? 'An account admin';
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Invitation</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h2>You have been invited to join {{ $accountName }}</h2>
    <p>{{ $inviterName }} invited you to join the account.</p>
    <p>Click the button below to accept the invite and create your account:</p>
    <p>
        <a href="{{ $inviteUrl }}" style="display:inline-block;padding:10px 16px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Accept Invitation
        </a>
    </p>
    <p>If the button doesn't work, copy and paste this link into your browser:</p>
    <p>{{ $inviteUrl }}</p>
</body>
</html>
