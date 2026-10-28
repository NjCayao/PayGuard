<?php

namespace Tests\Feature\Panel;

use App\Models\Cashier;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Tests\TestCase;

class VerificationExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_exports_xlsx_with_filtered_rows(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->for($owner->business)->for($cashier)->create([
            'status' => 'verified',
            'reason_code' => 'verified',
            'amount' => '50.00',
        ]);
        Verification::factory()->for($owner->business)->for($cashier)->create([
            'input_type' => 'text',
            'status' => 'error',
            'reason_code' => 'text_help',
        ]);
        Verification::factory()->create();

        $response = $this->actingAs($owner)->get('/api/panel/verifications/export?format=xlsx');

        $response->assertOk();
        $response->assertHeader(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        $path = tempnam(sys_get_temp_dir(), 'payguard-test-');
        file_put_contents($path, $response->streamedContent());

        $rows = [];
        $reader = new XlsxReader;
        $reader->open($path);

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
        }

        $reader->close();
        @unlink($path);

        $this->assertCount(2, $rows);
        $this->assertSame('Fecha', $rows[0][0]);
        $this->assertSame('Verificado', $rows[1][7]);
    }

    public function test_exports_pdf_report(): void
    {
        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->for($owner->business)->for($cashier)->create([
            'status' => 'verified',
            'reason_code' => 'verified',
        ]);

        $response = $this->actingAs($owner)->get('/api/panel/verifications/export?format=pdf');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->streamedContent());
    }

    public function test_export_rejects_wide_ranges_and_unknown_formats(): void
    {
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/api/panel/verifications/export?format=csv')
            ->assertStatus(422);

        $from = now('America/Lima')->subDays(40)->toDateString();
        $to = now('America/Lima')->toDateString();

        $this->actingAs($owner)->get("/api/panel/verifications/export?format=xlsx&date_from={$from}&date_to={$to}")
            ->assertStatus(422);
    }

    public function test_export_rejects_too_many_rows(): void
    {
        config()->set('payguard.exports.max_rows', 1);

        $owner = User::factory()->create();
        $cashier = Cashier::factory()->create(['business_id' => $owner->business_id]);

        Verification::factory()->count(2)->for($owner->business)->for($cashier)->create();

        $this->actingAs($owner)->get('/api/panel/verifications/export?format=xlsx')
            ->assertStatus(422);
    }
}
