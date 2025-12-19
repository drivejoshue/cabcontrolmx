<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #{{ $invoice->id }} – {{ $tenant->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #222;
            margin: 20px;
        }
        .header {
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
        }
        .header .sub {
            font-size: 11px;
            color: #666;
        }
        .row {
            width: 100%;
            margin-bottom: 12px;
        }
        .col-6 {
            width: 48%;
            display: inline-block;
            vertical-align: top;
        }
        h2 {
            font-size: 14px;
            margin: 0 0 6px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px;
            font-size: 11px;
        }
        th {
            background: #f5f5f5;
            text-align: left;
        }
        .text-right {
            text-align: right;
        }
        .totals td {
            font-weight: bold;
        }
        .small {
            font-size: 10px;
            color: #777;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>Factura #{{ $invoice->id }}</h1>
    <div class="sub">
        Emitida: {{ $invoice->issue_date }} · Vence: {{ $invoice->due_date }}<br>
        Tenant: {{ $tenant->name }} (ID {{ $tenant->id }})<br>
        Periodo: {{ $invoice->period_start }} → {{ $invoice->period_end }}
    </div>
</div>

<div class="row">
    <div class="col-6">
        <h2>Datos del tenant</h2>
        <div class="small">
            Nombre: {{ $tenant->name }}<br>
            Slug: {{ $tenant->slug }}<br>
            Timezone: {{ $tenant->timezone }}<br>
            Ciudad: {{ $tenant->city ?? '—' }}<br>
        </div>
    </div>
    <div class="col-6">
        <h2>Plan / Billing</h2>
        <div class="small">
            Plan: {{ $profile->plan_code ?? '—' }}<br>
            Modelo:
            @if(($profile->billing_model ?? 'per_vehicle') === 'commission')
                Comisión por viaje
            @else
                Por vehículo
            @endif
            <br>
            Estado: {{ $profile->status ?? 'trial' }}<br>
            Día de corte: {{ $profile->invoice_day ?? 1 }}<br>
        </div>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>Concepto</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Importe ({{ $invoice->currency }})</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Base mensual</td>
        <td class="text-right">1</td>
        <td class="text-right">{{ number_format($invoice->base_fee, 2) }}</td>
    </tr>
    <tr>
        <td>Vehículos facturados (incluidos y extra)</td>
        <td class="text-right">{{ $invoice->vehicles_count }}</td>
        <td class="text-right">{{ number_format($invoice->vehicles_fee, 2) }}</td>
    </tr>
    <tr class="totals">
        <td colspan="2" class="text-right">Total</td>
        <td class="text-right">{{ number_format($invoice->total, 2) }}</td>
    </tr>
    </tbody>
</table>

@if(!empty($invoice->notes))
    <p class="small" style="margin-top:10px;">
        Notas: {{ $invoice->notes }}
    </p>
@endif

<p class="small" style="margin-top:20px;">
    Este documento es un resumen interno de facturación del servicio Orbana.
    Para cualquier aclaración, contacte a soporte con el ID de tenant y el número de factura.
</p>

</body>
</html>
