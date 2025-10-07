@extends('layouts.admin')
@section('title','Vehículo')

@push('styles')
<style>
  .thumb-md{width:96px;height:64px;object-fit:cover}
  .avatar-sm{width:40px;height:40px;object-fit:cover;border-radius:50%}
</style>
@endpush

@section('content')
<div class="container-fluid p-0">

  {{-- Header + acciones --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-3">
      <div class="rounded border bg-white d-flex align-items-center justify-content-center" style="width:60px;height:60px;">
        <i data-feather="truck"></i>
      </div>
      <div>
        <h3 class="mb-0">
          Económico #{{ $v->economico }}
          @php $activo = (int)($v->active ?? 0); @endphp
          <span class="badge {{ $activo ? 'bg-success' : 'bg-secondary' }} align-middle">
            {{ $activo ? 'Activo' : 'Inactivo' }}
          </span>
        </h3>
        <div class="text-muted">
          Placa: {{ $v->plate ?: '—' }} · {{ $v->brand ?: '—' }} {{ $v->model ?: '' }} {{ $v->year ? '('.$v->year.')' : '' }}
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a href="{{ route('vehicles.edit', ['id'=>$v->id]) }}" class="btn btn-primary">
        <i data-feather="edit-2"></i> Editar
      </a>
      <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">
        <i data-feather="arrow-left"></i> Volver
      </a>
    </div>
  </div>

  {{-- Pills --}}
  <ul class="nav nav-pills mb-3">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#tab-detalles">
        <i data-feather="info"></i> Detalles
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-foto">
        <i data-feather="image"></i> Imagen
      </a>
    </li>
  </ul>

  <div class="tab-content">

    {{-- Detalles --}}
    <div class="tab-pane fade show active" id="tab-detalles">
      <div class="row g-3">

        {{-- Ficha técnica --}}
        <div class="col-12 col-xl-7">
          <div class="card h-100">
            <div class="card-header"><strong>Ficha técnica</strong></div>
            <div class="card-body">
              <dl class="row mb-0">
                <dt class="col-sm-4">Económico</dt>
                <dd class="col-sm-8">#{{ $v->economico }}</dd>

                <dt class="col-sm-4">Placa</dt>
                <dd class="col-sm-8">{{ $v->plate ?: '—' }}</dd>

                <dt class="col-sm-4">Marca</dt>
                <dd class="col-sm-8">{{ $v->brand ?: '—' }}</dd>

                <dt class="col-sm-4">Modelo</dt>
                <dd class="col-sm-8">{{ $v->model ?: '—' }}</dd>

                <dt class="col-sm-4">Color</dt>
                <dd class="col-sm-8">{{ $v->color ?: '—' }}</dd>

                <dt class="col-sm-4">Año</dt>
                <dd class="col-sm-8">{{ $v->year ?: '—' }}</dd>

                <dt class="col-sm-4">Capacidad</dt>
                <dd class="col-sm-8">{{ $v->capacity ?: '—' }}</dd>

                <dt class="col-sm-4">Póliza / ID</dt>
                <dd class="col-sm-8">{{ $v->policy_id ?: '—' }}</dd>

                <dt class="col-sm-4">Estado</dt>
                <dd class="col-sm-8">
                  @if($activo)<span class="badge bg-success">Activo</span>
                  @else <span class="badge bg-secondary">Inactivo</span>@endif
                </dd>

                <dt class="col-sm-4">Creado</dt>
                <dd class="col-sm-8">{{ $v->created_at ?? '—' }}</dd>

                <dt class="col-sm-4">Actualizado</dt>
                <dd class="col-sm-8">{{ $v->updated_at ?? '—' }}</dd>
              </dl>
            </div>
          </div>
        </div>

        {{-- Resumen + mini foto --}}
        <div class="col-12 col-xl-5">
          <div class="card h-100">
            <div class="card-header"><strong>Resumen</strong></div>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                @php
                  $foto = null;
                  if (!empty($v->foto_path))    { $foto = asset('storage/'.$v->foto_path); }
                  elseif (!empty($v->photo_url)){ $foto = $v->photo_url; }
                @endphp
                <div class="flex-shrink-0">
                  @if($foto)
                    <img src="{{ $foto }}" class="rounded border thumb-md" alt="Foto vehículo">
                  @else
                    <div class="rounded border bg-light d-flex align-items-center justify-content-center thumb-md">
                      <span class="text-muted small">Sin foto</span>
                    </div>
                  @endif
                </div>
                <div class="flex-grow-1">
                  <div class="mb-1"><strong>#{{ $v->economico }}</strong></div>
                  <div class="text-muted small">
                    {{ $v->brand ?: '—' }} {{ $v->model ?: '' }} {{ $v->year ? '('.$v->year.')' : '' }}
                  </div>
                  <div class="text-muted small">Placa: {{ $v->plate ?: '—' }}</div>
                </div>
              </div>

              <div class="alert alert-secondary mb-0">
                <div class="fw-semibold mb-1">¿Cambiar chofer?</div>
                <div class="small mb-2">
                  La asignación se realiza <strong>desde la ficha del conductor</strong> (Drivers → Ver → “Asignar vehículo”).
                </div>
                <a href="{{ route('drivers.index') }}" class="btn btn-sm btn-outline-primary">
                  Ir a Conductores
                </a>
              </div>

              @if($v->foto_path)
                <div class="mt-3">
                  <div class="small text-muted">Imagen almacenada en:</div>
                  <code class="small">storage/{{ $v->foto_path }}</code>
                </div>
              @endif
            </div>
          </div>
        </div>

        {{-- Choferes asignados (vigentes) --}}
        <div class="col-12">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <strong>Choferes asignados actualmente</strong>
            </div>
            <div class="card-body">
              @if(($currentDrivers ?? collect())->count())
                <div class="table-responsive">
                  <table class="table table-sm align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>Chofer</th>
                        <th>Teléfono</th>
                        <th>Desde</th>
                        <th></th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($currentDrivers as $cd)
                        <tr>
                          <td>
                            <div class="d-flex align-items-center gap-2">
                              @php $df = $cd->foto_path ? asset('storage/'.$cd->foto_path) : null; @endphp
                              @if($df)
                                <img src="{{ $df }}" class="avatar-sm border" alt="">
                              @else
                                <div class="avatar-sm border d-flex align-items-center justify-content-center bg-light">
                                  <i data-feather="user"></i>
                                </div>
                              @endif
                              <a href="{{ route('drivers.show',$cd->driver_id) }}" class="text-decoration-none">
                                {{ $cd->name }}
                              </a>
                            </div>
                          </td>
                          <td>{{ $cd->phone ?? '—' }}</td>
                          <td>{{ $cd->start_at }}</td>
                          <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('drivers.show',$cd->driver_id) }}">
                              Ver conductor
                            </a>
                          </td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              @else
                <div class="alert alert-info mb-0">Este vehículo no tiene chofer asignado actualmente.</div>
              @endif
            </div>
          </div>
        </div>

        {{-- Histórico --}}
        <div class="col-12">
          <div class="card">
            <div class="card-header"><strong>Histórico de asignaciones</strong></div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                  <thead class="table-light">
                    <tr><th>Chofer</th><th>Teléfono</th><th>Desde</th><th>Hasta</th></tr>
                  </thead>
                  <tbody>
                    @forelse($assignments ?? [] as $a)
                      <tr>
                        <td>
                          <a class="text-decoration-none" href="{{ route('drivers.show',$a->driver_id) }}">
                            {{ $a->name }}
                          </a>
                        </td>
                        <td>{{ $a->phone ?? '—' }}</td>
                        <td>{{ $a->start_at }}</td>
                        <td>{{ $a->end_at ?? 'Vigente' }}</td>
                      </tr>
                    @empty
                      <tr><td colspan="4" class="text-center text-muted">Sin registros</td></tr>
                    @endforelse
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- row --}}
    </div> {{-- tab-detalles --}}

    {{-- Imagen --}}
    <div class="tab-pane fade" id="tab-foto">
      <div class="card">
        <div class="card-header"><strong>Imagen del vehículo</strong></div>
        <div class="card-body">
          @php
            $foto = null;
            if (!empty($v->foto_path))    { $foto = asset('storage/'.$v->foto_path); }
            elseif (!empty($v->photo_url)){ $foto = $v->photo_url; }
          @endphp

          @if($foto)
            <div class="text-center">
              <img src="{{ $foto }}" class="img-fluid rounded border" style="max-height:440px;object-fit:contain;">
            </div>
          @else
            <div class="alert alert-info mb-0">Este vehículo no tiene imagen cargada todavía.</div>
          @endif

          <div class="mt-3 d-flex gap-2">
            <a href="{{ route('vehicles.edit', ['id'=>$v->id]) }}" class="btn btn-primary">
              <i data-feather="upload"></i> Subir/Reemplazar foto
            </a>
            <a href="{{ route('vehicles.index') }}" class="btn btn-outline-secondary">
              <i data-feather="arrow-left"></i> Volver al listado
            </a>
          </div>
        </div>
      </div>
    </div> {{-- tab-foto --}}

  </div> {{-- tab-content --}}
</div>
@endsection
