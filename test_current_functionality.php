<?php

/**
 * Test script to verify current SOAP functionality before replacing Laminas
 */

require_once __DIR__ . '/vendor/autoload.php';

use PhpWSDL2\Servers\WebServiceServer;

// Simple test service class
class TestService
{
    /**
     * Simple test method
     * @param string $name
     * @return string
     */
    public function sayHello(string $name): string
    {
        return "Hello, " . $name . "!";
    }

    /**
     * Another test method
     * @param int $a
     * @param int $b
     * @return int
     */
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }
}

try {
    echo "Testing current SOAP functionality with the native PHP SOAP extension...\n";

    // Create WebServiceServer instance
    $server = new WebServiceServer(
        TestService::class,
        'http://localhost/test',
        'TestService'
    );

    // Test WSDL generation
    echo "1. Testing WSDL generation...\n";
    $wsdl = $server->generateWsdl();

    if (!empty($wsdl) && strpos($wsdl, '<?xml') === 0) {
        echo "   ✓ WSDL generation successful\n";
        echo "   WSDL length: " . strlen($wsdl) . " bytes\n";

        // Save WSDL for inspection
        file_put_contents(__DIR__ . '/test_wsdl.xml', $wsdl);
        echo "   WSDL saved to test_wsdl.xml\n";
    } else {
        echo "   ✗ WSDL generation failed\n";
    }

    // Test SOAP options
    echo "2. Testing SOAP options...\n";
    $options = $server->getSoapOptions();
    if (!empty($options)) {
        echo "   ✓ SOAP options retrieved successfully\n";
        echo "   Options: " . json_encode($options, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo "   ✗ SOAP options retrieval failed\n";
    }

    echo "\nCurrent functionality test completed successfully!\n";

} catch (Exception $e) {
    echo "Error testing current functionality: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
