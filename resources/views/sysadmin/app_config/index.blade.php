@extends('layouts.sysadmin') {{-- ajusta a tu layout sysadmin --}}
@section('content')

<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">App Remote Config</h4>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="row g-3">
    {{-- Passenger --}}
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header"><strong>Passenger</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('sysadmin.app_config.update') }}">
            @csrf
            <input type="hidden" name="app" value="passenger">

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Min versionCode</label>
                <input class="form-control" type="number" min="1" name="min_version_code"
                       value="{{ old('min_version_code', $cfgPassenger->min_version_code) }}" required>
              </div>
              <div class="col-6">
                <label class="form-label">Latest versionCode</label>
                <input class="form-control" type="number" min="1" name="latest_version_code"
                       value="{{ old('latest_version_code', $cfgPassenger->latest_version_code) }}">
              </div>

              <div class="col-12">
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="pForce" name="force_update" value="1"
                         {{ old('force_update', (int)$cfgPassenger->force_update) ? 'checked' : '' }}>
                  <label class="form-check-label" for="pForce">
                    Force update (bloquea si versionCode &lt; min)
                  </label>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label mt-2">Mensaje</label>
                <input class="form-control" type="text" name="message"
                       value="{{ old('message', $cfgPassenger->message) }}">
              </div>

              <div class="col-12">
                <label class="form-label mt-2">Play URL</label>
                <input class="form-control" type="text" name="play_url"
                       value="{{ old('play_url', $cfgPassenger->play_url) }}">
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-primary">Guardar Passenger</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Driver --}}
    <div class="col-12 col-lg-6">
      <div class="card">
        <div class="card-header"><strong>Driver</strong></div>
        <div class="card-body">
          <form method="POST" action="{{ route('sysadmin.app_config.update') }}">
            @csrf
            <input type="hidden" name="app" value="driver">

            <div class="row g-2">
              <div class="col-6">
                <label class="form-label">Min versionCode</label>
                <input class="form-control" type="number" min="1" name="min_version_code"
                       value="{{ old('min_version_code', $cfgDriver->min_version_code) }}" required>
              </div>
              <div class="col-6">
                <label class="form-label">Latest versionCode</label>
                <input class="form-control" type="number" min="1" name="latest_version_code"
                       value="{{ old('latest_version_code', $cfgDriver->latest_version_code) }}">
              </div>

              <div class="col-12">
                <div class="form-check mt-2">
                  <input class="form-check-input" type="checkbox" id="dForce" name="force_update" value="1"
                         {{ old('force_update', (int)$cfgDriver->force_update) ? 'checked' : '' }}>
                  <label class="form-check-label" for="dForce">
                    Force update (bloquea si versionCode &lt; min)
                  </label>
                </div>
              </div>

              <div class="col-12">
                <label class="form-label mt-2">Mensaje</label>
                <input class="form-control" type="text" name="message"
                       value="{{ old('message', $cfgDriver->message) }}">
              </div>

              <div class="col-12">
                <label class="form-label mt-2">Play URL</label>
                <input class="form-control" type="text" name="play_url"
                       value="{{ old('play_url', $cfgDriver->play_url) }}">
              </div>
            </div>

            <div class="d-flex justify-content-end mt-3">
              <button class="btn btn-primary">Guardar Driver</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>

@endsection
