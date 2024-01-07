<?php

namespace Crescat\SaloonSdkGenerator\Parsers;

use Crescat\SaloonSdkGenerator\Contracts\Parser;
use Crescat\SaloonSdkGenerator\Data\Generator\ApiSpecification;
use Crescat\SaloonSdkGenerator\Data\Generator\Endpoint;
use Crescat\SaloonSdkGenerator\Data\Generator\Method;
use Crescat\SaloonSdkGenerator\Data\Generator\Parameter;
use Illuminate\Support\Str;

class HarParser implements Parser
{
    protected array $harData;

    public function __construct(array $harData)
    {
        $this->harData = $harData;
    }

    public static function build($filePath): self
    {
        $content = file_get_contents($filePath);

        return new self(json_decode($content, true));
    }

    public function parse(): ApiSpecification
    {
        return new ApiSpecification(
            name: 'HAR File', // HAR doesn't have a name field
            description: 'Parsed from HAR file',
            baseUrl: null, // HAR files don't typically have a base URL, but it could be inferred
            securityRequirements: [],
            components: null,
            endpoints: $this->parseItems()

        );
    }

    protected function parseItems(): array
    {
        $requests = [];

        foreach ($this->harData['log']['entries'] as $entry) {

            if (!Str::contains($entry['request']['url'], "/api/")) {
                continue;
            }

            $urlComponents = parse_url($entry['request']['url']);
            $path = $urlComponents['path'] ?? '/';
            $query = $urlComponents['query'] ?? '';


            $requests[] = new Endpoint(X
                name: Str::of($path)->replace("/", " ")->prepend($entry['request']['method'] . " ")->pluralStudly()->toString(),
                method: Method::parse($entry['request']['method']),
                pathSegments: Str::of($path)->trim('/')->explode('/')->toArray(),
                collection: null, //  todo: figure out a smart way to generate a resource, possibly by inferingit from the path segment
                response: null,
                description: $entry['request']['httpVersion'],
                queryParameters: $this->mapParamsFromQuery($query),
                pathParameters: [], // HAR doesn't typically differentiate between path and query params
                bodyParameters: [], // TODO: Extract body params if needed
            );
        }

        return $requests;
    }

    protected function mapParamsFromQuery(string $query): array
    {
        parse_str($query, $params);

        return collect($params)
            ->map(fn($value, $key) => new Parameter(
                type: 'mixed', // We don't infer types here
                nullable: true,
                name: $key,
                description: ''
            ))
            ->all();
    }
}
