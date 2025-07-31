# PhpWSDL2

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.0-blue.svg)](https://php.net/)

PhpWSDL2 is a modern PHP library for creating WSDL/SOAP web services with support for multiple protocols including SOAP, JSON, REST, XML-RPC, and HTTP. It provides automatic service description generation, client code generation, and comprehensive documentation features.

## Features

- 🚀 **Multiple Protocol Support**: SOAP, JSON, REST, XML-RPC, HTTP
- 📝 **Automatic Documentation**: HTML service descriptors and PDF generation
- 🔧 **Client Code Generation**: Generate clients for PHP, JavaScript, and more
- 🎯 **Modern PHP**: Built for PHP 8.0+ with strict typing
- 📦 **Framework Independent**: Works with any PHP application
- 🔍 **Reflection-Based**: Automatic service discovery using PHP reflection
- 🎨 **Professional Output**: Clean, well-formatted documentation and clients

## Installation

Install PhpWSDL2 via Composer:

```bash
composer require webair-srl/phpwsdl2
```

## Quick Start

### 1. Create Your Service Class

```php
<?php

class MyWebService
{
    /**
     * Add two numbers
     * 
     * @param int $a First number
     * @param int $b Second number
     * @return int Sum of the numbers
     */
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    /**
     * Get user information
     * 
     * @param string $username Username to lookup
     * @return string JSON encoded user data
     */
    public function getUser(string $username): string
    {
        return json_encode([
            'username' => $username,
            'email' => $username . '@example.com',
            'created' => date('Y-m-d H:i:s')
        ]);
    }
}
```

### 2. Create Your Service Endpoint

```php
<?php
// service.php

require_once 'vendor/autoload.php';

use PhpWSDL2\PhpWSDL2;

// Create and configure the service
$service = PhpWSDL2::create(
    MyWebService::class,
    'https://example.com/service.php',
    'MyWebService'
);

// Handle all incoming requests
$service->handleRequest();
```

### 3. Access Your Service

- **Service Description**: `https://example.com/service.php`
- **WSDL**: `https://example.com/service.php?WSDL`
- **PDF Documentation**: `https://example.com/service.php?PDF`
- **REST Call**: `https://example.com/service.php/add/5/3/`

## Client Downloads

PhpWSDL2 automatically generates client code for multiple languages and protocols:

| Client Type | URL Parameter | Description |
|-------------|---------------|-------------|
| PHP SOAP | `?PHPSOAPCLIENT` | PHP SOAP client class |
| PHP JSON | `?PHPJSONCLIENT` | PHP JSON client class |
| JavaScript JSON | `?JSJSONCLIENT` | JavaScript JSON client |
| JavaScript (Minified) | `?JSJSONCLIENT&min` | Minified JavaScript client |
| PHP XML-RPC | `?PHPRPCCLIENT` | PHP XML-RPC client class |
| PHP HTTP | `?PHPHTTPCLIENT` | PHP HTTP client class |
| PHP REST | `?PHPRESTCLIENT` | PHP REST client class |

## Usage Examples

### Using Generated PHP SOAP Client

```php
<?php
require_once 'MyWebService_SOAP_Client.php';

try {
    $client = new MyWebService_SOAP_Client();
    $result = $client->add(5, 3);
    echo "Result: " . $result; // Result: 8
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Using Generated JavaScript Client

```javascript
var client = new MyWebService_JSON_Client();

// Synchronous call
try {
    var result = client.add(5, 3);
    console.log("Result:", result);
} catch (e) {
    console.error("Error:", e.message);
}

// Asynchronous call
client.add(5, 3, function(result, data) {
    if (result.error) {
        console.error("Error:", result.error);
    } else {
        console.log("Success:", result);
    }
});
```

### REST API Calls

```bash
# GET request
curl "https://example.com/service.php/add/5/3/"

# Returns JSON response
{"result": 8}
```

### JSON API Calls

```bash
# POST request with JSON data
curl -X POST "https://example.com/service.php" \
     -d "json={\"call\":\"add\",\"param\":[5,3]}"

# Returns JSON response
{"result": 8}
```

## Advanced Configuration

### Custom SOAP Options

```php
<?php
use PhpWSDL2\PhpWSDL2;

$service = new PhpWSDL2(MyWebService::class, 'https://example.com/service.php');

// Configure SOAP options
$service->getServer()->setSoapOptions([
    'cache_wsdl' => WSDL_CACHE_MEMORY,
    'soap_version' => SOAP_1_2
]);

$service->handleRequest();
```

### Programmatic Usage

```php
<?php
use PhpWSDL2\PhpWSDL2;

$service = new PhpWSDL2(MyWebService::class, 'https://example.com/service.php');

// Generate service descriptor HTML
$html = $service->getServiceDescriptor();

// Generate WSDL
$wsdl = $service->generateWsdl();

// Get service methods
$methods = $service->getServiceMethods();

// Generate specific client
$soapClient = $service->getClientGenerator()->generate('soap', 'php');
```

## Documentation Features

### HTML Service Descriptor

PhpWSDL2 automatically generates professional HTML documentation that includes:

- Service endpoint and WSDL URLs
- Complete method listings with parameters and return types
- REST endpoint URLs for each method
- Download links for all client types
- Professional styling similar to phpWSDL

### PDF Documentation

Generate PDF documentation with:
- Complete service description
- Method signatures and documentation
- Parameter details and return types
- REST endpoint information

## Protocol Support

### SOAP
- Full WSDL 1.1 and 2.0 support
- Automatic WSDL generation
- Complex type support via reflection

### REST
- Clean URL routing (`/method/param1/param2/`)
- JSON response format
- GET request support

### JSON
- POST parameter: `json={"call":"method","param":["arg1","arg2"]}`
- Structured JSON responses
- Error handling with proper HTTP status codes

### XML-RPC
- Standard XML-RPC protocol support
- Automatic method registration
- Fault handling

### HTTP
- Simple POST/GET parameter support
- `call` parameter for method name
- `param` array for arguments

## Requirements

- PHP 8.0 or higher
- ext-soap
- ext-json
- ext-reflection
- laminas/laminas-soap
- tecnickcom/tcpdf (for PDF generation)

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

PhpWSDL2 is developed by [tQuadra](https://www.tquadra.it) and is inspired by the original phpWSDL library, modernized for PHP 8+ with additional features and improved architecture.

## Support

- 📖 [Documentation](https://github.com/tquadra/phpwsdl2/wiki)
- 🐛 [Issue Tracker](https://github.com/tquadra/phpwsdl2/issues)
- 💬 [Discussions](https://github.com/tquadra/phpwsdl2/discussions)
