<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orbana - Driver Quality</title>
    <style>
        @page { margin: 112px 30px 76px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#0f172a; }

        header { position: fixed; top: -92px; left: 0; right: 0; height: 82px; }
        footer { position: fixed; bottom: -52px; left: 0; right: 0; height: 46px; }

        :root{
            --accent: {{ $brand['accent'] ?? '#0b1220' }};
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
  letter-spacing: 0;
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
                    Reporte de calidad de conductores
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
                CONFIDENCIAL · Uso interno. Prohibida la reproducción, distribución o transmisión (total o parcial) sin autorización por escrito.
                La información se genera con base en filtros y registros al momento de emisión. Orbana no asume responsabilidad por uso indebido,
                interpretación, publicación o divulgación no autorizada.
            </td>
            <td class="xs muted" style="text-align:right; white-space:nowrap;">
                @if(!empty($brand['logo_path']) && file_exists($brand['logo_path']))
                    <img src="file://{{ $brand['logo_path'] }}" style="height:9px; vertical-align:middle; opacity:0.85;">
                @endif
            </td>
        </tr>
    </table>
</footer>

<h1 class="title">Reporte de calidad de conductores</h1>
<p class="subtitle">
    Periodo: <span class="badge">{{ $filters['from'] }} → {{ $filters['to'] }}</span>
    @if(!empty($filters['driver_id'])) <span class="badge">Driver: #{{ (int)$filters['driver_id'] }}</span> @endif
    @if(($filters['q'] ?? '') !== '') <span class="badge">Búsqueda: "{{ $filters['q'] }}"</span> @endif
    @if(!empty($filters['min_rating'])) <span class="badge">Min rating: {{ (int)$filters['min_rating'] }}</span> @endif
    @if(!empty($filters['issue_status'])) <span class="badge">Issue status: {{ $filters['issue_status'] }}</span> @endif
    @if(!empty($filters['severity'])) <span class="badge">Severidad: {{ $filters['severity'] }}</span> @endif
    @if(!empty($filters['category'])) <span class="badge">Categoría: {{ $filters['category'] }}</span> @endif
    @if(isset($filters['forward_to_platform']) && $filters['forward_to_platform'] !== null)
        <span class="badge">Forward: {{ (int)$filters['forward_to_platform'] }}</span>
    @endif
    @if(!empty($filters['only_with'])) <span class="badge">Solo: {{ $filters['only_with'] }}</span> @endif
</p>

<table class="cards">
    <tr>
        <td style="width:25%; padding-right:8px;">
  <div class="card">
  <p class="kpi">{{ number_format((float)($kpi['rating_avg_weighted'] ?? 0), 2) }}</p>

    <p class="kpiLabel">Promedio global (ponderado)</p>
  </div>
</td>

        <td style="width:25%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$kpi['drivers_total']) }}</p>
                <p class="kpiLabel">Drivers del partner</p>
            </div>
        </td>
        
        <td style="width:25%; padding-right:8px;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$kpi['ratings_total']) }}</p>
                <p class="kpiLabel">Ratings (total)</p>
            </div>
        </td>
        <td style="width:25%;">
            <div class="card">
                <p class="kpi">{{ number_format((int)$kpi['issues_openish_total']) }}</p>
                <p class="kpiLabel">Issues abiertos/en revisión</p>
            </div>
        </td>
    </tr>
</table>

<div class="sectionTitle">Filtros aplicados</div>
<div class="filters">
    <div class="row"><b>Rango:</b> {{ $filters['from'] }} 00:00:00 → {{ $filters['to'] }} 23:59:59</div>
    <div class="row"><b>Min rating:</b> {{ !empty($filters['min_rating']) ? (int)$filters['min_rating'] : '—' }}</div>
    <div class="row"><b>Issue status:</b> {{ $filters['issue_status'] ?? '—' }}</div>
    <div class="row"><b>Severidad:</b> {{ $filters['severity'] ?? '—' }}</div>
    <div class="row"><b>Categoría:</b> {{ $filters['category'] ?? '—' }}</div>
    <div class="row"><b>Forward:</b>
        @if(isset($filters['forward_to_platform']) && $filters['forward_to_platform'] !== null)
            {{ (int)$filters['forward_to_platform'] ? 'Sí' : 'No' }}
        @else
            —
        @endif
    </div>

    <div class="hr"></div>
    <div class="xs muted">
        Export: <b>{{ $policy['scope'] }}</b> · Límite: <b>{{ (int)$policy['limitRows'] }}</b> ·
        Filtrados: <b>{{ (int)$totalFiltered }}</b> · Mostrados: <b>{{ count($rows) }}</b>
        @if($totalFiltered > count($rows))
            · <b>Nota:</b> el PDF fue limitado. Ajusta filtros o exporta por rangos.
        @endif
    </div>
</div>

<div class="sectionTitle">Indicadores</div>
<div class="disclaimer xs muted">
    • Promedio global de calificación (ponderado): <b>{{ number_format((float)($charts['rating_avg'] ?? 0), 2) }}</b> / 5.00<br>
    • Ventana temporal por “fecha final” del ride (finished_at o canceled_at).<br>
</div>

<div class="sectionTitle">Gráficas</div>

@if(!empty($charts['ratings_dist_png']))
  <div style="border:1px solid var(--line); border-radius: 12px; padding: 8px; background:#fff; margin-bottom:8px;">
      <img src="{{ $charts['ratings_dist_png'] }}" style="width:100%; height:auto;">
  </div>
@endif

@if(!empty($charts['issues_sev_png']))
  <div style="border:1px solid var(--line); border-radius: 12px; padding: 8px; background:#fff;">
      <img src="{{ $charts['issues_sev_png'] }}" style="width:100%; height:auto;">
  </div>
@else
  <div class="muted small">No fue posible generar gráficas (GD no disponible o sin datos).</div>
@endif


<div class="page-break"></div>
@if(count($rows) === 0)
  <div class="disclaimer">
    <b>Sin resultados.</b>
    <div class="muted small">No hay conductores que cumplan los filtros actuales en el periodo seleccionado.</div>
  </div>
@else


<div class="sectionTitle">Detalle por conductor</div>
<table class="grid">
    <thead>
    <tr>
        <th style="width:46px;">ID</th>
        <th style="width:150px;">Conductor</th>
        <th style="width:110px;">Contacto</th>
        <th style="width:70px;" class="right">Ratings</th>
        <th style="width:80px;" class="right">Promedio</th>
        <th style="width:70px;" class="right">Issues</th>
        <th style="width:70px;" class="right">Abiertos</th>
        <th style="width:90px;" class="right">Sev C/H</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $d)
        @php
            $name = (string)($d->name ?? '');
            $phone = (string)($d->phone ?? '');
            $email = (string)($d->email ?? '');
            $sevCH = ((int)$d->sev_critical) . '/' . ((int)$d->sev_high);
        @endphp
        <tr>
            <td class="nowrap">#{{ (int)$d->id }}</td>
            <td class="clip1">{{ $name }}</td>
            <td class="clip1">
                {{ \Illuminate\Support\Str::limit(trim($phone.' '.$email), 28) }}
            </td>
            <td class="right nowrap">{{ number_format((int)$d->ratings_count) }}</td>
            <td class="right nowrap">{{ number_format((float)$d->rating_avg, 2) }}</td>
            <td class="right nowrap">{{ number_format((int)$d->issues_count) }}</td>
            <td class="right nowrap">{{ number_format((int)$d->issues_openish) }}</td>
            <td class="right nowrap">{{ $sevCH }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
  
@endif
<script type="text/php">
if (isset($pdf)) {
    $text = "Página {PAGE_NUM} / {PAGE_COUNT}";
    $size = 8;
    $x = 465; $y = 828;
    $pdf->page_text($x, $y, $text, null, $size, [0.39, 0.45, 0.55]);
}
</script>


<div class="page-break"></div>
<div class="sectionTitle">Detalle de ratings (últimos {{ (int)$detailLimit }})</div>

<table class="grid">
  <thead>
    <tr>
      <th style="width:54px;">Rating ID</th>
      <th style="width:54px;">Ride</th>
      <th style="width:150px;">Conductor</th>
      <th style="width:78px;">Fecha ride</th>
      <th style="width:78px;">Fecha rating</th>
      <th style="width:46px;" class="right">⭐</th>
      <th>Comentario</th>
    </tr>
  </thead>
  <tbody>
    @forelse(($ratingsRows ?? []) as $x)
      <tr>
        <td class="nowrap">#{{ (int)$x->id }}</td>
        <td class="nowrap">#{{ (int)$x->ride_id }}</td>
        <td class="clip1">{{ $x->driver_name ?? ('#'.$x->driver_id) }}</td>
        <td class="nowrap">{{ $x->ride_final_at ? \Carbon\Carbon::parse($x->ride_final_at)->format('Y-m-d') : '—' }}</td>
        <td class="nowrap">{{ $x->created_at ? \Carbon\Carbon::parse($x->created_at)->format('Y-m-d') : '—' }}</td>
        <td class="right nowrap">{{ (int)$x->rating }}</td>
        <td>{{ $x->comment ?: '—' }}</td>
      </tr>
    @empty
      <tr><td colspan="7" class="text-muted p-2">Sin ratings con los filtros actuales.</td></tr>
    @endforelse
  </tbody>
</table>

@if(($kpi['ratings_total'] ?? 0) > count($ratingsRows ?? []))
  <div class="xs muted" style="margin-top:6px;">
    Nota: se muestran solo los últimos {{ (int)$detailLimit }} ratings por tamaño del PDF. Ajusta filtros para acotar.
  </div>
@endif


<div class="page-break"></div>
<div class="sectionTitle">Incidencias (últimos {{ (int)$detailLimit }})</div>

@if(($issuesRows ?? collect())->count() > 0)
  <table class="grid">
    <thead>
    <tr>
      <th style="width:44px;">ID</th>
      <th style="width:58px;">Ride</th>
      <th style="width:140px;">Driver</th>
      <th style="width:78px;">Categoría</th>
      <th style="width:70px;">Sev</th>
      <th style="width:78px;">Status</th>
      <th style="width:95px;">Fecha</th>
      <th>Título</th>
    </tr>
    </thead>
    <tbody>
    @foreach($issuesRows as $x)
      <tr>
        <td class="nowrap">#{{ (int)$x->id }}</td>
        <td class="nowrap">#{{ (int)$x->ride_id }}</td>
        <td class="clip1">{{ $x->driver_name ?? ('#'.$x->driver_id) }}</td>
        <td class="nowrap">{{ $x->category }}</td>
        <td class="nowrap">{{ $x->severity }}</td>
        <td class="nowrap">{{ $x->status }}</td>
        <td class="nowrap">{{ $x->created_at }}</td>
        <td>{{ $x->title ?: '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
@else
  <div class="muted small">Sin incidencias con los filtros actuales.</div>
@endif

</body>
</html>
