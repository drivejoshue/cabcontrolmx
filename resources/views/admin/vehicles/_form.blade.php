@csrf
<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-body">
        <ul class="nav nav-pills mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-v-datos">Datos</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-v-opc">Opciones</a></li>
        </ul>
        <div class="tab-content">
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
              <div class="col-sm-4">
                <label class="form-label">Marca</label>
                <input type="text" name="brand" class="form-control"
                       value="{{ old('brand', $v->brand ?? '') }}">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Modelo</label>
                <input type="text" name="model" class="form-control"
                       value="{{ old('model', $v->model ?? '') }}">
              </div>
              <div class="col-sm-4">
                <label class="form-label">Color</label>
                <input type="text" name="color" class="form-control"
                       value="{{ old('color', $v->color ?? '') }}">
              </div>
            </div>

            <div class="row g-2 mt-2">
              <div class="col-sm-4">
                <label class="form-label">Año</label>
                <input type="number" name="year" class="form-control" min="1970" max="2100"
                       value="{{ old('year', $v->year ?? '') }}">
              </div>
              <div class="col-sm-8">
                <label class="form-label">Póliza (ID)</label>
                <input type="text" name="policy_id" class="form-control"
                       value="{{ old('policy_id', $v->policy_id ?? '') }}">
              </div>
            </div>
          </div>

          <div class="tab-pane fade" id="tab-v-opc">
            <div class="form-check form-switch mb-2">
              <input class="form-check-input" type="checkbox" name="active" value="1"
                     {{ old('active', ($v->active ?? 1) ? 1 : 0) ? 'checked' : '' }}>
              <label class="form-check-label">Activo</label>
            </div>
            <small class="text-muted">La foto se guarda en storage/public/vehicles y se referencia en <code>foto_path</code>.</small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card">
      <div class="card-body">
        <label class="form-label d-block">Foto</label>
        <div class="d-flex align-items-center gap-3">
          @php $fp = $v->foto_path ?? null; @endphp
          <div>
            @if($fp)
              <img src="{{ asset('storage/'.$fp) }}" class="rounded border" style="width:96px;height:96px;object-fit:cover;">
            @else
              <div class="bg-light border rounded d-flex align-items-center justify-content-center" style="width:96px;height:96px;">—</div>
            @endif
          </div>
          <input type="file" name="foto" accept="image/*" class="form-control">
        </div>
      </div>
      <div class="card-footer d-grid gap-2">
        <button class="btn btn-primary" type="submit">Guardar</button>
        <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</div>
