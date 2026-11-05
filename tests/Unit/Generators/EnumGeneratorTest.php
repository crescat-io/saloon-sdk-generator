<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Generators\EnumGenerator;

beforeEach(function () {
    $this->generator = new EnumGenerator(new Config(
        connectorName: 'MyConnector',
        namespace: 'VendorName',
        enumNamespaceSuffix: 'Enums',
        generateEnums: true
    ));

    $this->dummySpec = new ApiSpecification(
        name: 'ApiName',
        description: 'Example API',
        baseUrl: new BaseUrl(url: 'https://api.example.com'),
        securityRequirements: [],
        components: new Components,
        endpoints: []
    );
});

test('generates valid enum case names from camelCase values', function () {
    $enumValues = ['DebitNote', 'CreditNote', 'Invoice'];

    $result = $this->generator->registerEnum(
        $enumValues,
        'Document type',
        'DocumentDto',
        'type'
    );

    expect($result)->not->toBeNull()
        ->and($result['className'])->toBe('DocumentTypeEnum');

    $phpFiles = $this->generator->generate($this->dummySpec);
    $enumClass = $phpFiles[0]->file->getNamespaces()['VendorName\Enums']->getClasses()['DocumentTypeEnum'];

    $cases = $enumClass->getCases();
    expect($cases)->toHaveCount(3)
        ->and($cases['DEBIT_NOTE']->getValue())->toBe('DebitNote')
        ->and($cases['CREDIT_NOTE']->getValue())->toBe('CreditNote')
        ->and($cases['INVOICE']->getValue())->toBe('Invoice');
});

test('generates valid enum case names from numeric values', function () {
    $enumValues = ['0', '1', '2'];

    $result = $this->generator->registerEnum(
        $enumValues,
        'Status code',
        'StatusDto',
        'code'
    );

    $phpFiles = $this->generator->generate($this->dummySpec);
    $enumClass = $phpFiles[0]->file->getNamespaces()['VendorName\Enums']->getClasses()['StatusCodeEnum'];

    $cases = $enumClass->getCases();
    expect($cases)->toHaveCount(3)
        ->and($cases['VALUE_0']->getValue())->toBe('0')
        ->and($cases['VALUE_1']->getValue())->toBe('1')
        ->and($cases['VALUE_2']->getValue())->toBe('2');
});

test('deduplicates enums with identical values', function () {
    // Register first enum
    $result1 = $this->generator->registerEnum(
        ['Active', 'Inactive', 'Pending'],
        'First enum',
        'FirstDto',
        'status'
    );

    // Register second enum with same values
    $result2 = $this->generator->registerEnum(
        ['Active', 'Inactive', 'Pending'],
        'Second enum',
        'SecondDto',
        'state'
    );

    // Should return the same enum
    expect($result1['className'])->toBe($result2['className']);

    $phpFiles = $this->generator->generate($this->dummySpec);

    // Should only generate one enum file
    expect($phpFiles)->toHaveCount(1);
});

test('builds correct fully qualified namespace', function () {
    $fqn = $this->generator->buildEnumFqn('StatusEnum');

    expect($fqn)->toBe('VendorName\Enums\StatusEnum');
});

test('cleans HTML entities in descriptions', function () {
    $enumValues = ['Value1', 'Value2'];

    $result = $this->generator->registerEnum(
        $enumValues,
        'Status &quot;Active&quot; or &quot;Inactive&quot;',
        'StatusDto',
        'status'
    );

    $phpFiles = $this->generator->generate($this->dummySpec);
    $enumClass = $phpFiles[0]->file->getNamespaces()['VendorName\Enums']->getClasses()['StatusStatusEnum'];

    $comment = $enumClass->getComment();
    expect($comment)->toContain('"Active"')
        ->and($comment)->toContain('"Inactive"')
        ->and($comment)->not->toContain('&quot;');
});
