<?php

namespace App;

use App\Data\Postman\PostmanCollection;
use App\Data\Saloon\GeneratedFile;
use App\Generators\RequestGenerator;

class Generator
{
    protected RequestGenerator $requestGenerator;

    public function __construct(
        protected PostmanCollection $postmanCollection,

        protected string $requestNamespace = 'Crescat\Paddle\Compiled\Requests',
        protected string $resourceNamespace = 'Crescat\Paddle\Compiled\Resources',
    ) {
        $this->requestGenerator = new RequestGenerator(
            postmanCollection: $this->postmanCollection,
            namespace: $this->requestNamespace
        );
    }

    /**
     * @return GeneratedFile[]
     */
    public static function fromJson(string $json): array
    {
        return self::fromArray(json_decode($json, true));
    }

    /**
     * @return GeneratedFile[]
     */
    public static function fromArray(array $data): array
    {

        return (new self(PostmanCollection::fromJson($data)))->generate();
    }

    /**
     * @return GeneratedFile[]
     */
    public function generate(): array
    {
        // TODO:  FOR EACH REQUEST ENDPOINT
        // TODO:    1. Generate request classes for endpoint
        // TODO:    2. Generate DTO for endpoint

        // TODO:  For each itemGroup
        // TODO:    1. create a Resource class in the Resources folder
        // TODO:    2. FOR EACH RELATED REQUEST CLASS
        // TODO:        1. Add method shortcut (camelcase the request classname) with method params that returns the DTO

        $requests = $this->requestGenerator->generate();

        return $requests;
    }
}
