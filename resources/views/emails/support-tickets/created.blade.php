<x-mail::message>
# {{ $severity['label'] }} support ticket

<x-mail::panel>
**{{ $ticket->ticket_number }} · {{ $ticket->categoryLabel() }}**  
**Severity:** {{ $severity['label'] }} — {{ $severity['message'] }}  
**Response guidance:** {{ $severity['attention'] }}
</x-mail::panel>

## {{ $ticket->subject }}

{{ $ticket->description }}

@if ($ticket->attachments->isNotEmpty())
## Attachments

@foreach ($ticket->attachments as $attachment)
- {{ $attachment->original_name }} ({{ number_format($attachment->size / 1024, 1) }} KB)
@endforeach
@endif

<x-mail::table>
| Reporter | Submitted | Page |
| :--- | :--- | :--- |
| {{ $ticket->user->name }} ({{ $ticket->user->email }}) | {{ $ticket->submitted_at?->timezone(config('app.timezone'))->format('M j, Y g:i A T') }} | {{ $ticket->page_url ?? 'Not provided' }} |
</x-mail::table>

This request has been saved in the support ticket history. Reply directly to the reporter’s email if you need more context.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
