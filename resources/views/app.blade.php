<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="turnstile-site-key" content="{{ config('payguard.registration.turnstile_site_key') }}">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <title>PayGuard</title>
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    </head>
    <body class="bg-slate-100 text-slate-900 antialiased">
        <div id="app"></div>
    </body>
</html>
