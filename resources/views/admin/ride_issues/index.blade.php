@extends('layout.admin') {{-- ajusta al layout que uses --}}

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Reportes de viajes</h1>

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-3">
            <select name="status" class="form-control">
                <option value="">Todos los estados</option>
                @foreach (['open' => 'Abierto', 'in_review' => 'En revisión', 'resolved' => 'Resuelto', 'closed' => 'Cerrado'] as $key => $label)
                    <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <select name="category" class="form-control">
                <option value="">Todas las categorías</option>
                @foreach ([
                    'safety'          => 'Seguridad',
                    'overcharge'      => 'Cobro incorrecto',
                    'route'           => 'Ruta',
                    'driver_behavior' => 'Conductor',
                    'vehicle'         => 'Vehículo',
                    'lost_item'       => 'Objeto perdido',
                    'payment'         => 'Pago',
                    'app_problem'     => 'App',
                    'other'           => 'Otro',
                ] as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filtrar</button>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Viaje</th>
                        <th>Categoría</th>
                        <th>Severidad</th>
                        <th>Estado</th>
                        <th>Pasajero</th>
                        <th>Conductor</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($issues as $issue)
                    <tr>
                        <td>#{{ $issue->id }}</td>
                        <td>{{ $issue->created_at }}</td>
                        <td>
                            @if ($issue->ride)
                                ID {{ $issue->ride->id }}<br>
                                {{ $issue->ride->origin_label }} → {{ $issue->ride->dest_label }}
                            @endif
                        </td>
                        <td>{{ $issue->category }}</td>
                        <td>{{ $issue->severity }}</td>
                        <td>{{ $issue->status }}</td>
                        <td>{{ optional($issue->passenger)->name ?? '-' }}</td>
                        <td>{{ optional($issue->driver)->name ?? '-' }}</td>
                        <td>
                            <a href="{{ route('admin.ride_issues.show', $issue) }}" class="btn btn-sm btn-outline-primary">
                                Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center p-4">No hay reportes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($issues instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="card-footer">
                {{ $issues->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
