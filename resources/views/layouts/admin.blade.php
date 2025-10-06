<?php
/** Layout base: sidebar + topbar + content */
?>
<!doctype html>
<html lang="es" data-theme="default" data-layout="fluid" data-sidebar-position="left" data-sidebar-layout="default">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name') }} · @yield('title','Admin') </title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

  <!-- Tema AdminKit: servido estático desde /public -->
 <link
  id="themeStylesheet"
  rel="stylesheet"
  href="{{ asset('assets/adminkit/light.css') }}"
  data-light="{{ asset('assets/adminkit/light.css') }}"
  data-dark="{{ asset('assets/adminkit/dark.css') }}"
>

@vite(['resources/css/app.css','resources/js/app.js','resources/js/adminkit.js'])

  @stack('styles')
</head>
<body>
<div class="wrapper">
  {{-- SIDEBAR --}}
  @include('partials.sidebar_adminkit')

  <div class="main">
    {{-- TOPBAR --}}
    @include('partials.topbar_adminkit')

    <main class="content">
      <div class="container-fluid p-0">
        @yield('content')
      </div>
    </main>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row text-muted">
          <div class="col-6 text-start">
            <p class="mb-0"><strong>{{ config('app.name') }}</strong> &copy; {{ date('Y') }}</p>
          </div>
          <div class="col-6 text-end"><small class="text-muted">v1</small></div>
        </div>
      </div>
    </footer>
  </div>
</div>

@stack('scripts')
</body>
</html>
