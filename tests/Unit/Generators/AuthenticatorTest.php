<?php

use Crescat\SaloonSdkGenerator\Data\Generator\ApiKeyLocation;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Components;
use Crescat\SaloonSdkGenerator\Data\Generator\Config;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityRequirement;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\SecuritySchemeType;
use Crescat\SaloonSdkGenerator\Generators\ConnectorGenerator;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;

beforeEach(function () {
    $this->generator = new ConnectorGenerator(new Config(
        connectorName: 'Client',
        namespace: 'Crescat'
    ));
});

it('handles basic authentication', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('basicAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::http,
                    scheme: 'basic',
                    description: 'Basic authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new BasicAuthenticator($this->username, $this->password);')
        ->and($class->getMethods()['__construct']->hasParameter('username'))->toBeTrue()
        ->and($class->getMethods()['__construct']->hasParameter('password'))->toBeTrue();
});

it('handles digest authentication', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('digestAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::http,
                    scheme: 'digest',
                    description: 'Digest authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new DigestAuthenticator($this->username, $this->password, "digest");');
});

it('handles bearer token authentication', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('bearerAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::http,
                    scheme: 'bearer',
                    description: 'Bearer token authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new TokenAuthenticator($this->bearerToken, "Bearer");')
        ->and($class->getMethods()['__construct']->hasParameter('bearerToken'))->toBeTrue();
});

it('handles api key authentication in the header with X- prefix', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('apiKeyAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::apiKey,
                    name: 'X-Api-Key',
                    in: ApiKeyLocation::header,
                    description: 'API key authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new HeaderAuthenticator($this->apiKey, "X-Api-Key");')
        ->and($class->getMethods()['__construct']->hasParameter('apiKey'))->toBeTrue();
});

it('handles api key authentication in the header without X- prefix', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('apiKeyAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::apiKey,
                    // Commonly found when dealing with APIs that use Azure API Management
                    // See: https://learn.microsoft.com/en-us/azure/api-management/api-management-subscriptions#use-a-subscription-key
                    name: 'Ocp-Apim-Subscription-Key',
                    in: ApiKeyLocation::header,
                    description: 'API key authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new HeaderAuthenticator($this->ocpApimSubscriptionKey, "Ocp-Apim-Subscription-Key");')
        ->and($class->getMethods()['__construct']->hasParameter('ocpApimSubscriptionKey'))->toBeTrue();
});

it('handles api key authentication in the query', function () {

    $connectorPhpFile = $this->generator->generate(new ApiSpecification(
        name: 'Example',
        description: 'Example API',
        baseUrl: new BaseUrl(
            url: 'https://examnple.com.com/v1',
        ),
        securityRequirements: [
            // Arbitrary and not used in this test
            new SecurityRequirement('apiKeyAuth'),
        ],
        components: new Components(
            securitySchemes: [
                new SecurityScheme(
                    type: SecuritySchemeType::apiKey,
                    name: 'secretKey',
                    in: ApiKeyLocation::query,
                    description: 'API key authentication',
                ),
            ]
        ),
        endpoints: []
    ));

    $class = $connectorPhpFile->getNamespaces()['Crescat']->getClasses()['Client'];

    expect($class)->toBeInstanceOf(ClassType::class);

    $defaultAuth = $class->getMethods()['defaultAuth'];

    expect($defaultAuth)->toBeInstanceOf(Method::class)
        ->and($defaultAuth->getParameters())->toHaveCount(0)
        ->and($defaultAuth->getBody())->toBe('return new QueryAuthenticator("secretKey", $this->secretKey);')
        ->and($class->getMethods()['__construct']->hasParameter('secretKey'))->toBeTrue();
});

// TODO: Test other authentication methods (OAuth2, OpenID Connect, etc)
