<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orbana - Vehicles Report</title>
    <style>
        @page { margin: 112px 30px 76px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#0f172a; }

        header { position: fixed; top: -92px; left: 0; right: 0; height: 82px; }
        footer { position: fixed; bottom: -52px; left: 0; right: 0; height: 46px; }

        :root{
            --accent: {{ $brand['accent'] ?? '#00CCFF' }};
            --ink: #0f172a;
            --muted: #64748b;
            --line: #e2e8f0;
            --soft: #f1f5f9;
            --head: #0b1220;
            --headText: #ffffff;
            --chip: #eef2ff;
        }

        .hr { height:1px; background: var(--line); margin:8px 0; }
        .muted { color: var(--muted); }
        .small { font-size: 8.5px; }
        .xs { font-size: 8px; line-height: 1.15; }
        .title { font-size: 16px; font-weight: 800; margin: 0; color: var(--ink); }
        .subtitle { font-size: 10.5px; margin: 4px 0 0 0; color: #334155; }

        .badge{
            display:inline-block;
            padding:2px 7px;
            border-radius: 999px;
            background: var(--chip);
            color: #0b1220;
            border: 1px solid #c7d2fe;
            font-size: 8.5px;
        }

        .cards { width:100%; border-collapse: collapse; margin-top: 10px; }
        .card { border:1px solid var(--line); border-radius: 12px; padding: 10px; background: #fff; }
        .kpi { font-size: 15px; font-weight:800; margin:0; color: var(--ink); }
        .kpiLabel { margin:2px 0 0 0; color: var(--muted); font-size: 8.5px; }

        .sectionTitle { font-size: 11px; font-weight: 800; margin: 14px 0 7px 0; color: var(--ink); }

        .filters { border:1px solid var(--line); border-radius: 12px; padding: 10px; background: #fff; }
        .filters .row { margin: 2px 0; }

        table.grid { width:100%; border-collapse: collapse; table-layout: fixed; }
        table.grid th{
            background: #0b1220;
            color: #ffffff;
            border: 1px solid #0b1220;
            padding: 7px 6px;
            text-align:left;
            font-size: 8.6px;
            text-transform: none;
        }
        table.grid td{
            border:1px solid var(--line);
            padding: 5px 6px;
            vertical-align: top;
            font-size: 8.6px;
            color: var(--ink);
            background: #fff;
        }
        table.grid tr:nth-child(even) td { background: #fbfdff; }

        .right { text-align:right; }
        .nowrap { white-space: nowrap; }
        .clip1 { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .brandRow { width:100%; border-collapse: collapse; }
        .brandRow td { vertical-align: middle; }
        .brandName { font-size: 12px; font-weight: 800; color: var(--ink); line-height: 1.1; }
        .brandSub { font-size: 8.5px; color: var(--muted); margin-top: 2px; }
        .accentBar { height: 4px; background: var(--accent); border-radius: 999px; }

        .disclaimer { border: 1px solid var(--line); background: var(--soft); border-radius: 12px; padding: 9px 10px; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<header>
    <table class="brandRow">
        <tr>
            <td style="width:20px;"></td>
            <td>
                <div class="brandName">{{ $partnerBrand['name'] ?? ('Partner #'.$partnerId) }}</div>
                <div class="brandSub">
                    Reporte de vehículos
                    @if(!empty($partnerBrand['city']) || !empty($partnerBrand['state']))
                        · {{ $partnerBrand['city'] ?? '' }}{{ !empty($partnerBrand['state']) ? (', '.$partnerBrand['state']) : '' }}
                    @endif
                    @if(!empty($partnerBrand['contact_phone'])) · {{ $partnerBrand['contact_phone'] }} @endif
                    @if(!empty($partnerBrand['contact_email'])) · {{ $partnerBrand['contact_email'] }} @endif
                </div>

                @if(!empty($partnerBrand['legal_name']) || !empty($partnerBrand['rfc']))
                    <div class="brandSub">
                        {{ $partnerBrand['legal_name'] ?? '' }}{!! !empty($partnerBrand['rfc']) ? ' · RFC: '.$partnerBrand['rfc'] : '' !!}
                    </div>
                @endif
            </td>
            <td style="text-align:right;">
                <div class="small muted">Generado: {{ $generatedAt }}</div>
                <div class="small muted">Partner #{{ $partnerId }} · Tenant #{{ $tenantId }}</div>
            </td>
        </tr>
    </table>
    <div class="accentBar"></div>
</header>

<footer>
    <div class="hr"></div>
    <table style="width:100%;">
        <tr>
            <td class="xs muted">
                CONFIDENCIAL · Uso interno. Prohibida la reproducción o distribución sin autorización.
                Información generada con base en registros al momento de emisión.
            </td>
            <td class="xs muted" style="text-align:right; white-space:nowrap;">
                @if(!empty($brand['logo_path']) && file_exists($brand['logo_path']))
                    <img src="file://{{ $brand['logo_path'] }}" style="height:9px; vertical-align:middle; opacity:0.85;">
                @endif
            </td>
        </tr>
    </table>
</footer>

<h1 class="title">Reporte de vehículos</h1>
<p class="subtitle">
    Periodo: <span class="badge">{{ $filters['from'] }} → {{ $filters['to'] }}</span>
    <span class="badge">Status: {{ $filters['status'] === '' ? 'finished+canceled' : $filters['status'] }}</span>
    @if($filters['active'] !== null && $filters['active'] !== '')
        <span class="badge">Activo: {{ (int)$filters['active'] }}</span>
    @endif
    @if(!empty($filters['verification_status']))
        <span class="badge">Verificación: {{ $filters['verification_status'] }}</span>
    @endif
    @if(($filters['q'] ?? '') !== '')
        <span class="badge">Búsqueda: "{{ $filters['q'] }}"</span>
    @endif
</p>

<table class="cards">
    <tr>
        <td style="width:25%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)($kpi['vehicles_total'] ?? 0)) }}</p>
                <p class="kpiLabel">Vehículos del partner</p>
            </div>
        </td>
        <td style="width:25%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)($kpi['rides_total'] ?? 0)) }}</p>
                <p class="kpiLabel">Rides (total)</p>
            </div>
        </td>
        <td style="width:25%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((float)($kpi['km_sum'] ?? 0), 1) }}</p>
                <p class="kpiLabel">Km (sum, finished)</p>
            </div>
        </td>
        <td style="width:25%;">
            <div class="card">
                <p class="kpi">{{ number_format((float)($kpi['amount_sum'] ?? 0), 2) }}</p>
                <p class="kpiLabel">Monto (sum, finished)</p>
            </div>
        </td>
    </tr>
</table>

<div class="sectionTitle">Filtros aplicados</div>
<div class="filters">
    <div class="row"><b>Rango:</b> {{ $filters['from'] }} 00:00:00 → {{ $filters['to'] }} 23:59:59</div>
    <div class="row"><b>Status:</b> {{ $filters['status'] === '' ? 'finished+canceled' : $filters['status'] }}</div>
    <div class="row"><b>Activo:</b> {{ ($filters['active'] !== null && $filters['active'] !== '') ? (int)$filters['active'] : '—' }}</div>
    <div class="row"><b>Verificación:</b> {{ $filters['verification_status'] ?: '—' }}</div>
    <div class="row"><b>Búsqueda:</b> {{ ($filters['q'] ?? '') !== '' ? $filters['q'] : '—' }}</div>

    <div class="hr"></div>
    <div class="xs muted">
        Export: <b>{{ $policy['scope'] }}</b> · Límite: <b>{{ (int)$policy['limitRows'] }}</b> ·
        Filtrados: <b>{{ (int)$totalFiltered }}</b> · Mostrados: <b>{{ count($rows) }}</b>
        @if($totalFiltered > count($rows))
            · <b>Nota:</b> el PDF fue limitado. Ajusta filtros o exporta por rangos.
        @endif
    </div>
</div>

<div class="sectionTitle">Detalle por vehículo</div>

@if(count($rows) === 0)
    <div class="disclaimer">
        <b>Sin resultados.</b>
        <div class="muted small">No hay vehículos que cumplan los filtros actuales en el periodo seleccionado.</div>
    </div>
@else
<table class="grid">
    <thead>
    <tr>
        <th style="width:48px;">ID</th>
        <th style="width:92px;">Económico</th>
        <th style="width:82px;">Placa</th>
        <th>Vehículo</th>
        <th style="width:62px;" class="right">Rides</th>
        <th style="width:62px;" class="right">Fin</th>
        <th style="width:62px;" class="right">Can</th>
        <th style="width:86px;" class="right">Monto</th>
        <th style="width:70px;" class="right">Km</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $v)
        @php
            $veh = trim(($v->brand ?? '').' '.($v->model ?? '').' '.($v->year ?? ''));
            $km = ((float)($v->distance_m_sum ?? 0))/1000.0;
        @endphp
        <tr>
            <td class="nowrap">#{{ (int)$v->id }}</td>
            <td class="nowrap">{{ $v->economico }}</td>
            <td class="nowrap">{{ $v->plate }}</td>
            <td class="clip1">{{ $veh ?: ('Vehículo #'.(int)$v->id) }}</td>
            <td class="right nowrap">{{ (int)$v->rides_total }}</td>
            <td class="right nowrap">{{ (int)$v->rides_finished }}</td>
            <td class="right nowrap">{{ (int)$v->rides_canceled }}</td>
            <td class="right nowrap">{{ number_format((float)($v->amount_sum ?? 0), 2) }}</td>
            <td class="right nowrap">{{ number_format($km, 1) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<div class="page-break"></div>

<div class="sectionTitle">Detalle de rides (últimos {{ (int)$detailLimit }})</div>

@if(($ridesRows ?? collect())->count() > 0)
<table class="grid">
    <thead>
    <tr>
        <th style="width:52px;">Ride</th>
        <th style="width:88px;">Fecha</th>
        <th style="width:78px;">Status</th>
        <th style="width:95px;">Vehículo</th>
        <th style="width:155px;">Chofer</th>
        <th style="width:76px;" class="right">Monto</th>
        <th style="width:60px;" class="right">Km</th>
        <th style="width:60px;" class="right">Min</th>
    </tr>
    </thead>
    <tbody>
    @foreach($ridesRows as $r)
        @php
            $km = ((float)($r->distance_m ?? 0))/1000.0;
            $min = ((float)($r->duration_s ?? 0))/60.0;
        @endphp
        <tr>
            <td class="nowrap">#{{ (int)$r->id }}</td>
            <td class="nowrap">{{ $r->ride_final_at ? \Carbon\Carbon::parse($r->ride_final_at)->format('Y-m-d') : '—' }}</td>
            <td class="nowrap">{{ $r->status }}</td>
            <td class="nowrap">{{ $r->vehicle_economico }}</td>
            <td class="clip1">{{ $r->driver_name }}</td>
            <td class="right nowrap">{{ number_format((float)$r->amount, 2) }}</td>
            <td class="right nowrap">{{ number_format($km, 1) }}</td>
            <td class="right nowrap">{{ number_format($min, 0) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@else
    <div class="muted small">Sin rides con los filtros actuales.</div>
@endif

<script type="text/php">
if (isset($pdf)) {
    $text = "Página {PAGE_NUM} / {PAGE_COUNT}";
    $size = 8;
    $x = 465; $y = 828;
    $pdf->page_text($x, $y, $text, null, $size, [0.39, 0.45, 0.55]);
}
</script>

</body>
</html>
