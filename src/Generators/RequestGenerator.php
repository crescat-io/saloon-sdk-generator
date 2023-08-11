<?php

namespace Crescat\SaloonSdkGenerator\Generators;

use Crescat\SaloonSdkGenerator\Data\Postman\Item;
use Crescat\SaloonSdkGenerator\Data\Postman\ItemGroup;
use Crescat\SaloonSdkGenerator\Data\Postman\PostmanCollection;
use Crescat\SaloonSdkGenerator\Data\Saloon\GeneratedFile;
use Crescat\SaloonSdkGenerator\Utils;
use DateTime;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method as GeneratedMethod;
use Nette\PhpGenerator\PhpFile;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class RequestGenerator
{
    protected array $usedClassNames = [];

    protected array $collectionQueue = [];

    protected ?ClassType $currentClass = null;

    public function __construct(
        protected PostmanCollection $postmanCollection,
        protected string $namespace,
        protected string $fallbackCollectionName = 'Misc',
        protected array $ignoredParameters = ['order_by', 'after', 'per_page'],
    ) {

    }

    /**
     * @return GeneratedFile[]
     */
    public function generate(): array
    {
        return $this->generateItems($this->postmanCollection->item);
    }

    /**
     * @return GeneratedFile[]
     */
    public function generateItems($items): array
    {
        $requests = [];

        foreach ($items as $item) {

            if ($item instanceof ItemGroup) {
                // Nested resource Ids aka "{customer_id}" are not considered a "collection", skip those
                if (! Str::contains($item->name, ['{', '}'])) {
                    $this->collectionQueue[] = $item->name;
                }

                $requests = [...$requests, ...$this->generateItems($item->item)];
                array_pop($this->collectionQueue);
            }

            if ($item instanceof Item) {

                //                if ($item->id != 'f68bdf64-2b4e-5b98-a46f-3674f9cb2776') {
                //                    continue;
                //                }

                $requests = [...$requests, $this->generateRequestClass($item)];
            }
        }

        return $requests;
    }

    protected function classNameAlreadyUsed($className): bool
    {
        return in_array($className, $this->usedClassNames);
    }

    protected function generateRequestClass(Item $item): GeneratedFile
    {
        $this->currentClass = $this->buildClassDefinition($item);

        $this->buildHttpMethodProperty($item);
        $this->buildQueryParamMethod($item);
        $this->buildBodyDataMethod($item);
        $this->buildEndpointMethod($item);

        $collection = $this->safeVariableName(end($this->collectionQueue));
        $collectionName = Str::studly($collection ?: $this->fallbackCollectionName);

        $file = new PhpFile;
        $file->addNamespace("{$this->namespace}\\{$collectionName}")
            ->addUse(Method::class)
            ->addUse(DateTime::class)
            ->addUse(Request::class)
            ->add($this->currentClass);

        // TODO: Might remove this in the future
        $file->addComment('@noinspection SpellCheckingInspection');

        return new GeneratedFile(
            id: $item->id,
            name: $item->name,
            className: $this->currentClass->getName(),
            collection: $collection,
            collectionName: $collectionName,
            phpFile: $file,
        );
    }

    protected function getOrCreateCurrentConstructor(): GeneratedMethod
    {
        if ($this->currentClass->hasMethod('__construct')) {
            return $this->currentClass->getMethod('__construct');
        }

        return $this->currentClass->addMethod('__construct');
    }

    protected function buildEndpointMethod(Item $item): void
    {
        $this->currentClass->addMethod('resolveEndpoint')
            ->setPublic()
            ->setReturnType('string')
            ->addBody($this->resolvePath($item));
    }

    // TODO: merge into buildEndpointMethod()
    protected function resolvePath(Item $item): string
    {
        // TODO: Unsure what should happen in this case, maybe fallback to "/".
        if ($item->request->url->path == null) {
            return new Literal("return ''; // TODO: implement manually - Path could not be resolved");
        }

        if (is_string($item->request->url->path)) {
            return new Literal(sprintf('return "%s";', $item->request->url->path));
        }

        $segments = [];

        foreach ($item->request->url->path as $segment) {

            if (Str::startsWith($segment, ':')) {
                $variable = collect($item->request->url->variable)->firstWhere('key', Str::after($segment, ':'));

                $propertyName = $this->safeVariableName($variable['key']);

                $this->getOrCreateCurrentConstructor()
                    ->addComment(
                        trim(sprintf('@param string $%s %s', $propertyName, Arr::get($variable, 'description', '')))
                    )
                    ->addPromotedParameter($propertyName)
                    ->setType('string')
                    ->setNullable(false)
                    ->setProtected();

                $segments[] = new Literal(sprintf('{$this->%s}', $propertyName));
            } else {
                $segments[] = $segment;
            }
        }

        return new Literal(sprintf('return "%s";', implode('/', $segments)));
    }

    protected function buildBodyDataMethod(Item $item): void
    {

        $body = $item->request->body?->rawAsJson();

        if (! $body) {
            return;
        }

        $bodyParamNameMapping = [];

        foreach (Utils::extractExpectedTypes($body) as $bodyParam => $paramTypes) {

            if (! $paramTypes) {
                continue;
            }

            $paramTypes = Arr::wrap($paramTypes);
            $bodyParamKey = Str::camel($bodyParam);
            $bodyParamNameMapping[$bodyParam] = $bodyParamKey;

            $this->getOrCreateCurrentConstructor()
                ->addPromotedParameter($bodyParamKey)
                ->setProtected()
                ->setNullable(in_array('null', $paramTypes))
                ->setType($type = match (true) {
                    // TODO: Cleanup and simplify this
                    in_array('string', $paramTypes) => 'string',
                    in_array('uri', $paramTypes) => 'string',

                    in_array('integer', $paramTypes) => 'int',
                    in_array('boolean', $paramTypes) => 'bool',
                    in_array('number', $paramTypes) => 'float',

                    in_array('dateTime', $paramTypes) => 'DateTime',
                    in_array('null-date-time', $paramTypes) => 'DateTime',
                    in_array('string-date-time', $paramTypes) => 'DateTime',

                    in_array('object', $paramTypes) => 'array',
                    in_array('array', $paramTypes) => 'array',
                    default => 'mixed',
                });

            // TODO: Cleanup and simplify this
            $this->getOrCreateCurrentConstructor()->addComment(
                in_array('null', $paramTypes)
                    ? trim("@param ?$type \$$bodyParamKey ")
                    : trim("@param $type \$$bodyParamKey ")
            );
        }

        if (! $bodyParamNameMapping) {
            return;
        }

        $arrayLiteral = (new Dumper)->dump(
            collect($bodyParamNameMapping)
                ->mapWithKeys(fn ($propName, $bodyParamName) => [
                    $bodyParamName => new Literal("\$this->{$propName}"),
                ])
                ->toArray()
        );

        $this->currentClass->addMethod('defaultBody')
            ->setReturnType('array')
            ->addBody("return {$arrayLiteral};");
    }

    protected function safeVariableName($text): string
    {

        $safe = Str::of($text)
            ->remove(["'", "'", '.', ','])
            ->slug(' ')
            ->camel()
            ->toString() ?: $this->fallbackName();

        dump("$text -> $safe");

        return $safe;
    }

    protected function buildQueryParamMethod(Item $item): void
    {
        $queryParams = $item->request->url->query ?? [];
        // Query params
        if (! $queryParams) {
            return;
        }

        $queryParamNameMapping = [];

        foreach ($queryParams as $queryParam) {

            if (! $queryParam) {
                continue;
            }

            $rawKey = Arr::get($queryParam, 'key');

            // TODO: Currently does not handle "array"-like query params  like this:
            //  - "key": "documents[proof_of_registration][files][1]",
            //  - "key": "expand[0]",

            $key = $this->safeVariableName($rawKey);
            $value = Arr::get($queryParam, 'value');

            $queryParamNameMapping[$rawKey] = $key;

            $promotedParam = $this->getOrCreateCurrentConstructor()
                ->addPromotedParameter($key)
                ->setProtected()
                ->setNullable();

            // If the default value specified looks like a "type" annotation, dont use it as the default value.
            if ($value && ! Str::contains($value, ['<', '>'])) {
                $promotedParam->setDefaultValue($value);
            }

            $this->getOrCreateCurrentConstructor()->addComment(
                trim("@param mixed \$$key ".Arr::get($queryParam, 'description', ''))
            );
        }

        if (! $queryParamNameMapping) {
            return;
        }

        $arrayLiteral = (new Dumper)->dump(
            collect($queryParamNameMapping)
                ->mapWithKeys(fn ($propName, $queryParamName) => [
                    $queryParamName => new Literal("\$this->{$propName}"),
                ])
                ->toArray()
        );

        $this->currentClass->addMethod('defaultQuery')
            ->setReturnType('array')
            ->addBody("return {$arrayLiteral};");

    }

    protected function buildHttpMethodProperty(Item $item): void
    {
        $this->currentClass->addProperty('method')
            ->setProtected()
            ->setType(Method::class)
            ->setValue(
                new Literal(
                    sprintf('Method::%s', $item->request?->method)
                )
            );
    }

    /**
     * Generate Classname based on the name of the response,
     * assumes names are good, short and descriptive and not used as descriptions.
     *
     * Good: Add Customer Subscription
     * Bad: Adds a subscription to a customer
     *
     * Examples:
     * - Update a subscription -> UpdateSubscription (paddle)
     * - Retrieve a Session's line items -> RetrieveSessionLineItems (stripe)
     */
    protected function endpointNameToClassName($name): string
    {
        $modified = Str::of($name)
            ->replace(' a ', ' ')
            ->replace(' an ', ' ')
            ->replace("'s ", ' ')
            ->replace(':', ' ')
            ->replace('.', ' ')
            ->replace(',', ' ')
            ->replace('(', ' ')
            ->replace(')', ' ')
            ->replace('/', ' ')
            ->studly()
            ->toString();

        dump("CLASSNAME: $name -> $modified");

        return $modified;
    }

    protected function fallbackName(): string
    {
        return sprintf('UnnamedRequest%s', Str::random(3));
    }

    protected function buildClassDefinition(Item $item): ClassType
    {

        $className = $this->endpointNameToClassName($item->name);

        // Deal with a potential classname conflict
        if ($this->classNameAlreadyUsed($className)) {
            // TODO: Handle this better in the future, should not occur in our use case, but if it does, make it obvious
            $className = sprintf('%sConflict%s', $className, Str::random(3));
        }

        // Edge case
        if ($className == null || $className == '') {
            $className = $this->fallbackName();
        }
        dump("GEN: $className");

        $this->usedClassNames[] = $className;

        return (new ClassType($className))
            ->setExtends(Request::class)
            ->setComment($item->name)
            ->addComment('')
            ->addComment(
                Utils::wrapLongLines($item->request?->description ?? '')
            );
    }
}
