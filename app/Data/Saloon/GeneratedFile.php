<?php

namespace App\Data\Saloon;

use Nette\PhpGenerator\PhpFile;

class GeneratedFile
{
    public function __construct(
        public ?string $id,
        public string $name,
        public string $className,
        public ?string $collection,
        public ?string $collectionName,
        public PhpFile $phpFile,

    ) {

    }
}
