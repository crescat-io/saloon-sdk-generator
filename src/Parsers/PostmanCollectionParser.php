<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Crescat\SaloonSdkGenerator\Data\Postman\Item;
use Crescat\SaloonSdkGenerator\Data\Postman\ItemGroup;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
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
        return $this->parseItems($this->postmanCollection->item);
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
            method: $item->request->method,
            pathSegments: $item->request->url->path,
            collection: end($this->collectionQueue),
            response: $item->request->body?->rawAsJson(),
            description: $item->description,
            queryParameters: collect($item->request->url->query)->map(function ($param) {
                if (! Arr::get($param, 'key')) {
                    return null;
                }

                return new Parameter(
                    type: 'string',
                    nullable: true,
                    name: Arr::get($param, 'key'),
                    description: Arr::get($param, 'description', '')
                );
            })->filter()->values()->toArray(),
            pathParameters: collect($item->request->url->query)->map(function ($param) {
                if (! Arr::get($param, 'key')) {
                    return null;
                }

                return new Parameter(
                    type: 'string',
                    nullable: true,
                    name: Arr::get($param, 'key'),
                    description: Arr::get($param, 'description', ''),
                );
            })->filter()->values()->toArray(),

            //            bodyParameters: collect(Utils::extractExpectedTypes($item->request->body?->rawAsJson()) ?? [])
            //                ->filter()
            //                ->map(function ($bodyParam, $key ) {
            //
            //
            //
            //                    dump($key);
            //                    dump($bodyParam);
            //
            //                    return new Parameter(
            //                        type: 'mixed',
            //                        name: $bodyParam,
            //                    );
            //                })
            //                ->toArray()

        );
    }
}
