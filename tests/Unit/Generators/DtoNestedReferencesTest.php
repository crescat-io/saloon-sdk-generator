<?php

use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Generators\DtoGenerator;
use Crescat\SaloonSdkGenerator\Parsers\OpenApiParser;

it('generates DTOs for nested schema references', function () {
    $specFile = sample_path('nested-refs.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );

    $generator = new DtoGenerator($config);
    $files = $generator->generate($spec);

    // Should generate all the DTOs
    $expectedDtos = [
        'UserResponse',
        'ProductResponse',
        'User',
        'Product',
        'UserProfile',
        'Money',
        'Dimensions',
        'Address',
        'GeoCoordinates',
        'LocationAccuracy',
        'Category',
        'Review',
        'ReviewAuthor',
        'ErrorResponse',
        'ErrorDetails',
        'ValidationError',
        'ResponseMetadata',
    ];

    foreach ($expectedDtos as $dtoName) {
        expect($files)->toHaveKey($dtoName);
    }

    // Verify that shared references (ResponseMetadata, ErrorResponse) are only generated once
    expect(count($files))->toBe(count($expectedDtos));
});

it('generates DTOs with correct property references', function () {
    $specFile = sample_path('nested-refs.yml');
    $parser = OpenApiParser::build($specFile);
    $spec = $parser->parse();

    $config = new Config(
        connectorName: 'TestConnector',
        namespace: 'TestNamespace'
    );

    $generator = new DtoGenerator($config);
    $files = $generator->generate($spec);

    // Check UserResponse DTO has references to User and ResponseMetadata
    $userResponseDto = $files['UserResponse'];
    $userResponseCode = (string) $userResponseDto;

    expect($userResponseCode)->toContain('public ?User $data = null');
    expect($userResponseCode)->toContain('public ?ResponseMetadata $metadata = null');

    // Check ProductResponse DTO has references to Product and ResponseMetadata
    $productResponseDto = $files['ProductResponse'];
    $productResponseCode = (string) $productResponseDto;

    expect($productResponseCode)->toContain('public ?Product $data = null');
    expect($productResponseCode)->toContain('public ?ResponseMetadata $metadata = null');

    // Check User DTO has reference to UserProfile
    $userDto = $files['User'];
    $userCode = (string) $userDto;

    expect($userCode)->toContain('public ?UserProfile $profile = null');

    // Check UserProfile DTO has reference to Address
    $userProfileDto = $files['UserProfile'];
    $userProfileCode = (string) $userProfileDto;

    expect($userProfileCode)->toContain('public ?Address $address = null');

    // Check Product DTO has references to Money and Dimensions
    $productDto = $files['Product'];
    $productCode = (string) $productDto;

    expect($productCode)->toContain('public ?Money $price = null');
    expect($productCode)->toContain('public ?Dimensions $dimensions = null');

    // Check ErrorResponse DTO has references to ErrorDetails and ResponseMetadata
    $errorResponseDto = $files['ErrorResponse'];
    $errorResponseCode = (string) $errorResponseDto;

    expect($errorResponseCode)->toContain('public ?ErrorDetails $error = null');
    expect($errorResponseCode)->toContain('public ?ResponseMetadata $metadata = null');

    // Check ErrorDetails DTO has array of ValidationError references
    $errorDetailsDto = $files['ErrorDetails'];
    $errorDetailsCode = (string) $errorDetailsDto;

    expect($errorDetailsCode)->toContain('public ?array $validationErrors = null');

    // Check deeper nesting: Address -> GeoCoordinates -> LocationAccuracy
    $addressDto = $files['Address'];
    $addressCode = (string) $addressDto;

    expect($addressCode)->toContain('public ?GeoCoordinates $coordinates = null');

    $geoCoordinatesDto = $files['GeoCoordinates'];
    $geoCoordinatesCode = (string) $geoCoordinatesDto;

    expect($geoCoordinatesCode)->toContain('public ?LocationAccuracy $accuracy = null');

    // Check Product has array references
    $productCode = (string) $productDto;

    expect($productCode)->toContain('public ?array $categories = null');
    expect($productCode)->toContain('public ?array $reviews = null');

    // Check Review has reference to ReviewAuthor
    $reviewDto = $files['Review'];
    $reviewCode = (string) $reviewDto;

    expect($reviewCode)->toContain('public ?ReviewAuthor $author = null');

    // Check Category has self-reference
    $categoryDto = $files['Category'];
    $categoryCode = (string) $categoryDto;

    expect($categoryCode)->toContain('public ?Category $parent = null');
});

it('handles circular references gracefully', function () {
    // Add a test spec with circular references
    $specContent = <<<'YAML'
openapi: 3.0.3
info:
  title: Circular References Test
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /nodes:
    get:
      operationId: get-nodes
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Node'
components:
  schemas:
    Node:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
        parent:
          $ref: '#/components/schemas/Node'
        children:
          type: array
          items:
            $ref: '#/components/schemas/Node'
YAML;

    $tempFile = tempnam(sys_get_temp_dir(), 'circular-refs').'.yml';
    file_put_contents($tempFile, $specContent);

    try {
        $parser = OpenApiParser::build($tempFile);
        $spec = $parser->parse();

        $config = new Config(
            connectorName: 'TestConnector',
            namespace: 'TestNamespace'
        );

        $generator = new DtoGenerator($config);
        $files = $generator->generate($spec);

        // Should generate the Node DTO
        expect($files)->toHaveKey('Node');

        // Check that Node DTO has self-references
        $nodeDto = $files['Node'];
        $nodeCode = (string) $nodeDto;

        expect($nodeCode)->toContain('public ?Node $parent = null');
        expect($nodeCode)->toContain('public ?array $children = null');
    } finally {
        unlink($tempFile);
    }
});

it('handles anyOf union types', function () {
    $specContent = <<<'YAML'
openapi: 3.0.3
info:
  title: Union Types Test
  version: 1.0.0
servers:
  - url: https://api.example.com
paths:
  /test:
    get:
      operationId: get-test
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/TestDto'
components:
  schemas:
    TestDto:
      type: object
      properties:
        id:
          type: string
        value:
          anyOf:
            - type: string
            - type: integer
            - type: "null"
        reference:
          anyOf:
            - $ref: '#/components/schemas/OptionA'
            - $ref: '#/components/schemas/OptionB'
            - type: "null"
    OptionA:
      type: object
      properties:
        typeA:
          type: string
    OptionB:
      type: object
      properties:
        typeB:
          type: integer
YAML;

    $tempFile = tempnam(sys_get_temp_dir(), 'union-types').'.yml';
    file_put_contents($tempFile, $specContent);

    try {
        $parser = OpenApiParser::build($tempFile);
        $spec = $parser->parse();

        $config = new Config(
            connectorName: 'TestConnector',
            namespace: 'TestNamespace'
        );

        $generator = new DtoGenerator($config);
        $files = $generator->generate($spec);

        expect($files)->toHaveKey('TestDto');

        $testDto = $files['TestDto'];
        $testDtoCode = (string) $testDto;

        // Check that union type is generated correctly for primitive types
        expect($testDtoCode)->toContain('string|int|null $value');

        // Check that union type is generated correctly with DTO references
        expect($testDtoCode)->toContain('OptionA|OptionB|null $reference');

        // Verify the referenced DTOs were generated
        expect($files)->toHaveKey('OptionA');
        expect($files)->toHaveKey('OptionB');
    } finally {
        unlink($tempFile);
    }
});
