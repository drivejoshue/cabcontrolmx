{{-- resources/views/admin/dispatch_settings/edit.blade.php --}}
@extends('layouts.admin')

@section('title','Dispatch Settings')

@section('content')
@if(session('ok'))
  <div class="alert alert-success">{{ session('ok') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

<form class="row g-3" method="POST" action="{{ route('admin.dispatch_settings.update') }}">
  @csrf
  @method('PUT')

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Auto Dispatch</h5>
      <span class="badge bg-secondary">
        Tenant: {{ auth()->user()->tenant_id ?? 'sin-tenant' }}
      </span>
      </div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Habilitado</label>
          <select name="auto_enabled" class="form-select">
            <option value="1" @selected(old('auto_enabled', (int)$row->auto_enabled) == 1)>Sí</option>
            <option value="0" @selected(old('auto_enabled', (int)$row->auto_enabled) == 0)>No</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Delay (s)</label>
          <input type="number" step="1" min="0" class="form-control"
                 name="auto_dispatch_delay_s"
                 value="{{ old('auto_dispatch_delay_s', $row->auto_dispatch_delay_s) }}" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Previsualizar N</label>
          <input type="number" step="1" min="1" class="form-control"
                 name="auto_dispatch_preview_n"
                 value="{{ old('auto_dispatch_preview_n', $row->auto_dispatch_preview_n) }}" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Radio previsualización (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="auto_dispatch_radius_km"
                 value="{{ old('auto_dispatch_radius_km', $row->auto_dispatch_radius_km) }}" />
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header"><h5 class="mb-0">Olas & Expiración</h5></div>
      <div class="card-body row g-3">
        <div class="col-md-3">
          <label class="form-label">Tamaño de ola (N)</label>
          <input type="number" step="1" min="1" class="form-control"
                 name="wave_size_n"
                 value="{{ old('wave_size_n', $row->wave_size_n) }}" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Expira oferta (seg)</label>
          <input type="number" step="1" min="5" class="form-control"
                 name="offer_expires_sec"
                 value="{{ old('offer_expires_sec', $row->offer_expires_sec) }}" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Lead time (min)</label>
          <input type="number" step="1" min="0" class="form-control"
                 name="lead_time_min"
                 value="{{ old('lead_time_min', $row->lead_time_min) }}" />
        </div>
        <div class="col-md-3">
          <label class="form-label">Auto-asignar si único</label>
          <select name="auto_assign_if_single" class="form-select">
            <option value="1" @selected(old('auto_assign_if_single', (int)$row->auto_assign_if_single) == 1)>Sí</option>
            <option value="0" @selected(old('auto_assign_if_single', (int)$row->auto_assign_if_single) == 0)>No</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card shadow-sm border-0">
      <div class="card-header"><h5 class="mb-0">Búsqueda & Bases</h5></div>
      <div class="card-body row g-3">
        <div class="col-md-4">
          <label class="form-label">Radio general (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="nearby_search_radius_km"
                 value="{{ old('nearby_search_radius_km', $row->nearby_search_radius_km) }}" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Radio de base (km)</label>
          <input type="number" step="0.01" min="0" class="form-control"
                 name="stand_radius_km"
                 value="{{ old('stand_radius_km', $row->stand_radius_km) }}" />
        </div>
        <div class="col-md-4">
          <label class="form-label">Usar Google para ETA</label>
          <select name="use_google_for_eta" class="form-select">
            <option value="1" @selected(old('use_google_for_eta', (int)$row->use_google_for_eta) == 1)>Sí</option>
            <option value="0" @selected(old('use_google_for_eta', (int)$row->use_google_for_eta) == 0)>No</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 text-end">
    <button class="btn btn-lg btn-primary shadow">Guardar cambios</button>
  </div>
</form>
@endsection
