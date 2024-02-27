<?php

namespace Crescat\SaloonSdkGenerator\Data;

use Nette\PhpGenerator\PhpFile;

class TaggedOutputFile
{
    public function __construct(
        public readonly string $tag,
        public readonly string|PhpFile $file,
        public readonly ?string $path = null
    ) {

    }
}
