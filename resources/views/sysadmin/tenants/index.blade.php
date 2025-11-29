@extends('layouts.admin')

@section('title', 'SysAdmin – Tenants')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Tenants</h1>
        <a href="{{ route('sysadmin.tenants.create') }}" class="btn btn-primary">
            + Nuevo tenant
        </a>
    </div>

    <table class="table table-striped">
        <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Slug</th>
            <th>Timezone</th>
            <th>Marketplace</th>
            <th>Creado</th>
            <th></th>
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
                <td>
                    <a href="{{ route('sysadmin.tenants.edit', $tenant) }}" class="btn btn-sm btn-outline-secondary">
                        Editar
                    </a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    {{ $tenants->links() }}
</div>
@endsection
