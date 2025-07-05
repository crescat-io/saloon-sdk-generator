# Changelog

All notable changes to this project will be documented in this file.

## [1.3.1] - 2025-01-05

### Fixed
- Updated dependency constraints to support Laravel 11 and newer termwind versions
- Resolved global installation conflicts with illuminate/http and nunomaduro/termwind

### Changed
- illuminate/http version constraint updated from `^10.0` to `^10.0|^11.0`
- nunomaduro/termwind version constraint updated from `^1.15.1` to `^1.15.1|^2.0`

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

## [1.2.0] - 2024-01-05

### Added
- **DTO Generation**: SDK Generator now generates DTOs from OpenAPI specifications
- **Authentication Support**: Automatic detection and scaffolding for various authentication methods:
  - API Key authentication (header and query)
  - HTTP authentication (basic and digest)
  - Bearer Token authentication
  - Header Token authentication
  - OAuth2 authentication (Client Credentials and Authorization Code Grant)
- Support for Swagger 1.x/2.x to OpenAPI 3.x conversion via API
- Spotify Web API sample for testing
- Tests for authentication code generation
- Support for variables in base URL

### Changed
- **Breaking**: Minimum PHP version bumped from `^8.1` to `^8.2`
- Improved authentication code generation with proper namespace imports
- Bearer token authentication now explicitly specifies "Bearer" prefix

### Fixed
- Deprecated `tryFrom(null)` warnings by passing empty string instead
- Various test improvements and fixes

### Contributors
- @HelgeSverre made their first contribution in PR #18

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.1.0...v1.2.0

## [1.1.0] - 2023-10-04

### Changed
- **Major**: Upgraded to Saloon v3 compatibility
- Removed Response contract/interface (no longer needed in Saloon v3)
- Updated all dependencies to support Saloon v3

### Contributors
- @Sammyjo20 made their first contribution in PR #11

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.5...v1.1.0

## [1.0.5] - 2023-09-18

### Fixed
- Fixed issue when operation ID is null in OpenAPI specifications

### Contributors
- @bobbyshaw made their first contribution in PR #8

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.4...v1.0.5

## [1.0.4] - 2023-09-14

### Added
- `ParserNotRegisteredException` for better error handling
- Helpful error message in CLI when parser type is missing

### Changed
- Parser Factory now gives clear error when parser type is not registered
- Improved error messaging for missing parsers

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.3...v1.0.4

## [1.0.3] - 2023-09-11

### Added
- Crescat API sample
- Protection against PHP reserved keyword collisions in generated names

### Changed
- Improved array return method generation with nullable type hints
- Arrays can now be wrapped in `array_filter` to remove null values
- Switched from checking "nullable" to "required" for proper OpenAPI parsing

### Fixed
- Issue #5: Nullable parameters now properly handled with correct type hints

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.2...v1.0.3

## [1.0.2] - 2023-08-19

### Added
- Altinn sample Postman collection for complex naming scenarios
- TODO comments for duplicated method names

### Changed
- PostmanCollectionParser now handles object lists in responses
- Method names now use camelCase instead of PascalCase
- Duplicate method suffixes changed from random strings to counters (1, 2, 3, etc.)

### Fixed
- Improved handling of complex Postman collections with duplicate names

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.1...v1.0.2

## [1.0.1] - 2023-08-18

### Fixed
- Issue #1: Fixed generation failure when Request and Resource class names were equal
- Now aliases Request class when names collide

**Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/v1.0.0...v1.0.1

## [1.0.0] - 2023-08-17

### Added
- Initial release of Saloon SDK Generator
- Support for OpenAPI 3.x specifications
- Support for Postman Collections
- Basic SDK generation with:
  - Connector class generation
  - Request class generation
  - Resource class generation
- Composer global installation support
- Dry run mode
- ZIP archive generation
- Support for:
  - Path parameters
  - Query parameters
  - JSON body parameters (for POST/PATCH requests)
- Automatic nullable parameter handling
- Collision detection for duplicate class names