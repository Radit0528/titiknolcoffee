<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $order->code }}</title>
    @vite(['resources/css/print.css', 'resources/js/cashier/receipt.js'])
</head>
<body>
    <div class="print-actions">
        <button type="button" data-print>Cetak ulang</button>
        <a href="{{ route('orders.show', $order) }}">Kembali</a>
    </div>
    <x-receipt.receipt :order="$order" :settings="$settings" :logo-url="$logoUrl" :width="$width" />
</body>
</html>
