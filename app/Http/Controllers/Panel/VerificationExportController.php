<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\VerificationExportRequest;
use App\Services\Panel\VerificationExporter;
use App\Services\Panel\VerificationListQuery;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VerificationExportController extends Controller
{
    public function export(
        VerificationExportRequest $request,
        VerificationListQuery $list,
        VerificationExporter $exporter,
    ): StreamedResponse {
        $business = $request->user()->business;
        $filters = $request->validated();

        if (! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $days = Carbon::parse($filters['date_from'])->diffInDays(Carbon::parse($filters['date_to'])) + 1;

            if ($days > config('payguard.exports.max_range_days')) {
                abort(422, 'El rango de fechas no puede pasar de '.config('payguard.exports.max_range_days').' días.');
            }
        }

        $count = (clone $list->for($business, $filters))->count();

        if ($count > config('payguard.exports.max_rows')) {
            abort(422, 'Hay demasiadas verificaciones para exportar. Reduce el rango de fechas.');
        }

        $suffix = ($filters['date_from'] ?? 'inicio').'_'.($filters['date_to'] ?? 'hoy');

        if ($filters['format'] === 'pdf') {
            $content = $exporter->toPdf($business, $filters);

            return response()->streamDownload(
                function () use ($content): void {
                    echo $content;
                },
                "verificaciones-{$suffix}.pdf",
                ['Content-Type' => 'application/pdf']
            );
        }

        $path = $exporter->toXlsx($business, $filters);

        return response()->streamDownload(
            function () use ($path): void {
                readfile($path);
                @unlink($path);
            },
            "verificaciones-{$suffix}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }
}
