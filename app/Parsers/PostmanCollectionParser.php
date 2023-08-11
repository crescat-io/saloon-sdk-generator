<?php

namespace App\Parsers;

use App\Data\Generator\Endpoint;
use App\Data\Generator\Parameter;
use App\Data\Postman\Item;
use App\Data\Postman\ItemGroup;
use App\Data\Postman\PostmanCollection;
use App\Utils;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PostmanCollectionParser implements Parser
{
    protected array $collectionQueue = [];

    public function __construct(protected PostmanCollection $postmanCollection)
    {
    }

    /**
     * @return array|Endpoint[]
     */
    public function parse(): array
    {
        /** @noinspection PhpParamsInspection */
        return $this->parseItems($this->postmanCollection->item);
    }

    /**
     * @return array|Endpoint[]
     */
    public function parseItems(ItemGroup $items): array
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
            method: $item->request->method,
            pathSegments: $item->request->url->path,
            collection: end($this->collectionQueue),
            response: $item->request->body?->rawAsJson(),
            description: $item->description,
            queryParameters: array_map(
                callback: fn ($param) => new Parameter(
                    type: 'string',
                    name: Arr::get($param, 'key'),
                    description: Arr::get($param, 'description', ''),
                ),
                array: $item->request->url->query
            ),
            pathParameters: array_map(
                callback: fn ($param) => new Parameter(
                    type: 'string',
                    name: Arr::get($param, 'key'),
                    description: Arr::get($param, 'description', ''),
                ),
                array: $item->request->url->variable
            ),
            bodyParameters: array_map(
                callback: function ($paramTypes, $bodyParam) {

                    if (! $paramTypes) {
                        return null;
                    }

                    return new Parameter(
                        type: 'mixed',
                        name: $bodyParam,
                    );
                },
                array: Utils::extractExpectedTypes($item->request->body?->rawAsJson())
            ),
        );
    }
}
