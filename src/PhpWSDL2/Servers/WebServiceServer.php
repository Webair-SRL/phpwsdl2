<?php

declare(strict_types=1);

namespace PhpWSDL2\Servers;

use Laminas\Soap\AutoDiscover;
use Laminas\Soap\Server;
use ReflectionClass;
use Exception;

/**
 * WebServiceServer - Handles incoming web service requests
 *
 * This class handles SOAP, REST, JSON, and RPC requests
 */
class WebServiceServer
{
    private string $serviceClass;
    private string $endpoint;
    private string $serviceName;
    private array $soapOptions;

    public function __construct(string $serviceClass, string $endpoint, string $serviceName)
    {
        $this->serviceClass = $serviceClass;
        $this->endpoint = $endpoint;
        $this->serviceName = $serviceName;

        $this->soapOptions = [
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]),
            'cache_wsdl' => WSDL_CACHE_NONE,
        ];
    }

    /**
     * Handle WSDL request
     */
    public function handleWsdlRequest(): void
    {
        header('Content-Type: text/xml');
        $autodiscover = new AutoDiscover();
        $autodiscover->setClass($this->serviceClass)
            ->setUri($this->endpoint)
            ->setServiceName($this->serviceName);
        $autodiscover->handle();
    }

    /**
     * Handle SOAP request
     */
    public function handleSoapRequest(): void
    {
        $wsdlFile = $this->getWsdlFile();

        if (!file_exists($wsdlFile)) {
            $this->generateWsdlFile($wsdlFile);
        }

        $server = new Server($wsdlFile, $this->soapOptions);
        $server->setClass($this->serviceClass);
        $server->handle();
    }

    /**
     * Handle REST request
     */
    public function handleRestRequest(string $pathInfo): void
    {
        $path = trim($pathInfo, "/");
        $parts = explode('/', $path);
        $method = array_shift($parts);

        if (empty($method)) {
            $this->sendError(400, 'Method name is required');
            return;
        }

        $service = new $this->serviceClass();

        if (!method_exists($service, $method)) {
            $this->sendError(404, "Method $method not found");
            return;
        }

        try {
            $result = call_user_func_array([$service, $method], $parts);
            header('Content-Type: application/json');
            echo $result;
        } catch (\ArgumentCountError $e) {
            $this->sendError(400, 'Invalid number of arguments for method ' . $method);
        } catch (Exception $e) {
            $this->sendError(500, 'Exception in method ' . $method . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle general service request (JSON, RPC, HTTP)
     */
    public function handleServiceRequest(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $input = file_get_contents('php://input');

        if ($this->isJsonRequest()) {
            $this->handleJsonRequest();
        } elseif ($this->isRpcRequest($input)) {
            $this->handleRpcRequest($input);
        } elseif ($this->isHttpRequest()) {
            $this->handleHttpRequest();
        } else {
            // Default to SOAP
            $this->handleSoapRequest();
        }
    }

    /**
     * Handle JSON request
     */
    private function handleJsonRequest(): void
    {
        $jsonData = $_POST['json'] ?? $_GET['json'] ?? '';

        if (empty($jsonData)) {
            $this->sendError(400, 'JSON data is required');
            return;
        }

        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError(400, 'Invalid JSON: ' . json_last_error_msg());
            return;
        }

        $method = $data['call'] ?? '';
        $params = $data['param'] ?? [];

        if (empty($method)) {
            $this->sendError(400, 'Method name is required');
            return;
        }

        $service = new $this->serviceClass();

        if (!method_exists($service, $method)) {
            $this->sendError(404, "Method $method not found");
            return;
        }

        try {
            $result = call_user_func_array([$service, $method], $params);
            header('Content-Type: application/json');
            echo json_encode(['result' => $result]);
        } catch (Exception $e) {
            $this->sendError(500, 'Exception in method ' . $method . ': ' . $e->getMessage());
        }
    }

    /**
     * Handle XML-RPC request
     */
    private function handleRpcRequest(string $input): void
    {
        if (!function_exists('xmlrpc_server_create')) {
            $this->sendError(500, 'XML-RPC extension is not available');
            return;
        }

        $server = xmlrpc_server_create();

        // Register all public methods
        $reflection = new ReflectionClass($this->serviceClass);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$method->isConstructor() &&
                !$method->isDestructor() &&
                $method->getDeclaringClass()->getName() === $this->serviceClass) {

                xmlrpc_server_register_method($server, $method->getName(), [$this, 'rpcMethodHandler']);
            }
        }

        $response = xmlrpc_server_call_method($server, $input, new $this->serviceClass());

        header('Content-Type: text/xml');
        echo $response;

        xmlrpc_server_destroy($server);
    }

    /**
     * Handle HTTP request
     */
    private function handleHttpRequest(): void
    {
        $method = $_POST['call'] ?? $_GET['call'] ?? '';
        $params = $_POST['param'] ?? $_GET['param'] ?? [];

        if (empty($method)) {
            $this->sendError(400, 'Method name is required');
            return;
        }

        $service = new $this->serviceClass();

        if (!method_exists($service, $method)) {
            $this->sendError(404, "Method $method not found");
            return;
        }

        try {
            $result = call_user_func_array([$service, $method], $params);
            header('Content-Type: text/plain');
            echo $result;
        } catch (Exception $e) {
            $this->sendError(500, 'Exception in method ' . $method . ': ' . $e->getMessage());
        }
    }

    /**
     * RPC method handler
     */
    public function rpcMethodHandler(string $methodName, array $params, $serviceInstance): mixed
    {
        if (!method_exists($serviceInstance, $methodName)) {
            return xmlrpc_create_fault(404, "Method $methodName not found");
        }

        try {
            return call_user_func_array([$serviceInstance, $methodName], $params);
        } catch (Exception $e) {
            return xmlrpc_create_fault(500, 'Exception in method ' . $methodName . ': ' . $e->getMessage());
        }
    }

    /**
     * Generate WSDL content
     */
    public function generateWsdl(): string
    {
        $autodiscover = new AutoDiscover();
        $autodiscover->setClass($this->serviceClass)
            ->setUri($this->endpoint)
            ->setServiceName($this->serviceName);
        return $autodiscover->toXml();
    }

    /**
     * Check if request is JSON
     */
    private function isJsonRequest(): bool
    {
        return isset($_POST['json']) || isset($_GET['json']);
    }

    /**
     * Check if request is XML-RPC
     */
    private function isRpcRequest(string $input): bool
    {
        if (empty($input)) {
            return false;
        }

        $xml = new \DOMDocument();
        if (!$xml->loadXML($input)) {
            return false;
        }

        $xpath = new \DOMXPath($xml);
        $methodCall = $xpath->query('/*');

        return $methodCall->length > 0 && $methodCall->item(0)->nodeName === 'methodCall';
    }

    /**
     * Check if request is HTTP
     */
    private function isHttpRequest(): bool
    {
        return isset($_POST['call']) || isset($_GET['call']);
    }

    /**
     * Get WSDL file path
     */
    private function getWsdlFile(): string
    {
        return sys_get_temp_dir() . '/' . $this->serviceName . '.wsdl';
    }

    /**
     * Generate WSDL file
     */
    private function generateWsdlFile(string $wsdlFile): void
    {
        $wsdlContent = $this->generateWsdl();
        file_put_contents($wsdlFile, $wsdlContent);
    }

    /**
     * Send error response
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => $message,
        ]);
    }

    /**
     * Set SOAP options
     */
    public function setSoapOptions(array $options): void
    {
        $this->soapOptions = array_merge($this->soapOptions, $options);
    }

    /**
     * Get SOAP options
     */
    public function getSoapOptions(): array
    {
        return $this->soapOptions;
    }
}
