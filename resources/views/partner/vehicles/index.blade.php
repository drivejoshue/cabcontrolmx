@extends('layouts.partner')
@section('title','Vehículos')

@push('styles')
<style>
  .veh-thumb{
    width:88px;height:58px;border-radius:10px;
    object-fit:cover; border:1px solid rgba(0,0,0,.12);
    background: rgba(0,0,0,.03);
    flex:0 0 auto;
  }
  [data-bs-theme="dark"] .veh-thumb{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.05);
  }
  .veh-fallback{
    width:88px;height:58px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    border:1px solid rgba(0,0,0,.12);
    background: rgba(0,0,0,.04);
    color:#6c757d;
    flex:0 0 auto;
  }
  [data-bs-theme="dark"] .veh-fallback{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
    color: rgba(255,255,255,.70);
  }
  .veh-main{ line-height:1.15; }
  .veh-sub{ font-size:.82rem; color: var(--tblr-muted, #6c757d); }
  .chip{
    display:inline-block;
    padding:.2rem .55rem;
    border-radius:999px;
    border:1px solid rgba(0,0,0,.12);
    background: rgba(0,0,0,.03);
    font-size:.78rem;
    margin-right:.25rem;
  }
  [data-bs-theme="dark"] .chip{
    border-color: rgba(255,255,255,.12);
    background: rgba(255,255,255,.06);
  }
</style>
@endpush

@section('content')
<div class="container-fluid">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Vehículos</h3>
      <div class="text-muted">Aquí solo ves y administras tus vehículos.</div>
    </div>
    <a href="{{ route('partner.vehicles.create') }}" class="btn btn-primary">Nuevo vehículo</a>
  </div>

  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="GET" action="{{ route('partner.vehicles.index') }}">
        <div class="col-md-10">
          <input class="form-control" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por económico, placa, marca o modelo">
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
              <th>Vehículo</th>
              <th>Detalles</th>
              <th>Verificación</th>
              <th>Activo</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($vehicles as $v)
              @php
                $vs = $v->verification_status ?? 'pending';
                $vsBadge = $vs==='verified' ? 'success' : ($vs==='rejected' ? 'danger' : 'warning');

                $foto = null;
                if (!empty($v->foto_path)) {
                  $foto = asset('storage/'.$v->foto_path);
                } elseif (!empty($v->photo_url)) {
                  $foto = $v->photo_url;
                }

                $title = trim('Eco '.$v->economico);
                $brandModel = trim(($v->brand ?? '').' '.($v->model ?? ''));
                $year = $v->year ? (string)$v->year : null;

                $type = !empty($v->type) ? strtoupper($v->type) : null;
                $cap  = !empty($v->capacity) ? (int)$v->capacity : null;

                $plate = $v->plate ?? '—';
              @endphp
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    @if($foto)
                      <img src="{{ $foto }}" class="veh-thumb" alt="Foto vehículo">
                    @else
                      <div class="veh-fallback">
                        <span class="small">Sin foto</span>
                      </div>
                    @endif

                    <div class="veh-main">
                      <div class="fw-semibold">
                        <a class="text-decoration-none" href="{{ route('partner.vehicles.show',$v->id) }}">
                          {{ $title }}
                        </a>
                      </div>
                      <div class="veh-sub">
                        Placa: <b>{{ $plate }}</b>
                      </div>
                    </div>
                  </div>
                </td>

                <td class="text-muted small">
                  <div class="mb-1">
                    {{ $brandModel ?: '—' }}
                    @if($year) <span class="text-muted">({{ $year }})</span> @endif
                  </div>

                  <div>
                    @if($type)<span class="chip">Tipo: {{ $type }}</span>@endif
                    @if($cap)<span class="chip">Cap: {{ $cap }}</span>@endif
                    @if(!empty($v->color))<span class="chip">Color: {{ $v->color }}</span>@endif
                  </div>
                </td>

                <td>
                  <span class="badge bg-{{ $vsBadge }} text-uppercase">{{ $vs }}</span>
                </td>

                <td>
                  <span class="badge bg-{{ ((int)($v->active ?? 1)===1) ? 'success' : 'secondary' }}">
                    {{ ((int)($v->active ?? 1)===1) ? 'Sí' : 'No' }}
                  </span>
                </td>

                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary" href="{{ route('partner.vehicles.show',$v->id) }}">Ver</a>
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('partner.vehicles.edit',$v->id) }}">Editar</a>
                  <a class="btn btn-sm btn-outline-dark" href="{{ route('partner.vehicles.documents.index',$v->id) }}">Documentos</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center py-4 text-muted">Sin vehículos aún.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(method_exists($vehicles,'links'))
      <div class="card-footer">{{ $vehicles->links() }}</div>
    @endif
  </div>
</div>
@endsection
