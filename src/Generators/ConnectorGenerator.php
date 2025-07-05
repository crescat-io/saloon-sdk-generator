<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Generator\ApiKeyLocation;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Generator\SecuritySchemeType;
use Crescat\SaloonSdkGenerator\Generator;
use Crescat\SaloonSdkGenerator\Helpers\MethodGeneratorHelper;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use Crescat\SaloonSdkGenerator\Helpers\TemplateHelper;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Saloon\Contracts\Authenticator;
use Saloon\Helpers\OAuth2\OAuthConfig;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Auth\CertificateAuthenticator;
use Saloon\Http\Auth\DigestAuthenticator;
use Saloon\Http\Auth\HeaderAuthenticator;
use Saloon\Http\Auth\MultiAuthenticator;
use Saloon\Http\Auth\QueryAuthenticator;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\OAuth2\AuthorizationCodeGrant;
use Saloon\Traits\OAuth2\ClientCredentialsGrant;
use SensitiveParameter;

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

        $this->addAuthenticators($namespace, $classType, $specification);
        $this->addMethodsForResources($namespace, $classType, $specification);

        $namespace->add($classType);

        return $classFile;
    }

    protected function addConstructor(
        ClassType $classType,
        ApiSpecification $specification
    ): ClassType {
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
                // TODO: Refactor
                $name = NameHelper::safeVariableName(preg_replace('/^X-/', '', $securityScheme->name));
                MethodGeneratorHelper::addParameterAsPromotedProperty(
                    $classConstructor,
                    new Parameter(type: 'string', nullable: false, name: $name, description: $securityScheme->description)
                );
            }

            if ($securityScheme->type === SecuritySchemeType::http) {

                if ($securityScheme->scheme === 'bearer') {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'bearerToken', description: $securityScheme->description)
                    );

                    continue;
                }

                if ($securityScheme->scheme === 'basic' || $securityScheme->scheme === 'digest') {
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'username', description: $securityScheme->description)
                    );
                    MethodGeneratorHelper::addParameterAsPromotedProperty(
                        $classConstructor,
                        new Parameter(type: 'string', nullable: false, name: 'password', description: $securityScheme->description)
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

            if ($securityScheme->type === SecuritySchemeType::mutualTLS) {
                MethodGeneratorHelper::addParameterAsPromotedProperty(
                    $classConstructor,
                    new Parameter(type: 'string', nullable: false, name: 'certPath'),
                );

                MethodGeneratorHelper::addParameterAsPromotedProperty(
                    $classConstructor,
                    new Parameter(type: 'string', nullable: true, name: 'certPassword'),
                    sensitive: true
                );
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
        PhpNamespace $namespace,
        ClassType $classType,
        ApiSpecification $specification

    ): ClassType {

        $authenticators = [];

        foreach ($specification->components->securitySchemes ?? [] as $securityScheme) {
            if ($securityScheme->type === SecuritySchemeType::apiKey) {
                // TODO: Support cookie auth later
                $name = NameHelper::safeVariableName(preg_replace('/^X-/', '', $securityScheme->name));
                switch ($securityScheme->in) {
                    case ApiKeyLocation::query:
                        $authenticators[] = new Literal(sprintf('new QueryAuthenticator("%s", $this->%s)', $securityScheme->name, $name));
                        $namespace->addUse(QueryAuthenticator::class);
                        break;
                    case ApiKeyLocation::header:
                        $authenticators[] = new Literal(sprintf('new HeaderAuthenticator($this->%s, "%s")', $name, $securityScheme->name));
                        $namespace->addUse(HeaderAuthenticator::class);
                        break;
                    default:
                        $authenticators[] = null;
                        break;
                }
            }

            if ($securityScheme->type === SecuritySchemeType::http) {
                switch ($securityScheme->scheme) {
                    case 'bearer':
                        $authenticators[] = new Literal('new TokenAuthenticator($this->bearerToken, "Bearer")');
                        $namespace->addUse(TokenAuthenticator::class);
                        break;
                    case 'basic':
                        $authenticators[] = new Literal('new BasicAuthenticator($this->username, $this->password)');
                        $namespace->addUse(BasicAuthenticator::class);
                        break;
                    case 'digest':
                        // TODO: does this require you to provide a "digest" as well?
                        $authenticators[] = new Literal('new DigestAuthenticator($this->username, $this->password, "digest")');
                        $namespace->addUse(DigestAuthenticator::class);
                        break;
                    default:
                        $authenticators[] = new Literal('new TokenAuthenticator($this->token)');
                        $namespace->addUse(TokenAuthenticator::class);
                        break;
                }
            }

            if ($securityScheme->type === SecuritySchemeType::mutualTLS) {
                $authenticators[] = new Literal('new CertificateAuthenticator($this->certPath, $this->certPassword)');
                $namespace->addUse(CertificateAuthenticator::class);
            }

            if ($securityScheme->type === SecuritySchemeType::oauth2) {

                $namespace
                    ->addUse(OAuthConfig::class)
                    ->addUse(SensitiveParameter::class);

                if ($securityScheme->flows->authorizationCode !== null) {
                    $namespace->addUse(AuthorizationCodeGrant::class);

                    $classType->addTrait(AuthorizationCodeGrant::class);

                    $classType->addMethod('defaultOauthConfig')
                        ->setReturnType(OAuthConfig::class)
                        ->setBody(
                            new Literal(
                                implode("\n", [
                                    'return OAuthConfig::make()',
                                    '->setClientId($this->clientId)',
                                    '->setClientSecret($this->clientSecret)',
                                    '->setDefaultScopes($this->scopes)',
                                    '->setAuthorizeEndpoint($this->authorizationUrl)',
                                    '->setTokenEndpoint($this->tokenUrl);',
                                ])
                            )
                        );

                }

                if ($securityScheme->flows->clientCredentials !== null) {
                    $namespace->addUse(ClientCredentialsGrant::class);

                    $classType->addTrait(ClientCredentialsGrant::class);
                    $classType->addMethod('defaultOauthConfig')
                        ->setReturnType(OAuthConfig::class)
                        ->setBody(
                            new Literal(
                                implode("\n", [
                                    'return OAuthConfig::make()',
                                    '->setClientId($this->clientId)',
                                    '->setClientSecret($this->clientSecret)',
                                    '->setDefaultScopes($this->scopes)',
                                    '->setTokenEndpoint($this->tokenUrl);',
                                ])

                            )
                        );
                }

                // TODO: Support password grant
            }

            // TODO: Support openIdConnect
        }

        $authenticators = array_filter($authenticators);

        // If there is only one authenticator, we can use it as the defaultAuth method.
        if (count($authenticators) === 1) {
            $classType->addMethod('defaultAuth')->setReturnType(Authenticator::class)->setBody(sprintf('return %s;', $authenticators[0]));
            $namespace->addUse(Authenticator::class);
        }

        // If there are multiple authenticators, we need to use the MultiAuthenticator.
        if (count($authenticators) > 1) {
            $namespace->addUse(MultiAuthenticator::class);
            $classType->addMethod('getAuthenticator')
                ->setReturnType(MultiAuthenticator::class)
                ->setBody(
                    new Literal(
                        sprintf(
                            "return new MultiAuthenticator(\n\t%s\n);",
                            implode(",\n\t", $authenticators)
                        )
                    )
                );
        }

        return $classType;
    }

    protected function addMethodsForResources(
        PhpNamespace $namespace,
        ClassType $classType,
        ApiSpecification $specification
    ): ClassType {
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
