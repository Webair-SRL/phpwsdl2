<?php
/**
 * PhpWSDL2 Basic Service Example
 *
 * This example demonstrates how to create a simple web service using PhpWSDL2
 * with multiple methods and proper PHPDoc documentation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpWSDL2\PhpWSDL2;

/**
 * Example Web Service Class
 *
 * This class demonstrates various method types and documentation patterns
 * that work well with PhpWSDL2's automatic service discovery.
 */
class ExampleWebService
{
    /**
     * Add two numbers together
     *
     * @param int $a First number to add
     * @param int $b Second number to add
     * @return int The sum of both numbers
     */
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Multiply two numbers
     *
     * @param float $a First number
     * @param float $b Second number
     * @return float Product of the two numbers
     */
    public function multiply(float $a, float $b): float
    {
        return $a * $b;
    }

    /**
     * Get user information by username
     *
     * @param string $username The username to lookup
     * @return string JSON encoded user information
     */
    public function getUserInfo(string $username): string
    {
        // Simulate database lookup
        $users = [
            'john' => [
                'id' => 1,
                'username' => 'john',
                'email' => 'john@example.com',
                'name' => 'John Doe',
                'created' => '2023-01-15 10:30:00'
            ],
            'jane' => [
                'id' => 2,
                'username' => 'jane',
                'email' => 'jane@example.com',
                'name' => 'Jane Smith',
                'created' => '2023-02-20 14:45:00'
            ]
        ];

        if (isset($users[$username])) {
            return json_encode([
                'success' => true,
                'user' => $users[$username]
            ]);
        }

        return json_encode([
            'success' => false,
            'error' => 'User not found'
        ]);
    }

    /**
     * Calculate the factorial of a number
     *
     * @param int $n The number to calculate factorial for (must be >= 0)
     * @return int The factorial result
     */
    public function factorial(int $n): int
    {
        if ($n < 0) {
            throw new InvalidArgumentException('Number must be non-negative');
        }

        if ($n <= 1) {
            return 1;
        }

        $result = 1;
        for ($i = 2; $i <= $n; $i++) {
            $result *= $i;
        }

        return $result;
    }

    /**
     * Generate a greeting message
     *
     * @param string $name The name to greet
     * @param string $language Language code (en, es, fr, it)
     * @return string Localized greeting message
     */
    public function greet(string $name, string $language = 'en'): string
    {
        $greetings = [
            'en' => 'Hello',
            'es' => 'Hola',
            'fr' => 'Bonjour',
            'it' => 'Ciao'
        ];

        $greeting = $greetings[$language] ?? $greetings['en'];

        return json_encode([
            'message' => "$greeting, $name!",
            'language' => $language,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Validate an email address
     *
     * @param string $email Email address to validate
     * @return string JSON response with validation result
     */
    public function validateEmail(string $email): string
    {
        $isValid = filter_var($email, FILTER_VALIDATE_EMAIL) !== false;

        return json_encode([
            'email' => $email,
            'valid' => $isValid,
            'message' => $isValid ? 'Email is valid' : 'Email is not valid'
        ]);
    }

    /**
     * Get current server time
     *
     * @param string $format Date format (default: Y-m-d H:i:s)
     * @return string Formatted current date and time
     */
    public function getServerTime(string $format = 'Y-m-d H:i:s'): string
    {
        return date($format);
    }

    /**
     * Convert temperature between Celsius and Fahrenheit
     *
     * @param float $temperature Temperature value
     * @param string $from Source unit (C or F)
     * @param string $to Target unit (C or F)
     * @return string JSON response with converted temperature
     */
    public function convertTemperature(float $temperature, string $from, string $to): string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if (!in_array($from, ['C', 'F']) || !in_array($to, ['C', 'F'])) {
            return json_encode([
                'error' => 'Invalid temperature unit. Use C or F.'
            ]);
        }

        if ($from === $to) {
            $converted = $temperature;
        } elseif ($from === 'C' && $to === 'F') {
            $converted = ($temperature * 9/5) + 32;
        } else { // F to C
            $converted = ($temperature - 32) * 5/9;
        }

        return json_encode([
            'original' => [
                'value' => $temperature,
                'unit' => $from
            ],
            'converted' => [
                'value' => round($converted, 2),
                'unit' => $to
            ]
        ]);
    }
}

// Get the current URL for the endpoint
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script = $_SERVER['SCRIPT_NAME'] ?? '/service.php';
$endpoint = "$protocol://$host$script";

// Create and configure the PhpWSDL2 service
$service = PhpWSDL2::create(
    ExampleWebService::class,
    $endpoint,
    'ExampleWebService'
);

// Handle the incoming request
$service->handleRequest();
