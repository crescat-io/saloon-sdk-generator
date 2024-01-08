<?php

namespace Crescat\SaloonSdkGenerator\Data\Generator;

use Crescat\SaloonSdkGenerator\Data\TaggedOutputFile;
use Nette\PhpGenerator\PhpFile;

class GeneratedCode
{
    /**
     * @param array|PhpFile[] $requestClasses
     * @param array|PhpFile[] $resourceClasses
     * @param array|PhpFile[] $dtoClasses
     * @param array|PhpFile[]|TaggedOutputFile $additionalFiles
     */
    public function __construct(
        public array    $requestClasses = [],
        public array    $resourceClasses = [],
        public array    $dtoClasses = [],
        public ?PhpFile $connectorClass = null,
        public array    $additionalFiles = [],
    )
    {

    }

    /**
     * @param string $tag
     * @return array|TaggedOutputFile[]
     */
    public function getWithTag(string $tag): array
    {
        return collect($this->additionalFiles)
            ->whereInstanceOf(TaggedOutputFile::class)
            ->filter(fn(TaggedOutputFile $file) => $file->tag === $tag)
            ->values()
            ->toArray();
    }

    public function addAdditionalFile(PhpFile|TaggedOutputFile $additionalFile): static
    {
        $this->additionalFiles[] = $additionalFile;

        return $this;
    }
}
