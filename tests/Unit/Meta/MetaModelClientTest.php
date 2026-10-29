<?php

namespace Tests\Unit\Meta;

use App\Services\Meta\MetaModelClient;
use App\Services\Meta\MetaModelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Monolog\Logger as MonologLogger;
use Tests\TestCase;

class MetaModelClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('payguard.meta.base_url', 'https://api.meta.ai/v1');
        config()->set('payguard.meta.api_key', 'test-key');
        config()->set('payguard.meta.vision_model', 'muse-spark-1.3');
        config()->set('payguard.meta.vision_timeout', 5);
        config()->set('payguard.meta.vision_max_tokens', 100);
    }

    public function test_returns_decoded_json_content(): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '{"amount":"50.00"}']]],
            ]),
        ]);

        $decoded = app(MetaModelClient::class)->complete(
            [['role' => 'user', 'content' => 'hi']],
            ['type' => 'json_schema', 'json_schema' => ['name' => 'test', 'schema' => ['type' => 'object']]]
        );

        $this->assertSame(['amount' => '50.00'], $decoded);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.meta.ai/v1/chat/completions'
                && $request['model'] === 'muse-spark-1.3'
                && $request->hasHeader('Authorization', 'Bearer test-key');
        });
    }

    public function test_throws_on_http_error(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['error' => 'boom'], 500)]);

        $this->expectException(MetaModelException::class);

        app(MetaModelClient::class)->complete([], ['type' => 'json_object']);
    }

    public function test_throws_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('timeout');
        });

        $this->expectException(MetaModelException::class);

        app(MetaModelClient::class)->complete([], ['type' => 'json_object']);
    }

    public function test_throws_on_empty_content(): void
    {
        Http::fake(['api.meta.ai/*' => Http::response(['choices' => []])]);

        $this->expectException(MetaModelException::class);

        app(MetaModelClient::class)->complete([], ['type' => 'json_object']);
    }

    public function test_empty_content_reports_finish_reason_and_usage(): void
    {
        $handler = new TestHandler();
        Log::swap(new Logger(new MonologLogger('test', [$handler])));

        Http::fake(['api.meta.ai/*' => Http::response([
            'choices' => [['message' => ['content' => ''], 'finish_reason' => 'length']],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 300, 'total_tokens' => 420],
        ])]);

        try {
            app(MetaModelClient::class)->complete([], ['type' => 'json_object'], maxTokens: 300);
            $this->fail('An empty response should throw.');
        } catch (MetaModelException $e) {
            $this->assertStringContainsString('finish_reason: length', $e->getMessage());
        }

        $this->assertTrue($handler->hasWarningRecords());

        $record = $handler->getRecords()[0];
        $context = $record instanceof LogRecord ? $record->context : $record['context'];

        $this->assertSame('Meta Model API returned an empty response.', $record instanceof LogRecord ? $record->message : $record['message']);
        $this->assertSame('length', $context['finish_reason']);
        $this->assertSame(420, $context['usage']['total_tokens']);
        $this->assertSame(300, $context['max_tokens']);
    }

    public function test_throws_on_invalid_json(): void
    {
        Http::fake([
            'api.meta.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'not json{']]],
            ]),
        ]);

        $this->expectException(MetaModelException::class);

        app(MetaModelClient::class)->complete([], ['type' => 'json_object']);
    }
}
