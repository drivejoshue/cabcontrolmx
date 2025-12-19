
@extends('layouts.admin')

@section('title', 'Recargar wallet')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h3 class="mb-0">Recargar wallet</h3>
            <div class="text-muted small">
                Recarga manual (simulación). Luego se conecta a MercadoPago/Conekta (OXXO/SPEI/tarjeta).
            </div>
        </div>
        <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header"><strong>Datos de recarga</strong></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.wallet.topup.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label class="form-label">Monto</label>
                            <input type="number"
                                   step="0.01"
                                   name="amount"
                                   value="{{ old('amount', request('amount')) }}"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   min="1"
                                   placeholder="Ej. 1500">
                            @error('amount') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">
                                Consejo: recarga el monto sugerido para cubrir el siguiente cargo del plan.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas (opcional)</label>
                            <input type="text"
                                   name="notes"
                                   value="{{ old('notes') }}"
                                   class="form-control @error('notes') is-invalid @enderror"
                                   maxlength="255"
                                   placeholder="Ej. Recarga para cubrir fin de mes">
                            @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="{{ route('admin.wallet.index') }}" class="btn btn-outline-secondary">Cancelar</a>
                            <button class="btn btn-primary">Aplicar recarga</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info mt-3 mb-0">
                <strong>Próximo paso:</strong> aquí se reemplaza el “aplicar recarga” por
                “crear intento de pago” y “confirmación por webhook” (MercadoPago/Conekta),
                incluyendo OXXO/SPEI/tarjeta.
            </div>
        </div>
    </div>

</div>
@endsection
