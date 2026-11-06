# Changelog

All notable changes to this project will be documented in this file.

## [1.7.0] - 2026-11-06

### Changed
- Synced with upstream crescat-io/saloon-sdk-generator v1.6.0 (Laravel 13 support, multi-authenticator `defaultAuth()` fix)
- Dropped Saloon v3 support due to security issues; `saloonphp/saloon` and `saloonphp/laravel-plugin` now require `^4.0`
- Bumped minimum PHP to `^8.3` (required by Laravel Zero 13, which the binary is built with)
- Upgraded dev dependencies to `laravel-zero/framework ^13.0` and `pestphp/pest ^5.0`

## [1.6.0] - 2026-03-26

### Added
- Saloon v4 support: upgraded `saloonphp/saloon` to `^4.0` and `saloonphp/laravel-plugin` to `^4.0`
- Generated SDKs now accept both Saloon v3 and v4 (`^3.0|^4.0`)

### Fixed
- Multi-authenticator connectors now correctly use `defaultAuth()` instead of `getAuthenticator()`, consistent with single-authenticator behavior and Saloon's intended override pattern

### Credits
- Thanks to @Sammyjo20 for the Saloon v4 upgrade in PR #40

## [1.5.0] - 2026-03-25

### Added
- Laravel 13 support: widened `illuminate/http` to `^13.0`
- Widened `spatie/laravel-data` to `^3.10|^4.0` (v4 supports Laravel 13)
- Widened `laravel-zero/framework` dev dep to `^10.3.0|^12.0`
- Widened `pestphp/pest` dev dep to `^2.28.0|^3.0`

### Changed
- Bumped generated SDK PHP minimum to `^8.2`
- Widened generated SDK dev dependencies: `pestphp/pest ^2.0|^3.0`, `orchestra/testbench ^8.0|^9.0|^10.0`, `phpunit/phpunit ^10.0|^11.0|^12.0`
- Updated test assertions to match new dependency constraints

### Credits
- Thanks to @brandonjjon for Laravel 13 support in PR #39

## [1.4.2] - 2025-11-17

### Changed
- Removed pinned nikic/php-parser dependency constraint to allow v5.x as transitive dependency
- Upgraded nikic/php-parser from v4.19.4 to v5.6.2 (transitive dependency via PHPUnit)
- Updated multiple dependencies to latest patch versions for improved compatibility

### Credits
- Thanks to @jaulz for the php-parser dependency update in PR #32

## [1.4.1] - 2025-11-06

### Changed
- Added Laravel 12 support to illuminate/http dependency constraint

## [1.4.0] - 2025-11-06

### Added
- **Test Generation**: New `--pest` flag to generate Pest PHP test suites for generated SDKs
- `PestTestGenerator`: Automatically generates test files for each resource with proper setup and fixtures
- `ComposerGenerator`: Generates composer.json file with proper dependencies and configuration
- `PostProcessor` contract for extensible post-processing of generated code
- Test stubs for Pest test generation (Pest.php, TestCase.php, resource tests)
- Sample API specifications (Google Drive, Paddle Billing, Paddle OpenAPI)
- Fallback to path-based naming when endpoint has no name in OpenAPI spec
- Generated tests include proper constructor arguments, method parameters, and fixture names

### Changed
- composer.json is now always generated (not just when --pest is enabled)
- Pinned nikic/php-parser to v4.x for compatibility
- Improved endpoint naming fallback strategy

### Fixed
- OpenAPI Reference resolution in parameter schemas - properly resolves Reference objects before accessing type property (fixes #30)
- Test generator parameter handling for path, body, query, and header parameters
- Test generator properly respects ignored query and header parameters
- Test suite configuration now properly excludes generated tests in tests/Output directory
- ArchTest now properly included in test runs
- Removed outdated TODO comment about duplicate DTOs (references work correctly)

### Known Limitations
- Test generator constructor argument handling for nullable parameters is not configurable
- Exception handling in test generator silently returns null (no user feedback)
- Request body type assumptions (JSON for POST/PATCH) are not configurable

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