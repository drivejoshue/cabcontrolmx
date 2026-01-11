<div class="table-responsive">
  <table class="table card-table table-vcenter">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Estado</th>
        <th class="text-end">Acciones</th>
      </tr>
    </thead>
    <tbody>
      @forelse($items as $u)
        @php $isMe = auth()->check() && (int)auth()->id() === (int)$u->id; @endphp
        <tr>
          <td class="fw-medium">
            {{ $u->name }}
            @if($isMe)<span class="badge bg-secondary-lt ms-2">Tú</span>@endif
          </td>
          <td class="text-muted">{{ $u->email }}</td>
          <td>
            @php $role = $u->role ?: 'none'; @endphp
            @if($role==='admin')
              <span class="badge bg-indigo-lt"><i class="ti ti-shield me-1"></i>Admin</span>
            @elseif($role==='dispatcher')
              <span class="badge bg-azure-lt"><i class="ti ti-headset me-1"></i>Dispatcher</span>
            @elseif($role==='driver')
              <span class="badge bg-lime-lt"><i class="ti ti-steering-wheel me-1"></i>Driver</span>
            @else
              <span class="badge bg-secondary-lt">None</span>
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
              <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $u) }}">
                <i class="ti ti-pencil me-1"></i> Editar
              </a>

              @if((int)$u->active===1)
                <form method="POST" action="{{ route('admin.users.deactivate', $u) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-danger" @disabled($isMe)>
                    <i class="ti ti-user-off me-1"></i> Desactivar
                  </button>
                </form>
              @else
                <form method="POST" action="{{ route('admin.users.reactivate', $u) }}" class="d-inline">
                  @csrf
                  <button class="btn btn-sm btn-outline-success">
                    <i class="ti ti-user-check me-1"></i> Reactivar
                  </button>
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
