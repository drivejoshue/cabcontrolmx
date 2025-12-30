<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #{{ $invoice->id }} · Orbana</title>
    <style>
        /* Reset y tipografía básica */
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; margin: 24px; }
        h1,h2,h3,h4 { margin: 0 0 6px 0; }
        small, .small { font-size: 10px; color: #6b7280; }
        .muted { color:#6b7280; }
        .mb-0{margin-bottom:0}.mb-1{margin-bottom:6px}.mb-2{margin-bottom:10px}.mb-3{margin-bottom:14px}.mb-4{margin-bottom:18px}
        .pt-1{padding-top:6px}.pt-2{padding-top:10px}
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:10px; }
        .pill-paid{ background:#dcfce7; color:#065f46; }
        .pill-pending{ background:#fef9c3; color:#92400e; }
        .pill-overdue{ background:#fee2e2; color:#991b1b; }
        .pill-draft{ background:#e5e7eb; color:#374151; }

        /* Tarjetas y tablas */
        .card { border:1px solid #e5e7eb; border-radius:8px; padding:12px; }
        .grid { display: table; width:100%; }
        .col { display: table-cell; vertical-align: top; }
        .col-6 { width:50%; }
        .col-4 { width:33.3333%; }
        .col-8 { width:66.6666%; }

        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align:left; background:#f3f4f6; }
        .table th, .table td { padding:8px 6px; border-bottom:1px solid #e5e7eb; vertical-align: top; }
        .table .num { text-align: right; white-space: nowrap; }

        .totals td { border-top:1px solid #111827; font-weight: 700; }
        .subtle { background:#f9fafb; }

        /* Encabezado con barra de color */
        .brandbar { height: 6px; background: #0ea5e9; border-radius: 3px; margin-bottom: 10px; }
        .logo-box { display:inline-block; padding:6px 10px; border:1px solid #e5e7eb; border-radius:6px; font-weight:700; letter-spacing:.5px; }

        /* Cuadros placeholder (QR/banco) */
        .box { border:1px dashed #cbd5e1; border-radius:8px; padding:10px; }
        .qr { width:100px; height:100px; border:1px solid #e5e7eb; border-radius:6px; background:#fafafa; display:inline-block; }

        /* Footer */
        .footer { margin-top: 18px; border-top:1px solid #e5e7eb; padding-top:10px; }
        .w-100{width:100%}
        .nowrap{white-space:nowrap}
    </style>
</head>
<body>
@php
    $status = strtolower($invoice->status ?? 'pending');
    $statusPillClass = [
        'paid'    => 'pill-paid',
        'overdue' => 'pill-overdue',
        'draft'   => 'pill-draft',
        'pending' => 'pill-pending',
    ][$status] ?? 'pill-pending';

    // Reserva de datos “Orbana empresa”
    $company = [
        'name'    => 'ORBANA (datos fiscales en proceso)',
        'tagline' => 'Sistema de despacho y marketplace',
        'rfc'     => 'RFC: PENDIENTE',
        'addr1'   => 'Dirección fiscal por definir',
        'addr2'   => 'Ciudad · Estado · CP',
        'email'   => 'facturacion@orbana.mx',
        'phone'   => '—'
    ];

    // Ten en cuenta que algunos campos pueden ser null si no existe billingProfile
    $bp = $invoice->billingProfile;
    $included = (int)($bp->included_vehicles ?? 0);
    $vehiclesCount = (int)($invoice->vehicles_count ?? 0);
    $currency = $invoice->currency ?? 'MXN';

    // Impuestos opcionales: deja 0% por ahora; cuando tengan RFC/facturación, ajustan.
    $taxRate = 0.00; // 0.16 para IVA 16% cuando aplique
    $subtotal = (float)($invoice->total ?? 0);
    // Si quieres calcular subtotal a partir de base+extras, usa: $subtotal = (float)$invoice->base_fee + (float)$invoice->vehicles_fee;
    $tax = round($subtotal * $taxRate, 2);
    $grand = round($subtotal + $tax, 2);

    // “Resumen de cálculo” informativo
    $baseFee = (float)($invoice->base_fee ?? 0);
    $extraFee = (float)($invoice->vehicles_fee ?? 0);
    $extraUnit = (float)($bp->price_per_vehicle ?? 0);
    $extrasQty = max(0, $vehiclesCount - $included);
@endphp

<div class="brandbar"></div>

<table class="w-100">
    <tr>
        <td>
            <div class="logo-box">ORBANA</div>
            <div class="small">{{ $company['tagline'] }}</div>
        </td>
        <td class="text-right">
            <h1>Factura #{{ $invoice->id }}</h1>
            <div class="mb-1">
                Emisión: {{ optional($invoice->issue_date)->toDateString() ?? '—' }} ·
                Vence: {{ optional($invoice->due_date)->toDateString() ?? '—' }}
            </div>
            <span class="pill {{ $statusPillClass }}">{{ strtoupper($status) }}</span>
        </td>
    </tr>
</table>

<div class="grid mb-3">
    <div class="col col-6">
        <div class="card">
            <h3 class="mb-1">Central / Tenant</h3>
            <div><strong>{{ optional($invoice->tenant)->name ?? '—' }}</strong></div>
            <div class="small">
                Tenant ID: {{ $invoice->tenant_id }}<br>
                Plan: {{ $bp->plan_code ?? '—' }}<br>
                Modelo: {{ ($bp->billing_model ?? 'per_vehicle') === 'commission' ? 'Comisión' : 'Por vehículo' }}
            </div>
        </div>
    </div>
    <div class="col col-6">
        <div class="card">
            <h3 class="mb-1">Periodo facturado</h3>
            <div class="mb-1">
                {{ optional($invoice->period_start)->toDateString() ?? '—' }}
                &rarr;
                {{ optional($invoice->period_end)->toDateString() ?? '—' }}
            </div>
            <div class="small muted">
                Zona horaria del tenant si aplica.
            </div>
        </div>
    </div>
</div>

<h3 class="mb-1">Detalle de cargos</h3>
<table class="table mb-2">
    <thead>
    <tr>
        <th>Concepto</th>
        <th class="num">Monto ({{ $currency }})</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>
            Base mensual
            <span class="small">({{ $included }} vehículos incluidos)</span>
        </td>
        <td class="num">${{ number_format($baseFee, 2) }}</td>
    </tr>
    <tr>
        <td>
            Vehículos extra
            <span class="small">
                (Activos: {{ $vehiclesCount }}, Extras: {{ $extrasQty }},
                Tarifa x extra: ${{ number_format($extraUnit, 2) }} {{ $currency }})
            </span>
        </td>
        <td class="num">${{ number_format($extraFee, 2) }}</td>
    </tr>
    <tr class="subtle">
        <td>Subtotal</td>
        <td class="num">${{ number_format($subtotal, 2) }}</td>
    </tr>
    <tr class="subtle">
        <td>Impuestos ({{ number_format($taxRate*100,0) }}%)</td>
        <td class="num">${{ number_format($tax, 2) }}</td>
    </tr>
    <tr class="totals">
        <td>Total</td>
        <td class="num">${{ number_format($grand, 2) }} {{ $currency }}</td>
    </tr>
    </tbody>
</table>

<div class="grid mb-3">
    <div class="col col-8">
        <div class="card">
            <h3 class="mb-1">Datos del emisor (reserva)</h3>
            <div class="mb-1"><strong>{{ $company['name'] }}</strong></div>
            <div class="small">
                {{ $company['rfc'] }}<br>
                {{ $company['addr1'] }}<br>
                {{ $company['addr2'] }}<br>
                {{ $company['email'] }} · {{ $company['phone'] }}
            </div>
        </div>
    </div>
    <div class="col col-4">
        <div class="card">
            <h3 class="mb-1">Pago y referencia</h3>
            <div class="small mb-1">
                Método preferido: Wallet del tenant (Orbana).
            </div>
            <div class="box text-center">
                <div class="qr"></div>
                <div class="small pt-1">QR de pago (placeholder)</div>
                <div class="small">Referencia: INV-{{ $invoice->id }}</div>
            </div>
        </div>
    </div>
</div>

@if($invoice->notes)
    <div class="card mb-3">
        <h3 class="mb-1">Notas</h3>
        <div class="small" style="white-space: pre-wrap;">{{ $invoice->notes }}</div>
    </div>
@endif

<div class="footer small">
    Este documento es un resumen de cargos generado por Orbana para la central.
    En futuras iteraciones se integrará la facturación fiscal (CFDI). Para soporte:
    <span class="nowrap">soporte@orbana.mx</span>.
</div>
</body>
</html>
