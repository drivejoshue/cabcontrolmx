<?php /** @var object $stand */ ?>
@extends('layouts.admin')
@section('title','Paradero #'.$stand->id)

@section('content')
<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h3 class="mb-0">Paradero #{{ $stand->id }}</h3>
    <a href="{{ route('taxistands.index') }}" class="btn btn-outline-secondary">
      <i data-feather="arrow-left"></i> Volver
    </a>
  </div>

  <div class="card">
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Nombre</dt>
        <dd class="col-sm-9">{{ $stand->nombre }}</dd>

        <dt class="col-sm-3">Sector</dt>
        <dd class="col-sm-9">{{ $stand->sector_id }}</dd>

        <dt class="col-sm-3">Coordenadas</dt>
        <dd class="col-sm-9">{{ $stand->latitud }}, {{ $stand->longitud }}</dd>

        <dt class="col-sm-3">Capacidad</dt>
        <dd class="col-sm-9">{{ $stand->capacidad ?? 0 }}</dd>

        <dt class="col-sm-3">Código</dt>
        <dd class="col-sm-9"><code>{{ $stand->codigo }}</code></dd>

        <dt class="col-sm-3">QR Secret</dt>
        <dd class="col-sm-9"><code>{{ $stand->qr_secret }}</code></dd>
      </dl>
    </div>
  </div>
</div>
@endsection
