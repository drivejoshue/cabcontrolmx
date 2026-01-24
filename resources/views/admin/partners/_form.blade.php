@php
  $isEdit = isset($partner) && $partner->exists;
@endphp

<div class="row g-3">

  {{-- ALERTS / instrucciones --}}
  <div class="col-12">
    @if(!$isEdit)
      <div class="alert alert-info">
        <div class="d-flex">
          <div class="me-2"><i class="ti ti-info-circle"></i></div>
          <div>
            <div class="fw-semibold">Alta de Partner</div>
            <div class="small text-muted">
              <b>Code</b> y <b>Slug</b> se generan automáticamente. Al guardar, se crea también el <b>usuario Owner</b>.
              <br>
              <b>Siguiente paso:</b> subir documentos del partner para verificación de Orbana.
            </div>
          </div>
        </div>
      </div>
    @else
      <div class="alert alert-warning">
        <div class="d-flex">
          <div class="me-2"><i class="ti ti-alert-triangle"></i></div>
          <div>
            <div class="fw-semibold">Edición</div>
            <div class="small text-muted">
              Aquí solo modificas datos del partner. La documentación se gestiona en el apartado de <b>Documentos</b>.
            </div>
          </div>
        </div>
      </div>
    @endif
  </div>

  {{-- DATOS --}}
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="ti ti-id-badge-2 me-1"></i> Datos</div>
      </div>

      <div class="card-body">

        <div class="row g-2">
          {{-- CODE (auto) --}}
          <div class="col-md-4">
            <label class="form-label">Code</label>

            @if(!$isEdit)
              <input type="text" class="form-control" value="Automático" disabled>
              <input type="hidden" name="code" value="">
              <small class="text-muted">Se asigna al crear.</small>
            @else
              <input type="text" name="code" class="form-control"
                     value="{{ old('code', $partner->code) }}" required>
              @error('code')<div class="text-danger small">{{ $message }}</div>@enderror
            @endif
          </div>

          {{-- SLUG (auto) --}}
          <div class="col-md-4">
            <label class="form-label">Slug</label>

            @if(!$isEdit)
              <input type="text" class="form-control" value="Automático" disabled>
              <input type="hidden" name="slug" value="">
              <small class="text-muted">Se genera desde el nombre.</small>
            @else
              <input type="text" name="slug" class="form-control"
                     value="{{ old('slug', $partner->slug) }}"
                     placeholder="ej. mi-partner">
              @error('slug')<div class="text-danger small">{{ $message }}</div>@enderror
            @endif
          </div>

          {{-- TIPO --}}
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="kind" class="form-select" required>
              @php $kind = old('kind', $partner->kind ?: 'partner'); @endphp
              <option value="partner" @selected($kind==='partner')>Partner</option>
              <option value="recruiter" @selected($kind==='recruiter')>Recruiter</option>
              <option value="affiliate" @selected($kind==='affiliate')>Affiliate</option>
            </select>
            @error('kind')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="name" class="form-control"
                 value="{{ old('name', $partner->name) }}" required>
          @error('name')<div class="text-danger small">{{ $message }}</div>@enderror
          @if(!$isEdit)
            <small class="text-muted">De este nombre se genera el slug automáticamente.</small>
          @endif
        </div>

        <div class="row g-2 mt-2">
          <div class="col-md-6">
            <label class="form-label">Estado</label>
            @php $st = old('status', $partner->status ?: 'active'); @endphp
            <select name="status" class="form-select" required>
              <option value="active" @selected($st==='active')>Activo</option>
              <option value="suspended" @selected($st==='suspended')>Suspendido</option>
              <option value="closed" @selected($st==='closed')>Cerrado</option>
            </select>
            @error('status')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

          <div class="col-md-6 d-flex align-items-end">
            @php $ia = old('is_active', $partner->is_active ?? 1); @endphp
            <label class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked((int)$ia===1)>
              <span class="form-check-label">Habilitado</span>
            </label>
          </div>
        </div>

        <hr class="my-3">

        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label">Contacto</label>
            <input type="text" name="contact_name" class="form-control"
                   value="{{ old('contact_name', $partner->contact_name) }}">
            @error('contact_name')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label class="form-label">Teléfono</label>
            <input type="text" name="contact_phone" class="form-control"
                   value="{{ old('contact_phone', $partner->contact_phone) }}">
            @error('contact_phone')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="contact_email" class="form-control"
                   value="{{ old('contact_email', $partner->contact_email) }}">
            @error('contact_email')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>
        </div>

        <div class="mt-3">
          <label class="form-label">Notas</label>
          <textarea name="notes" class="form-control" rows="3">{{ old('notes', $partner->notes) }}</textarea>
          @error('notes')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

      </div>
    </div>
  </div>

  {{-- OWNER (solo crear nuevo) --}}
  <div class="col-lg-5">
    @if(!$isEdit)
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="ti ti-user-plus me-1"></i> Acceso inicial (Owner)</div>
        </div>
        <div class="card-body">

          <div class="alert alert-secondary">
            <div class="small">
              Este usuario será el <b>Owner</b> del partner y podrá entrar al portal del partner para:
              <b>recargar</b>, ver wallet y operar su panel.
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label">Nombre del Owner</label>
            <input type="text" name="owner_name" class="form-control" value="{{ old('owner_name') }}" required>
            @error('owner_name')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

          <div class="mb-2">
            <label class="form-label">Email del Owner</label>
            <input type="email" name="owner_email" class="form-control" value="{{ old('owner_email') }}" required>
            @error('owner_email')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

          <div class="mb-0">
            <label class="form-label">Password del Owner</label>
            <input type="password" name="owner_password" class="form-control" required>
            <small class="text-muted">Mínimo 6 caracteres.</small>
            @error('owner_password')<div class="text-danger small">{{ $message }}</div>@enderror
          </div>

        </div>
      </div>
    @else
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="ti ti-info-circle me-1"></i> Nota</div>
        </div>
        <div class="card-body small text-muted">
          El Owner y los miembros del partner se administran desde el portal del partner.
        </div>
      </div>
    @endif
  </div>

</div>
