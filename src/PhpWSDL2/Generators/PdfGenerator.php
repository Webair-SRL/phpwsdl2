<?php

declare(strict_types=1);

namespace PhpWSDL2\Generators;

use ReflectionClass;
use ReflectionMethod;
use TCPDF;

/**
 * PdfGenerator - Generates PDF documentation for web services
 *
 * This class generates PDF documentation using TCPDF library
 */
class PdfGenerator
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
     * Generate PDF documentation
     */
    public function generate(): string
    {
        // Create PDF using TCPDF
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('PhpWSDL2');
        $pdf->SetAuthor('PhpWSDL2');
        $pdf->SetTitle($this->serviceName . ' SOAP WebService interface description');
        $pdf->SetSubject('WebService API Documentation');

        // Set default header data
        $pdf->SetHeaderData('', 0, $this->serviceName . ' SOAP WebService', 'Interface Description');

        // Set header and footer fonts
        $pdf->setHeaderFont(['helvetica', '', 10]);
        $pdf->setFooterFont(['helvetica', '', 8]);

        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont('courier');

        // Set margins
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 25);

        // Set image scale factor
        $pdf->setImageScale(1.25);

        // Add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont('helvetica', '', 10);

        // Build HTML content for PDF
        $html = $this->generatePdfContent();

        // Print text using writeHTMLCell()
        $pdf->writeHTML($html, true, false, true, false, '');

        return $pdf->Output('', 'S'); // Return as string
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
     * Generate PDF content as HTML
     */
    private function generatePdfContent(): string
    {
        $html = '<h1>' . htmlspecialchars($this->serviceName) . ' SOAP WebService interface description</h1>';

        $html .= '<p><strong>Endpoint URI:</strong> ' . htmlspecialchars($this->endpoint) . '</p>';
        $html .= '<p><strong>WSDL URI:</strong> ' . htmlspecialchars($this->endpoint) . '?WSDL</p>';

        $html .= '<p><strong>Client Downloads Available:</strong></p>';
        $html .= '<ul>';
        $html .= '<li>PHP SOAP Client: ' . htmlspecialchars($this->endpoint) . '?PHPSOAPCLIENT</li>';
        $html .= '<li>PHP JSON Client: ' . htmlspecialchars($this->endpoint) . '?PHPJSONCLIENT</li>';
        $html .= '<li>JavaScript JSON Client: ' . htmlspecialchars($this->endpoint) . '?JSJSONCLIENT</li>';
        $html .= '<li>PHP XML RPC Client: ' . htmlspecialchars($this->endpoint) . '?PHPRPCCLIENT</li>';
        $html .= '<li>PHP HTTP Client: ' . htmlspecialchars($this->endpoint) . '?PHPHTTPCLIENT</li>';
        $html .= '<li>PHP REST Client: ' . htmlspecialchars($this->endpoint) . '?PHPRESTCLIENT</li>';
        $html .= '</ul>';

        $html .= '<p>The PhpWSDL2 extension allows PhpWSDL2 to serve these protocols: SOAP, JSON, XML RPC, http, REST</p>';

        $html .= '<h2>Index</h2>';
        $html .= '<h3>Public methods:</h3>';
        $html .= '<ul>';
        foreach ($this->publicMethods as $method) {
            $html .= '<li>' . htmlspecialchars($method['name']) . '</li>';
        }
        $html .= '</ul>';

        $html .= '<h2>Public methods</h2>';

        foreach ($this->publicMethods as $method) {
            $html .= $this->generateMethodPdfContent($method);
        }

        $html .= '<hr>';
        $html .= '<p><em>Powered by PhpWSDL2</em></p>';

        return $html;
    }

    /**
     * Generate PDF content for a single method
     */
    private function generateMethodPdfContent(array $method): string
    {
        $html = '<h3>' . htmlspecialchars($method['name']) . '</h3>';

        // Method signature
        $signature = $method['return'] ? explode(':', $method['return'])[0] . ' ' : '';
        $signature .= $method['name'] . ' (';
        $paramStrings = [];
        foreach ($method['parameters'] as $param) {
            $paramStrings[] = $param['type'] . ' ' . $param['name'];
        }
        $signature .= implode(', ', $paramStrings) . ')';

        $html .= '<p><code>' . htmlspecialchars($signature) . '</code></p>';

        if ($method['description']) {
            $html .= '<p>' . htmlspecialchars($method['description']) . '</p>';
        }

        // Parameters
        if (!empty($method['parameters'])) {
            $html .= '<p><strong>Parameters:</strong></p>';
            foreach ($method['parameters'] as $param) {
                $html .= '<p><strong>' . htmlspecialchars($param['type']) . ' ' . htmlspecialchars($param['name']) . '</strong><br>';
                $html .= htmlspecialchars($param['description']) . '</p>';
            }
        }

        // Return value
        if ($method['return']) {
            $returnParts = explode(':', $method['return'], 2);
            $html .= '<p><strong>Return value ' . htmlspecialchars($returnParts[0]) . ':</strong> ';
            $html .= (isset($returnParts[1]) ? htmlspecialchars(trim($returnParts[1])) : '') . '</p>';
        }

        // REST URI
        $restParams = [];
        foreach ($method['parameters'] as $param) {
            $restParams[] = ':' . $param['name'];
        }
        $restUri = $this->endpoint . '/' . $method['name'] . '/' . implode('/', $restParams) . '/';
        $html .= '<p><strong>Default GET REST URI:</strong> ' . htmlspecialchars($restUri) . '</p>';

        $html .= '<hr>';
        return $html;
    }
}
