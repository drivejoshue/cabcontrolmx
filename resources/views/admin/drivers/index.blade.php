@extends('layouts.admin')
@section('title','Conductores')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Conductores</h3>
  <a href="{{ route('admin.drivers.create') }}" class="btn btn-primary"><i data-feather="plus"></i> Nuevo</a>
</div>

<form class="mb-3" method="get">
  <div class="input-group">
    <input type="text" class="form-control" name="q" value="{{ $q }}" placeholder="Buscar por nombre, teléfono, email…">
    <button class="btn btn-outline-secondary">Buscar</button>
  </div>
</form>

<div class="card">
  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>Foto</th><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Status</th><th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
      @forelse($drivers as $d)
        <tr>
          <td style="width:72px">
            @if($d->foto_path)
              <img src="{{ asset('storage/'.$d->foto_path) }}" class="rounded" style="width:64px;height:40px;object-fit:cover;">
            @else
              <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:64px;height:40px;">—</div>
            @endif
          </td>
          <td>{{ $d->name }}</td>
          <td>{{ $d->phone ?? '—' }}</td>
          <td>{{ $d->email ?? '—' }}</td>
          <td>
            <span class="badge
              @if($d->status==='idle') bg-success
              @elseif($d->status==='busy') bg-warning text-dark
              @else bg-secondary @endif">
              {{ $d->status }}
            </span>
          </td>
          <td class="text-end">
            <a href="{{ route('admin.drivers.show',$d->id) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
            <a href="{{ route('admin.drivers.edit',$d->id) }}" class="btn btn-sm btn-primary">Editar</a>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-center text-muted">Sin conductores.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card-footer">
    {{ $drivers->links() }}
  </div>
</div>
@endsection
