@extends('layouts.sysadmin')

@section('title', 'SysAdmin – Tenants')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Tenants</h1>
        <a href="{{ route('sysadmin.tenants.create') }}" class="btn btn-primary">
            + Nuevo tenant
        </a>
    </div>

    <table class="table table-striped align-middle">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Slug</th>
            <th>Timezone</th>
            <th>Marketplace</th>
            <th>Creado</th>
            <th class="text-end">Acciones</th>
        </tr>
        </thead>
        <tbody>
        @foreach($tenants as $tenant)
            <tr>
                <td>{{ $tenant->id }}</td>
                <td>{{ $tenant->name }}</td>
                <td>{{ $tenant->slug }}</td>
                <td>{{ $tenant->timezone }}</td>
                <td>
                    @if($tenant->allow_marketplace)
                        <span class="badge text-bg-success">Sí</span>
                    @else
                        <span class="badge text-bg-secondary">No</span>
                    @endif
                </td>
                <td>{{ $tenant->created_at }}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm" role="group">
                        <a href="{{ route('sysadmin.tenants.edit', $tenant) }}" class="btn btn-outline-secondary">
                            Editar
                        </a>
                        <a href="{{ route('sysadmin.tenants.billing.show', $tenant) }}" class="btn btn-outline-primary">
                            Billing
                        </a>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $tenants->links() }}
</div>
@endsection
