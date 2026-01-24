@php
  $isEdit = isset($place) && $place->exists;

  // Normalizar fare_rule (por si viene JSON string o null)
  $fr = old('fare_rule') ?? (
      is_array($place->fare_rule ?? null)
        ? $place->fare_rule
        : (json_decode($place->fare_rule ?? '[]', true) ?: [])
  );

  $frEnabled = (bool)($fr['enabled'] ?? false);
  $frMode    = (string)($fr['mode'] ?? 'extra_fixed_tiered');
  $frLabel   = (string)($fr['label'] ?? 'Tarifa especial');

  $fullExtra = (int)($fr['full_extra'] ?? 0);
  $nearExtra = (int)($fr['near_extra'] ?? 0);
  $fullTotal = (int)($fr['full_total'] ?? 0);
  $nearTotal = (int)($fr['near_total'] ?? 0);

  $fareIsActive = (bool) old('fare_is_active', $place->fare_is_active ?? false);
  $fareRadiusM  = (int)  old('fare_radius_m', (int)($place->fare_radius_m ?? 0));
  $nearRadiusM  = (int)  old('fare_near_origin_radius_m', (int)($place->fare_near_origin_radius_m ?? 0));
@endphp

<div class="card">
  <div class="card-body">
    <div class="row g-3">
      {{-- Columna izquierda: Datos del lugar --}}
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Datos del lugar</h6>
          <span class="text-secondary small">CityPlace</span>
        </div>

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

          <div class="col-12">
            <label class="form-label">Dirección (opcional)</label>
            <input name="address" value="{{ old('address', $place->address) }}" class="form-control" maxlength="255">
            @error('address')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Categoría</label>
            <input name="category" value="{{ old('category', $place->category) }}" class="form-control" maxlength="40" placeholder="airport, terminal, plaza, imss...">
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

          <div class="col-md-4">
            <label class="form-label">Prioridad</label>
            <input type="number" name="priority" value="{{ old('priority', $place->priority ?? 0) }}" class="form-control" min="0" max="9999" required>
            @error('priority')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-4 d-flex align-items-end">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="is_featured" value="1"
                     @checked(old('is_featured', $place->is_featured) ? true : false)>
              <span class="form-check-label">Featured</span>
            </label>
          </div>

          <div class="col-md-4 d-flex align-items-end">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" name="is_active" value="1"
                     @checked(old('is_active', $place->is_active ?? true) ? true : false)>
              <span class="form-check-label">Activo</span>
            </label>
          </div>
        </div>
      </div>

      {{-- Columna derecha: Tarifa especial --}}
      <div class="col-12 col-lg-6">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <h6 class="mb-0">Tarifa especial</h6>
          <span class="text-secondary small">Geofence + regla</span>
        </div>

        <div class="p-3 border rounded">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="fare_is_active" value="1"
                       @checked($fareIsActive)>
                <span class="form-check-label">Activar tarifa especial para este lugar</span>
              </label>
              @error('fare_is_active')<div class="text-danger small">{{ $message }}</div>@enderror
              <div class="form-text">Switch maestro. Si está apagado, no aplica ninguna regla aunque exista.</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">Radio destino (m)</label>
              <input type="number" name="fare_radius_m"
                     value="{{ $fareRadiusM }}"
                     class="form-control" min="0" max="50000" step="1">
              <div class="form-text">Si el destino cae dentro de este radio, se evalúa la regla.</div>
              @error('fare_radius_m')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Radio “near” origen (m)</label>
              <input type="number" name="fare_near_origin_radius_m"
                     value="{{ $nearRadiusM }}"
                     class="form-control" min="0" max="50000" step="1">
              <div class="form-text">Si el origen está dentro, aplica tier “near”.</div>
              @error('fare_near_origin_radius_m')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <hr class="my-2">
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="fare_rule_enabled" value="1"
                       @checked(old('fare_rule_enabled', $frEnabled) ? true : false)>
                <span class="form-check-label">Regla habilitada</span>
              </label>
              <div class="form-text">La regla vive en <code>fare_rule</code>. Esto controla <code>enabled</code>.</div>
              @error('fare_rule_enabled')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-7">
              <label class="form-label">Nombre (label)</label>
              <input name="fare_rule_label"
                     value="{{ old('fare_rule_label', $frLabel) }}"
                     class="form-control" maxlength="60" placeholder="Tarifa Aeropuerto / Tarifa Especial">
              @error('fare_rule_label')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-5">
              <label class="form-label">Modo</label>
              <select name="fare_rule_mode" class="form-select">
                <option value="extra_fixed_tiered" @selected(old('fare_rule_mode', $frMode)==='extra_fixed_tiered')>
                  Sumar extra (near/full)
                </option>
                <option value="total_fixed_tiered" @selected(old('fare_rule_mode', $frMode)==='total_fixed_tiered')>
                  Total fijo (near/full)
                </option>
              </select>
              @error('fare_rule_mode')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            {{-- Tiered inputs (mostramos ambos, pero aclaramos cuál se usa por modo) --}}
            <div class="col-md-6">
              <label class="form-label">Extra FULL</label>
              <input type="number" name="fare_full_extra"
                     value="{{ old('fare_full_extra', $fullExtra) }}"
                     class="form-control" min="0" max="999999" step="1">
              <div class="form-text">Usado solo si el modo es “Sumar extra”.</div>
              @error('fare_full_extra')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Extra NEAR</label>
              <input type="number" name="fare_near_extra"
                     value="{{ old('fare_near_extra', $nearExtra) }}"
                     class="form-control" min="0" max="999999" step="1">
              <div class="form-text">Usado solo si el modo es “Sumar extra”.</div>
              @error('fare_near_extra')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Total FULL</label>
              <input type="number" name="fare_full_total"
                     value="{{ old('fare_full_total', $fullTotal) }}"
                     class="form-control" min="0" max="999999" step="1">
              <div class="form-text">Usado solo si el modo es “Total fijo”.</div>
              @error('fare_full_total')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-md-6">
              <label class="form-label">Total NEAR</label>
              <input type="number" name="fare_near_total"
                     value="{{ old('fare_near_total', $nearTotal) }}"
                     class="form-control" min="0" max="999999" step="1">
              <div class="form-text">Usado solo si el modo es “Total fijo”.</div>
              @error('fare_near_total')<div class="text-danger small">{{ $message }}</div>@enderror
            </div>

            <div class="col-12">
              <div class="alert alert-info mb-0">
                <strong>Checklist:</strong>
                <code>Activo</code> + <code>Activar tarifa especial</code> + <code>Regla habilitada</code> y <code>Radio destino</code> &gt; 0.
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="card-footer d-flex gap-2">
    <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Guardar cambios' : 'Crear lugar' }}</button>
    <a class="btn btn-outline-secondary" href="{{ route('sysadmin.city-places.index') }}">Volver</a>
  </div>
</div>
