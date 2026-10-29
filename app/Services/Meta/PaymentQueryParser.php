<?php

namespace App\Services\Meta;

class PaymentQueryParser
{
    public function __construct(private MetaModelClient $client) {}

    public function looksLikeQuery(?string $text): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        foreach (config('payguard.query.prefilter_patterns', []) as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    public function parse(?string $text): PaymentQueryData
    {
        if (! $this->looksLikeQuery($text)) {
            return PaymentQueryData::notAQuery();
        }

        $start = microtime(true);

        $decoded = $this->client->complete(
            [
                ['role' => 'system', 'content' => 'You read short Peruvian cashier questions in Spanish and return only JSON.'],
                ['role' => 'user', 'content' => $this->prompt((string) $text)],
            ],
            ['type' => 'json_schema', 'json_schema' => [
                'name' => 'payment_query',
                'schema' => $this->schema(),
            ]],
            maxTokens: (int) config('payguard.meta.query_max_tokens'),
        );

        $ms = (int) round((microtime(true) - $start) * 1000);

        return PaymentQueryData::fromModel($decoded, $decoded, $ms);
    }

    private function prompt(string $text): string
    {
        return <<<PROMPT
            A cashier asks about a digital-wallet payment. Question: "{$text}"
            Return a JSON object with:
            - is_payment_query: true only if the cashier asks whether a Yape or Plin payment arrived. Greetings, thanks and anything else are false.
            - wallet: "yape", "plin" or "unknown". An amount in soles, "S/", "lucas" or no wallet mentioned means "yape" unless Plin is named.
            - amount: the asked amount as a decimal string like "50.00", or null if there is none. Understand Spanish number words ("cincuenta" is 50, "veinte con cincuenta" is 20.50) and slang ("50 lucas" is 50).
            - payer_name: the payer first or full name if mentioned ("de Juan" is "Juan"), or null.
            - security_code: the 3-digit security code if the cashier says it ("código 944", "cód 944"), or null.
            Use null for any field you cannot read. Do not guess values.
            PROMPT;
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'is_payment_query' => ['type' => 'boolean'],
                'wallet' => ['type' => 'string'],
                'amount' => ['type' => ['string', 'number', 'null']],
                'payer_name' => ['type' => ['string', 'null']],
                'security_code' => ['type' => ['string', 'number', 'null']],
            ],
            'required' => ['is_payment_query', 'wallet', 'amount', 'payer_name', 'security_code'],
            'additionalProperties' => false,
        ];
    }
}
