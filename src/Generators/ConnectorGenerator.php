<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiKeyLocation;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\SecurityScheme;
use Crescat\SaloonSdkGenerator\Data\Generator\SecuritySchemeType;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\TemplateHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Saloon\Http\Connector;

class ConnectorGenerator extends Generator
{
    public function generate(ApiSpecification $specification): PhpFile|array
    {
        return $this->generateConnectorClass($specification);
    }

    protected function generateConnectorClass(ApiSpecification $specification): ?PhpFile
    {

        $classType = new ClassType($this->config->connectorName);
        $classType->setExtends(Connector::class);

        if ($specification->name) {
            $classType->addComment($specification->name);
        }

        if ($specification->description) {
            $classType->addComment($specification->name ? "\n{$specification->description}" : $specification->description);
        }

        $classFile = new PhpFile();

        $this->addConstructor($classType, $specification);
        $this->addResolveBaseUrlMethod($classType, $specification);

        $namespace = $classFile
            ->addNamespace("{$this->config->namespace}")
            ->addUse(Connector::class);

        $this->addMethodsForResources($namespace, $classType, $specification);
        $this->addAuthenticators($namespace, $classType, $specification);

        $namespace->add($classType);

        return $classFile;
    }

    protected function addConstructor(
        ClassType        $classType,
        ApiSpecification $specification
    ): ClassType
    {
        $classConstructor = $classType->addMethod('__construct');

        // Add Base Url Property
        foreach ($specification->baseUrl->parameters as $parameter) {
            MethodGeneratorHelper::addParameterAsPromotedProperty(
                $classConstructor,
                new Parameter(
                    type: 'string',
                    nullable: false,
                    name: $parameter->name,
                    description: $parameter->description
                )
            );
        }

        // TODO: split into multiple methods

        // Add api tokens and other auth parameters as properties on the constructor.
        foreach ($specification->components->securitySchemes ?? [] as $securityScheme) {
            if ($securityScheme->type === SecuritySchemeType::apiKey) {
                MethodGeneratorHelper::addParameterAsPromotedProperty(
                    $classConstructor,
                    $this->getApiKeyParameter($securityScheme)
                );
            }

            if ($securityScheme->type === SecuritySchemeType::http) {

                if ($securityScheme->scheme === 'bearer') {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(
                            type: 'string',
                            nullable: false,
                            name: 'bearerToken',
                            description: $securityScheme->description
                        )
                    );

                    continue;
                }

                if ($securityScheme->scheme === 'basic') {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'username', description: $securityScheme->description)
                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'password', description: $securityScheme->description
                        )
                    );

                    continue;
                }

                MethodGeneratorHelper::addParameterAsPromotedProperty(
                    $classConstructor,
                    new Parameter(type: 'string', nullable: false, name: 'token', description: $securityScheme->description)
                );
            }

            if ($securityScheme->type === SecuritySchemeType::oauth2) {
                if ($securityScheme->flows->authorizationCode !== null) {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'clientId'),
                        sensitive: true
                    );

                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'clientSecret'),
                        sensitive: true
                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: true, name: 'authorizationUrl'),
                        $securityScheme->flows->authorizationCode->authorizationUrl ?? null

                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: true, name: 'tokenUrl'),
                        $securityScheme->flows->authorizationCode->tokenUrl ?? null

                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: true, name: 'refreshUrl'),
                        $securityScheme->flows->authorizationCode->refreshUrl ?? null

                    );

                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'array', nullable: true, name: 'scopes'),
                        $securityScheme->flows->authorizationCode->scopes ?? []
                    );
                }

                if ($securityScheme->flows->clientCredentials !== null) {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'clientId'),
                        sensitive: true
                    );

                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'clientSecret'),
                        sensitive: true
                    );

                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: true, name: 'tokenUrl'),
                        $securityScheme->flows->clientCredentials->tokenUrl ?? null

                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: true, name: 'refreshUrl'),
                        $securityScheme->flows->clientCredentials->refreshUrl ?? null

                    );

                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'array', nullable: true, name: 'scopes'),
                        $securityScheme->flows->clientCredentials->scopes ?? []
                    );
                }

                // TODO: Support password grant and other types later.
            }

        }

        return $classType;
    }

    protected function addResolveBaseUrlMethod(ClassType $classType, ApiSpecification $specification): ClassType
    {
        $params = [];
        foreach ($specification->baseUrl->parameters as $parameter) {
            $params[$parameter->name] = sprintf('{$this->%s}', NameHelper::safeVariableName($parameter->name));
        }
        $baseUrlWithParams = TemplateHelper::render($specification->baseUrl->url ?? 'TODO', $params);

        $classType->addMethod('resolveBaseUrl')
            ->setReturnType('string')
            ->setBody(new Literal(sprintf('return "%s";', $baseUrlWithParams)));

        return $classType;
    }

    protected function addAuthenticators(
        PhpNamespace     $namespace,
        ClassType        $classType,
        ApiSpecification $specification

    ): ClassType
    {

        $authenticators = [];

        foreach ($specification->components->securitySchemes ?? [] as $securityScheme) {
            if ($securityScheme->type === SecuritySchemeType::apiKey) {
                $name = NameHelper::safeVariableName(preg_replace('/^X-/', '', $securityScheme->name));
                $authenticators[] = match ($securityScheme->in) {
                    ApiKeyLocation::query => new Literal(sprintf('return new QueryAuthenticator($this->%s, );', $name)),
                    ApiKeyLocation::header => new Literal(sprintf('return new HeaderAuthenticator($this->%s);', $name)),
                    // TODO: Support cookie auth later
                    default => null
                };
            }

            if ($securityScheme->type === SecuritySchemeType::http) {


                if ($securityScheme->scheme === 'bearer') {
                    $authenticators[] = new Literal('return new TokenAuthenticator($this->bearerToken);');
                }


                if ($securityScheme->scheme === 'basic') {
                    $authenticators[] = new Literal('return new BasicAuthenticator($this->username, $this->password)');
                }

                // Fallback for other types of "token" authentication
                $authenticators[] = new Literal('return new TokenAuthenticator($this->token);');
            }

            if ($securityScheme->type === SecuritySchemeType::oauth2) {
                if ($securityScheme->flows->authorizationCode !== null) {

                }

                if ($securityScheme->flows->clientCredentials !== null) {

                }

                // TODO: Support password grant and other types later.
            }

        }

        return $classType;
    }

    protected function getApiKeyParameter(SecurityScheme $securityScheme): Parameter
    {
        dump($securityScheme->name);

        // Remove X- header prefix
        return new Parameter(
            type: 'string',
            nullable: false,
            name: NameHelper::safeVariableName(preg_replace('/^X-/', '', $securityScheme->name)),
            description: $securityScheme->description
        );
    }

    protected function addMethodsForResources(
        PhpNamespace     $namespace,
        ClassType        $classType,
        ApiSpecification $specification
    ): ClassType
    {
        $collections = collect($specification->endpoints)
            ->map(function (Endpoint $endpoint) {
                return NameHelper::connectorClassName($endpoint->collection ?: $this->config->fallbackResourceName);
            })
            ->unique()
            ->sort()
            ->all();

        foreach ($collections as $collection) {
            $resourceClassName = NameHelper::connectorClassName($collection);
            $resourceFQN = "{$this->config->namespace}\\{$this->config->resourceNamespaceSuffix}\\{$resourceClassName}";

            $namespace->addUse($resourceFQN);

            // TODO: method names like "authenticate" will cause name collision with the Connector class methods,
            //  add a list of reserved method names and find a way to rename the method to something else, or add a pre/suffix

            $classType
                ->addMethod(NameHelper::safeVariableName($collection))
                ->setReturnType($resourceFQN)
                ->setBody(
                    new Literal(sprintf('return new %s($this);', $resourceClassName))
                );

        }

        return $classType;
    }
}
