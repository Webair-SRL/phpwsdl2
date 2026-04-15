<?php

declare(strict_types=1);

namespace PhpWSDL2\Servers;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Exception;
use SoapServer;

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
        echo $this->generateWsdl();
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

        $server = new SoapServer($wsdlFile, $this->soapOptions);
        $server->setClass($this->serviceClass);
        $server->handle();
    }

    /**
     * Handle REST request
     */
    public function handleRestRequest(string $pathInfo): void
    {
        $path = $pathInfo;

// rimuove lo slash iniziale se presente
        if (substr($path, 0, 1) === '/') {
            $path = substr($path, 1);
        }

// rimuove lo slash finale se presente
        if (substr($path, -1) === '/') {
            $path = substr($path, 0, -1);
        }
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
        $reflection = new ReflectionClass($this->serviceClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        // Filter out constructor, destructor and inherited methods
        $serviceMethods = [];
        foreach ($methods as $method) {
            if (!$method->isConstructor() &&
                !$method->isDestructor() &&
                $method->getDeclaringClass()->getName() === $this->serviceClass) {
                $serviceMethods[] = $method;
            }
        }

        $wsdl = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $wsdl .= '<definitions xmlns="http://schemas.xmlsoap.org/wsdl/" ' .
                 'xmlns:tns="' . $this->endpoint . '" ' .
                 'xmlns:soap="http://schemas.xmlsoap.org/wsdl/soap/" ' .
                 'xmlns:xsd="http://www.w3.org/2001/XMLSchema" ' .
                 'targetNamespace="' . $this->endpoint . '">' . "\n";

        // Types section
        $wsdl .= '<types>' . "\n";
        $wsdl .= '<xsd:schema targetNamespace="' . $this->endpoint . '">' . "\n";
        $wsdl .= '</xsd:schema>' . "\n";
        $wsdl .= '</types>' . "\n";

        // Messages section
        foreach ($serviceMethods as $method) {
            $methodName = $method->getName();

            // Request message
            $wsdl .= '<message name="' . $methodName . 'Request">' . "\n";
            foreach ($method->getParameters() as $param) {
                $paramType = $this->getParameterType($param);
                $wsdl .= '<part name="' . $param->getName() . '" type="xsd:' . $paramType . '"/>' . "\n";
            }
            $wsdl .= '</message>' . "\n";

            // Response message
            $wsdl .= '<message name="' . $methodName . 'Response">' . "\n";
            $returnType = $this->getReturnType($method);
            $wsdl .= '<part name="return" type="xsd:' . $returnType . '"/>' . "\n";
            $wsdl .= '</message>' . "\n";
        }

        // PortType section
        $wsdl .= '<portType name="' . $this->serviceName . 'PortType">' . "\n";
        foreach ($serviceMethods as $method) {
            $methodName = $method->getName();
            $wsdl .= '<operation name="' . $methodName . '">' . "\n";
            $wsdl .= '<input message="tns:' . $methodName . 'Request"/>' . "\n";
            $wsdl .= '<output message="tns:' . $methodName . 'Response"/>' . "\n";
            $wsdl .= '</operation>' . "\n";
        }
        $wsdl .= '</portType>' . "\n";

        // Binding section
        $wsdl .= '<binding name="' . $this->serviceName . 'Binding" type="tns:' . $this->serviceName . 'PortType">' . "\n";
        $wsdl .= '<soap:binding style="rpc" transport="http://schemas.xmlsoap.org/soap/http"/>' . "\n";
        foreach ($serviceMethods as $method) {
            $methodName = $method->getName();
            $wsdl .= '<operation name="' . $methodName . '">' . "\n";
            $wsdl .= '<soap:operation soapAction="' . $this->endpoint . '#' . $methodName . '"/>' . "\n";
            $wsdl .= '<input><soap:body use="encoded" namespace="' . $this->endpoint . '" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/></input>' . "\n";
            $wsdl .= '<output><soap:body use="encoded" namespace="' . $this->endpoint . '" encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"/></output>' . "\n";
            $wsdl .= '</operation>' . "\n";
        }
        $wsdl .= '</binding>' . "\n";

        // Service section
        $wsdl .= '<service name="' . $this->serviceName . '">' . "\n";
        $wsdl .= '<port name="' . $this->serviceName . 'Port" binding="tns:' . $this->serviceName . 'Binding">' . "\n";
        $wsdl .= '<soap:address location="' . $this->endpoint . '"/>' . "\n";
        $wsdl .= '</port>' . "\n";
        $wsdl .= '</service>' . "\n";

        $wsdl .= '</definitions>' . "\n";

        return $wsdl;
    }

    /**
     * Get parameter type for WSDL
     */
    private function getParameterType(ReflectionParameter $param): string
    {
        $type = $param->getType();
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();
            switch ($typeName) {
                case 'int': return 'int';
                case 'float': return 'float';
                case 'bool': return 'boolean';
                case 'array': return 'string'; // Arrays as strings in SOAP
                default: return 'string';
            }
        }
        return 'string';
    }

    /**
     * Get return type for WSDL
     */
    private function getReturnType(ReflectionMethod $method): string
    {
        $type = $method->getReturnType();
        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();
            switch ($typeName) {
                case 'int': return 'int';
                case 'float': return 'float';
                case 'bool': return 'boolean';
                case 'array': return 'string'; // Arrays as strings in SOAP
                default: return 'string';
            }
        }
        return 'string';
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
