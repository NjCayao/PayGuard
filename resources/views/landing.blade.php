<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="PayGuard verifica los pagos con Yape de tu negocio por WhatsApp. Cada pago, verificado.">
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <title>PayGuard — Cada pago, verificado.</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="bg-slate-100 text-slate-900 antialiased">
        <main class="mx-auto w-full max-w-2xl px-4 py-12 sm:px-6">
            <div class="text-center">
                <svg class="mx-auto" width="64" height="64" viewBox="0 0 48 48" role="img" aria-label="PayGuard">
                    <path d="M24 4 L40 10 L40 22 C40 32 33 40 24 44 C15 40 8 32 8 22 L8 10 Z" fill="#047857"/>
                    <path d="M16.5 24 L21.5 29 L31.5 18.5" fill="none" stroke="#ffffff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <p class="mt-4 text-3xl font-bold">
                    <span class="text-slate-900">Pay</span><span class="text-emerald-700">Guard</span>
                </p>
                <p class="mt-1 text-lg text-slate-600">Cada pago, verificado.</p>
            </div>

            <div class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
                <p class="text-slate-700">
                    PayGuard es el asistente de WhatsApp que protege a tu negocio de las
                    capturas falsas de Yape. Tus cajeros reenvían la captura del cliente
                    y reciben la confirmación en segundos.
                </p>
                <ol class="mt-6 space-y-4">
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white">1</span>
                        <p class="text-slate-700">Instala la app en el celular que recibe los pagos.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white">2</span>
                        <p class="text-slate-700">Registra a tus cajeros con su número de WhatsApp.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white">3</span>
                        <p class="text-slate-700">Ellos reenvían cada captura a PayGuard por WhatsApp.</p>
                    </li>
                    <li class="flex gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-sm font-bold text-white">4</span>
                        <p class="text-slate-700">Reciben al instante si el pago es real o no coincide.</p>
                    </li>
                </ol>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a
                        href="/app/iniciar-sesion"
                        class="rounded-md bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-800"
                    >
                        Entrar
                    </a>
                    <a
                        href="/app/crear-cuenta"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Crear cuenta
                    </a>
                </div>
            </div>

            <p class="mt-8 text-center text-sm text-slate-500">PayGuard · Cada pago, verificado.</p>
        </main>
    </body>
</html>
