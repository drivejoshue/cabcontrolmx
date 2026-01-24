@extends('layouts.sysadmin')

@section('content')
@php
  $fr = is_array($place->fare_rule ?? null)
      ? $place->fare_rule
      : (json_decode($place->fare_rule ?? '[]', true) ?: []);

  $frEnabled = (bool)($fr['enabled'] ?? false);
  $frMode    = (string)($fr['mode'] ?? '');
  $frLabel   = (string)($fr['label'] ?? 'Tarifa especial');
@endphp

<div class="page-header d-print-none">
  <div class="container-xl">
    <div class="row g-2 align-items-center">
      <div class="col">
        <h2 class="page-title">{{ $place->label }}</h2>
        <div class="text-secondary">
          {{ $place->city?->name }}
          @if($place->category) · {{ $place->category }} @endif
        </div>
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('sysadmin.city-places.edit', $place) }}">Editar</a>
        <form class="d-inline" method="POST" action="{{ route('sysadmin.city-places.destroy', $place) }}"
              onsubmit="return confirm('¿Eliminar este lugar?')">
          @csrf
          @method('DELETE')
          <button class="btn btn-outline-danger">Eliminar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="page-body">
  <div class="container-xl">
    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row g-3">
      {{-- Datos --}}
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Datos del lugar</h3>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-4 text-secondary">Ciudad</dt><dd class="col-8">{{ $place->city?->name }}</dd>
              <dt class="col-4 text-secondary">Categoría</dt><dd class="col-8">{{ $place->category ?: '—' }}</dd>
              <dt class="col-4 text-secondary">Dirección</dt><dd class="col-8">{{ $place->address ?: '—' }}</dd>
              <dt class="col-4 text-secondary">Coordenadas</dt><dd class="col-8">{{ $place->lat }}, {{ $place->lng }}</dd>
              <dt class="col-4 text-secondary">Prioridad</dt><dd class="col-8">{{ (int)$place->priority }}</dd>
              <dt class="col-4 text-secondary">Featured</dt><dd class="col-8">{{ $place->is_featured ? 'Sí' : 'No' }}</dd>
              <dt class="col-4 text-secondary">Activo</dt><dd class="col-8">{{ $place->is_active ? 'Sí' : 'No' }}</dd>
            </dl>
          </div>
          <div class="card-footer">
            <a class="btn btn-outline-primary" href="{{ route('sysadmin.city-places.index', ['city_id' => $place->city_id]) }}">
              Ver todos en esta ciudad
            </a>
          </div>
        </div>
      </div>

      {{-- Tarifa --}}
      <div class="col-12 col-lg-6">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Tarifa especial</h3>
          </div>
          <div class="card-body">
            <dl class="row mb-0">
              <dt class="col-5 text-secondary">Tarifa especial activa</dt>
              <dd class="col-7">{{ $place->fare_is_active ? 'Sí' : 'No' }}</dd>

              <dt class="col-5 text-secondary">Radio destino (m)</dt>
              <dd class="col-7">{{ (int)($place->fare_radius_m ?? 0) }}</dd>

              <dt class="col-5 text-secondary">Radio “near” origen (m)</dt>
              <dd class="col-7">{{ (int)($place->fare_near_origin_radius_m ?? 0) }}</dd>

              <dt class="col-5 text-secondary">Regla habilitada</dt>
              <dd class="col-7">{{ $frEnabled ? 'Sí' : 'No' }}</dd>

              <dt class="col-5 text-secondary">Nombre</dt>
              <dd class="col-7">{{ $frLabel ?: '—' }}</dd>

              <dt class="col-5 text-secondary">Modo</dt>
              <dd class="col-7">
                @if($frMode === 'extra_fixed_tiered')
                  Sumar extra (near/full)
                @elseif($frMode === 'total_fixed_tiered')
                  Total fijo (near/full)
                @else
                  —
                @endif
              </dd>
            </dl>

            <hr class="my-3">

            <div class="row g-2">
              <div class="col-6">
                <div class="text-secondary small mb-1">Extra FULL</div>
                <div class="fw-semibold">{{ (int)($fr['full_extra'] ?? 0) }}</div>
              </div>
              <div class="col-6">
                <div class="text-secondary small mb-1">Extra NEAR</div>
                <div class="fw-semibold">{{ (int)($fr['near_extra'] ?? 0) }}</div>
              </div>
              <div class="col-6">
                <div class="text-secondary small mb-1">Total FULL</div>
                <div class="fw-semibold">{{ (int)($fr['full_total'] ?? 0) }}</div>
              </div>
              <div class="col-6">
                <div class="text-secondary small mb-1">Total NEAR</div>
                <div class="fw-semibold">{{ (int)($fr['near_total'] ?? 0) }}</div>
              </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
              <strong>Condición:</strong>
              <code>Activo</code> + <code>Tarifa especial activa</code> + <code>Regla habilitada</code> + <code>Radio destino</code> &gt; 0.
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
