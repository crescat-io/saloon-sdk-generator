<?php

declare(strict_types=1);

namespace Crescat\SaloonSdkGenerator\Normalizers;

use cebe\openapi\exceptions\UnresolvableReferenceException;
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

final class OpenApiNormalizer
{
    private SchemaCache $schemaCache;

    private ReferenceContext $context;

    /**
     * @throws UnresolvableReferenceException
     */
    public function __construct(private OpenApi $spec)
    {
        $this->schemaCache = new SchemaCache();
        $this->context = new ReferenceContext($this->spec, '/');
    }

    /**
     * @throws UnresolvableReferenceException
     */
    public function normalize(): OpenApi
    {
        $this->normalizeBodies();
        $this->normalizeResponses();

        $this->schemaCache->reset();
        // Set $createUnlessRef to false at this (top) level, so that we don't re-create all the existing schemas
        $this->normalizeSchemas($this->spec->components->schemas, createUnlessRef: false);

        // Now that we've normalized all the schemas and references, we can resolve them
        $this->spec->resolveReferences($this->context);

        return $this->spec;
    }

    /**
     * @param  Schema[]|Reference[]  $schemas
     * @param  bool  $createUnlessRef  If true, treat any non-reference schemas as inline schemas that need to be
     *                                 added to the spec's /components/schemas section.
     * @return Schema[]|Reference[]
     */
    private function normalizeSchemas(array $schemas, bool $createUnlessRef): array
    {
        $normalized = [];
        foreach ($schemas as $name => $schema) {
            $normalized[$name] = $this->normalizeSchema($schema, $name, $createUnlessRef);
        }

        return $normalized;
    }

    private function normalizeSchema(
        Schema|Reference $schema,
        ?string $name = null,
        bool $createUnlessRef = true
    ): Schema|Reference {
        if ($schema instanceof Reference) {
            return $schema;
        }

        if (! $name && $createUnlessRef && $schema->type === Type::OBJECT) {
            throw new InvalidArgumentException(
                'Cannot create an object schema without a name. If $createUnlessRef is true, $name must not be null.'
            );
        }

        $normalized = $this->schemaCache->getSchema($schema, $name, $this->context);
        if ($normalized) {
            return $normalized;
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
            $schema->items = $this->normalizeSchema($schema->items, $name);
        } elseif ($schema->type === Type::OBJECT) {
            $schema = $this->normalizeObject($schema, $createUnlessRef, $name);
        }
        $this->schemaCache->add($name, $schema);

        return $schema;
    }

    private function normalizeBodies(): void
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

    private function normalizeResponses(): void
    {
        $this->mapOperations(function (Operation &$operation) {
            $responses = [];
            foreach ($operation->responses->getResponses() as $httpCode => $response) {
                if (! $response->content || $httpCode === 204) {
                    $responses[$httpCode] = $response;

                    continue;
                }

                foreach ($response->content as $contentType => $mediaType) {
                    $schema = $mediaType->schema;
                    if ($schema instanceof Reference) {
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

    private function mapOperations(callable $callback): void
    {
        foreach ($this->spec->paths as $path => $item) {
            foreach ($item->getOperations() as $method => &$operation) {
                $callback($operation, $method, $path);
                $item->{$method} = $operation;
            }
            $this->spec->paths[$path] = $item;
        }
    }

    private function addSchema(Schema $schema, string $name): Reference
    {
        $newRef = "#/components/schemas/$name";

        $destinationRef = new Reference(['$ref' => $newRef], Schema::class);
        $destinationRef->setDocumentContext($this->spec, new JsonPointer(''));

        $components = $this->spec->components;
        $schemas = $components->schemas;

        $schemas[$name] = $schema;
        $this->schemaCache->add($name, $schema);

        $components->schemas = $schemas;
        $this->spec->components = $components;

        return $destinationRef;
    }

    private function findMatchingSchema(Schema $schema, string $schemaName): ?Reference
    {
        foreach ($this->spec->components->schemas as $name => $existingSchema) {
            if ($name === $schemaName && $existingSchema == $schema) {
                return new Reference(['$ref' => "#/components/schemas/$name"]);
            }
        }

        return null;
    }

    private function normalizeObject(Schema|Reference $schema, bool $createUnlessRef, ?string $name): Reference|Schema
    {
        $schema->properties = $this->normalizeSchemas($schema->properties, true);

        if (! is_bool($schema->additionalProperties)) {
            $additionalProperties = $schema->additionalProperties;
            $schema->additionalProperties = $this->normalizeSchema(
                $additionalProperties,
                $additionalProperties->title ?? null,
                false
            );
        }

        if ($createUnlessRef) {
            $matchingRef = $this->findMatchingSchema($schema, $name);
            if ($matchingRef) {
                $schema = $matchingRef;
            } else {
                if ($this->schemaCache->has($name)) {
                    $existingSchema = $this->schemaCache->getSchema($schema, $name, $this->context);
                    // If the name is in the cache but the schema is different, we need a new name,
                    // because this is an inline schema with the same name but a different definition
                    if (! $existingSchema) {
                        $newName = $name;
                        $i = 2;
                        do {
                            $newName = $name.$i;
                            $i++;
                        } while ($this->schemaCache->has($newName));

                        $schema->title = $newName;
                        $schema = $this->addSchema($schema, $newName);
                    } else {
                        if ($existingSchema instanceof Reference) {
                            $refText = $existingSchema->getSerializableData()['$ref'];
                        } else {
                            $refText = "#/components/schemas/$name";
                        }
                        $destinationRef = new Reference(['$ref' => $refText], Schema::class);
                        $destinationRef->setDocumentContext($this->spec, new JsonPointer(''));
                        $schema = $destinationRef;
                    }
                } else {
                    $schema = $this->addSchema($schema, $name);
                }
            }
        }

        return $schema;
    }
}
