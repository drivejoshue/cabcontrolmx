@extends('layouts.sysadmin')

@section('content')
<div class="container-xl">
  @if(session('ok')) <div class="alert alert-success">{{ session('ok') }}</div> @endif

  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Topup Manual #{{ $topup->id }}</h3>
    </div>
    <div class="card-body">
      <dl class="row mb-0">
        <dt class="col-sm-3">Tenant</dt><dd class="col-sm-9">{{ $topup->tenant_id }}</dd>
        <dt class="col-sm-3">Partner</dt><dd class="col-sm-9">{{ $topup->partner_id }}</dd>
        <dt class="col-sm-3">Monto</dt><dd class="col-sm-9">{{ number_format((float)$topup->amount, 2) }} {{ $topup->currency }}</dd>
        <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ $topup->status }}</dd>
        <dt class="col-sm-3">Ext Ref</dt><dd class="col-sm-9">{{ $topup->external_reference }}</dd>
        <dt class="col-sm-3">Reviewed</dt><dd class="col-sm-9">{{ $topup->reviewed_at }} (by {{ $topup->reviewed_by }})</dd>
        <dt class="col-sm-3">Credited</dt><dd class="col-sm-9">{{ $topup->credited_at }}</dd>
      </dl>
    </div>
  </div>
</div>
@endsection
