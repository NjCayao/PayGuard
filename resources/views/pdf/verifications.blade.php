<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Verificaciones - {{ $business->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 0; }
        p.meta { color: #64748b; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
    <h1>Verificaciones - {{ $business->name }}</h1>
    <p class="meta">
        @if (empty($filters['date_from']) && empty($filters['date_to']))
            Todas las fechas.
        @else
            Del {{ $filters['date_from'] ?? 'inicio' }} al {{ $filters['date_to'] ?? 'hoy' }}.
        @endif
        Generado el {{ $generatedAt->format('d/m/Y H:i') }}.
    </p>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}">No hay verificaciones en este rango.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
