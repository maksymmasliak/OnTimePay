<p>Hello,</p>

<p>Please find attached invoice #{{ $invoice->id }}, due on {{ $invoice->due_date->toDateString() }}.</p>

<p>Total amount: {{ $invoice->total_amount }}</p>
