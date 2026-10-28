<?php

namespace App\Services\Panel;

use App\Models\Business;
use App\Models\Verification;
use Dompdf\Dompdf;
use Dompdf\Options as PdfOptions;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Options as XlsxOptions;
use OpenSpout\Writer\XLSX\Writer;

class VerificationExporter
{
    private const HEADERS = [
        'Fecha', 'Cajero', 'Tipo', 'Consulta', 'Billetera', 'Monto',
        'Destinatario', 'Código', 'Resultado', 'Motivo',
    ];

    private const INPUT_LABELS = [
        'image' => 'Imagen',
        'audio' => 'Audio',
        'text' => 'Texto',
    ];

    public function __construct(private VerificationListQuery $list) {}

    public function toXlsx(Business $business, array $filters): string
    {
        $path = tempnam(storage_path('framework/cache'), 'payguard-xlsx-');

        $writer = new Writer(new XlsxOptions(tempFolder: storage_path('framework/cache')));
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(self::HEADERS));

        $this->rows($business, $filters, function (array $row) use ($writer): void {
            $writer->addRow(Row::fromValues($row));
        });

        $writer->close();

        return $path;
    }

    public function toPdf(Business $business, array $filters): string
    {
        $rows = [];

        $this->rows($business, $filters, function (array $row) use (&$rows): void {
            $rows[] = $row;
        });

        $options = new PdfOptions;
        $options->setTempDir(storage_path('framework/cache'));
        $options->setFontCache(storage_path('framework/cache'));
        $options->setIsRemoteEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('pdf.verifications', [
            'business' => $business,
            'headers' => self::HEADERS,
            'rows' => $rows,
            'filters' => $filters,
            'generatedAt' => now($business->timezone),
        ])->render());
        $pdf->setPaper('a4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    private function rows(Business $business, array $filters, callable $each): void
    {
        $timezone = $business->timezone;

        $this->list->for($business, $filters)
            ->with('cashier')
            ->orderBy('created_at')
            ->chunk(500, function ($verifications) use ($each, $timezone): void {
                foreach ($verifications as $verification) {
                    $each($this->row($verification, $timezone));
                }
            });
    }

    private function row(Verification $verification, string $timezone): array
    {
        $query = $verification->transcript ?? $verification->extracted['raw_text'] ?? '';

        return [
            $verification->created_at->copy()->setTimezone($timezone)->format('d/m/Y H:i'),
            $verification->cashier->name,
            self::INPUT_LABELS[$verification->input_type] ?? $verification->input_type,
            mb_substr((string) $query, 0, 120),
            $verification->wallet ?? '',
            $verification->amount ?? '',
            $verification->recipient_name ?? '',
            $verification->security_code ?? '',
            $this->label($verification),
            $verification->reason ?? '',
        ];
    }

    private function label(Verification $verification): string
    {
        if ($verification->reason_code === 'multiple_matches') {
            return 'Varios pagos: se pidió captura';
        }

        if ($verification->reason_code === 'already_confirmed') {
            return 'Ya confirmado antes';
        }

        if ($verification->reason_code === 'offline') {
            return $verification->status === 'pending'
                ? 'Pendiente: celular desconectado'
                : 'No confirmado: celular desconectado';
        }

        return [
            'processing' => 'En revisión',
            'pending' => 'Pendiente',
            'verified' => 'Verificado',
            'not_found' => 'No encontrado',
            'mismatch' => 'No coincide',
            'reused' => 'Reutilizada',
            'error' => 'Error',
        ][$verification->status] ?? $verification->status;
    }
}
