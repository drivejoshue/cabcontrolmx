<?php /** @var \Illuminate\Pagination\LengthAwarePaginator $sectores */ ?>
@extends('layouts.admin')
@section('title','Sectores')

@section('content')
<div class="container-fluid px-0">
  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <h3 class="card-title mb-0">Sectores</h3>
        <div class="text-muted" style="font-size:.85rem;">
          Los sectores definen áreas de referencia/cobertura para paraderos (Taxi Stands).
        </div>
      </div>

      <a href="{{ route('admin.sectores.create') }}" class="btn btn-primary">
        <i data-feather="plus"></i> Nuevo sector
      </a>
    </div>

    <div class="table-responsive">
      <table class="table card-table table-vcenter">
        <thead>
          <tr>
            <th style="width:70px;">ID</th>
            <th>Nombre</th>
            <th style="width:110px;">Activo</th>
            <th class="text-muted" style="width:190px;">Actualizado</th>
            <th class="text-end" style="width:170px;">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($sectores as $s)
          <tr>
            <td class="text-muted">{{ $s->id }}</td>

            <td class="fw-semibold">
              <a class="text-decoration-none" href="{{ route('admin.sectores.show',$s->id) }}">
                {{ $s->nombre }}
              </a>
            </td>

            <td>
              @if($s->activo)
                <span class="badge bg-success-lt text-success">Activo</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">Inactivo</span>
              @endif
            </td>

            <td class="text-muted">{{ $s->updated_at }}</td>

            <td class="text-end">
              <div class="btn-list justify-content-end flex-nowrap">
                <a href="{{ route('admin.sectores.show',$s->id) }}"
                   class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                  <i data-feather="eye"></i>
                </a>

                <a href="{{ route('admin.sectores.edit',$s->id) }}"
                   class="btn btn-sm btn-outline-primary" title="Editar">
                  <i data-feather="edit-2"></i>
                </a>

                <form action="{{ route('admin.sectores.destroy',$s->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('¿Desactivar sector?');">
                  @csrf @method('DELETE')
                  <button class="btn btn-sm btn-outline-danger" type="submit" title="Desactivar">
                    <i data-feather="trash-2"></i>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-center text-muted py-4">Sin sectores</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $sectores->links() }}
    </div>
  </div>
</div>
@endsection
