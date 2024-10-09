<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class ImportProductDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tblProductData')->truncate();
    }    

    public function test_it_imports_products_from_valid_csv()
    {
        // Given: A sample CSV file path
        $filePath = base_path('tests/Fixtures/sample.csv');

        // When: Running the import command in test mode
        Artisan::call('import:product-data', [
            '--file' => $filePath,
            '--test' => true
        ]);

        // Then: Assert that the output includes successful imports
        $this->assertStringContainsString('Import complete.', Artisan::output());
    }

    public function test_it_skips_invalid_rows()
    {
        // Given: A CSV with invalid rows
        $filePath = base_path('tests/Fixtures/invalid_rows.csv');

        // When: Running the command
        Artisan::call('import:product-data', [
            '--file' => $filePath,
            '--test' => true
        ]);

        // Then: Assert that the skipped message is shown
        $this->assertStringContainsString('Skipped row due to column mismatch', Artisan::output());
    }

    public function test_it_handles_duplicates()
    {
        // Given: A CSV with a duplicate row
        $filePath = base_path('tests/Fixtures/sample_with_duplicates.csv');

        // When: Running the command
        Artisan::call('import:product-data', [
            '--file' => $filePath,
            '--test' => true
        ]);

        // Then: Assert that the command output indicates a duplicate product was skipped
        $this->assertStringContainsString('Skipped product due to duplicate code', Artisan::output());
    }
}
