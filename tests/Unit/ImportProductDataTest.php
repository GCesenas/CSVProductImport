<?php

namespace Tests\Unit;

use App\Console\Commands\ImportProductData;
use Tests\TestCase;
use ReflectionMethod;

class ImportProductDataTest extends TestCase
{
    private function callProtectedMethod($object, $methodName, $args = [])
    {
        $reflection = new ReflectionMethod($object, $methodName);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $args);
    }

    public function test_clean_price_with_valid_input()
    {
        $command = new ImportProductData();

        $result = $this->callProtectedMethod($command, 'cleanPrice', ['123.45']);
        $this->assertEquals(123.45, $result);

        $result = $this->callProtectedMethod($command, 'cleanPrice', ['$1,234.56']);
        $this->assertEquals(1234.56, $result);
    }

    public function test_clean_stock_with_valid_input()
    {
        $command = new ImportProductData();

        $result = $this->callProtectedMethod($command, 'cleanStock', ['50']);
        $this->assertEquals(50, $result);

        $result = $this->callProtectedMethod($command, 'cleanStock', ['not a number']);
        $this->assertEquals(0, $result);
    }

    public function test_detect_delimiter()
    {
        $command = new ImportProductData();
        $filePath = base_path('tests/Fixtures/sample.csv');

        $result = $this->callProtectedMethod($command, 'detectDelimiter', [$filePath]);
        $this->assertEquals(',', $result);
    }
}
