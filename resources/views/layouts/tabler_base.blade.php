<!doctype html>
<html lang="es" data-bs-theme="dark" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="page-id" content="@yield('page-id','app')">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name') }} · @yield('title','Panel')</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/css/tabler.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/tabler-icons.min.css">
  @stack('styles')
</head>

<body class="layout-fluid">
  @yield('body')

  <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta21/dist/js/tabler.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => window.feather?.replace?.());
  </script>

  @stack('scripts')

  @include('partials.billing_gate_modal')
</body>
</html>
