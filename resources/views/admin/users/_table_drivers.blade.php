<div class="table-responsive">
  <table class="table card-table table-vcenter">
    <thead>
      <tr>
        <th>Usuario</th>
        <th>Email</th>
        <th>Conductor</th>
        <th>Estado</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $u)
        <tr>
          <td class="fw-medium">{{ $u->name }}</td>
          <td class="text-muted">{{ $u->email }}</td>
          <td>
            @if(!empty($u->driver_id))
              <a href="{{ route('admin.drivers.show',['id'=>$u->driver_id]) }}" class="text-decoration-none">
                #{{ $u->driver_id }} {{ $u->driver_name ?: 'Conductor' }}
              </a>
              <div class="text-muted small">
                status: {{ $u->driver_status ?? '—' }} · active: {{ isset($u->driver_active) ? (int)$u->driver_active : '—' }}
              </div>
            @else
              <span class="text-muted">Sin registro en drivers</span>
            @endif
          </td>
          <td>
            @if((int)$u->active===1)
              <span class="badge bg-success-lt">Activo</span>
            @else
              <span class="badge bg-danger-lt">Desactivado</span>
            @endif
          </td>
          <td class="text-end">
            <div class="btn-list flex-nowrap justify-content-end">
              <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit',$u->id) }}">
                <i class="ti ti-pencil me-1"></i> Editar
              </a>
              @if((int)$u->active===1)
                <form method="POST" action="{{ route('admin.users.deactivate',$u->id) }}" class="d-inline">@csrf
                  <button class="btn btn-sm btn-outline-danger"><i class="ti ti-user-off me-1"></i> Desactivar</button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.users.reactivate',$u->id) }}" class="d-inline">@csrf
                  <button class="btn btn-sm btn-outline-success"><i class="ti ti-user-check me-1"></i> Reactivar</button>
                </form>
              @endif
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="text-center text-muted py-4">Sin resultados.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>
