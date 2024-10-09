<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProductData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:product-data {--file=} {--test}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import product data from a CSV file into the database';

    /**
     * Execute the console command to import product data from a CSV file.
     *
     * This method processes the CSV file provided, validates the data, and inserts it into the database.
     * It supports a test mode where no data is actually inserted, useful for validating the import process.
     *
     * @return int Exit code indicating success or failure.
     */
    public function handle()
    {
        // Get the file path and test mode option from command-line arguments
        $filePath = $this->option('file');
        $isTestMode = $this->option('test');

        // Validate the file path
        if (!$filePath || !file_exists($filePath)) {
            $this->error('Please provide a valid CSV file path using the --file option.');
            return 1;
        }

        // Detect and convert file encoding to UTF-8
        $fileContent = file_get_contents($filePath);
        $encoding = mb_detect_encoding($fileContent, 'UTF-8, ISO-8859-1', true);
        $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);

        // Remove BOM if present
        if (substr($fileContent, 0, 3) === "\xEF\xBB\xBF") {
            $fileContent = substr($fileContent, 3);
        }

        // Normalize line endings to Unix-style
        $fileContent = str_replace(["\r\n", "\r"], "\n", $fileContent);

        // Detect delimiter and parse CSV rows
        $delimiter = $this->detectDelimiter($filePath);
        $rows = array_map(function ($line) use ($delimiter) {
            return str_getcsv($line, $delimiter);
        }, explode("\n", $fileContent));

        // Define required headers and extract headers from CSV
        $requiredHeaders = ['Product Code', 'Product Name', 'Product Description', 'Stock', 'Cost in GBP', 'Discontinued'];
        $header = array_shift($rows);

        // Validate if required headers are present
        if (array_diff($requiredHeaders, $header)) {
            $this->error('The CSV file does not have the required headers: ' . implode(', ', $requiredHeaders));
            return 1;
        }

        // Display mode information to the user
        $this->info($isTestMode ? 'Running in test mode. No data will be inserted.' : 'Importing data...');

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Temporary storage for product codes in test mode
        $processedProductCodes = [];

        // Process each row in the CSV file (without reloading from the file)
        foreach ($rows as $row) {
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }

            // Skip rows that do not match the header length
            if (count($row) !== count($header)) {
                $errors[] = "Skipped row due to column mismatch: " . implode(', ', $row);
                $skipped++;
                continue;
            }

            // Combine the header with the row data
            $data = array_combine($header, $row);

            try {
                // Clean and validate the data
                $price = $this->cleanPrice($data['Cost in GBP'] ?? '');
                $stockLevel = $this->cleanStock($data['Stock'] ?? '');
                $discontinued = strtolower(trim($data['Discontinued'] ?? '')) === 'yes';

                // Validate the length of the fields before insertion
                if (strlen($data['Product Name']) > 50) {
                    $this->warn("Skipped product: {$data['Product Name']} due to excessive length in product name.");
                    $skipped++;
                    continue;
                }

                if (strlen($data['Product Description']) > 255) {
                    $this->warn("Skipped product: {$data['Product Name']} due to excessive length in product description.");
                    $skipped++;
                    continue;
                }

                if (strlen($data['Product Code']) > 10) {
                    $this->warn("Skipped product: {$data['Product Name']} due to excessive length in product code.");
                    $skipped++;
                    continue;
                }

                // Apply business rules to determine if the product should be imported
                if ($price < 5 && $stockLevel < 10) {
                    $this->warn("Skipped product: {$data['Product Name']} due to low price and stock.");
                    $skipped++;
                    continue;
                }

                if ($price > 1000) {
                    $this->warn("Skipped product: {$data['Product Name']} due to high price.");
                    $skipped++;
                    continue;
                }

                // Check if the product code already exists to avoid duplicates
                $existingProduct = $isTestMode
                    ? in_array($data['Product Code'], $processedProductCodes)
                    : DB::table('tblProductData')
                    ->where('strProductCode', $data['Product Code'])
                    ->exists();

                if ($existingProduct) {
                    $this->warn("Skipped product due to duplicate code: {$data['Product Name']}");
                    $skipped++;
                    continue;
                }

                // Add the product code to the list of processed codes in test mode
                if ($isTestMode) {
                    $processedProductCodes[] = $data['Product Code'];
                }

                // Prepare product data for insertion into the database
                $productData = [
                    'strProductName' => $data['Product Name'],
                    'strProductDesc' => $data['Product Description'] ?? 'No description available',
                    'strProductCode' => $data['Product Code'],
                    'dtmAdded' => !empty($data['dtmAdded']) ? Carbon::parse($data['dtmAdded']) : null,
                    'dtmDiscontinued' => $discontinued ? Carbon::now() : null,
                    'intStockLevel' => $stockLevel,
                    'decPrice' => $price,
                ];

                // Insert the data if not in test mode
                if (!$isTestMode) {
                    DB::table('tblProductData')->insert($productData);
                }

                $this->info("Imported product: {$data['Product Name']}");
                $imported++;
            } catch (\Exception $e) {
                // Catch and store any errors that occur during processing
                $errors[] = "Failed to import product: {$data['Product Name']}. Error: " . $e->getMessage();
                $skipped++;
            }
        }

        // Calculate total processed items
        $processed = $imported + $skipped;

        // Report the results
        $this->info("Import complete. Processed: $processed, Imported: $imported, Skipped: $skipped");

        // Display any errors that occurred during the import
        if (!empty($errors)) {
            $this->error('The following errors occurred during the import:');
            foreach ($errors as $error) {
                $this->error($error);
            }
        }

        return 0;
    }

    /**
     * Clean the price value by removing non-numeric characters and converting it to a float.
     *
     * @param string $price
     * @return float
     */
    protected function cleanPrice($price)
    {
        // Remove non-numeric characters except dot (.) and cast to float
        $cleanedPrice = preg_replace('/[^\d.]/', '', $price);
        return is_numeric($cleanedPrice) ? (float) $cleanedPrice : 0.0;
    }

    /**
     * Clean the stock value and ensure it is a valid integer.
     *
     * @param string $stock
     * @return int
     */
    protected function cleanStock($stock)
    {
        // If the stock is not numeric, treat it as zero
        return is_numeric($stock) ? (int) $stock : 0;
    }

    /**
     * Detect the delimiter used in the provided CSV file.
     *
     * @param string $filePath
     * @return string
     */
    protected function detectDelimiter($filePath)
    {
        // Define possible delimiters for CSV files
        $delimiters = [',', ';', "\t", '|'];


        $firstLine = fgets(fopen($filePath, 'r'));

        // Initialize an array to store the count of each delimiter in the first line
        $result = [];
        foreach ($delimiters as $delimiter) {
            $result[$delimiter] = substr_count($firstLine, $delimiter);
        }

        // Return the delimiter with the highest occurrence in the first line
        return array_search(max($result), $result);
    }
}
