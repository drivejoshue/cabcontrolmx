@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Editar tenant')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="mb-0">
            Editar tenant #{{ $tenant->id }} – {{ $tenant->name }}
        </h1>

        <div class="d-flex gap-2">
            <a href="{{ route('sysadmin.tenants.index') }}" class="btn btn-outline-secondary">
                Volver a lista
            </a>
            <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}" class="btn btn-outline-primary">
                Ver billing
            </a>
        </div>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        {{-- Form principal --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <strong>Datos del tenant</strong>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('sysadmin.tenants.update', $tenant) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="name" class="form-control"
                                   value="{{ old('name', $tenant->name) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Slug</label>
                            <input type="text" name="slug" class="form-control"
                                   value="{{ old('slug', $tenant->slug) }}" required>
                            <small class="text-muted">
                                Usado para URLs internas o subdominios (ej: <code>{{ $tenant->slug }}.orbana.mx</code>).
                            </small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Timezone</label>
                            <input type="text" name="timezone" class="form-control"
                                   value="{{ old('timezone', $tenant->timezone ?? 'America/Mexico_City') }}" required>
                            <small class="text-muted">
                                Ej: <code>America/Mexico_City</code>, <code>America/Monterrey</code>.
                            </small>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Latitud</label>
                                <input type="number" step="0.000001" name="latitud" class="form-control"
                                       value="{{ old('latitud', $tenant->latitud) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Longitud</label>
                                <input type="number" step="0.000001" name="longitud" class="form-control"
                                       value="{{ old('longitud', $tenant->longitud) }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Radio de cobertura (km)</label>
                                <input type="number" step="0.1" name="coverage_radius_km" class="form-control"
                                       value="{{ old('coverage_radius_km', $tenant->coverage_radius_km) }}">
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1"
                                   id="allow_marketplace" name="allow_marketplace"
                                   {{ old('allow_marketplace', $tenant->allow_marketplace) ? 'checked' : '' }}>
                            <label class="form-check-label" for="allow_marketplace">
                                Visible en marketplace (Orbana Passenger)
                            </label>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary" type="submit">
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Resumen lateral / Billing quick view --}}
        <div class="col-md-4">
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Resumen rápido</strong>
                </div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <dt class="col-5">Tenant ID</dt>
                        <dd class="col-7">{{ $tenant->id }}</dd>

                        <dt class="col-5">Nombre</dt>
                        <dd class="col-7">{{ $tenant->name }}</dd>

                        <dt class="col-5">Slug</dt>
                        <dd class="col-7"><code>{{ $tenant->slug }}</code></dd>

                        <dt class="col-5">Timezone</dt>
                        <dd class="col-7">{{ $tenant->timezone }}</dd>

                        <dt class="col-5">Marketplace</dt>
                        <dd class="col-7">
                            @if($tenant->allow_marketplace)
                                <span class="badge text-bg-success">Sí</span>
                            @else
                                <span class="badge text-bg-secondary">No</span>
                            @endif
                        </dd>

                        <dt class="col-5">Creado</dt>
                        <dd class="col-7">{{ $tenant->created_at }}</dd>
                    </dl>
                </div>
            </div>

            @php $bp = $tenant->billingProfile ?? null; @endphp
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Billing actual</strong>
                    <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}" class="btn btn-sm btn-outline-primary">
                        Ver detalle
                    </a>
                </div>
                <div class="card-body small">
                    @if($bp)
                        <dl class="row mb-0">
                            <dt class="col-5">Plan</dt>
                            <dd class="col-7">{{ $bp->plan_code ?? '—' }}</dd>

                            <dt class="col-5">Modelo</dt>
                            <dd class="col-7">{{ $bp->billing_model ?? 'per_vehicle' }}</dd>

                            <dt class="col-5">Estado</dt>
                            <dd class="col-7">{{ $bp->status ?? 'trial' }}</dd>

                            <dt class="col-5">Trial hasta</dt>
                            <dd class="col-7">
                                {{ optional($bp->trial_ends_at)->toDateString() ?? '—' }}
                            </dd>

                            <dt class="col-5">Día corte</dt>
                            <dd class="col-7">{{ $bp->invoice_day ?? 1 }}</dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">
                            Sin perfil de facturación configurado.<br>
                            Crea uno desde la pantalla de Billing.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
