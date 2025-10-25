@extends('layouts.admin')
@section('title','Dashboard')
@section('content')
<div class="row g-3">
<div class="col-12">
<div class="card shadow-sm border-0">
<div class="card-body d-flex align-items-center justify-content-between">
<div>
<h3 class="card-title mb-1">Bienvenido 👋</h3>
<p class="text-muted mb-0">Administra tu operación, ajustes de despacho y políticas de tarifa.</p>
</div>
<div class="text-end small text-muted">
<div>{{ now()->format('d M Y H:i') }}</div>
<div>Tenant: <strong>{{ auth()->user()->tenant_id ?? '-' }}</strong></div>
</div>
</div>
</div>
</div>


@can('admin')
<div class="col-md-4">
<a class="text-decoration-none" href="{{ route('admin.tenants.edit', auth()->user()->tenant_id ?? 1) }}">
<div class="card shadow-sm border-0 h-100">
<div class="card-body">
<h5 class="card-title mb-2">Tenant Settings</h5>
<p class="text-muted mb-0">Nombre, zona horaria, coordenadas y opciones generales.</p>
</div>
</div>
</a>
</div>
<div class="col-md-4">
<a class="text-decoration-none" href="{{ route('admin.dispatch_settings.edit') }}">
<div class="card shadow-sm border-0 h-100">
<div class="card-body">
<h5 class="card-title mb-2">Dispatch Settings</h5>
<p class="text-muted mb-0">Radio, olas, expiración y auto-assign.</p>
</div>
</div>
</a>
</div>
<div class="col-md-4">
<a class="text-decoration-none" href="{{ route('admin.fare_policies.index') }}">
<div class="card shadow-sm border-0 h-100">
<div class="card-body">
<h5 class="card-title mb-2">Tarifas</h5>
<p class="text-muted mb-0">Base, por km/min, nocturno y redondeo.</p>
</div>
</div>
</a>
</div>
@endcan
</div>
@endsection




