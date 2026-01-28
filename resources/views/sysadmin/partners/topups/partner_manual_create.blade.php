@extends('layouts.sysadmin')

@section('content')
<div class="container-xl">

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Recarga manual a Partner</h3>
    </div>

    <div class="card-body">
      @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
      @if($errors->any())
        <div class="alert alert-danger">
          <ul class="mb-0">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
          </ul>
        </div>
      @endif

      <form method="POST" action="{{ route('sysadmin.topups.manual.store') }}">
        @csrf

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Tenant ID</label>
            <input type="number" name="tenant_id" class="form-control" value="{{ old('tenant_id', $tenantId ?? '') }}" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Partner ID</label>
            <input type="number" name="partner_id" class="form-control" value="{{ old('partner_id', $partnerId ?? '') }}" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Monto</label>
            <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required>
          </div>

          <div class="col-md-3">
            <label class="form-label">Moneda</label>
            <input type="text" name="currency" class="form-control" value="{{ old('currency','MXN') }}">
          </div>

          <div class="col-md-6">
            <label class="form-label">Folio/Ref (opcional)</label>
            <input type="text" name="bank_ref" class="form-control" value="{{ old('bank_ref') }}" placeholder="Ej: AJUSTE-ENERO-01">
          </div>

          <div class="col-md-6">
            <label class="form-label">Notas (opcional)</label>
            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}" placeholder="Motivo / auditoría">
          </div>
        </div>

        <div class="mt-4">
          <button class="btn btn-primary" type="submit">Crear y acreditar</button>
          <a class="btn btn-link" href="{{ url()->previous() }}">Cancelar</a>
        </div>
      </form>

    </div>
  </div>

</div>
@endsection
