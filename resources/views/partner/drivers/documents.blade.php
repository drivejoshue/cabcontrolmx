
@extends('layouts.partner')
@section('title','Documentos del conductor')

@section('content')
<div class="container-fluid">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h3 class="mb-0">Documentos del conductor</h3>
      <div class="text-muted">
        Conductor: <b>{{ $driver->name }}</b>
        @if($driver->phone)
          · Tel: <b>{{ $driver->phone }}</b>
        @endif
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="{{ route('partner.drivers.show', ['id'=>$driver->id]) }}" class="btn btn-outline-secondary">
        Volver a ficha
      </a>
    </div>
  </div>

  @if(session('ok')) 
    <div class="alert alert-success">{{ session('ok') }}</div> 
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)
          <li>{{ $e }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @php
    $vs      = $driver->verification_status ?? 'pending';
    $vsBadge = $vs === 'verified' 
        ? 'success' 
        : ($vs === 'rejected' ? 'danger' : 'warning');

    // docs aprobados por tipo (último aprobado)
    $approvedByType = collect($docs)
      ->where('status','approved')
      ->groupBy('type')
      ->map(fn($g)=>$g->sortByDesc('id')->first());

    $requiredOk = collect($required)->every(fn($t)=>isset($approvedByType[$t]));
  @endphp

  {{-- Bloque de estado de verificación --}}
  <div class="card mb-3">
    <div class="card-body">
      <div class="d-flex flex-wrap align-items-center gap-2">
        <div><b>Status verificación:</b></div>
        <span class="badge bg-{{ $vsBadge }}">{{ $vs }}</span>

        @if(!empty($driver->verification_notes))
          <span class="text-danger ms-2">
            <b>Notas:</b> {{ $driver->verification_notes }}
          </span>
        @endif
      </div>

      <hr class="my-3">

      <div class="mb-2"><b>Requeridos:</b></div>
      <div class="d-flex flex-wrap gap-2">
        @foreach($required as $rt)
          @php $ok = isset($approvedByType[$rt]); @endphp
          <span class="badge bg-{{ $ok ? 'success' : 'secondary' }}">
            {{ $types[$rt] ?? $rt }} {{ $ok ? '✓' : '—' }}
          </span>
        @endforeach
      </div>

      @if(!$requiredOk)
        <div class="alert alert-info mt-3 mb-0">
          Sube los documentos requeridos. Cuando estén completos y correctos,
          Pasaran por un proso de validacion y verificacion de identidad para seguridad de los usuarios.
        </div>
      @else
        <div class="alert alert-success mt-3 mb-0">
          Ya cuentas con todos los documentos requeridos. Están listos para revisión de Orbana.
        </div>
      @endif

      <div class="mt-3 mb-0 small text-muted">
        Para <b>INE</b> y <b>Licencia</b> sube un PDF o imagen donde se vea
        <b>frente y reverso</b>. Puedes tomar foto a ambos lados y generar un PDF
        o una imagen compuesta.
      </div>
    </div>
  </div>

  {{-- Formulario de subida --}}
  <div class="card mb-3">
    <div class="card-header"><b>Subir documento</b></div>
    <div class="card-body">
      <form method="POST" action="{{ route('partner.drivers.documents.store',$driver->id) }}" enctype="multipart/form-data">
        @csrf
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="type" class="form-select" required>
              @foreach($types as $k=>$label)
                <option value="{{ $k }}">{{ $label }}</option>
              @endforeach
            </select>
            <small class="text-muted d-block">
              Requeridos: Licencia, INE, Selfie.
            </small>
          </div>
          <div class="col-md-5">
            <label class="form-label">Archivo</label>
            <input type="file" name="file" class="form-control" required>
            <small class="text-muted">Máx. 6 MB. Imagen o PDF.</small>
          </div>
          <div class="col-md-3">
            <button class="btn btn-primary w-100">Subir</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla de documentos --}}
  <div class="card">
    <div class="card-header"><b>Documentos cargados</b></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-striped mb-0 align-middle">
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Status</th>
              <th>Notas revisión</th>
              <th class="text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            @forelse($docs as $d)
              @php
                $badge = $d->status === 'approved'
                    ? 'success'
                    : ($d->status === 'rejected' ? 'danger' : 'warning');
              @endphp
              <tr>
                <td>{{ $types[$d->type] ?? $d->type }}</td>
                <td><span class="badge bg-{{ $badge }}">{{ $d->status }}</span></td>
                <td class="text-muted">{{ $d->review_notes }}</td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-secondary"
                     href="{{ route('partner.drivers.documents.download',$d->id) }}">
                    Descargar
                  </a>

                  @if($d->status !== 'approved')
                    <form class="d-inline" method="POST" action="{{ route('partner.drivers.documents.delete',$d->id) }}">
                      @csrf
                      <button class="btn btn-sm btn-outline-danger"
                              onclick="return confirm('¿Eliminar documento?')">
                        Borrar
                      </button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center py-3 text-muted">
                  Sin documentos aún.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
@endsection
