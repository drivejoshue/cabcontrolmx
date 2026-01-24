@extends('layouts.partner')

@section('content')
<div class="container-fluid">

  <div class="page-header d-print-none mb-3">
    <div class="row align-items-center">
      <div class="col">
        <div class="page-pretitle">Monitor</div>
        <h2 class="page-title">Inbox</h2>
        <div class="text-muted">Historial tipo inbox (sin push). Registro de eventos importantes.</div>
      </div>

      <div class="col-auto ms-auto d-flex gap-2">
        <form method="POST" action="{{ route('partner.inbox.readAll') }}">
          @csrf
          <button class="btn btn-outline-secondary">
            <i class="ti ti-checks me-1"></i> Marcar todo como leído
          </button>
        </form>
      </div>
    </div>
  </div>

  {{-- KPI + filtros --}}
  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card">
        <div class="card-body">
          <div class="text-muted small">No leídas</div>
          <div class="fs-3 fw-bold">{{ (int)($unreadCount ?? 0) }}</div>
          <div class="text-muted small">en tu inbox</div>
        </div>
      </div>
    </div>

    <div class="col-md-9">
      <div class="card">
        <div class="card-body">
          <form method="GET" action="{{ route('partner.inbox.index') }}" class="row g-2 align-items-end">

            <div class="col-md-4">
              <label class="form-label">Buscar</label>
              <input type="text"
                     class="form-control"
                     name="q"
                     value="{{ $filters['q'] ?? request('q') }}"
                     placeholder="título o contenido">
            </div>

            <div class="col-md-3">
              <label class="form-label">Tipo</label>

              @php
                $types = $types ?? collect();
                $typeVal = $filters['type'] ?? request('type');
              @endphp

              @if($types instanceof \Illuminate\Support\Collection && $types->count() > 0)
                <select class="form-select" name="type">
                  <option value="">Todos</option>
                  @foreach($types as $t)
                    <option value="{{ $t }}" {{ (string)$typeVal===(string)$t ? 'selected' : '' }}>
                      {{ $t }}
                    </option>
                  @endforeach
                </select>
              @else
                <input type="text"
                       class="form-control"
                       name="type"
                       value="{{ $typeVal }}"
                       placeholder="ride_canceled, topup_approved...">
              @endif
            </div>

            <div class="col-md-2">
              <label class="form-label">Nivel</label>
              @php $levelVal = $filters['level'] ?? request('level'); @endphp
              <select class="form-select" name="level">
                <option value="">Todos</option>
                @foreach(['info'=>'Info','success'=>'Success','warning'=>'Warning','danger'=>'Danger'] as $k=>$v)
                  <option value="{{ $k }}" {{ (string)$levelVal===(string)$k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-2">
              <label class="form-label">Estado</label>
              @php $stateVal = $filters['state'] ?? request('state', 'all'); @endphp
              <select class="form-select" name="state">
                <option value="all"    {{ $stateVal==='all' ? 'selected' : '' }}>Todas</option>
                <option value="unread" {{ $stateVal==='unread' ? 'selected' : '' }}>No leídas</option>
                <option value="read"   {{ $stateVal==='read' ? 'selected' : '' }}>Leídas</option>
              </select>
            </div>

            <div class="col-md-1 d-flex gap-2">
              <button class="btn btn-primary w-100" title="Filtrar">
                <i class="ti ti-filter"></i>
              </button>
              <a class="btn btn-outline-secondary w-100" title="Limpiar" href="{{ route('partner.inbox.index') }}">
                <i class="ti ti-x"></i>
              </a>
            </div>

          </form>
        </div>
      </div>
    </div>
  </div>

  {{-- Lista --}}
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-semibold">Mensajes</div>
      <div class="text-muted small">Más recientes primero</div>
    </div>

    <div class="list-group list-group-flush">
      @forelse($items as $n)
        @php
          $lvl = (string)($n->level ?? 'info');
          $lvlBadge = match($lvl) {
            'danger'  => 'bg-red-lt text-red',
            'warning' => 'bg-yellow-lt text-yellow',
            'success' => 'bg-green-lt text-green',
            default   => 'bg-blue-lt text-blue',
          };

          $isUnread = empty($n->read_at);
          $title = $n->title ?: '(Sin título)';
          $body  = $n->body ?: '';
          $dt    = !empty($n->created_at) ? \Carbon\Carbon::parse($n->created_at)->format('Y-m-d H:i') : '-';

          $hasEntity = !empty($n->entity_type) && !empty($n->entity_id);
        @endphp

        <div class="list-group-item">
          <div class="d-flex align-items-start gap-3">

            {{-- Indicador leído/no leído --}}
            <div class="pt-1">
              @if($isUnread)
                <span class="badge bg-red-lt text-red">No leído</span>
              @else
                <span class="badge bg-secondary-lt text-secondary">Leído</span>
              @endif
            </div>

            <div class="flex-grow-1">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div class="fw-semibold">
                  {{ $title }}
                  <span class="badge {{ $lvlBadge }} ms-2">{{ $lvl }}</span>

                  @if(!empty($n->type))
                    <span class="badge bg-secondary-lt text-secondary ms-1">{{ $n->type }}</span>
                  @endif
                </div>
                <div class="text-muted small">{{ $dt }}</div>
              </div>

              @if($body)
                <div class="text-muted mt-1">{!! nl2br(e($body)) !!}</div>
              @endif

              <div class="d-flex gap-2 mt-2 flex-wrap">
                @if($hasEntity)
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('partner.inbox.go', $n->id) }}">
                    Ver relacionado
                  </a>
                @endif

                @if($isUnread)
                  <form method="POST" action="{{ route('partner.inbox.read', $n->id) }}">
                    @csrf
                    <button class="btn btn-outline-secondary btn-sm">
                      <i class="ti ti-check me-1"></i> Marcar leído
                    </button>
                  </form>
                @endif
              </div>
            </div>

          </div>
        </div>
      @empty
        <div class="p-3 text-muted">No hay notificaciones con los filtros actuales.</div>
      @endforelse
    </div>

    <div class="card-footer">
      {{ $items->links() }}
    </div>
  </div>

</div>
@endsection
