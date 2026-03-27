<?php

use cebe\openapi\spec\Schema;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\GeneratedCode;
use Crescat\SaloonSdkGenerator\Generators\DtoGenerator;
use Crescat\SaloonSdkGenerator\Generators\FactoryGenerator;

beforeEach(function () {
    $this->config = new Config(
        connectorName: 'MyConnector',
        namespace: 'VendorName'
    );

    $userSchema = new Schema([
        'type' => 'object',
        'title' => 'User',
        'description' => 'A user in the system',
        'properties' => [
            'id' => new Schema(['type' => 'integer']),
            'name' => new Schema(['type' => 'string']),
            'email' => new Schema(['type' => 'string']),
            'is_active' => new Schema(['type' => 'boolean']),
        ],
    ]);

    $this->specification = new ApiSpecification(
        name: 'TestAPI',
        description: 'Test API',
        baseUrl: null,
        securityRequirements: [],
        components: new Components(
            schemas: [
                'User' => $userSchema,
            ]
        ),
        endpoints: []
    );
});

test('generates factory class with Faker definitions', function () {
    $dtoGenerator = new DtoGenerator($this->config);
    $dtoClasses = $dtoGenerator->generate($this->specification);

    $generatedCode = new GeneratedCode(dtoClasses: $dtoClasses);

    $factoryGenerator = new FactoryGenerator;
    $factories = $factoryGenerator->process($this->config, $this->specification, $generatedCode);

    expect($factories)->toBeArray()
        ->and($factories)->toHaveKey('UserFactory');

    $factoryFile = $factories['UserFactory'];
    expect($factoryFile->tag)->toBe('factories')
        ->and($factoryFile->path)->toBe('Factories/UserFactory.php');

    $factoryContent = (string) $factoryFile->file;

    expect($factoryContent)
        ->toContain('class UserFactory extends Factory')
        ->toContain('protected function definition(): array')
        ->toContain('\'id\' => $this->faker->numberBetween(1, 100)')
        ->toContain('\'name\' => $this->faker->name()')
        ->toContain('\'email\' => $this->faker->safeEmail()')
        ->toContain('\'isActive\' => $this->faker->boolean()');
});

test('skips properties based on getPropertiesToSkip hook', function () {
    $customFactoryGenerator = new class extends FactoryGenerator
    {
        protected function getPropertiesToSkip(): array
        {
            return ['id'];
        }
    };

    $dtoGenerator = new DtoGenerator($this->config);
    $dtoClasses = $dtoGenerator->generate($this->specification);

    $generatedCode = new GeneratedCode(dtoClasses: $dtoClasses);

    $factories = $customFactoryGenerator->process($this->config, $this->specification, $generatedCode);

    $factoryContent = (string) $factories['UserFactory']->file;

    expect($factoryContent)
        ->not->toContain('\'id\' =>')
        ->toContain('\'name\' =>')
        ->toContain('\'email\' =>')
        ->toContain('\'isActive\' =>');
});
