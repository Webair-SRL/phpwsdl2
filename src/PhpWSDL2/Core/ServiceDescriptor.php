<?php

declare(strict_types=1);

namespace PhpWSDL2\Core;

use ReflectionClass;
use ReflectionMethod;

/**
 * ServiceDescriptor - Generates HTML service descriptions
 *
 * This class extracts service information using reflection and generates
 * HTML documentation similar to phpWSDL format
 */
class ServiceDescriptor
{
    private string $serviceClass;
    private string $endpoint;
    private string $serviceName;
    private array $publicMethods = [];

    public function __construct(string $serviceClass, string $endpoint, string $serviceName)
    {
        $this->serviceClass = $serviceClass;
        $this->endpoint = $endpoint;
        $this->serviceName = $serviceName;
        $this->extractMethods();
    }

    /**
     * Generate HTML service descriptor
     */
    public function generateHtml(): string
    {
        $html = $this->getHtmlHeader();
        $html .= $this->getServiceInfo();
        $html .= $this->getClientDownloadLinks();
        $html .= $this->getProtocolInfo();
        $html .= $this->getMethodsIndex();
        $html .= $this->getMethodsDetails();
        $html .= $this->getHtmlFooter();

        return $html;
    }

    /**
     * Get public methods information
     */
    public function getPublicMethods(): array
    {
        return $this->publicMethods;
    }

    /**
     * Extract methods from service class using reflection
     */
    private function extractMethods(): void
    {
        $reflection = new ReflectionClass($this->serviceClass);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if (!$method->isConstructor() &&
                !$method->isDestructor() &&
                $method->getDeclaringClass()->getName() === $this->serviceClass) {

                $this->publicMethods[] = $this->parseMethod($method);
            }
        }
    }

    /**
     * Parse method information from reflection
     */
    private function parseMethod(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();
        $description = '';
        $params = [];
        $returnType = '';

        if ($docComment) {
            // Parse PHPDoc comments
            if (preg_match('/\*\s*(.+?)(?=\*\s*@|\*\/)/s', $docComment, $matches)) {
                $description = trim(preg_replace('/\*\s*/', '', $matches[1]));
            }

            // Extract @param annotations
            preg_match_all('/\*\s*@param\s+(\S+)\s+\$(\S+)\s*(.*)/', $docComment, $paramMatches, PREG_SET_ORDER);
            foreach ($paramMatches as $paramMatch) {
                $params[] = [
                    'type' => $paramMatch[1],
                    'name' => $paramMatch[2],
                    'description' => trim($paramMatch[3])
                ];
            }

            // Extract @return annotation
            if (preg_match('/\*\s*@return\s+(\S+)\s*(.*)/', $docComment, $returnMatch)) {
                $returnType = $returnMatch[1] . (isset($returnMatch[2]) ? ': ' . trim($returnMatch[2]) : '');
            }
        }

        return [
            'name' => $method->getName(),
            'description' => $description,
            'parameters' => $params,
            'return' => $returnType
        ];
    }

    /**
     * Generate HTML header
     */
    private function getHtmlHeader(): string
    {
        return '<!DOCTYPE html>
<html>
<head>
    <title>' . htmlspecialchars($this->serviceName) . ' SOAP WebService interface description</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 1px solid #ccc; }
        h3 { color: #888; }
        .method { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .parameter { margin: 5px 0; }
        .code { font-family: monospace; background: #f5f5f5; padding: 2px 4px; }
        .uri { color: #0066cc; }
        .description { margin: 10px 0; }
        ul { margin: 10px 0; }
        .download-links { margin: 20px 0; }
        .download-links p { margin: 5px 0; }
    </style>
</head>
<body>';
    }

    /**
     * Generate service information section
     */
    private function getServiceInfo(): string
    {
        return '
    <h1>' . htmlspecialchars($this->serviceName) . ' SOAP WebService interface description</h1>
    
    <p><strong>Endpoint URI:</strong> <span class="uri">' . htmlspecialchars($this->endpoint) . '</span></p>
    
    <p><strong>WSDL URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?WSDL" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?WSDL</span></a></p>';
    }

    /**
     * Generate client download links
     */
    private function getClientDownloadLinks(): string
    {
        return '
    <div class="download-links">
        <p><strong>PHP SOAP client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?PHPSOAPCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?PHPSOAPCLIENT</span></a></p>
        
        <p><strong>PHP JSON client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?PHPJSONCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?PHPJSONCLIENT</span></a></p>
        
        <p><strong>JavaScript JSON client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?JSJSONCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?JSJSONCLIENT</span></a></p>
        
        <p><strong>Compressed JavaScript JSON client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?JSJSONCLIENT&min" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?JSJSONCLIENT&min</span></a></p>
        
        <p><strong>PHP XML RPC client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?PHPRPCCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?PHPRPCCLIENT</span></a></p>
        
        <p><strong>PHP http client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?PHPHTTPCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?PHPHTTPCLIENT</span></a></p>
        
        <p><strong>PHP REST client download URI:</strong> <a href="' . htmlspecialchars($this->endpoint) . '?PHPRESTCLIENT" target="_blank"><span class="uri">' . htmlspecialchars($this->endpoint) . '?PHPRESTCLIENT</span></a></p>
    </div>';
    }

    /**
     * Generate protocol information
     */
    private function getProtocolInfo(): string
    {
        return '
    <p>The PhpWSDL2 extension allows PhpWSDL2 to serve these protocols: SOAP, JSON, XML RPC, http, REST</p>';
    }

    /**
     * Generate methods index
     */
    private function getMethodsIndex(): string
    {
        $html = '
    <h2>Index</h2>
    <h3>Public methods:</h3>
    <ul>';

        foreach ($this->publicMethods as $method) {
            $html .= '<li><a href="#' . $method['name'] . '">' . $method['name'] . '</a></li>';
        }

        $html .= '</ul>';
        return $html;
    }

    /**
     * Generate detailed methods documentation
     */
    private function getMethodsDetails(): string
    {
        $html = '
    <h2>Public methods</h2>';

        foreach ($this->publicMethods as $method) {
            $html .= $this->generateMethodDetail($method);
        }

        return $html;
    }

    /**
     * Generate detail for a single method
     */
    private function generateMethodDetail(array $method): string
    {
        $html = '<div class="method">
            <h3 id="' . $method['name'] . '">' . $method['name'] . '</h3>';

        // Method signature
        $signature = $method['return'] ? explode(':', $method['return'])[0] . ' ' : '';
        $signature .= $method['name'] . ' (';
        $paramStrings = [];
        foreach ($method['parameters'] as $param) {
            $paramStrings[] = $param['type'] . ' ' . $param['name'];
        }
        $signature .= implode(', ', $paramStrings) . ')';

        $html .= '<p class="code">' . htmlspecialchars($signature) . '</p>';

        if ($method['description']) {
            $html .= '<div class="description">' . htmlspecialchars($method['description']) . '</div>';
        }

        // Parameters
        if (!empty($method['parameters'])) {
            foreach ($method['parameters'] as $param) {
                $html .= '<div class="parameter">
                    <strong>' . htmlspecialchars($param['type']) . ' ' . htmlspecialchars($param['name']) . '</strong><br>
                    ' . htmlspecialchars($param['description']) . '
                </div>';
            }
        }

        // Return value
        if ($method['return']) {
            $returnParts = explode(':', $method['return'], 2);
            $html .= '<div class="parameter">
                <strong>Return value ' . htmlspecialchars($returnParts[0]) . ':</strong> ' .
                (isset($returnParts[1]) ? htmlspecialchars(trim($returnParts[1])) : '') . '
            </div>';
        }

        // REST URI
        $restParams = [];
        foreach ($method['parameters'] as $param) {
            $restParams[] = ':' . $param['name'];
        }
        $restUri = $this->endpoint . '/' . $method['name'] . '/' . implode('/', $restParams) . '/';
        $html .= '<p><strong>Default GET REST URI:</strong> <a href="' . htmlspecialchars($restUri) . '" target="_blank"><span class="uri">' . htmlspecialchars($restUri) . '</span></a></p>';

        $html .= '</div>';
        return $html;
    }

    /**
     * Generate HTML footer
     */
    private function getHtmlFooter(): string
    {
        return '
    <hr>
    <p><em>Powered by <a href="https://github.com/tquadra/phpwsdl2" target="_blank">PhpWSDL2</a> - PDF download: <a href="' . htmlspecialchars($this->endpoint) . '?PDF" target="_blank">Download this page as PDF</a></em></p>
</body>
</html>';
    }
}
