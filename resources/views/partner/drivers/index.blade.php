@extends('layouts.partner')
@section('title','Conductores')

@push('styles')
<style>
  .avatar-xs{
    width:38px;height:38px;border-radius:50%;
    object-fit:cover; flex:0 0 auto;
  }
  .avatar-fallback{
    width:38px;height:38px;border-radius:50%;
    display:inline-flex;align-items:center;justify-content:center;
    font-weight:700; font-size:.85rem;
    border:1px solid rgba(0,0,0,.12);
    background: rgba(0,0,0,.04);
  }
  [data-bs-theme="dark"] .avatar-fallback{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
  }
  .cell-main{ line-height:1.1; }
  .cell-sub{ font-size:.82rem; color: var(--tblr-muted, #6c757d); }
</style>
@endpush

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Conductores</h3>
      <div class="text-muted">Aquí solo ves y administras tus conductores.</div>
    </div>
    <a href="{{ route('partner.drivers.create') }}" class="btn btn-primary">Nuevo conductor</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="GET" action="{{ route('partner.drivers.index') }}">
        <div class="col-md-10">
          <input class="form-control" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por nombre, teléfono o email">
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-secondary">Buscar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Conductor</th>
              <th>Contacto</th>
              <th>Status</th>
              <th>Verificación</th>
              <th>Activo</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($drivers as $d)
              @php
                $vs = $d->verification_status ?? 'pending';
                $vsBadge = $vs==='verified' ? 'success' : ($vs==='rejected' ? 'danger' : 'warning');

                $name = $d->name ?? '—';
                $initials = collect(preg_split('/\s+/', trim($name)))
                  ->filter()
                  ->take(2)
                  ->map(fn($p) => mb_strtoupper(mb_substr($p,0,1)))
                  ->join('');

                // Foto driver: usa el campo que sí tengas
                $avatar = null;
                if (!empty($d->foto_path)) {
                  $avatar = asset('storage/'.$d->foto_path);
                } elseif (!empty($d->photo_url)) {
                  $avatar = $d->photo_url;
                }
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    @if($avatar)
                      <img src="{{ $avatar }}" class="avatar-xs" alt="Foto conductor">
                    @else
                      <span class="avatar-fallback" title="{{ $name }}">{{ $initials ?: '—' }}</span>
                    @endif

                    <div class="cell-main">
                      <div class="fw-semibold">
                        <a class="text-decoration-none" href="{{ route('partner.drivers.show',$d->id) }}">
                          {{ $name }}
                        </a>
                      </div>
                      <div class="cell-sub">ID: {{ $d->id }}</div>
                    </div>
                  </div>
                </td>

                <td class="text-muted small">
                  {{ $d->phone ?? '—' }}<br>
                  {{ $d->email ?? '—' }}
                </td>

                <td>
                  <span class="badge bg-secondary text-uppercase">{{ $d->status ?? 'offline' }}</span>
                </td>

                <td>
                  <span class="badge bg-{{ $vsBadge }} text-uppercase">{{ $vs }}</span>
                </td>

                <td>
                  <span class="badge bg-{{ ((int)($d->active ?? 1)===1) ? 'success' : 'secondary' }}">
                    {{ ((int)($d->active ?? 1)===1) ? 'Sí' : 'No' }}
                  </span>
                </td>

                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="{{ route('partner.drivers.show',$d->id) }}">Ver</a>
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('partner.drivers.edit',$d->id) }}">Editar</a>
                  <a class="btn btn-sm btn-outline-dark" href="{{ route('partner.drivers.documents.index',$d->id) }}">Documentos</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center py-4 text-muted">Sin conductores aún.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($drivers,'links'))
      <div class="card-footer">
        {{ $drivers->links() }}
      </div>
    @endif
  </div>
</div>
@endsection
