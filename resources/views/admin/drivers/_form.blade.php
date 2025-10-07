@csrf
<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-body">
        <ul class="nav nav-pills mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-datos">Datos</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-estado">Estado</a></li>
        </ul>
        <div class="tab-content">
          <div class="tab-pane fade show active" id="tab-datos">
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <input type="text" name="name" class="form-control" required
                     value="{{ old('name', $driver->name ?? '') }}">
            </div>
            <div class="row g-2">
              <div class="col">
                <label class="form-label">Teléfono</label>
                <input type="text" name="phone" class="form-control"
                       value="{{ old('phone', $driver->phone ?? '') }}">
              </div>
              <div class="col">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email', $driver->email ?? '') }}">
              </div>
            </div>
            <div class="mb-3 mt-2">
              <label class="form-label">Documento (licencia/ID)</label>
              <input type="text" name="document_id" class="form-control"
                     value="{{ old('document_id', $driver->document_id ?? '') }}">
            </div>
          </div>

          <div class="tab-pane fade" id="tab-estado">
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                @php $st = old('status', $driver->status ?? 'offline'); @endphp
                @foreach(['offline','idle','busy'] as $opt)
                  <option value="{{ $opt }}" @selected($st===$opt)>{{ $opt }}</option>
                @endforeach
              </select>
            </div>
            <small class="text-muted">Los campos de última ubicación se manejan en tiempo real.</small>
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
          @php $fp = $driver->foto_path ?? null; @endphp
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
        <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</div>
