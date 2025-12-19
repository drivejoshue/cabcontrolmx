@csrf

<div class="row g-3">

  {{-- RESUMEN DE PLAN / BILLING --}}
  <div class="col-12">
    <div class="alert alert-{{ ($canRegister ?? true) ? 'info' : 'danger' }} d-flex justify-content-between align-items-center">
      <div>
        <strong>Plan del tenant:</strong>
        @if(isset($profile) && $profile)
          {{ strtoupper($profile->billing_model) }}
          @if($profile->status)
            · Status: <span class="badge bg-secondary">{{ $profile->status }}</span>
          @endif
        @else
          <span class="text-danger">Sin perfil de facturación configurado</span>
        @endif
        <br>
        <small class="text-muted">
          Vehículos activos: <strong>{{ $activeVehicles ?? 0 }}</strong>
          @if(isset($profile) && $profile && $profile->billing_model === 'per_vehicle')
            @if($profile->status === 'trial')
              · Trial:
              hasta {{ $profile->trial_vehicles ?? 5 }} vehículos,
              @if($profile->trial_ends_at)
                hasta {{ $profile->trial_ends_at->format('d/m/Y') }}
              @else
                sin fecha de fin definida
              @endif
            @elseif($profile->max_vehicles)
              · Límite de plan: {{ $profile->max_vehicles }} vehículos
            @endif
          @endif
        </small>
      </div>

      @if(isset($billingMessage) && $billingMessage)
        <div class="ms-3 text-end">
          <span class="text-danger small">{{ $billingMessage }}</span>
        </div>
      @endif
    </div>
  </div>

  {{-- DATOS PRINCIPALES DEL VEHÍCULO --}}
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-body">
        <ul class="nav nav-pills mb-3">
          <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-v-datos">Datos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-v-opc">Opciones</a>
          </li>
        </ul>

        <div class="tab-content">
          {{-- TAB: DATOS --}}
          <div class="tab-pane fade show active" id="tab-v-datos">
            <div class="row g-2">
              <div class="col-sm-4">
                <label class="form-label">Económico</label>
                <input type="text" name="economico" class="form-control" required
                       value="{{ old('economico', $v->economico ?? '') }}">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Placa</label>
                <input type="text" name="plate" class="form-control" required
                       value="{{ old('plate', $v->plate ?? '') }}">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Capacidad</label>
                <input type="number" name="capacity" class="form-control" min="1" max="10"
                       value="{{ old('capacity', $v->capacity ?? 4) }}">
              </div>
            </div>

          <div class="row g-2 mt-2">
    <div class="col-sm-8">
        <label class="form-label">Marca y modelo</label>
        <select name="catalog_id" id="catalog_id" class="form-select js-vehicle-catalog">
            <option value="">Escribe para buscar...</option>
            @foreach($vehicleCatalog as $item)
                <option value="{{ $item->id }}"
                        data-brand="{{ $item->brand }}"
                        data-model="{{ $item->model }}">
                    {{ $item->brand }} - {{ $item->model }} ({{ strtoupper($item->type) }})
                </option>
            @endforeach
        </select>
        <small class="text-muted">
            Empieza a escribir “Versa”, “Tsuru”, “Jetta”, etc. para encontrar rápido.
        </small>
    </div>

    <div class="col-sm-4">
        <label class="form-label">Color</label>
        <input type="text" name="color" class="form-control"
               value="{{ old('color', $v->color ?? '') }}">
    </div>
</div>

{{-- Campos ocultos/fijos donde realmente guardamos brand/model en vehicles --}}
<input type="hidden" name="brand"  id="brand_input"  value="{{ old('brand', $v->brand ?? '') }}">
<input type="hidden" name="model"  id="model_input"  value="{{ old('model', $v->model ?? '') }}">


            <div class="row g-2 mt-2">
              {{-- Año como dropdown de años recientes --}}
              <div class="col-sm-4">
                <label class="form-label">Año</label>
                @php
                  $currentYearValue = old('year', $v->year ?? '');
                @endphp
                <select name="year" class="form-select">
                  <option value="">Seleccione año...</option>
                  @foreach($years as $y)
                    <option value="{{ $y }}"
                      {{ (string)$currentYearValue === (string)$y ? 'selected' : '' }}>
                      {{ $y }}
                    </option>
                  @endforeach
                </select>
              </div>

              <div class="col-sm-8">
                <label class="form-label">Póliza (ID)</label>
                <input type="text" name="policy_id" class="form-control"
                       value="{{ old('policy_id', $v->policy_id ?? '') }}">
                <small class="text-muted">
                  Opcional. Puedes registrar el número de póliza o referencia interna.
                </small>
              </div>
            </div>
          </div>

          {{-- TAB: OPCIONES --}}
          <div class="tab-pane fade" id="tab-v-opc">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="active" value="1"
                     {{ old('active', ($v->active ?? 1) ? 1 : 0) ? 'checked' : '' }}>
              <label class="form-check-label">Activo</label>
            </div>
            <small class="text-muted">
              La foto se guarda en <code>storage/app/public/vehicles</code> y se referencia en
              <code>foto_path</code>. El vehículo será enviado a verificación de documentos
              inmediatamente después de guardarlo.
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- FOTO + BOTONES --}}
  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-body">
        <label class="form-label d-block">Foto</label>
        <div class="d-flex align-items-center gap-3">
          @php $fp = $v->foto_path ?? null; @endphp
          <div>
            @if($fp)
              <img src="{{ asset('storage/'.$fp) }}"
                   class="rounded border"
                   style="width:96px;height:96px;object-fit:cover;">
            @else
              <div class="bg-light border rounded d-flex align-items-center justify-content-center"
                   style="width:96px;height:96px;">
                —
              </div>
            @endif
          </div>
          <input type="file" name="foto" accept="image/*" class="form-control">
        </div>
        <small class="text-muted d-block mt-2">
          Sube una foto clara del vehículo (frontal o lateral) para facilitar la verificación.
        </small>
      </div>
      <div class="card-footer d-grid gap-2">
        <button class="btn btn-primary" type="submit" {{ !($canRegister ?? true) ? 'disabled' : '' }}>
          Guardar y continuar con documentos
        </button>
        <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Si usas jQuery + Select2
    if (window.jQuery && $.fn.select2) {
        $('#catalog_id').select2({
            theme: 'bootstrap-5',
            placeholder: 'Escribe marca o modelo...',
            allowClear: true,
            width: '100%'
        });

        $('#catalog_id').on('change', function () {
            const opt = $(this).find('option:selected');
            const brand = opt.data('brand') || '';
            const model = opt.data('model') || '';

            $('#brand_input').val(brand);
            $('#model_input').val(model);
        });
    }
});
</script>
@endpush
