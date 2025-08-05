<?php

declare(strict_types=1);

namespace PhpWSDL2;

use PhpWSDL2\Core\ServiceDescriptor;
use PhpWSDL2\Generators\ClientGenerator;
use PhpWSDL2\Generators\PdfGenerator;
use PhpWSDL2\Servers\WebServiceServer;
use ReflectionClass;

/**
 * PhpWSDL2 - Modern PHP WSDL/SOAP WebService Library
 *
 * Main class that provides service description, client generation,
 * and server functionality for multiple protocols (SOAP, JSON, REST, RPC, HTTP)
 */
class PhpWSDL2
{
    private string $serviceClass;
    private string $endpoint;
    private string $serviceName;
    private ServiceDescriptor $descriptor;
    private ClientGenerator $clientGenerator;
    private PdfGenerator $pdfGenerator;
    private WebServiceServer $server;

    /**
     * Initialize PhpWSDL2 with service configuration
     *
     * @param string $serviceClass The service class name
     * @param string $endpoint The service endpoint URL
     * @param string $serviceName Optional service name (defaults to class name)
     */
    public function __construct(string $serviceClass, string $endpoint, string $serviceName = '')
    {
        $this->serviceClass = $serviceClass;
        $this->endpoint = rtrim($endpoint, '/');
        $this->serviceName = $serviceName ?: $this->extractServiceName($serviceClass);

        $this->descriptor = new ServiceDescriptor($serviceClass, $this->endpoint, $this->serviceName);
        $this->clientGenerator = new ClientGenerator($serviceClass, $this->endpoint);
        $this->pdfGenerator = new PdfGenerator($serviceClass, $this->endpoint, $this->serviceName);
        $this->server = new WebServiceServer($serviceClass, $this->endpoint, $this->serviceName);
    }

    /**
     * Handle incoming web service requests
     * Automatically detects request type and responds appropriately
     */
    public function handleRequest(): void
    {
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH);

        $serviceName = trim($this->serviceName, '/'); // es: PIMWS
        $pathInfo = '';

        // Trova "/$serviceName/" all'interno della URI
        $pos = stripos($path, '/' . $serviceName . '/');
        if ($pos !== false) {
            $pathInfo = substr($path, $pos + strlen('/' . $serviceName));
            $pathInfo = '/' . ltrim($pathInfo, '/');
        } else {
            // fallback se path è esattamente /PIMWS (senza trailing slash)
            if (rtrim($path, '/') === '/' . $serviceName) {
                $pathInfo = '/';
            }
        }

        // Handle WSDL requests
        if (stripos($queryString, 'wsdl') !== false) {
            $this->server->handleWsdlRequest();
            return;
        }

        // Handle PDF download
        if (stripos($queryString, 'pdf') !== false) {
            $this->handlePdfDownload();
            return;
        }

        // Handle client downloads
        if ($this->handleClientDownload($queryString)) {
            return;
        }

        // Handle REST requests
        if (!empty($pathInfo) && $pathInfo != '/') {
            $this->server->handleRestRequest($pathInfo);
            return;
        }

        // Handle SOAP/JSON/RPC requests
        if (!empty($_POST) || !empty(file_get_contents('php://input'))) {
            $this->server->handleServiceRequest();
            return;
        }

        // Default: show service descriptor
        if (empty($queryString)) {
            $this->showServiceDescriptor();
            return;
        }

        // Fallback to SOAP server
        $this->server->handleSoapRequest();
    }

    /**
     * Generate and display the service descriptor HTML
     */
    public function showServiceDescriptor(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->descriptor->generateHtml();
    }

    /**
     * Generate service descriptor HTML
     */
    public function getServiceDescriptor(): string
    {
        return $this->descriptor->generateHtml();
    }

    /**
     * Handle PDF download request
     */
    private function handlePdfDownload(): void
    {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $this->serviceName . '_API_Documentation.pdf"');
        echo $this->pdfGenerator->generate();
    }

    /**
     * Handle client download requests
     */
    private function handleClientDownload(string $queryString): bool
    {
        $queryLower = strtolower($queryString);

        if (strpos($queryLower, 'phpsoapclient') !== false) {
            $this->downloadClient('soap', 'php');
            return true;
        }

        if (strpos($queryLower, 'phpjsonclient') !== false) {
            $this->downloadClient('json', 'php');
            return true;
        }

        if (strpos($queryLower, 'jsjsonclient') !== false) {
            $minified = strpos($queryString, 'min') !== false;
            $this->downloadClient('json', 'javascript', $minified);
            return true;
        }

        if (strpos($queryLower, 'phprpcclient') !== false) {
            $this->downloadClient('rpc', 'php');
            return true;
        }

        if (strpos($queryLower, 'phphttpclient') !== false) {
            $this->downloadClient('http', 'php');
            return true;
        }

        if (strpos($queryLower, 'phprestclient') !== false) {
            $this->downloadClient('rest', 'php');
            return true;
        }

        return false;
    }

    /**
     * Download a specific client type
     */
    private function downloadClient(string $protocol, string $language, bool $minified = false): void
    {
        $client = $this->clientGenerator->generate($protocol, $language, $minified);

        $extension = $language === 'javascript' ? 'js' : 'php';
        $suffix = $minified ? '.min' : '';
        $filename = $this->serviceName . '_' . strtoupper($protocol) . '_Client' . $suffix . '.' . $extension;

        $contentType = $language === 'javascript' ? 'application/javascript' : 'application/x-php';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $client;
    }

    /**
     * Get the service class name
     */
    public function getServiceClass(): string
    {
        return $this->serviceClass;
    }

    /**
     * Get the service endpoint
     */
    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Get the service name
     */
    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    /**
     * Extract service name from class name
     */
    private function extractServiceName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }

    /**
     * Get available service methods
     */
    public function getServiceMethods(): array
    {
        return $this->descriptor->getPublicMethods();
    }

    /**
     * Generate WSDL content
     */
    public function generateWsdl(): string
    {
        return $this->server->generateWsdl();
    }

    /**
     * Create a simple factory method for quick setup
     */
    public static function create(string $serviceClass, string $endpoint, string $serviceName = ''): self
    {
        return new self($serviceClass, $endpoint, $serviceName);
    }
}
