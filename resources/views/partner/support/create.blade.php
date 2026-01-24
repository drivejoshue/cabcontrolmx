@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Cuenta</div>
        <h2 class="page-title">Nueva solicitud</h2>
        <div class="text-muted">Describe el pedido con detalle. Adjuntos vendrán después.</div>
      </div>
      <div class="col-auto ms-auto">
        <a class="btn btn-outline-secondary" href="{{ route('partner.support.index') }}">
          <i class="ti ti-arrow-left me-1"></i> Volver
        </a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="{{ route('partner.support.store') }}" class="row g-3">
        @csrf

        <div class="col-md-8">
          <label class="form-label">Asunto</label>
          <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required maxlength="190">
          @error('subject') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label class="form-label">Categoría</label>
          <select name="category" class="form-select" required>
            @foreach([
              'taxi_stand'=>'TaxiStand / Bases',
              'tariff'=>'Tarifas',
              'bug'=>'Bug / Inconsistencia',
              'suggestion'=>'Sugerencia',
              'other'=>'Otro'
            ] as $k=>$v)
              <option value="{{ $k }}" {{ old('category')===$k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
          </select>
          @error('category') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-4">
          <label class="form-label">Prioridad</label>
          <select name="priority" class="form-select">
            @foreach(['low'=>'Baja','normal'=>'Normal','high'=>'Alta','urgent'=>'Urgente'] as $k=>$v)
              <option value="{{ $k }}" {{ old('priority','normal')===$k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
          </select>
          @error('priority') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-12">
          <label class="form-label">Mensaje</label>
          <textarea name="message" rows="8" class="form-control" required maxlength="8000"
                    placeholder="Qué necesitas, en qué pantalla, pasos para reproducir, IDs (ride/driver), evidencias...">{{ old('message') }}</textarea>
          @error('message') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary">
            <i class="ti ti-send me-1"></i> Enviar
          </button>
          <a class="btn btn-outline-secondary" href="{{ route('partner.support.index') }}">Cancelar</a>
        </div>

      </form>
    </div>
  </div>

</div>
@endsection
