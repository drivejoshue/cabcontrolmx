{{-- resources/views/admin/drivers/_fields.blade.php --}}
@csrf
@isset($method) @method($method) @endisset

<div class="row g-3">
  <div class="col-12 col-lg-8">
    <div class="card">
      <div class="card-body">
        <ul class="nav nav-pills mb-3">
          <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tab-datos">Datos</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-estado">Estado</a></li>
          <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tab-cuenta">Cuenta (usuario)</a></li>
        </ul>

        <div class="tab-content">
          {{-- ====== DATOS ====== --}}
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
                <label class="form-label">Email (contacto)</label>
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

          {{-- ====== ESTADO ====== --}}
          <div class="tab-pane fade" id="tab-estado">
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">Status</label>
                @php $st = old('status', $driver->status ?? 'offline'); @endphp
                <select name="status" class="form-select">
                  @foreach(['offline','idle','busy'] as $opt)
                    <option value="{{ $opt }}" @selected($st===$opt)>{{ $opt }}</option>
                  @endforeach
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label d-block">Activo</label>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input" type="checkbox" name="active" value="1"
                         @checked(old('active', ($driver->active ?? 1))==1)>
                  <label class="form-check-label">Habilitado para operar</label>
                </div>
              </div>
            </div>
            <small class="text-muted d-block mt-2">Ubicación en tiempo real se actualiza desde la app.</small>
          </div>

          {{-- ====== CUENTA (USUARIO) ====== --}}
          <div class="tab-pane fade" id="tab-cuenta">
            @php
              $hasUser = !empty($driver->user_id ?? null);
              $linkedEmail = $driver->user_email ?? null; // pásalo desde el Controller si quieres mostrarlo
            @endphp

            @if($hasUser)
              <div class="alert alert-info d-flex justify-content-between align-items-center">
                <div>
                  <div class="fw-semibold mb-1">Usuario vinculado</div>
                  <div class="small m-0">{{ $linkedEmail ?? '—' }}</div>
                </div>
              </div>

              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="chkChangePwd" name="change_password" value="1">
                <label class="form-check-label" for="chkChangePwd">Cambiar contraseña</label>
              </div>

              <div id="boxChangePwd" class="row g-2" style="display:none">
                <div class="col-md-6">
                  <label class="form-label">Nueva contraseña</label>
                  <input type="password" name="new_password" class="form-control" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Confirmar contraseña</label>
                  <input type="password" name="new_password_confirmation" class="form-control" autocomplete="new-password">
                </div>
              </div>
            @else
              <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="chkCreateUser" name="create_user" value="1" {{ old('create_user')?'checked':'' }}>
                <label class="form-check-label" for="chkCreateUser">Crear usuario para este conductor</label>
              </div>

              <div id="boxCreateUser" style="display: {{ old('create_user')?'block':'none' }}">
                <div class="mb-2">
                  <label class="form-label">Email de acceso</label>
                  <input type="email" name="user_email" class="form-control"
                         value="{{ old('user_email', $driver->email ?? '') }}">
                </div>
                <div class="row g-2">
                  <div class="col-md-6">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="user_password" class="form-control" autocomplete="new-password">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Confirmar contraseña</label>
                    <input type="password" name="user_password_confirmation" class="form-control" autocomplete="new-password">
                  </div>
                </div>
                <small class="text-muted">
                  Se creará en <code>users</code> y se enlazará a este conductor con rol <b>driver</b>.
                </small>
              </div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- FOTO --}}
  <div class="col-12 col-lg-4">
    <div class="card h-100">
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
    </div>
  </div>
</div>

{{-- FOOTER: UN SOLO SUBMIT (lo dejamos aquí, dentro del mismo form) --}}
<div class="card mt-3">
  <div class="card-body d-flex justify-content-between align-items-center">
    <div class="text-muted small">
      @if(!empty($driver->id))
        Editando #{{ $driver->id }}
      @else
        Nuevo conductor
      @endif
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('drivers.index') }}" class="btn btn-outline-secondary">Cancelar</a>
      <button class="btn btn-primary" type="submit">Guardar</button>
    </div>
  </div>
</div>
@push('scripts')
<script>
  // Toggle crear usuario
  (function(){
    const chkCreate = document.getElementById('chkCreateUser');
    const boxCreate = document.getElementById('boxCreateUser');
    if (chkCreate && boxCreate) {
      chkCreate.addEventListener('change', () => {
        boxCreate.style.display = chkCreate.checked ? 'block' : 'none';
      });
    }
  })();

  // Toggle cambiar contraseña
  (function(){
    const chkPwd = document.getElementById('chkChangePwd');
    const boxPwd = document.getElementById('boxChangePwd');
    if (chkPwd && boxPwd) {
      chkPwd.addEventListener('change', () => {
        boxPwd.style.display = chkPwd.checked ? 'block' : 'none';
      });
    }
  })();
</script>
@endpush
