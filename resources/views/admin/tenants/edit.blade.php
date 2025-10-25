@extends('layouts.admin')
@section('title','Tenant Settings')
@section('content')
<form class="row g-3" method="POST" action="{{ route('admin.tenants.update',$tenant) }}">
@csrf
@method('PUT')


<div class="col-12">
<div class="card shadow-sm border-0">
<div class="card-header d-flex align-items-center justify-content-between">
<h5 class="mb-0">Datos generales</h5>
<span class="badge bg-secondary">ID: {{ $tenant->id }}</span>
</div>
<div class="card-body row g-3">
<div class="col-md-6">
<label class="form-label">Nombre</label>
<input type="text" class="form-control" name="name" value="{{ old('name',$tenant->name) }}" required>
</div>
<div class="col-md-6">
<label class="form-label">Slug</label>
<input type="text" class="form-control" name="slug" value="{{ old('slug',$tenant->slug) }}" required>
</div>
<div class="col-md-4">
<label class="form-label">Timezone</label>
<input type="text" class="form-control" name="timezone" value="{{ old('timezone',$tenant->timezone) }}" required>
</div>
<div class="col-md-4">
<label class="form-label">UTC offset (min)</label>
<input type="number" class="form-control" name="utc_offset_minutes" value="{{ old('utc_offset_minutes',$tenant->utc_offset_minutes) }}">
</div>
<div class="col-md-2">
<label class="form-label">Latitud</label>
<input type="number" step="0.0000001" class="form-control" name="latitud" value="{{ old('latitud',$tenant->latitud) }}">
</div>
<div class="col-md-2">
<label class="form-label">Longitud</label>
<input type="number" step="0.0000001" class="form-control" name="longitud" value="{{ old('longitud',$tenant->longitud) }}">
</div>
<div class="col-md-4">
<label class="form-label">Marketplace</label>
<select name="allow_marketplace" class="form-select">
<option value="1" @selected($tenant->allow_marketplace)>Sí</option>
<option value="0" @selected(!$tenant->allow_marketplace)>No</option>
</select>
</div>
</div>
</div>
</div>


<div class="col-12 text-end">
<button class="btn btn-lg btn-primary shadow">Guardar</button>
</div>
</form>
@endsection