<?php

namespace App\Data\Postman;

class Event
{
    public function __construct(
        public string $listen,
        public array $script
    ) {
    }

    public static function fromJson(array $json): self
    {
        return new self(
            listen: $json['listen'],
            script: $json['script']
        );
    }
}
