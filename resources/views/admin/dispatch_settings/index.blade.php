@extends('layouts.admin')
@section('title','Tarifas')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
<h3 class="mb-0">Políticas de tarifa</h3>
<a href="{{ route('admin.fare_policies.create',['tenant_id'=>$tenantId]) }}" class="btn btn-primary shadow">Nueva</a>
</div>
@if(session('ok'))
<div class="alert alert-success">{{ session('ok') }}</div>
@endif
<div class="row g-3">
@forelse($policies as $p)
<div class="col-md-4">
<div class="card h-100 shadow-sm border-0">
<div class="card-body">
<div class="d-flex justify-content-between align-items-start">
<h5 class="card-title mb-1">Modo: <span class="badge text-bg-secondary">{{ $p->mode }}</span></h5>
<span class="text-muted small">#{{ $p->id }}</span>
</div>
<div class="text-muted small">Vigencia: {{ $p->active_from?->format('Y-m-d') ?? '—' }} → {{ $p->active_to?->format('Y-m-d') ?? '—' }}</div>
<hr>
<dl class="row small mb-0">
<dt class="col-7">Base</dt><dd class="col-5 text-end">{{ number_format($p->base_fee,2) }}</dd>
<dt class="col-7">Por km</dt><dd class="col-5 text-end">{{ number_format($p->per_km,2) }}</dd>
<dt class="col-7">Por min</dt><dd class="col-5 text-end">{{ number_format($p->per_min,2) }}</dd>
<dt class="col-7">Mult. noche</dt><dd class="col-5 text-end">{{ number_format($p->night_multiplier,2) }}</dd>
<dt class="col-7">Redondeo</dt><dd class="col-5 text-end">{{ $p->round_mode }} {{ $p->round_mode==='decimals' ? '('.$p->round_decimals.')' : '('.number_format($p->round_step,2).')' }}</dd>
<dt class="col-7">Mínimo</dt><dd class="col-5 text-end">{{ number_format($p->min_total,2) }}</dd>
</dl>
</div>
<div class="card-footer bg-transparent border-0 d-flex gap-2">
<a href="{{ route('admin.fare_policies.edit',$p) }}" class="btn btn-sm btn-outline-primary">Editar</a>
<form method="POST" action="{{ route('admin.fare_policies.destroy',$p) }}" onsubmit="return confirm('¿Eliminar política?');">
@csrf @method('DELETE')
<button class="btn btn-sm btn-outline-danger">Eliminar</button>
</form>
</div>
</div>
</div>
@empty
<div class="col-12">
<div class="card shadow-sm border-0"><div class="card-body text-center text-muted">Sin políticas todavía</div></div>
</div>
@endforelse
</div>
<div class="mt-3">{{ $policies->withQueryString()->links() }}</div>
@endsection