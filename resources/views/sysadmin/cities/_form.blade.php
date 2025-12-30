@php
  $isEdit = isset($city) && $city->exists;
@endphp

<div class="card">
  <div class="card-body">
    <div class="row g-3">

      <div class="col-md-6">
        <label class="form-label">Nombre</label>
        <input name="name" value="{{ old('name', $city->name) }}" class="form-control" maxlength="120" required>
        @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6">
        <label class="form-label">Slug (opcional)</label>
        <input name="slug" value="{{ old('slug', $city->slug) }}" class="form-control" maxlength="160" placeholder="veracruz">
        <div class="text-secondary small">Si lo dejas vacío, se genera a partir del nombre.</div>
        @error('slug')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-6">
        <label class="form-label">Timezone</label>
        <input name="timezone" value="{{ old('timezone', $city->timezone ?? 'America/Mexico_City') }}" class="form-control" maxlength="64">
        @error('timezone')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Centro Lat</label>
        <input name="center_lat" value="{{ old('center_lat', $city->center_lat) }}" class="form-control" required>
        @error('center_lat')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Centro Lng</label>
        <input name="center_lng" value="{{ old('center_lng', $city->center_lng) }}" class="form-control" required>
        @error('center_lng')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Radio (km)</label>
        <input type="number" step="0.1" min="1" max="999" name="radius_km"
               value="{{ old('radius_km', $city->radius_km ?? 30) }}" class="form-control" required>
        @error('radius_km')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3 d-flex align-items-end">
        <label class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" value="1"
                 @checked(old('is_active', $city->is_active ?? true) ? true : false)>
          <span class="form-check-label">Activa</span>
        </label>
        @error('is_active')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear ciudad' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('sysadmin.cities.index') }}">Volver</a>
  </div>
</div>
