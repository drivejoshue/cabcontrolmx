@extends('layouts.admin_tabler')

@section('title','Vehiculos')
@section('page-id','vehicles')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Vehículos</h3>
  <a href="{{ route('vehicles.create') }}" class="btn btn-primary"><i data-feather="plus"></i> Nuevo</a>
</div>

<form class="mb-3" method="get">
  <div class="input-group">
    <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Buscar por económico, placa, marca, modelo…">
    <button class="btn btn-outline-secondary">Buscar</button>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Foto</th><th>Económico</th><th>Placa</th><th>Marca/Modelo</th><th>Año</th><th>Cap.</th><th>Tipo</th><th>Activo</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($vehicles as $v)
        <tr>
          <td style="width:72px">
            @if($v->foto_path)
              <img src="{{ asset('storage/'.$v->foto_path) }}" class="rounded" style="width:64px;height:40px;object-fit:cover;">
            @else
              <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:64px;height:40px;">—</div>
            @endif
          </td>
          <td>{{ $v->economico }}</td>
          <td>{{ $v->plate }}</td>
          <td>{{ trim(($v->brand ?? '').' '.($v->model ?? '')) ?: '—' }}</td>
          <td>{{ $v->year ?? '—' }}</td>
          <td>{{ $v->capacity }}</td>
          <td>{{ $v->type ? strtoupper($v->type) : '—' }}</td>
          <td>@if($v->active) <span class="badge bg-success">Sí</span> @else <span class="badge bg-secondary">No</span> @endif</td>
          <td class="text-end">
            <a href="{{ route('vehicles.show',$v->id) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
            <a href="{{ route('vehicles.edit',$v->id) }}" class="btn btn-sm btn-primary">Editar</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-center text-muted">Sin vehículos.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    {{ $vehicles->links() }}
  </div>
</div>
@endsection
