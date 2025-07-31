<?php

declare(strict_types=1);

namespace PhpWSDL2\Generators;

use ReflectionClass;
use ReflectionMethod;

/**
 * ClientGenerator - Generates client code for multiple protocols
 *
 * This class generates client code for SOAP, JSON, JavaScript, RPC, HTTP, and REST protocols
 */
class ClientGenerator
{
    private string $serviceClass;
    private string $endpoint;
    private array $publicMethods = [];

    public function __construct(string $serviceClass, string $endpoint)
    {
        $this->serviceClass = $serviceClass;
        $this->endpoint = $endpoint;
        $this->extractMethods();
    }

    /**
     * Generate client code for specified protocol and language
     */
    public function generate(string $protocol, string $language, bool $minified = false): string
    {
        $method = 'generate' . ucfirst($protocol) . ucfirst($language) . 'Client';

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Unsupported protocol/language combination: {$protocol}/{$language}");
        }

        return $this->$method($minified);
    }

    /**
     * Extract public methods from service class
     */
    private function extractMethods(): void
    {
        $reflection = new ReflectionClass($this->serviceClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$method->isConstructor() &&
                !$method->isDestructor() &&
                $method->getDeclaringClass()->getName() === $this->serviceClass) {

                $this->publicMethods[] = [
                    'name' => $method->getName(),
                    'params' => array_map(fn($param) => $param->getName(), $method->getParameters())
                ];
            }
        }
    }

    /**
     * Generate PHP SOAP client
     */
    private function generateSoapPhpClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $php = '<?php
/**
 * ' . $serviceName . ' SOAP Client
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides access to the ' . $serviceName . ' SOAP service
 */

class ' . $serviceName . '_SOAP_Client
{
    private $client;
    private $endpoint;
    
    public function __construct($endpoint = "' . $this->endpoint . '")
    {
        $this->endpoint = $endpoint;
        $options = [
            "soap_version" => SOAP_1_1,
            "exceptions" => true,
            "trace" => 1,
            "cache_wsdl" => WSDL_CACHE_NONE
        ];
        
        try {
            $this->client = new SoapClient($endpoint . "?WSDL", $options);
        } catch (Exception $e) {
            throw new Exception("Failed to create SOAP client: " . $e->getMessage());
        }
    }
    
    /**
     * Get the SOAP client instance
     */
    public function getClient()
    {
        return $this->client;
    }
    
    /**
     * Get available methods
     */
    public function getMethods()
    {
        return $this->client->__getFunctions();
    }
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $php .= '
    /**
     * Call ' . $method['name'] . ' method
     */
    public function ' . $method['name'] . '()
    {
        $args = func_get_args();
        try {
            return $this->client->__soapCall("' . $method['name'] . '", $args);
        } catch (SoapFault $e) {
            throw new Exception("SOAP Error in ' . $method['name'] . ': " . $e->getMessage());
        }
    }
';
        }

        $php .= '}

// Example usage:
/*
try {
    $client = new ' . $serviceName . '_SOAP_Client();
    
    // Example method calls
    // $result = $client->methodName($param1, $param2);
    // echo $result;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>';

        return $php;
    }

    /**
     * Generate PHP JSON client
     */
    private function generateJsonPhpClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $php = '<?php
/**
 * ' . $serviceName . ' JSON Client
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides JSON-based access to the ' . $serviceName . ' service
 */

class ' . $serviceName . '_JSON_Client
{
    private $endpoint;
    
    public function __construct($endpoint = "' . $this->endpoint . '")
    {
        $this->endpoint = rtrim($endpoint, "/");
    }
    
    /**
     * Make a JSON request to the service
     */
    private function makeRequest($method, $params = [])
    {
        $data = [
            "call" => $method,
            "param" => $params
        ];
        
        $postData = "json=" . urlencode(json_encode($data));
        
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => $postData
            ]
        ]);
        
        $result = file_get_contents($this->endpoint, false, $context);
        
        if ($result === false) {
            throw new Exception("Failed to connect to service");
        }
        
        $decoded = json_decode($result, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $php .= '
    /**
     * Call ' . $method['name'] . ' method via JSON
     */
    public function ' . $method['name'] . '()
    {
        $args = func_get_args();
        return $this->makeRequest("' . $method['name'] . '", $args);
    }
';
        }

        $php .= '}

// Example usage:
/*
try {
    $client = new ' . $serviceName . '_JSON_Client();
    
    // Example method calls
    // $result = $client->methodName($param1, $param2);
    // echo json_encode($result);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>';

        return $php;
    }

    /**
     * Generate JavaScript JSON client
     */
    private function generateJsonJavascriptClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $js = '/**
 * ' . $serviceName . ' JSON Client (JavaScript)
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides JSON-based access to the ' . $serviceName . ' service from JavaScript
 */

var ' . $serviceName . '_JSON_Client = function(endpoint) {
    this.endpoint = endpoint || "' . $this->endpoint . '";
    
    // Make AJAX request
    this.makeRequest = function(method, params, callback, callbackData) {
        var xhr = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
        var data = "json=" + encodeURIComponent(JSON.stringify({
            call: method,
            param: params || []
        }));
        
        xhr.open("POST", this.endpoint, callback != null);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        
        if (callback) {
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var result = JSON.parse(xhr.responseText);
                            callback(result, callbackData);
                        } catch (e) {
                            callback({error: "Invalid JSON response"}, callbackData);
                        }
                    } else {
                        callback({error: "HTTP Error " + xhr.status + ": " + xhr.statusText}, callbackData);
                    }
                }
            };
            xhr.send(data);
        } else {
            xhr.send(data);
            if (xhr.status === 200) {
                return JSON.parse(xhr.responseText);
            } else {
                throw new Error("HTTP Error " + xhr.status + ": " + xhr.statusText);
            }
        }
    };
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $js .= '
    // Call ' . $method['name'] . ' method
    this.' . $method['name'] . ' = function() {
        var args = Array.prototype.slice.call(arguments);
        var callback = null;
        var callbackData = null;
        
        // Check if last arguments are callback functions
        if (args.length > 0 && typeof args[args.length - 1] === "function") {
            callback = args.pop();
            if (args.length > 0 && typeof args[args.length - 1] !== "function") {
                callbackData = args[args.length - 1];
            }
        }
        
        return this.makeRequest("' . $method['name'] . '", args, callback, callbackData);
    };
';
        }

        $js .= '};

// Example usage:
/*
var client = new ' . $serviceName . '_JSON_Client();

// Synchronous call
try {
    var result = client.methodName(param1, param2);
    console.log(result);
} catch (e) {
    console.error("Error:", e.message);
}

// Asynchronous call
client.methodName(param1, param2, function(result, data) {
    if (result.error) {
        console.error("Error:", result.error);
    } else {
        console.log("Success:", result);
    }
});
*/';

        if ($minified) {
            // Simple minification - remove comments and extra whitespace
            $js = preg_replace('/\/\*[\s\S]*?\*\//', '', $js);
            $js = preg_replace('/\/\/.*$/m', '', $js);
            $js = preg_replace('/\s+/', ' ', $js);
            $js = trim($js);
        }

        return $js;
    }

    /**
     * Generate PHP RPC client
     */
    private function generateRpcPhpClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $php = '<?php
/**
 * ' . $serviceName . ' XML-RPC Client
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides XML-RPC access to the ' . $serviceName . ' service
 */

class ' . $serviceName . '_RPC_Client
{
    private $endpoint;
    
    public function __construct($endpoint = "' . $this->endpoint . '")
    {
        $this->endpoint = $endpoint;
        
        if (!function_exists("xmlrpc_encode_request")) {
            throw new Exception("XML-RPC extension is not available");
        }
    }
    
    /**
     * Make an XML-RPC request
     */
    private function makeRequest($method, $params = [])
    {
        $request = xmlrpc_encode_request($method, $params);
        
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: text/xml\r\n",
                "content" => $request
            ]
        ]);
        
        $response = file_get_contents($this->endpoint, false, $context);
        
        if ($response === false) {
            throw new Exception("Failed to connect to service");
        }
        
        $result = xmlrpc_decode($response);
        
        if (is_array($result) && xmlrpc_is_fault($result)) {
            throw new Exception("XML-RPC Fault: " . $result["faultString"]);
        }
        
        return $result;
    }
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $php .= '
    /**
     * Call ' . $method['name'] . ' method via XML-RPC
     */
    public function ' . $method['name'] . '()
    {
        $args = func_get_args();
        return $this->makeRequest("' . $method['name'] . '", $args);
    }
';
        }

        $php .= '}

// Example usage:
/*
try {
    $client = new ' . $serviceName . '_RPC_Client();
    
    // Example method calls
    // $result = $client->methodName($param1, $param2);
    // echo $result;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>';

        return $php;
    }

    /**
     * Generate PHP HTTP client
     */
    private function generateHttpPhpClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $php = '<?php
/**
 * ' . $serviceName . ' HTTP Client
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides HTTP-based access to the ' . $serviceName . ' service
 */

class ' . $serviceName . '_HTTP_Client
{
    private $endpoint;
    
    public function __construct($endpoint = "' . $this->endpoint . '")
    {
        $this->endpoint = rtrim($endpoint, "/");
    }
    
    /**
     * Make an HTTP request
     */
    private function makeRequest($method, $params = [])
    {
        $postData = http_build_query([
            "call" => $method,
            "param" => $params
        ]);
        
        $context = stream_context_create([
            "http" => [
                "method" => "POST",
                "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
                "content" => $postData
            ]
        ]);
        
        $result = file_get_contents($this->endpoint, false, $context);
        
        if ($result === false) {
            throw new Exception("Failed to connect to service");
        }
        
        return $result;
    }
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $php .= '
    /**
     * Call ' . $method['name'] . ' method via HTTP
     */
    public function ' . $method['name'] . '()
    {
        $args = func_get_args();
        return $this->makeRequest("' . $method['name'] . '", $args);
    }
';
        }

        $php .= '}

// Example usage:
/*
try {
    $client = new ' . $serviceName . '_HTTP_Client();
    
    // Example method calls
    // $result = $client->methodName($param1, $param2);
    // echo $result;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>';

        return $php;
    }

    /**
     * Generate PHP REST client
     */
    private function generateRestPhpClient(bool $minified = false): string
    {
        $serviceName = $this->getServiceName();

        $php = '<?php
/**
 * ' . $serviceName . ' REST Client
 * Generated on ' . date('Y-m-d H:i:s') . '
 * 
 * This client provides REST-based access to the ' . $serviceName . ' service
 */

class ' . $serviceName . '_REST_Client
{
    private $endpoint;
    
    public function __construct($endpoint = "' . $this->endpoint . '")
    {
        $this->endpoint = rtrim($endpoint, "/");
    }
    
    /**
     * Make a REST request
     */
    private function makeRequest($path)
    {
        $url = $this->endpoint . "/" . ltrim($path, "/");
        
        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "Accept: application/json\r\n"
            ]
        ]);
        
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new Exception("Failed to connect to service");
        }
        
        return $result;
    }
';

        // Generate method wrappers
        foreach ($this->publicMethods as $method) {
            $methodName = $method['name'];
            $params = $method['params'];

            $php .= '
    /**
     * Call ' . $methodName . ' method via REST
     */
    public function ' . $methodName . '(';

            $paramList = [];
            foreach ($params as $param) {
                $paramList[] = '$' . $param;
            }
            $php .= implode(', ', $paramList);

            $php .= ')
    {
        $path = "' . $methodName . '";';

            foreach ($params as $param) {
                $php .= '
        $path .= "/" . urlencode($' . $param . ');';
            }

            $php .= '
        $path .= "/";
        
        return $this->makeRequest($path);
    }
';
        }

        $php .= '}

// Example usage:
/*
try {
    $client = new ' . $serviceName . '_REST_Client();
    
    // Example method calls
    // $result = $client->methodName($param1, $param2);
    // echo $result;
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
*/
?>';

        return $php;
    }

    /**
     * Get service name from class name
     */
    private function getServiceName(): string
    {
        $parts = explode('\\', $this->serviceClass);
        return end($parts);
    }
}
