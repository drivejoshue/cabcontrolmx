<?php
/** @var \Illuminate\Pagination\LengthAwarePaginator $taxistands */
?>
@extends('layouts.admin')
@section('title','Paraderos (Taxi Stands)')

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Paraderos (Taxi Stands)</h3>
    <a href="{{ route('taxistands.create') }}" class="btn btn-primary">
      <i data-feather="plus"></i> Nuevo paradero
    </a>
  </div>

  @if(session('ok'))
    <div class="alert alert-success">{{ session('ok') }}</div>
  @endif

  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Sector</th>
          <th>Lat/Lng</th>
          <th>Capacidad</th>
          <th>Activo</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        @forelse($taxistands as $s)
          <tr>
            <td>{{ $s->id }}</td>
            <td><a href="{{ route('taxistands.edit',$s->id) }}">{{ $s->nombre }}</a></td>
            <td>{{ $s->sector_nombre ?? '—' }}</td>
            <td class="text-muted">{{ number_format($s->latitud,6) }}, {{ number_format($s->longitud,6) }}</td>
            <td>{{ $s->capacidad ?? 0 }}</td>
            <td>
              @if(($s->activo ?? $s->active ?? 1) == 1)
                <span class="badge bg-success">Sí</span>
              @else
                <span class="badge bg-secondary">No</span>
              @endif
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-outline-secondary" href="{{ route('taxistands.edit',$s->id) }}">
                <i data-feather="edit-2"></i>
              </a>
              <a class="btn btn-sm btn-outline-info" href="{{ route('taxistands.edit',$s->id) }}#preview">
                <i data-feather="map"></i>
              </a>
              <form action="{{ route('taxistands.destroy',$s->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('¿Desactivar este paradero?');">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" type="submit">
                  <i data-feather="slash"></i>
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted">Sin paraderos aún.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">{{ $taxistands->links() }}</div>
</div>
@endsection
