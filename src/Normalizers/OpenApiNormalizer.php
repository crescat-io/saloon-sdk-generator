<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Normalizers;

use cebe\openapi\json\JsonPointer;
use cebe\openapi\ReferenceContext;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\Responses;
use cebe\openapi\spec\Schema;
use cebe\openapi\spec\Type;
use Crescat\SaloonSdkGenerator\Helpers\NameHelper;
use InvalidArgumentException;

class OpenApiNormalizer
{
    protected array $normalizedSchemas = [];

    public function __construct(protected OpenApi $spec)
    {
    }

    public function normalize(): OpenApi
    {
        $this->normalizeBodies();
        $this->normalizeResponses();

        $this->normalizedSchemas = [];
        // Set $createUnlessRef to false at this (top) level, so that we don't re-create all the existing schemas
        $this->normalizeSchemas($this->spec->components->schemas, createUnlessRef: false);

        // Now that we've normalized all the schemas and references, we can resolve them
        $context = new ReferenceContext($this->spec, '/');
        $this->spec->resolveReferences($context);

        return $this->spec;
    }

    /**
     * @param  Schema[]|Reference[]  $schemas
     * @param  bool  $createUnlessRef  If true, treat any non-reference schemas as inline schemas that need to be
     *                                 added to the spec's /components/schemas section.
     * @return Schema[]|Reference[]
     */
    protected function normalizeSchemas(array $schemas, bool $createUnlessRef): array
    {
        $normalized = [];
        foreach ($schemas as $name => $schema) {
            $safeName = is_string($name) ? NameHelper::normalize($name) : null;
            $normalized[$safeName] = $this->normalizeSchema($schema, $safeName, $createUnlessRef);
        }

        return $normalized;
    }

    protected function normalizeSchema(Schema|Reference $schema, ?string $name = null, bool $createUnlessRef = true): Schema|Reference
    {
        if ($schema instanceof Reference) {
            return $schema;
        }

        if (! $name && $createUnlessRef) {
            throw new InvalidArgumentException(
                'Cannot create a schema without a name. If $createUnlessRef is true, $name must not be null.'
            );
        }

        if (array_key_exists($name, $this->normalizedSchemas)) {
            return $this->normalizedSchemas[$name];
        }

        if (! $schema->title) {
            $schema->title = $name;
        }

        if ($schema->allOf) {
            $allOf = [];
            foreach ($schema->allOf as $i => $subSchema) {
                $allOf[] = $this->normalizeSchema($subSchema, "$name allOf $i", false);
            }
            $schema->allOf = $allOf;
        } elseif (Type::isScalar($schema->type)) {
            return $schema;
        } elseif ($schema->type === Type::ARRAY) {
            $schema->items = $this->normalizeSchema($schema->items, NameHelper::singularFromList($name ?? ''));
        } elseif ($schema->type === Type::OBJECT) {
            $schema->properties = $this->normalizeSchemas($schema->properties, true);
        }

        if ($createUnlessRef) {
            $matchingRef = $this->findMatchingSchema($schema, $name);
            if ($matchingRef) {
                $schema = $matchingRef;
            } else {
                $schema = $this->addSchema($schema, $name);
            if (!is_bool($schema->additionalProperties)) {
                $additionalProperties = $schema->additionalProperties;
                $schema->additionalProperties = $this->normalizeSchema(
                    $additionalProperties,
                    $additionalProperties->title ?? null,
                    false
                );
            }
            }
        }

        // We occasionally want schemas to be anonymous, like an inline schema in an allOf.
        // There's no good way to name them, and we can parse them without a name
        if ($name) {
            $this->normalizedSchemas[$name] = $schema;
        }

        return $schema;
    }

    protected function normalizeBodies(): void
    {
        $this->mapOperations(function (Operation &$operation) {
            if (! $operation->requestBody) {
                return;
            }

            $requestBody = $operation->requestBody;
            $content = $requestBody->content;
            foreach ($content as $contentType => $mediaType) {
                $schema = $mediaType->schema;
                if ($schema instanceof Reference) {
                    continue;
                }

                $schemaName = NameHelper::normalize(
                    $schema->title ?? NameHelper::safeClassName($operation->operationId, 'Request')
                );
                $matchingRef = $this->findMatchingSchema($schema, $schemaName);
                if ($matchingRef) {
                    $mediaType->schema = $matchingRef;
                } else {
                    $ref = $this->addSchema($schema, $schemaName);
                    $mediaType->schema = $ref;
                }

                $content[$contentType] = $mediaType;
                $requestBody->content = $content;
                $operation->requestBody = $requestBody;
            }
        });
    }

    protected function normalizeResponses(): void
    {
        $this->mapOperations(function (Operation &$operation) {
            $responses = [];
            foreach ($operation->responses->getResponses() as $httpCode => $response) {
                foreach ($response->content as $contentType => $mediaType) {
                    $schema = $mediaType->schema;
                    if ($schema instanceof Reference || Type::isScalar($schema->type)) {
                        continue;
                    }

                    $schemaName = NameHelper::normalize(
                        $schema->title ?? NameHelper::safeClassName($operation->operationId, 'Response')
                    );
                    $matchingRef = $this->findMatchingSchema($schema, $schemaName);
                    if ($matchingRef) {
                        $mediaType->schema = $matchingRef;
                    } else {
                        $ref = $this->addSchema($schema, $schemaName);
                        $mediaType->schema = $ref;
                    }

                    $response->{$contentType} = $mediaType;
                }

                $responses[$httpCode] = $response;
            }

            $operation->responses = new Responses($responses);
        });
    }

    protected function mapOperations(callable $callback): void
    {
        foreach ($this->spec->paths as $path => $item) {
            foreach ($item->getOperations() as $method => &$operation) {
                $callback($operation, $method, $path);
                $item->{$method} = $operation;
            }
            $this->spec->paths[$path] = $item;
        }
    }

    protected function addSchema(Schema $schema, string $name): Reference
    {
        $newRef = "#/components/schemas/$name";

        $destinationRef = new Reference(['$ref' => $newRef], Schema::class);
        $destinationRef->setDocumentContext($this->spec, new JsonPointer(''));
        $components = $this->spec->components;
        $schemas = $components->schemas;
        $schemas[$name] = $schema;
        $components->schemas = $schemas;
        $this->spec->components = $components;

        return $destinationRef;
    }

    protected function findMatchingSchema(Schema $schema, string $schemaName): ?Reference
    {
        foreach ($this->spec->components->schemas as $name => $existingSchema) {
            if ($name === $schemaName && $existingSchema == $schema) {
                return new Reference(['$ref' => "#/components/schemas/$name"]);
            }
        }

        return null;
    }
}
