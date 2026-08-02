<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $invoice->id }}</title>
</head>
<body>
<h1>Invoice #{{ $invoice->id }}</h1>
<p>Client: {{ $invoice->client->name }}</p>
<p>Due date: {{ $invoice->due_date->toDateString() }}</p>

<table>
    <thead>
    <tr>
        <th>Description</th>
        <th>Qty</th>
        <th>Unit price</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($invoice->items as $item)
        <tr>
            <td>{{ $item->description }}</td>
            <td>{{ $item->quantity }}</td>
            <td>{{ $item->unit_price }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p><strong>Total: {{ $invoice->total_amount }}</strong></p>
</body>
</html>
