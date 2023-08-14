<?php

namespace Crescat\SaloonSdkGenerator\Data\Postman;

class PostmanCollection
{
    /**
     * @param  Item[]|ItemGroup[]  $item
     */
    public function __construct(
        public Info $info,
        public array $item,
        public array $variables
    ) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            info: Info::fromJson($json['info']),
            item: array_map(fn ($item) => ItemGroup::parseItem($item), $json['item'] ?? []),
            variables: array_map(fn ($item) => Variable::fromJson($item), $json['variable'] ?? []),
        );
    }

    public static function fromString(string $content): self
    {
        return self::fromJson(json_decode($content, true));
    }
}
