# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-07-31

### Added
- Initial release of PhpWSDL2
- Modern PHP 8.0+ WSDL/SOAP web service library
- Multiple protocol support (SOAP, JSON, REST, XML-RPC, HTTP)
- Automatic service description generation using PHP reflection
- HTML service descriptor with professional styling
- PDF documentation generation using TCPDF
- Client code generation for multiple languages and protocols:
  - PHP SOAP client
  - PHP JSON client
  - JavaScript JSON client (with minification support)
  - PHP XML-RPC client
  - PHP HTTP client
  - PHP REST client
- Comprehensive request handling and routing
- Error handling with proper HTTP status codes
- Framework-independent design
- PSR-4 autoloading support
- Composer package configuration
- Complete documentation and examples
- MIT license

### Features
- **ServiceDescriptor**: Generates HTML service documentation
- **ClientGenerator**: Creates client code for multiple protocols
- **PdfGenerator**: Produces PDF documentation
- **WebServiceServer**: Handles all incoming requests and protocol routing
- **PhpWSDL2**: Main orchestration class with simple API

### Protocol Support
- **SOAP**: Full WSDL 1.1/2.0 support with Laminas SOAP integration
- **REST**: Clean URL routing with JSON responses
- **JSON**: POST parameter-based JSON API
- **XML-RPC**: Standard XML-RPC protocol support
- **HTTP**: Simple POST/GET parameter support

### Documentation
- Comprehensive README with installation and usage examples
- Complete API documentation
- Working example service implementation
- Client usage examples for all supported protocols

### Requirements
- PHP 8.0 or higher
- ext-soap, ext-json, ext-reflection
- laminas/laminas-soap ^2.10
- tecnickcom/tcpdf ^6.4

[Unreleased]: https://github.com/webair-srl/phpwsdl2/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/webair-srl/phpwsdl2/releases/tag/v1.0.0
