<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\TrialImageRequest;
use App\Http\Requests\Panel\TrialQueryRequest;
use App\Services\Demo\DemoProtection;
use App\Services\Demo\TrialRunner;
use App\Services\Demo\TrialSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrialController extends Controller
{
    public function __construct(
        private TrialSession $session,
        private TrialRunner $runner,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $business = $request->user()->business;

        DemoProtection::ensureDemo($business);

        $key = $this->session->resolveKey($request);
        $payments = $this->session->ensurePayments($business, $key);

        return response()->json(['data' => [
            'trial_key' => $key,
            'limits' => [
                'per_hour' => (int) config('payguard.demo.trial.per_hour'),
                'max_image_mb' => (int) config('payguard.demo.trial.max_image_mb'),
            ],
            'payments' => $payments
                ->map(fn ($payment) => [
                    'amount' => $payment->amount,
                    'payer_name' => $payment->payer_name,
                ])
                ->all(),
        ]]);
    }

    public function storeImage(TrialImageRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        DemoProtection::ensureDemo($business);

        $key = $this->session->resolveKey($request);
        $file = $request->file('image');

        $result = $this->runner->runImage(
            $business,
            $key,
            (string) file_get_contents($file->getRealPath()),
            (string) $file->getMimeType(),
        );

        return response()->json(['data' => $result->toArray()]);
    }

    public function storeQuery(TrialQueryRequest $request): JsonResponse
    {
        $business = $request->user()->business;

        DemoProtection::ensureDemo($business);

        $key = $this->session->resolveKey($request);

        $result = $this->runner->runQuery($business, $key, $request->validated()['text']);

        return response()->json(['data' => $result->toArray()]);
    }

    public function sample(Request $request, string $kind): Response
    {
        $business = $request->user()->business;

        DemoProtection::ensureDemo($business);

        abort_unless(in_array($kind, ['valid', 'fake'], true), 404);

        $key = $this->session->resolveKey($request);

        $png = $this->runner->sample($business, $key, $kind);
        $name = $kind === 'fake' ? 'muestra-falsa.png' : 'muestra-valida.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }
}
