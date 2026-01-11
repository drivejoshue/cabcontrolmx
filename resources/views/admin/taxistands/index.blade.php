<?php
/** @var \Illuminate\Pagination\LengthAwarePaginator $taxistands */
?>
@extends('layouts.admin')
@section('title','Paraderos (Taxi Stands)')

@section('content')
<div class="container-fluid px-0">
  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <h3 class="card-title mb-0">Paraderos (Taxi Stands)</h3>
        <div class="text-muted" style="font-size:.85rem;">Administración de paraderos por sector.</div>
      </div>
      <a href="{{ route('admin.taxistands.create') }}" class="btn btn-primary">
        <i data-feather="plus"></i> Nuevo paradero
      </a>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th>Nombre</th>
            <th>Sector</th>
            <th class="text-muted">Lat/Lng</th>
            <th style="width:110px;">Capacidad</th>
            <th style="width:100px;">Activo</th>
            <th class="text-end" style="width:170px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($taxistands as $s)
            <tr>
              <td class="text-muted">{{ $s->id }}</td>

              <td class="fw-semibold">
                <a href="{{ route('admin.taxistands.show',$s->id) }}" class="text-decoration-none">
                  {{ $s->nombre }}
                </a>
                <div class="text-muted" style="font-size:.82rem;">
                  Código: <code>{{ $s->codigo }}</code>
                </div>
              </td>

              <td>{{ $s->sector_nombre ?? '—' }}</td>

              <td class="text-muted">
                {{ number_format($s->latitud,6) }}, {{ number_format($s->longitud,6) }}
              </td>

              <td>{{ $s->capacidad ?? 0 }}</td>

              <td>
                @if(($s->activo ?? $s->active ?? 1) == 1)
                  <span class="badge bg-success-lt text-success">Activo</span>
                @else
                  <span class="badge bg-secondary-lt text-secondary">Inactivo</span>
                @endif
              </td>

              <td class="text-end">
                <div class="btn-list justify-content-end flex-nowrap">
                  {{-- Ver (show) --}}
                  <a class="btn btn-sm btn-outline-secondary"
                     href="{{ route('admin.taxistands.show',$s->id) }}" title="Ver detalle">
                    <i data-feather="eye"></i>
                  </a>

                  {{-- Editar --}}
                  <a class="btn btn-sm btn-outline-primary"
                     href="{{ route('admin.taxistands.edit',$s->id) }}" title="Editar">
                    <i data-feather="edit-2"></i>
                  </a>

                  {{-- Desactivar --}}
                  <form action="{{ route('admin.taxistands.destroy',$s->id) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('¿Desactivar este paradero?');">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar">
                      <i data-feather="trash-2"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="7" class="text-center text-muted py-4">Sin paraderos aún.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $taxistands->links() }}
    </div>
  </div>
</div>
@endsection
