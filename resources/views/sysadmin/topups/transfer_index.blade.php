@extends('layouts.sysadmin')

@section('title','Transferencias por validar')

@php
  $statusLabel = function($s) {
    return match($s) {
      'pending_review' => 'Pendiente',
      'credited'       => 'Acreditadas',
      'approved'       => 'Aprobadas',
      'rejected'       => 'Rechazadas',
      default          => 'Todas',
    };
  };

  // si quieres que por defecto sea Pendiente cuando viene vacío:
  $currentStatus = $status ?? request('status');
@endphp

@section('content')
<div class="container-fluid">

  <form class="row g-2 mb-3" method="GET" action="{{ route('sysadmin.topups.transfer.index') }}">
    <div class="col-auto">
      <select name="status" class="form-select">
        <option value="" @selected(empty($currentStatus))>Todas</option>
        <option value="pending_review" @selected(($currentStatus ?? '')==='pending_review')>Pendiente</option>
        <option value="credited" @selected(($currentStatus ?? '')==='credited')>Acreditadas</option>
        <option value="rejected" @selected(($currentStatus ?? '')==='rejected')>Rechazadas</option>
        
      </select>
    </div>

    <div class="col-auto">
      <button class="btn btn-outline-primary" type="submit">Filtrar</button>
    </div>

    @if(!empty($currentStatus))
      <div class="col-auto">
        <a href="{{ route('sysadmin.topups.transfer.index') }}" class="btn btn-outline-secondary">Limpiar</a>
      </div>
    @endif
  </form>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Transferencias</h3>
      <div class="text-muted small">
        Proveedor: <span class="fw-semibold">bank</span> ·
        Estado: <span class="fw-semibold">{{ $statusLabel($currentStatus) }}</span>
      </div>
    </div>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong>Solicitudes</strong>
      <span class="text-muted small">Mostrando {{ $items->count() }} de {{ $items->total() }}</span>
    </div>

    @include('sysadmin.topups._transfer_table', ['items' => $items])

    <div class="card-footer">
      {{ $items->withQueryString()->links() }}
    </div>
  </div>

</div>
@endsection
