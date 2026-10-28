<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Jobs\UpdateOwnerAlertStatus;
use App\Services\WhatsApp\IncomingMessageParser;
use App\Services\WhatsApp\SignatureVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('payguard.whatsapp.verify_token');

        if ($expected !== ''
            && $request->query('hub_mode') === 'subscribe'
            && hash_equals($expected, (string) $request->query('hub_verify_token'))) {
            return response((string) $request->query('hub_challenge'), 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request, SignatureVerifier $verifier, IncomingMessageParser $parser): Response
    {
        if (! $verifier->valid($request->getContent(), $request->header('X-Hub-Signature-256'))) {
            return response('Invalid signature', 403);
        }

        $payload = $request->json()->all() ?? [];

        foreach ($parser->parse($payload) as $message) {
            ProcessIncomingMessage::dispatch($message);
        }

        foreach ($parser->parseStatuses($payload) as $update) {
            UpdateOwnerAlertStatus::dispatch($update);
        }

        return response('OK', 200);
    }
}
