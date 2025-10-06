<?php /** @var \Illuminate\Pagination\LengthAwarePaginator $sectores */ ?>
@extends('layouts.admin')
@section('title','Sectores')

@section('content')
<div class="container-fluid p-0">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Sectores</h3>
    <a href="{{ route('sectores.create') }}" class="btn btn-primary">
      <i data-feather="plus"></i> Nuevo
    </a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="table-responsive">
    <table class="table align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Activo</th>
          <th>Actualizado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      @forelse($sectores as $s)
        <tr>
          <td>{{ $s->id }}</td>
          <td>{{ $s->nombre }}</td>
          <td>{!! $s->activo ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-secondary">No</span>' !!}</td>
          <td>{{ $s->updated_at }}</td>
          <td class="text-end">
            <a href="{{ route('sectores.show',$s->id) }}" class="btn btn-sm btn-outline-secondary"><i data-feather="eye"></i></a>
            <a href="{{ route('sectores.edit',$s->id) }}" class="btn btn-sm btn-outline-primary"><i data-feather="edit"></i></a>
            <form action="{{ route('sectores.destroy',$s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Desactivar sector?');">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button class="btn btn-sm btn-outline-danger"><i data-feather="x"></i></button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted">Sin sectores</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>

  {{ $sectores->links() }}
</div>
@endsection
