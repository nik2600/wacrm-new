@php
    $statusColor = match ($ticket->status) {
        'open' => '#E5A04E',
        'in_progress' => '#075E54',
        'pending' => '#D86F4E',
        'resolved' => '#0F8556',
        'closed' => '#6B807C',
        default => '#6B807C',
    };
@endphp
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brandName }} support reply</title>
</head>

<body
    style="margin:0;padding:0;background:#F5F3EC;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#0B1F1C;">

    <div
        style="max-width:560px;margin:32px auto;background:#FFFFFF;border:1px solid #EAE6DC;border-radius:14px;overflow:hidden;">

        {{-- Header --}}
        <div style="padding:18px 22px;border-bottom:1px solid #EAE6DC;">
            <div style="font:600 18px/1.2 Georgia,serif;color:#0B1F1C;">{{ $brandName }} {{ __('support') }}</div>
            <div
                style="font:400 11px/1.3 'JetBrains Mono',monospace;color:#6B807C;margin-top:4px;text-transform:uppercase;letter-spacing:0.12em;">
                Re: ticket #{{ $ticket->ticket_number }}
                &nbsp;·&nbsp;
                <span style="color:{{ $statusColor }};">{{ str_replace('_', ' ', $ticket->status) }}</span>
            </div>
        </div>

        {{-- Body --}}
        <div style="padding:22px;">
            <p style="margin:0 0 14px;font-size:14px;line-height:1.5;">
                Hi {{ $ticket->name ?: 'there' }},
            </p>
            <p style="margin:0 0 18px;font-size:14px;line-height:1.5;">
                Our support team replied to your ticket
                "<strong>{{ $ticket->subject }}</strong>":
            </p>

            <div
                style="padding:14px 16px;background:#F5F3EC;border-left:3px solid #075E54;border-radius:8px;font-size:14px;line-height:1.6;white-space:pre-wrap;color:#0B1F1C;">
                {{ $message->body }}</div>

            @if ($ticketUrl)
                <div style="margin-top:22px;text-align:center;">
                    <a href="{{ $ticketUrl }}"
                        style="display:inline-block;padding:10px 22px;background:#075E54;color:#FBFAF6;text-decoration:none;border-radius:999px;font-size:13px;font-weight:600;">
                        {{ __('View ticket & reply') }}
                    </a>
                </div>
            @endif

            <p style="margin:22px 0 0;font-size:12px;line-height:1.5;color:#6B807C;">
                Reply to this email to add to your ticket, or
                @if ($ticketUrl)
                    <a href="{{ $ticketUrl }}" style="color:#075E54;">{{ __('open it on the web') }}</a>
                @endif.
                If your question is resolved you can ignore this message — the ticket
                will close automatically.
            </p>
        </div>

        {{-- Footer --}}
        <div
            style="padding:14px 22px;background:#F5F3EC;border-top:1px solid #EAE6DC;font:400 10.5px/1.4 'JetBrains Mono',monospace;color:#6B807C;">
            Ticket #{{ $ticket->ticket_number }} ·
            opened {{ optional($ticket->created_at)->format('M j, Y') }} ·
            sent by {{ $brandName }}
        </div>
    </div>

</body>

</html>
