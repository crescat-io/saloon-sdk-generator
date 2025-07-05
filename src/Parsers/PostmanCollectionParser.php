<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\BaseUrl;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Postman\Item;
use Crescat\SaloonSdkGenerator\Data\Postman\ItemGroup;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
use Crescat\SaloonSdkGenerator\Data\Postman\Variable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostmanCollectionParser implements Parser
{
    protected array $collectionQueue = [];

    public function __construct(protected PostmanCollection $postmanCollection)
    {

    }

    public static function build($content): self
    {
        return json_validate($content)
            ? new self(PostmanCollection::fromString($content))
            : new self(PostmanCollection::fromString(file_get_contents($content)));
    }

    public function parse(): ApiSpecification
    {
        $baseUrlVariable = collect($this->postmanCollection->variables)->firstWhere(
            fn (Variable $var) => $var->key == 'baseUrl'
        );

        return new ApiSpecification(
            name: $this->postmanCollection->info->name,
            description: $this->postmanCollection->info->description,
            baseUrl: new BaseUrl($baseUrlVariable?->value ?: ''),
            securityRequirements: [],
            components: null,
            endpoints: $this->parseItems($this->postmanCollection->item)
        );
    }

    /**
     * @return array|Endpoint[]
     */
    public function parseItems(array $items): array
    {
        $requests = [];

        foreach ($items as $item) {

            if ($item instanceof ItemGroup) {
                // Nested resource Ids aka "{customer_id}" are not considered a "collection", skip those
                if (! Str::contains($item->name, ['{', '}'])) {
                    $this->collectionQueue[] = $item->name;
                }

                $requests = [...$requests, ...$this->parseItems($item->item)];
                array_pop($this->collectionQueue);
            }

            if ($item instanceof Item) {
                $requests = [...$requests, $this->parseEndpoint($item)];
            }
        }

        return $requests;
    }

    public function parseEndpoint(Item $item): ?Endpoint
    {
        return new Endpoint(
            name: $item->name,
            method: Method::parse($item->request->method),
            pathSegments: $item->request->url->path,
            collection: end($this->collectionQueue),
            response: $item->request->body?->rawAsJson(),
            description: $item->description,
            queryParameters: $this->parseQueryParameters($item),
            pathParameters: $this->parsePathParameters($item),
            bodyParameters: $this->parseBodyParameters($item),
            headerParameters: $this->parseHeaderParameters($item),
        );
    }

    protected function parseQueryParameters(Item $item): array
    {
        return collect($item->request->url->query)->map(function ($param) {
            if (! Arr::get($param, 'key')) {
                return null;
            }

            return new Parameter(
                type: 'string',
                nullable: true,
                name: Arr::get($param, 'key'),
                description: Arr::get($param, 'description', '')
            );
        })->filter()->values()->toArray();
    }

    protected function parsePathParameters(Item $item): array
    {
        return collect($item->request->url->path)
            ->filter(fn ($segment) => Str::startsWith($segment, ':'))
            ->map(function ($param) {
                return new Parameter(
                    type: 'string',
                    nullable: false,
                    name: $param,
                );
            })
            ->filter()
            ->values()
            ->toArray();
    }

    protected function parseBodyParameters(Item $item): array
    {
        $body = $item->request->body?->rawAsJson();

        if (! $body) {
            return [];
        }

        // TODO:
        //  This body parameter looks like it is a list of objects,
        //  currently don't generate DTOs, so lets simply tag this as a nullable array for now,
        //  in the future we can dive deeper into the structure of the individual items to generate
        //  a PHPDoc array shape or DTO that represents the permitted options, however the latter assumes
        //  that the example provided is complete, which is rarely the case.
        if (array_is_list($body)) {
            return [
                new Parameter(
                    type: 'array',
                    nullable: true,
                    name: 'values', // TODO: No way to derive a useful names, let's just use a standard name.
                ),
            ];
        }

        return collect(array_keys($body))
            ->filter()
            ->map(function ($param) {
                return new Parameter(
                    type: 'mixed',
                    nullable: true,
                    name: $param,
                );
            })
            ->toArray();
    }

    protected function parseHeaderParameters(Item $item): array
    {
        if (! $item->request->header) {
            return [];
        }

        return collect($item->request->header)
            ->filter(fn ($header) => Arr::get($header, 'key'))
            ->map(function ($header) {
                return new Parameter(
                    type: 'string',
                    nullable: true,
                    name: Arr::get($header, 'key'),
                    description: Arr::get($header, 'description', '')
                );
            })
            ->values()
            ->toArray();
    }
}
