@extends('layouts.admin')

@section('title', 'SysAdmin – Editar tenant')

@section('content')
<div class="container-fluid">
    <h1 class="mb-4">Editar tenant #{{ $tenant->id }} – {{ $tenant->name }}</h1>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('sysadmin.tenants.update', $tenant) }}">
        @csrf
        {{-- si usas PUT/POST según tu ruta --}}
        {{-- en tus rutas pusiste POST en update, si cambias a PUT agrega: @method('PUT') --}}
        @method('POST')

        {{-- mismos campos que en create, precargados con $tenant --}}
        <!-- ... igual que arriba pero con value="{{ old('campo', $tenant->campo) }}" -->

    </form>
</div>
@endsection
