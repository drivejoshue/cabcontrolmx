<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orbana - Reporte de Viajes</title>

    <style>
        @page { 
            margin: 112px 30px 76px 30px; 
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #0f172a;
            line-height: 1.4;
        }

        header { 
            position: fixed; 
            top: -92px; 
            left: 0; 
            right: 0; 
            height: 82px; 
            background: #ffffff;
            padding-bottom: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        footer { 
            position: fixed; 
            bottom: -52px; 
            left: 0; 
            right: 0; 
            height: 46px; 
            background: #ffffff;
            padding-top: 5px;
        }

        :root{
            --accent: {{ $brand['accent'] ?? '#0d1b3a' }};
            --ink: #0f172a;
            --muted: #475569;
            --line: #e2e8f0;
            --soft: #f8fafc;
            --head: #0d1b3a;
            --headText: #ffffff;
            --chip: #eff6ff;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .hr { 
            height: 1px; 
            background: var(--line); 
            margin: 12px 0; 
        }
        
        .muted { 
            color: var(--muted); 
        }
        
        .small { 
            font-size: 8.5px; 
        }
        
        .xs { 
            font-size: 8px; 
            line-height: 1.2; 
        }

        .title { 
            font-size: 18px; 
            font-weight: 800; 
            margin: 0 0 4px 0; 
            color: var(--ink);
            letter-spacing: -0.25px;
        }
        
        .subtitle { 
            font-size: 10.5px; 
            margin: 0 0 12px 0; 
            color: #475569; 
        }

        .badge{
            display: inline-block;
            padding: 3px 8px;
            border-radius: 6px;
            background: var(--chip);
            color: #1e40af;
            border: 1px solid #bfdbfe;
            font-size: 8.5px;
            font-weight: 500;
            margin: 0 4px 4px 0;
            box-shadow: var(--shadow);
        }

        .cards { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0 8px;
            margin: 12px 0;
        }
        
        .card {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 12px;
            background: #ffffff;
            box-shadow: var(--shadow);
            transition: all 0.2s ease;
        }
        
        .card:hover {
            border-color: #c7d2fe;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        }
        
        .kpi { 
            font-size: 16px; 
            font-weight: 800; 
            margin: 0; 
            color: var(--head); 
            letter-spacing: -0.5px;
        }
        
        .kpiLabel { 
            margin: 3px 0 0 0; 
            color: var(--muted); 
            font-size: 8.5px; 
            font-weight: 500;
        }

        .sectionTitle { 
            font-size: 12px; 
            font-weight: 700; 
            margin: 18px 0 10px 0; 
            color: var(--head);
            padding-bottom: 4px;
            border-bottom: 2px solid var(--line);
        }

        .filters {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px;
            background: var(--soft);
            box-shadow: var(--shadow);
        }
        
        .filters .row { 
            margin: 4px 0; 
            padding: 2px 0;
        }
        
        .filters .row b {
            color: var(--head);
            min-width: 80px;
            display: inline-block;
        }

        table.grid { 
            width: 100%; 
            border-collapse: separate; 
            border-spacing: 0;
            table-layout: fixed; 
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--line);
        }
        
        table.grid thead {
            background: var(--head);
        }
        
        table.grid th{
            color: var(--headText);
            padding: 10px 8px;
            text-align: left;
            font-size: 8.5px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            font-weight: 600;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        table.grid th:last-child {
            border-right: none;
        }
        
        table.grid td {
            border-bottom: 1px solid var(--line);
            padding: 8px;
            vertical-align: top;
            font-size: 8.8px;
            color: var(--ink);
            background: #ffffff;
        }
        
        table.grid tr:nth-child(even) td { 
            background: #fcfdff; 
        }
        
        table.grid tr:last-child td {
            border-bottom: none;
        }

        .right { 
            text-align: right; 
        }
        
        .nowrap { 
            white-space: nowrap; 
        }
        
        .page-break { 
            page-break-before: always; 
        }

        .brandRow { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .brandRow td { 
            vertical-align: middle; 
            padding: 4px 0;
        }
        
        .brandName { 
            font-size: 13px; 
            font-weight: 800; 
            color: var(--head); 
            line-height: 1.2; 
        }
        
        .brandSub { 
            font-size: 8.5px; 
            color: var(--muted); 
            margin-top: 3px; 
            font-weight: 500;
        }

        .accentBar { 
            height: 4px; 
            background: var(--accent); 
            border-radius: 2px;
            margin-top: 6px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .clip1 { 
            white-space: nowrap; 
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .disclaimer {
            border: 1px solid var(--line);
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 8px;
            padding: 12px;
            box-shadow: var(--shadow);
        }
        
        .chart-container {
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 10px;
            background: #ffffff;
            box-shadow: var(--shadow);
        }
        
        .page-number {
            position: absolute;
            bottom: 25px;
            right: 30px;
            font-size: 8px;
            color: var(--muted);
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 7.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-finished {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        .status-canceled {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .amount-cell {
            font-weight: 700;
            color: var(--head);
        }
        
        .route-info {
            font-size: 8.2px;
            line-height: 1.3;
        }
        
        .route-label {
            color: var(--head);
            font-weight: 600;
        }
    </style>
</head>

<body>

<header>
    <table class="brandRow">
        <tr>
            <td style="width:10px;"></td>

            <td>
                <div class="brandName">{{ $partnerBrand['name'] ?? ('Partner #'.$partnerId) }}</div>

                <div class="brandSub">
                    Reporte de viajes
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
                <div class="small" style="color: var(--head); font-weight: 600;">Generado: {{ $generatedAt }}</div>
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
                CONFIDENCIAL · Uso interno. Prohibida la reproducción, distribución o transmisión (total o parcial) sin autorización por escrito.
                La información se genera con base en los filtros aplicados y los registros existentes al momento de emisión.
                Orbana no asume responsabilidad por uso indebido, interpretación, publicación o divulgación no autorizada del contenido.
            </td>

            <td class="xs muted" style="text-align:right; white-space:nowrap;">
                @if(!empty($brand['logo_path']) && file_exists($brand['logo_path']))
                    <img src="file://{{ $brand['logo_path'] }}"
                         style="height:10px; vertical-align:middle; opacity:0.9;">
                @endif
            </td>
        </tr>
    </table>
</footer>

{{-- PORTADA --}}
<div style="margin-top: 10px;">
    <h1 class="title">Reporte de Viajes</h1>

    <p class="subtitle">
        <span class="badge" style="background: #0d1b3a; color: #ffffff; border-color: #0d1b3a;">
            {{ $filters['from'] }} → {{ $filters['to'] }}
        </span>
        @if($filters['status']) 
            <span class="badge">Estado: {{ $filters['status'] }}</span> 
        @endif
        @if(!empty($filters['driverId'])) 
            <span class="badge">Driver: #{{ (int)$filters['driverId'] }}</span> 
        @endif
        @if(!empty($filters['vehicleId'])) 
            <span class="badge">Vehículo: #{{ (int)$filters['vehicleId'] }}</span> 
        @endif
        @if(($filters['q'] ?? '') !== '') 
            <span class="badge">Búsqueda: "{{ $filters['q'] }}"</span> 
        @endif
    </p>
</div>

<table class="cards">
    <tr>
        <td style="width:33%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$stats->total) }}</p>
                <p class="kpiLabel">Registros totales</p>
                <div class="xs muted" style="margin-top: 4px;">Finalizados + Cancelados</div>
            </div>
        </td>
        <td style="width:33%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$stats->finished) }}</p>
                <p class="kpiLabel">Viajes finalizados</p>
                <div class="xs muted" style="margin-top: 4px;">Completados exitosamente</div>
            </div>
        </td>
        <td style="width:33%;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$stats->canceled) }}</p>
                <p class="kpiLabel">Viajes cancelados</p>
                <div class="xs muted" style="margin-top: 4px;">No completados</div>
            </div>
        </td>
    </tr>
</table>

<table class="cards">
    <tr>
        <td style="width:33%; padding-right:8px;">
            <div class="card">
                <p class="kpi">${{ number_format((float)$stats->amount_sum, 2) }}</p>
                <p class="kpiLabel">Monto total</p>
                <div class="xs muted" style="margin-top: 4px;">Solo viajes finalizados</div>
            </div>
        </td>
        <td style="width:33%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format(((float)$stats->distance_m_sum)/1000, 2) }} km</p>
                <p class="kpiLabel">Distancia total</p>
                <div class="xs muted" style="margin-top: 4px;">Solo viajes finalizados</div>
            </div>
        </td>
        <td style="width:33%;">
            <div class="card">
                <p class="kpi">{{ number_format(((float)$stats->duration_s_sum)/60, 0) }} min</p>
                <p class="kpiLabel">Duración total</p>
                <div class="xs muted" style="margin-top: 4px;">Solo viajes finalizados</div>
            </div>
        </td>
    </tr>
</table>

<div class="sectionTitle">Filtros aplicados</div>
<div class="filters">
    <div class="row"><b>Rango:</b> {{ $filters['from'] }} 00:00:00 → {{ $filters['to'] }} 23:59:59</div>
    <div class="row"><b>Estado:</b> {{ $filters['status'] ?: 'finished + canceled' }}</div>
    <div class="row"><b>Driver:</b> {{ $filters['driverId'] ? ('#'.(int)$filters['driverId']) : 'Todos' }}</div>
    <div class="row"><b>Vehículo:</b> {{ $filters['vehicleId'] ? ('#'.(int)$filters['vehicleId']) : 'Todos' }}</div>
    <div class="row"><b>Búsqueda:</b> {{ ($filters['q'] ?? '') !== '' ? $filters['q'] : '—' }}</div>

</div>

<div class="sectionTitle">Tendencia diaria</div>
@if($chartPng)
    <div class="chart-container">
        <img src="{{ $chartPng }}" style="width:100%; height:auto; border-radius: 4px;">
    </div>
@else
    <div class="chart-container">
        <div class="muted small" style="text-align: center; padding: 20px;">
            No fue posible generar la gráfica
        </div>
    </div>
@endif

<div class="disclaimer xs muted">
    • <b>"Monto"</b> = agreed_amount → total_amount → quoted_amount (según disponibilidad).<br>
    • <b>"Fecha final"</b> = finished_at o canceled_at (según estado).<br>
    • Este reporte refleja únicamente los datos filtrados y disponibles al momento de la generación.<br>
    • Los cálculos de distancia y duración aplican solo a viajes con estado "finished".
</div>

{{-- TABLA --}}
<div class="page-break"></div>

<div class="sectionTitle">Detalle de viajes</div>
<table class="grid">
    <thead>
    <tr>
        <th style="width:46px;">ID</th>
        <th style="width:68px;">Estado</th>
        <th style="width:100px;">Fecha final</th>
        <th style="width:110px;">Conductor</th>
        <th style="width:82px;">Vehículo</th>
        <th>Ruta</th>
        <th style="width:76px;" class="right">Monto</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $r)
        @php
            $amount = (float)($r->agreed_amount ?? $r->total_amount ?? $r->quoted_amount ?? 0);
            $finalAt = $r->finished_at ?? $r->canceled_at ?? $r->requested_at ?? $r->created_at;
            
            $statusClass = strtolower($r->status) === 'finished' ? 'status-finished' : 'status-canceled';
            $statusText = strtolower($r->status) === 'finished' ? 'Finalizado' : 'Cancelado';

            $veh = trim(($r->vehicle_economico ?? '').' '.($r->vehicle_plate ?? ''));
            if ($veh === '') $veh = '#'.($r->vehicle_id ?? '—');

            $o = \Illuminate\Support\Str::limit((string)($r->origin_label ?? ''), 70);
            $d = \Illuminate\Support\Str::limit((string)($r->dest_label ?? ''), 70);
        @endphp
        <tr>
            <td class="nowrap" style="font-weight: 600; color: var(--head);">#{{ $r->id }}</td>
            <td>
                <span class="status-badge {{ $statusClass }}">{{ $statusText }}</span>
            </td>
            <td class="nowrap">{{ $finalAt }}</td>
            <td class="clip1">{{ $r->driver_name ?? ('#'.$r->driver_id) }}</td>
            <td class="nowrap clip1">{{ $veh }}</td>
            <td class="route-info">
                <div><span class="route-label">O:</span> {{ $o }}</div>
                <div><span class="route-label">D:</span> {{ $d }}</div>

                @if(!empty($r->passenger_name) || !empty($r->passenger_phone))
                    <div class="xs muted clip1" style="margin-top: 2px; padding-top: 2px; border-top: 1px dashed #e2e8f0;">
                        👤 {{ $r->passenger_name ?? '' }}{{ !empty($r->passenger_phone) ? (' · '.$r->passenger_phone) : '' }}
                    </div>
                @endif
            </td>
            <td class="right nowrap amount-cell">
                ${{ number_format($amount, 2) }} {{ $r->currency ?? 'MXN' }}
            </td>
        </tr>
    @endforeach
    </tbody>
</table>

{{-- Paginado --}}
<script type="text/php">
if (isset($pdf)) {
    $text = "Página {PAGE_NUM} de {PAGE_COUNT}";
    $size = 8;
    
    // Posición inferior derecha
    $x = $pdf->get_width() - 50;
    $y = $pdf->get_height() - 28;
    
    $pdf->page_text($x, $y, $text, null, $size, [0.2, 0.3, 0.5]);
}
</script>

</body>
</html>