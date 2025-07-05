# Changelog

All notable changes to this project will be documented in this file.

## [1.3.0] - 2025-01-05

### Added
- Configurable header filtering support with sensible defaults for Saloon-managed headers
- Header parameter support for both OpenAPI and Postman parsers
- Default values for nullable parameters in ResourceGenerator for better developer experience
- Comprehensive tests for deeply nested DTO references
- Tests for nullable parameter default values
- Tests for header filtering functionality

### Fixed
- OpenAPI $ref resolution for parameters and DTOs (fixes #21, #24)
- Parameter references from components section now properly resolved
- DTO generation with nested references using FQN to prevent backslash prefix issues
- Multi-authenticator implementation generating invalid PHP (PR #26)
- SecurityRequirements object handling in OpenApiParser
- Missing namespace use and method params in authenticators

### Changed
- Default ignored headers now include: Authorization, Content-Type, Accept, Accept-Language, User-Agent
- ApiSpecification constructor now has default values for convenience
- Nullable parameters in resource methods now have default values for consistency

### Credits
- Thanks to @nikspyratos for the initial header support implementation in PR #10
- Thanks to @mmachatschek for the nullable parameters fix in PR #27 and multi-authenticator fix in PR #26

## [1.2.0] - Previous releases...