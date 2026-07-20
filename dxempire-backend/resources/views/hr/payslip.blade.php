<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        .page { padding: 30px; }

        /* Header */
        .header { border-bottom: 2px solid #1a56db; padding-bottom: 14px; margin-bottom: 18px; display: table; width: 100%; }
        .h-left { display: table-cell; vertical-align: middle; }
        .h-right { display: table-cell; vertical-align: middle; text-align: right; }
        .company { font-size: 20px; font-weight: bold; color: #1a56db; }
        .slip-title { font-size: 15px; font-weight: bold; color: #374151; }
        .slip-sub { font-size: 10px; color: #6b7280; margin-top: 3px; }

        /* Employee info */
        .info-grid { display: table; width: 100%; background: #f3f4f6; border-radius: 4px; padding: 12px; margin-bottom: 18px; }
        .info-cell { display: table-cell; width: 33%; vertical-align: top; }
        .info-label { font-size: 9px; font-weight: bold; text-transform: uppercase; color: #6b7280; letter-spacing: 0.4px; }
        .info-value { font-size: 12px; color: #111827; margin-top: 2px; }

        /* Earnings / Deductions tables */
        .tables-wrap { display: table; width: 100%; margin-bottom: 18px; }
        .table-cell { display: table-cell; width: 50%; vertical-align: top; }
        .table-cell:first-child { padding-right: 10px; }
        table.breakdown { width: 100%; border-collapse: collapse; }
        table.breakdown thead tr { background: #1a56db; }
        table.breakdown thead th { color: white; padding: 6px 8px; font-size: 10px; text-align: left; }
        table.breakdown thead th.right { text-align: right; }
        table.breakdown tbody td { padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #e5e7eb; }
        table.breakdown tbody td.right { text-align: right; }
        table.breakdown tfoot td { padding: 6px 8px; font-size: 11px; font-weight: bold; border-top: 2px solid #1a56db; }
        table.breakdown tfoot td.right { text-align: right; }

        /* Net pay box */
        .net-box { background: #1a56db; color: white; border-radius: 6px; padding: 14px 20px; display: table; width: 100%; margin-bottom: 18px; }
        .net-label { display: table-cell; font-size: 14px; }
        .net-amount { display: table-cell; text-align: right; font-size: 20px; font-weight: bold; }

        /* Attendance summary */
        .attend-grid { display: table; width: 100%; margin-bottom: 18px; }
        .attend-cell { display: table-cell; text-align: center; background: #f9fafb; border: 1px solid #e5e7eb; padding: 10px; }
        .attend-val { font-size: 18px; font-weight: bold; color: #1a56db; }
        .attend-lbl { font-size: 9px; color: #6b7280; margin-top: 2px; }

        .footer { border-top: 1px solid #e5e7eb; padding-top: 10px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="h-left">
            <div class="company">DXEMPIRE</div>
            <div style="font-size:10px;color:#6b7280;margin-top:3px;">Pay Slip — {{ $monthName }}</div>
        </div>
        <div class="h-right">
            <div class="slip-title">SALARY SLIP</div>
            <div class="slip-sub">Payroll Run #{{ $run->id }} &nbsp;|&nbsp; {{ ucfirst($run->status) }}</div>
        </div>
    </div>

    {{-- Employee info --}}
    <div class="info-grid">
        <div class="info-cell">
            <div class="info-label">Employee Name</div>
            <div class="info-value">{{ $employee->user->name }}</div>
            <div class="info-label" style="margin-top:8px;">Employee ID</div>
            <div class="info-value">EMP-{{ str_pad($employee->id, 4, '0', STR_PAD_LEFT) }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Designation</div>
            <div class="info-value">{{ $employee->designation ?? '—' }}</div>
            <div class="info-label" style="margin-top:8px;">Department</div>
            <div class="info-value">{{ $employee->department ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Pay Period</div>
            <div class="info-value">{{ $monthName }}</div>
            <div class="info-label" style="margin-top:8px;">Working Days</div>
            <div class="info-value">{{ $workingDays }} days</div>
        </div>
    </div>

    {{-- Attendance summary --}}
    <div class="attend-grid">
        @php
            $present  = $employee->attendance->where('status', 'present')->count();
            $halfDay  = $employee->attendance->where('status', 'half_day')->count();
            $absent   = $employee->attendance->where('status', 'absent')->count();
            $leave    = $employee->attendance->where('status', 'leave')->count();
        @endphp
        <div class="attend-cell"><div class="attend-val">{{ number_format($item->days_worked, 1) }}</div><div class="attend-lbl">Days Worked</div></div>
        <div class="attend-cell"><div class="attend-val">{{ $present }}</div><div class="attend-lbl">Present</div></div>
        <div class="attend-cell"><div class="attend-val">{{ $halfDay }}</div><div class="attend-lbl">Half Day</div></div>
        <div class="attend-cell"><div class="attend-val">{{ $absent }}</div><div class="attend-lbl">Absent</div></div>
        <div class="attend-cell"><div class="attend-val">{{ $leave }}</div><div class="attend-lbl">Leave</div></div>
    </div>

    {{-- Earnings & Deductions --}}
    <div class="tables-wrap">
        <div class="table-cell">
            <table class="breakdown">
                <thead><tr><th>Earnings</th><th class="right">Amount (₹)</th></tr></thead>
                <tbody>
                    <tr><td>Basic Salary</td><td class="right">{{ number_format($employee->basic_salary, 2) }}</td></tr>
                    <tr><td>Per Day Rate</td><td class="right">{{ number_format($perDayRate, 2) }}</td></tr>
                    <tr><td>Days Worked × Rate</td><td class="right">{{ number_format($item->basic, 2) }}</td></tr>
                </tbody>
                <tfoot><tr><td>Gross Earned</td><td class="right">{{ number_format($item->basic, 2) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="table-cell">
            <table class="breakdown">
                <thead><tr><th>Deductions</th><th class="right">Amount (₹)</th></tr></thead>
                <tbody>
                    <tr><td>PF (12%, max ₹1,800)</td><td class="right">{{ number_format($pf, 2) }}</td></tr>
                    <tr><td>Loss of Pay ({{ $absent }} day{{ $absent != 1 ? 's' : '' }})</td><td class="right">{{ number_format($lop, 2) }}</td></tr>
                </tbody>
                <tfoot><tr><td>Total Deductions</td><td class="right">{{ number_format($item->deductions, 2) }}</td></tr></tfoot>
            </table>
        </div>
    </div>

    {{-- Net pay --}}
    <div class="net-box">
        <div class="net-label">Net Take-Home Salary — {{ $monthName }}</div>
        <div class="net-amount">₹{{ number_format($item->net_salary, 2) }}</div>
    </div>

    <div class="footer">
        This is a system-generated pay slip and does not require a signature. &nbsp;|&nbsp; DXEMPIRE
    </div>

</div>
</body>
</html>
