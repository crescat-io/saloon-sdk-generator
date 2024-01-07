<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Nette\PhpGenerator\PhpFile;

class GeneratedCode
{
    /**
     * @param  array|PhpFile[]  $requestClasses
     * @param  array|PhpFile[]  $resourceClasses
     * @param  array|PhpFile[]  $dtoClasses
     * @param  array|PhpFile[]  $generatedFiles
     */
    public function __construct(
        public array $requestClasses = [],
        public array $resourceClasses = [],
        public array $dtoClasses = [],
        public ?PhpFile $connectorClass = null,
        public array $generatedFiles = [],
    ) {

    }
}
