<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 30px; }

        /* Header */
        .header { display: table; width: 100%; margin-bottom: 20px; border-bottom: 2px solid #1a56db; padding-bottom: 15px; }
        .header-left { display: table-cell; vertical-align: top; width: 60%; }
        .header-right { display: table-cell; vertical-align: top; text-align: right; }
        .company-name { font-size: 22px; font-weight: bold; color: #1a56db; }
        .company-meta { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.6; }
        .invoice-title { font-size: 18px; font-weight: bold; color: #1a56db; }
        .invoice-meta { font-size: 10px; color: #555; margin-top: 4px; line-height: 1.8; }

        /* Bill-to / Bill-from */
        .parties { display: table; width: 100%; margin-bottom: 20px; }
        .party-cell { display: table-cell; width: 50%; vertical-align: top; }
        .party-cell:last-child { padding-left: 20px; }
        .section-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #888; margin-bottom: 4px; letter-spacing: 0.5px; }
        .party-name { font-size: 13px; font-weight: bold; }
        .party-meta { font-size: 10px; color: #444; line-height: 1.7; }

        /* Items table */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items thead tr { background: #1a56db; color: white; }
        table.items thead th { padding: 7px 8px; font-size: 10px; text-align: left; }
        table.items thead th.right { text-align: right; }
        table.items tbody tr { border-bottom: 1px solid #e5e7eb; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
        table.items tbody td { padding: 7px 8px; font-size: 11px; }
        table.items tbody td.right { text-align: right; }

        /* Totals */
        .totals-wrap { display: table; width: 100%; }
        .totals-spacer { display: table-cell; width: 55%; }
        .totals-box { display: table-cell; width: 45%; }
        .totals-row { display: table; width: 100%; margin-bottom: 3px; }
        .totals-label { display: table-cell; font-size: 11px; color: #555; }
        .totals-value { display: table-cell; text-align: right; font-size: 11px; }
        .totals-row.grand { border-top: 2px solid #1a56db; padding-top: 5px; margin-top: 5px; }
        .totals-row.grand .totals-label,
        .totals-row.grand .totals-value { font-size: 13px; font-weight: bold; color: #1a56db; }

        /* Footer */
        .footer { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 12px; font-size: 10px; color: #777; text-align: center; }
        .badge { display: inline-block; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 10px; font-size: 10px; font-weight: bold; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="header-left">
            <div class="company-name">{{ $company['name'] }}</div>
            <div class="company-meta">
                {{ $company['address'] }}<br>
                GST: {{ $company['gst_number'] }} &nbsp;|&nbsp; {{ $company['phone'] }}<br>
                {{ $company['email'] }}
            </div>
        </div>
        <div class="header-right">
            <div class="invoice-title">TAX INVOICE</div>
            <div class="invoice-meta">
                Invoice No: <strong>{{ $invoice_number }}</strong><br>
                Order No: <strong>{{ $order->order_number }}</strong><br>
                Date: <strong>{{ $issued_at->format('d M Y') }}</strong><br>
                @if($order->awb_number)
                    AWB: <strong>{{ $order->awb_number }}</strong>
                @endif
            </div>
        </div>
    </div>

    {{-- Bill To --}}
    <div class="parties">
        <div class="party-cell">
            <div class="section-label">Bill To</div>
            @if($order->dealer)
                <div class="party-name">{{ $order->dealer->business_name }}</div>
                <div class="party-meta">
                    {{ $order->dealer->user->name }}<br>
                    {{ $order->dealer->user->phone }}<br>
                    @if($order->dealer->gst_number) GST: {{ $order->dealer->gst_number }}<br> @endif
                    {{ $order->dealer->state }} – {{ $order->dealer->pincode }}
                </div>
            @else
                <div class="party-name">Walk-in / Direct Customer</div>
            @endif
        </div>
        <div class="party-cell">
            <div class="section-label">Payment Status</div>
            <div style="margin-top:6px;">
                <span class="badge">{{ strtoupper(str_replace('_', ' ', $order->payment_status)) }}</span>
            </div>
            @if($order->dealer)
            <div class="party-meta" style="margin-top:8px;">
                Price Tier: {{ $order->dealer->price_tier ?? 'C' }}<br>
                Credit Used: ₹{{ number_format($order->credit_used, 2) }}
            </div>
            @endif
        </div>
    </div>

    {{-- Line items --}}
    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>Product</th>
                <th>IMEI / Serial</th>
                <th>Grade</th>
                <th class="right">Unit Price (₹)</th>
                <th class="right">GST {{ $order->items->first()?->gst_rate ?? 18 }}%</th>
                <th class="right">Line Total (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->product?->brand }} {{ $item->product?->model }}</td>
                <td style="font-size:10px;">{{ $item->product?->imei ?? '—' }}</td>
                <td>{{ $item->product?->grade ?? '—' }}</td>
                <td class="right">{{ number_format($item->unit_price, 2) }}</td>
                <td class="right">{{ number_format($item->gst_amount, 2) }}</td>
                <td class="right">{{ number_format($item->line_total, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Totals --}}
    <div class="totals-wrap">
        <div class="totals-spacer"></div>
        <div class="totals-box">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">₹{{ number_format($order->subtotal, 2) }}</div>
            </div>
            <div class="totals-row">
                <div class="totals-label">GST (18%)</div>
                <div class="totals-value">₹{{ number_format($order->gst_amount, 2) }}</div>
            </div>
            <div class="totals-row grand">
                <div class="totals-label">Total</div>
                <div class="totals-value">₹{{ number_format($order->total_amount, 2) }}</div>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        This is a computer-generated invoice and does not require a physical signature.<br>
        {{ $company['name'] }} &nbsp;|&nbsp; {{ $company['gst_number'] }} &nbsp;|&nbsp; {{ $company['email'] }}
    </div>

</div>
</body>
</html>
