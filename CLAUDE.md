# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

The Saloon SDK Generator is a Laravel Zero CLI application that generates PHP SDKs from API specifications (OpenAPI/Swagger and Postman Collections) using the Saloon HTTP client framework.

## Commands

### Development Commands
```bash
# Run the CLI in development
./codegen generate:sdk API_SPEC_FILE.{json|yaml|yml} --type={postman|openapi} [--name=SDK_NAME] [--output=OUTPUT_PATH] [--namespace=Company\\Integration] [--force] [--dry] [--zip]

# Convert Swagger v1/v2 to OpenAPI 3.0
./codegen convert old.json [output.json]
```

### Build & Testing
```bash
# Build the Phar binary (current version: 1.3.1)
composer build

# Run tests
composer test
composer test-coverage

# Format code (Laravel Pint)
composer format

# Clean generated test output
composer clean

# Generate all sample SDKs
composer generate:all
```

### Release Process

When preparing a new release:

1. **Update version numbers**:
   - Update `build-version` in `composer.json` scripts section
   - Build the new binary: `composer build`

2. **Update documentation**:
   - Add new version section to `CHANGELOG.md` with:
     - Version number and date
     - Added/Fixed/Changed sections
     - Credits to contributors
   - Update this file with the new version number

3. **Create git tag and release**:
   ```bash
   # Commit all changes
   git add composer.json composer.lock CHANGELOG.md builds/sdkgenerator
   git commit -m "Release vX.Y.Z: Brief description"
   
   # Create tag
   git tag vX.Y.Z
   
   # Push changes and tag
   git push origin master
   git push origin vX.Y.Z
   
   # Create GitHub release
   gh release create vX.Y.Z --title "vX.Y.Z: Brief title" --notes "Release notes..."
   ```

4. **Release notes format**:
   ```markdown
   ## What's Changed
   
   Brief description of the release.
   
   ### Added
   - New features
   
   ### Fixed
   - Bug fixes
   
   ### Changed
   - Breaking changes or improvements
   
   ### Credits
   - Thanks to @contributor for PR #XX
   
   ## Installation
   
   ```bash
   composer global require crescat-io/saloon-sdk-generator
   ```
   
   **Full Changelog**: https://github.com/crescat-io/saloon-sdk-generator/compare/vX.Y.Y...vX.Y.Z
   ```

## Architecture

### Core Components

1. **Parsers** (src/Parsers/): Convert API specifications to internal format
   - `OpenApiParser`: Handles OpenAPI/Swagger specs
   - `PostmanCollectionParser`: Handles Postman collections
   - Output: `ApiSpecification` data object

2. **Generators** (src/Generators/): Create PHP SDK components
   - `ConnectorGenerator`: Main SDK connector class
   - `RequestGenerator`: Individual endpoint request classes  
   - `ResourceGenerator`: API resource grouping classes
   - `DtoGenerator`: Data Transfer Objects
   - `BaseResourceGenerator`: Base resource class

3. **Data Objects** (src/Data/Generator/): Internal representations
   - `ApiSpecification`: Complete API structure
   - `Endpoint`: Individual API endpoint details
   - `Parameter`: Request/response parameters
   - `Config`: Generator configuration

4. **CodeGenerator** (src/CodeGenerator.php): Orchestrates the generation process

### Key Design Patterns

- **Parser Interface**: All parsers implement `Crescat\SaloonSdkGenerator\Contracts\Parser`
- **Generator Interface**: All generators implement `Crescat\SaloonSdkGenerator\Contracts\Generator`
- **Factory Pattern**: `Factory::parse()` and `Factory::registerParser()` for extensibility
- **Laravel Zero Commands**: CLI commands in src/Commands/
- **Reference Resolution**: Manual resolution of OpenAPI $ref references for parameters and schemas
- **Header Filtering**: Configurable filtering of headers managed by Saloon (Authorization, Content-Type, etc.)

### Testing Approach

- Uses Pest PHP testing framework
- Test samples in tests/Samples/
- Generated output goes to tests/Output/ (gitignored)
- Key test: `ConnectorGeneratorTest` validates the core generation logic

## Development Notes

- PHP 8.2+ required
- Follows PSR-4 autoloading
- Uses Laravel service container for dependency injection
- Nette PHP Generator for code generation
- Built binary distributed via Packagist as `builds/sdkgenerator`

## Recent Changes (v1.3.1)

- **Dependency Compatibility**: Updated Laravel and termwind constraints to support newer versions
- **Global Installation**: Fixed conflicts when installing globally with composer

### v1.3.0 Changes
- **OpenAPI $ref Resolution**: Fixed issues with parameter and schema references not being resolved
- **Header Filtering**: Added configurable header filtering with defaults for Saloon-managed headers
- **Nullable Parameters**: Resource methods now have default values for nullable parameters
- **Test Coverage**: Added comprehensive tests for nested DTOs, header filtering, and nullable parameters

## Common Issues & Solutions

1. **Circular References**: Parser uses RESOLVE_MODE_INLINE to avoid infinite loops
2. **DTO Type Prefixing**: Use FQN (fully qualified namespace) to prevent backslash prefix
3. **Header Management**: Default filtered headers: Authorization, Content-Type, Accept, Accept-Language, User-Agent