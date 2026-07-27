<!doctype html>
<html lang="en" class="light-style" dir="ltr" data-theme="theme-default" data-assets-path="/assets/" data-template="vertical-menu-template">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <meta name="description" content="Report and track non-tariff barriers on the Ethiopia–Djibouti trade corridor. Official national NTB portal." />
  <meta name="theme-color" content="#083344" />
  <title>NTB Portal — Non-Tariff Barriers</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />

  {{-- Vuexy CSS (VERTICAL MENU TEMPLATE) --}}
  <link rel="stylesheet" href="/assets/vendor/fonts/tabler-icons.css" />
  <link rel="stylesheet" href="/assets/vendor/css/core.css" />
  <link rel="stylesheet" href="/assets/vendor/css/theme-default.css" />
  <link rel="stylesheet" href="/assets/css/demo.css" />

  {{-- Helpers must load before config/menu --}}
  <script src="/assets/vendor/js/helpers.js"></script>
  <script src="/assets/js/config.js"></script>

  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
  <div id="app"></div>

  {{-- Vuexy JS (bootstrap only — menu/main.js break Vue Router SPA navigation) --}}
  <script src="/assets/vendor/libs/popper/popper.js"></script>
  <script src="/assets/vendor/js/bootstrap.js"></script>
</body>
</html>