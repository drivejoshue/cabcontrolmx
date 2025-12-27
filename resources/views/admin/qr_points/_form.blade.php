@php
  $isEdit = isset($item);
@endphp

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Nombre del punto</label>
    <input class="form-control" name="name" value="{{ old('name', $item->name ?? '') }}" required maxlength="120">
    <div class="form-text">Ej. “Hotel Fiesta”, “Restaurante La Parroquia”.</div>
  </div>

  <div class="col-md-6">
    <label class="form-label">Dirección (opcional)</label>
    <input class="form-control" name="address_text" value="{{ old('address_text', $item->address_text ?? '') }}" maxlength="255">
  </div>

  <div class="col-md-6">
    <label class="form-label">Latitud</label>
    <input class="form-control" name="lat" value="{{ old('lat', $item->lat ?? '') }}" required>
  </div>

  <div class="col-md-6">
    <label class="form-label">Longitud</label>
    <input class="form-control" name="lng" value="{{ old('lng', $item->lng ?? '') }}" required>
  </div>

  <div class="col-12">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="active" value="1"
             {{ old('active', isset($item) ? (int)$item->active : 1) ? 'checked' : '' }}>
      <label class="form-check-label">Activo</label>
    </div>
    <div class="form-text">Si está inactivo, el QR público responderá 404.</div>
  </div>
</div>

@if($errors->any())
  <div class="alert alert-danger mt-3 py-2">
    <div class="fw-semibold mb-1">Revisa los campos:</div>
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif
