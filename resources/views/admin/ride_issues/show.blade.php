@extends('admin.layout')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Reporte #{{ $issue->id }}</h1>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">{{ $issue->title }}</h5>
                    <p class="mb-1">
                        <strong>Categoría:</strong> {{ $issue->category }}<br>
                        <strong>Severidad:</strong> {{ $issue->severity }}<br>
                        <strong>Estado:</strong> {{ $issue->status }}<br>
                        <strong>Creado:</strong> {{ $issue->created_at }}<br>
                        @if($issue->resolved_at)
                            <strong>Resuelto:</strong> {{ $issue->resolved_at }}<br>
                        @endif
                    </p>

                    @if($issue->description)
                        <hr>
                        <p>{{ $issue->description }}</p>
                    @endif
                </div>
            </div>

            {{-- FUTURO: aquí podríamos listar notas internas, histórico, etc. --}}
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Viaje</div>
                <div class="card-body">
                    @if($issue->ride)
                        <p>
                            <strong>ID viaje:</strong> {{ $issue->ride->id }}<br>
                            <strong>Origen:</strong> {{ $issue->ride->origin_label }}<br>
                            <strong>Destino:</strong> {{ $issue->ride->dest_label }}<br>
                            <strong>Estado viaje:</strong> {{ $issue->ride->status }}
                        </p>
                    @else
                        <p>Sin información de viaje.</p>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Pasajero</div>
                <div class="card-body">
                    <p>
                        <strong>Nombre:</strong> {{ optional($issue->passenger)->name ?? '-' }}<br>
                        <strong>Teléfono:</strong> {{ optional($issue->passenger)->phone ?? '-' }}
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Conductor</div>
                <div class="card-body">
                    <p>
                        <strong>Nombre:</strong> {{ optional($issue->driver)->name ?? '-' }}
                    </p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">Actualizar estado</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.ride_issues.update_status', $issue) }}">
                        @csrf

                        <div class="mb-2">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-control">
                                @foreach (['open' => 'Abierto', 'in_review' => 'En revisión', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'] as $key => $label)
                                    <option value="{{ $key }}" @selected($issue->status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-2">
                            <label class="form-label">Nota interna (opcional)</label>
                            <textarea name="internal_note" class="form-control" rows="3"></textarea>
                        </div>

                        <button class="btn btn-primary w-100">Guardar</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection
