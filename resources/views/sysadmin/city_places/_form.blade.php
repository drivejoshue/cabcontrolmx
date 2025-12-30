@php
  $isEdit = isset($place) && $place->exists;
@endphp

<div class="card">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Ciudad</label>
        <select name="city_id" class="form-select" required>
          @foreach($cities as $c)
            <option value="{{ $c->id }}" @selected(old('city_id', $place->city_id) == $c->id)>{{ $c->name }}</option>
          @endforeach
        </select>
        @error('city_id')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-8">
        <label class="form-label">Label</label>
        <input name="label" value="{{ old('label', $place->label) }}" class="form-control" maxlength="160" required>
        @error('label')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-8">
        <label class="form-label">Dirección (opcional)</label>
        <input name="address" value="{{ old('address', $place->address) }}" class="form-control" maxlength="255">
        @error('address')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-4">
        <label class="form-label">Categoría</label>
        <input name="category" value="{{ old('category', $place->category) }}" class="form-control" maxlength="40" placeholder="terminal, plaza, imss...">
        @error('category')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Lat</label>
        <input name="lat" value="{{ old('lat', $place->lat) }}" class="form-control" required>
        @error('lat')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-3">
        <label class="form-label">Lng</label>
        <input name="lng" value="{{ old('lng', $place->lng) }}" class="form-control" required>
        @error('lng')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-2">
        <label class="form-label">Prioridad</label>
        <input type="number" name="priority" value="{{ old('priority', $place->priority ?? 0) }}" class="form-control" min="0" max="9999" required>
        @error('priority')<div class="text-danger small">{{ $message }}</div>@enderror
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <label class="form-check">
          <input class="form-check-input" type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $place->is_featured) ? true : false)>
          <span class="form-check-label">Featured</span>
        </label>
      </div>

      <div class="col-md-2 d-flex align-items-end">
        <label class="form-check">
          <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $place->is_active ?? true) ? true : false)>
          <span class="form-check-label">Activo</span>
        </label>
      </div>
    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear lugar' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('sysadmin.city-places.index') }}">Volver</a>
  </div>
</div>
