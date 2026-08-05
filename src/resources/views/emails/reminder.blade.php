@if($reminderType->value === 'overdue')
    <p>Hello,</p>
    <p>Invoice #{{ $invoice->id }} for {{ $invoice->total_amount }} was due on {{ $invoice->due_date->toDateString() }} and remains unpaid.</p>
    <p>Please arrange payment as soon as possible.</p>
@elseif($reminderType->value === 'reminder_1')
    <p>Hello,</p>
    <p>This is a reminder that invoice #{{ $invoice->id }} for {{ $invoice->total_amount }} is still outstanding, originally due {{ $invoice->due_date->toDateString() }}.</p>
@else
    <p>Hello,</p>
    <p>Invoice #{{ $invoice->id }} for {{ $invoice->total_amount }} is significantly overdue (originally due {{ $invoice->due_date->toDateString() }}).</p>
    <p>This account is being escalated to collections. Please contact us immediately to resolve this balance.</p>
@endif
