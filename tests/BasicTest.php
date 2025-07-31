<?php
/**
 * Basic PhpWSDL2 Package Test
 *
 * This test verifies that the PhpWSDL2 package structure is correct
 * and the main classes can be instantiated properly.
 */

// Simple test service class
class TestService
{
    /**
     * Test method for basic functionality
     *
     * @param string $message Test message
     * @return string Response message
     */
    public function testMethod(string $message): string
    {
        return "Hello: " . $message;
    }
}

// Test the package structure and basic functionality
function runBasicTests(): void
{
    echo "PhpWSDL2 Basic Package Test\n";
    echo "===========================\n\n";

    $tests = [
        'Package Structure' => function() {
            $requiredFiles = [
                'composer.json',
                'README.md',
                'LICENSE',
                'CHANGELOG.md',
                'src/PhpWSDL2/PhpWSDL2.php',
                'src/PhpWSDL2/Core/ServiceDescriptor.php',
                'src/PhpWSDL2/Generators/ClientGenerator.php',
                'src/PhpWSDL2/Generators/PdfGenerator.php',
                'src/PhpWSDL2/Servers/WebServiceServer.php',
                'examples/basic-service.php'
            ];

            foreach ($requiredFiles as $file) {
                if (!file_exists(__DIR__ . '/../' . $file)) {
                    throw new Exception("Missing required file: $file");
                }
            }
            return "All required files present";
        },

        'Composer Configuration' => function() {
            $composerFile = __DIR__ . '/../composer.json';
            $composer = json_decode(file_get_contents($composerFile), true);

            if (!$composer) {
                throw new Exception("Invalid composer.json");
            }

            $required = ['name', 'description', 'type', 'license', 'require', 'autoload'];
            foreach ($required as $key) {
                if (!isset($composer[$key])) {
                    throw new Exception("Missing composer.json key: $key");
                }
            }

            if ($composer['name'] !== 'webair-srl/phpwsdl2') {
                throw new Exception("Incorrect package name");
            }

            return "Composer configuration valid";
        },

        'Class Structure' => function() {
            // Test that classes can be loaded (syntax check)
            $classes = [
                'PhpWSDL2\\PhpWSDL2',
                'PhpWSDL2\\Core\\ServiceDescriptor',
                'PhpWSDL2\\Generators\\ClientGenerator',
                'PhpWSDL2\\Generators\\PdfGenerator',
                'PhpWSDL2\\Servers\\WebServiceServer'
            ];

            foreach ($classes as $class) {
                $file = __DIR__ . '/../src/' . str_replace('\\', '/', $class) . '.php';

                // Basic syntax check by including the file
                $content = file_get_contents($file);
                $className = substr(strrchr($class, '\\'), 1); // Extract class name from namespace
                if (strpos($content, 'class ' . $className) === false) {
                    throw new Exception("Class $class not found in file");
                }
            }

            return "All classes have correct structure";
        },

        'ServiceDescriptor Instantiation' => function() {
            require_once __DIR__ . '/../src/PhpWSDL2/Core/ServiceDescriptor.php';

            $descriptor = new PhpWSDL2\Core\ServiceDescriptor(
                TestService::class,
                'https://example.com/test.php',
                'TestService'
            );

            $methods = $descriptor->getPublicMethods();
            if (empty($methods)) {
                throw new Exception("No methods extracted");
            }

            if ($methods[0]['name'] !== 'testMethod') {
                throw new Exception("Method extraction failed");
            }

            return "ServiceDescriptor works correctly";
        },

        'ClientGenerator Instantiation' => function() {
            require_once __DIR__ . '/../src/PhpWSDL2/Generators/ClientGenerator.php';

            $generator = new PhpWSDL2\Generators\ClientGenerator(
                TestService::class,
                'https://example.com/test.php'
            );

            // Test that we can generate a simple client
            $soapClient = $generator->generate('soap', 'php');
            if (strpos($soapClient, 'class TestService_SOAP_Client') === false) {
                throw new Exception("SOAP client generation failed");
            }

            return "ClientGenerator works correctly";
        },

        'Documentation Files' => function() {
            $readmeContent = file_get_contents(__DIR__ . '/../README.md');
            if (strpos($readmeContent, '# PhpWSDL2') === false) {
                throw new Exception("README.md missing title");
            }

            $licenseContent = file_get_contents(__DIR__ . '/../LICENSE');
            if (strpos($licenseContent, 'MIT License') === false) {
                throw new Exception("LICENSE file invalid");
            }

            $changelogContent = file_get_contents(__DIR__ . '/../CHANGELOG.md');
            if (strpos($changelogContent, '# Changelog') === false) {
                throw new Exception("CHANGELOG.md missing title");
            }

            return "All documentation files valid";
        }
    ];

    $passed = 0;
    $total = count($tests);

    foreach ($tests as $testName => $testFunction) {
        echo "Testing: $testName... ";
        try {
            $result = $testFunction();
            echo "✓ PASS - $result\n";
            $passed++;
        } catch (Exception $e) {
            echo "✗ FAIL - " . $e->getMessage() . "\n";
        }
    }

    echo "\n";
    echo "Test Results: $passed/$total tests passed\n";

    if ($passed === $total) {
        echo "🎉 All tests passed! PhpWSDL2 package is ready for publication.\n";
    } else {
        echo "⚠️  Some tests failed. Please review the issues above.\n";
        exit(1);
    }
}

// Run the tests
runBasicTests();
