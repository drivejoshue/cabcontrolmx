<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura #{{ $invoice->id }} · Orbana</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
        h1, h2, h3 { margin: 0 0 6px 0; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .mb-1 { margin-bottom: 4px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 12px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 6px 4px; border-bottom: 1px solid #e0e0e0; }
        .table th { text-align: left; background: #f5f5f5; }
        .totals td { border-top: 1px solid #000; font-weight: bold; }
        .small { font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <table width="100%">
        <tr>
            <td>
                <h2>Orbana</h2>
                <div class="small">Sistema de despacho y marketplace</div>
            </td>
            <td class="text-right">
                <h1>Factura #{{ $invoice->id }}</h1>
                <div class="mb-1">
                    Emisión: {{ optional($invoice->issue_date)->toDateString() ?? '—' }}<br>
                    Vence: {{ optional($invoice->due_date)->toDateString() ?? '—' }}
                </div>
                <div class="small">
                    Status: {{ strtoupper($invoice->status) }}
                </div>
            </td>
        </tr>
    </table>

    <hr class="mb-2">

    <table width="100%" class="mb-3">
        <tr>
            <td width="50%">
                <h3>Central / Tenant</h3>
                <div><strong>{{ optional($invoice->tenant)->name ?? '—' }}</strong></div>
                <div class="small">
                    Tenant ID: {{ $invoice->tenant_id }}<br>
                    Plan: {{ optional($invoice->billingProfile)->plan_code ?? '—' }}<br>
                    Modelo: {{ optional($invoice->billingProfile)->billing_model ?? '—' }}
                </div>
            </td>
            <td width="50%" class="text-right">
                <h3>Periodo facturado</h3>
                <div>
                    {{ optional($invoice->period_start)->toDateString() ?? '—' }}
                    &rarr;
                    {{ optional($invoice->period_end)->toDateString() ?? '—' }}
                </div>
            </td>
        </tr>
    </table>

    <h3 class="mb-1">Detalle de cargos</h3>
    <table class="table mb-3">
        <thead>
        <tr>
            <th>Concepto</th>
            <th class="text-right">Monto</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td>
                Base mensual
                <span class="small">(incluye {{ $invoice->billingProfile->included_vehicles ?? 0 }} vehículos)</span>
            </td>
            <td class="text-right">
                ${{ number_format($invoice->base_fee, 2) }} {{ $invoice->currency }}
            </td>
        </tr>
        <tr>
            <td>
                Cargo por vehículos extra
                <span class="small">
                    ({{ $invoice->vehicles_count }} vehículos activos)
                </span>
            </td>
            <td class="text-right">
                ${{ number_format($invoice->vehicles_fee, 2) }} {{ $invoice->currency }}
            </td>
        </tr>
        <tr class="totals">
            <td>Total</td>
            <td class="text-right">
                ${{ number_format($invoice->total, 2) }} {{ $invoice->currency }}
            </td>
        </tr>
        </tbody>
    </table>

    @if($invoice->notes)
        <h3 class="mb-1">Notas</h3>
        <p class="small">{{ $invoice->notes }}</p>
    @endif

    <p class="small">
        Este documento es un resumen de cargos generados por Orbana para la central.
        En una futura iteración se puede enlazar a facturación fiscal / CFDI del proveedor.
    </p>
</body>
</html>
