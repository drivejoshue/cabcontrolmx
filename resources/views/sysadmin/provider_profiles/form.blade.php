@extends('layouts.sysadmin')
@section('title', $item->exists ? 'Editar proveedor' : 'Nuevo proveedor')

@section('content')
<div class="container-xl">
  <div class="mb-3">
    <h1 class="h3 mb-0">{{ $item->exists ? 'Editar proveedor' : 'Nuevo proveedor' }}</h1>
    <div class="text-muted">Este registro alimenta términos, pantallas de pago y notificaciones.</div>
  </div>

  @if ($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold">Revisa los campos:</div>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $item->exists ? route('sysadmin.provider-profiles.update',$item) : route('sysadmin.provider-profiles.store') }}">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="row g-3">
      <div class="col-12 col-lg-7">
        <div class="card">
          <div class="card-header"><h3 class="card-title">Datos básicos</h3></div>
          <div class="card-body">
            <label class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="active" value="1" @checked(old('active',$item->active))>
              <span class="form-check-label">Activo (si activas este, desactiva otros)</span>
            </label>

            <div class="mb-3">
              <label class="form-label">Nombre (display) *</label>
              <input class="form-control" name="display_name" value="{{ old('display_name',$item->display_name) }}" required>
            </div>

            <div class="mb-3">
              <label class="form-label">Nombre contacto *</label>
              <input class="form-control" name="contact_name" value="{{ old('contact_name',$item->contact_name) }}" required>
            </div>

            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">Teléfono</label>
                <input class="form-control" name="phone" value="{{ old('phone',$item->phone) }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">CP</label>
                <input class="form-control" name="postal_code" value="{{ old('postal_code',$item->postal_code) }}">
              </div>
            </div>

            <div class="row g-2 mt-1">
              <div class="col-md-6">
                <label class="form-label">Email soporte</label>
                <input class="form-control" name="email_support" value="{{ old('email_support',$item->email_support) }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email admin</label>
                <input class="form-control" name="email_admin" value="{{ old('email_admin',$item->email_admin) }}">
              </div>
            </div>

            <hr class="my-3">

            <div class="mb-2 fw-semibold">Dirección</div>
            <div class="mb-2">
              <label class="form-label">Dirección línea 1</label>
              <input class="form-control" name="address_line1" value="{{ old('address_line1',$item->address_line1) }}">
            </div>
            <div class="mb-2">
              <label class="form-label">Dirección línea 2</label>
              <input class="form-control" name="address_line2" value="{{ old('address_line2',$item->address_line2) }}">
            </div>
            <div class="row g-2">
              <div class="col-md-4"><label class="form-label">Ciudad</label><input class="form-control" name="city" value="{{ old('city',$item->city) }}"></div>
              <div class="col-md-4"><label class="form-label">Estado</label><input class="form-control" name="state" value="{{ old('state',$item->state) }}"></div>
              <div class="col-md-4"><label class="form-label">País</label><input class="form-control" name="country" value="{{ old('country',$item->country ?? 'México') }}"></div>
            </div>

          </div>
        </div>
      </div>

      <div class="col-12 col-lg-5">
        <div class="card mb-3">
          <div class="card-header"><h3 class="card-title">Datos fiscales</h3></div>
          <div class="card-body">
            <div class="mb-2">
              <label class="form-label">Razón social</label>
              <input class="form-control" name="legal_name" value="{{ old('legal_name',$item->legal_name) }}">
            </div>
            <div class="mb-2">
              <label class="form-label">RFC</label>
              <input class="form-control" name="rfc" value="{{ old('rfc',$item->rfc) }}">
            </div>
            <div class="mb-2">
              <label class="form-label">Régimen fiscal</label>
              <input class="form-control" name="tax_regime" value="{{ old('tax_regime',$item->tax_regime) }}">
            </div>
            <div class="mb-2">
              <label class="form-label">Domicilio fiscal</label>
              <textarea class="form-control" name="fiscal_address" rows="4">{{ old('fiscal_address',$item->fiscal_address) }}</textarea>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="card-title">Cuentas de depósito</h3></div>
          <div class="card-body">
            <div class="fw-semibold mb-2">Cuenta 1</div>
            <div class="mb-2"><label class="form-label">Banco</label><input class="form-control" name="acc1_bank" value="{{ old('acc1_bank',$item->acc1_bank) }}"></div>
            <div class="mb-2"><label class="form-label">Beneficiario</label><input class="form-control" name="acc1_beneficiary" value="{{ old('acc1_beneficiary',$item->acc1_beneficiary) }}"></div>
            <div class="mb-2"><label class="form-label">Número de cuenta</label><input class="form-control" name="acc1_account" value="{{ old('acc1_account',$item->acc1_account) }}"></div>
            <div class="mb-3"><label class="form-label">CLABE</label><input class="form-control" name="acc1_clabe" value="{{ old('acc1_clabe',$item->acc1_clabe) }}"></div>

            <div class="fw-semibold mb-2">Cuenta 2</div>
            <div class="mb-2"><label class="form-label">Banco</label><input class="form-control" name="acc2_bank" value="{{ old('acc2_bank',$item->acc2_bank) }}"></div>
            <div class="mb-2"><label class="form-label">Beneficiario</label><input class="form-control" name="acc2_beneficiary" value="{{ old('acc2_beneficiary',$item->acc2_beneficiary) }}"></div>
            <div class="mb-2"><label class="form-label">Número de cuenta</label><input class="form-control" name="acc2_account" value="{{ old('acc2_account',$item->acc2_account) }}"></div>
            <div><label class="form-label">CLABE</label><input class="form-control" name="acc2_clabe" value="{{ old('acc2_clabe',$item->acc2_clabe) }}"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary"><i class="ti ti-device-floppy"></i> Guardar</button>
      <a class="btn btn-outline-secondary" href="{{ route('sysadmin.provider-profiles.index') }}">Volver</a>
    </div>
  </form>
</div>
@endsection
