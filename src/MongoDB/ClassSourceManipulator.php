<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\MongoDB;

use Doctrine\ODM\MongoDB\Mapping\Attribute\Field;
use Doctrine\ODM\MongoDB\Types\Type;
use Exception;
use PhpParser\Builder;
use PhpParser\Lexer;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\Parser;
use PhpParser\PhpVersion;
use PhpParser\Token;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameValue;
use Symfony\Bundle\MakerBundle\Util\ClassSource\Model\ClassProperty;
use Symfony\Bundle\MakerBundle\Util\PrettyPrinter;

use function array_key_exists;
use function array_keys;
use function array_map;
use function array_merge;
use function array_search;
use function array_splice;
use function array_unshift;
use function array_values;
use function end;
use function get_debug_type;
use function gettype;
use function is_int;
use function preg_replace;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strpos;
use function strrpos;
use function substr;

/** @internal */
final class ClassSourceManipulator
{
    private const string CONTEXT_OUTSIDE_CLASS = 'outside_class';
    private const string CONTEXT_CLASS         = 'class';
    private const string CONTEXT_CLASS_METHOD  = 'class_method';

    private Parser $parser;
    private Lexer\Emulative $lexer;
    private PrettyPrinter $printer;
    private ConsoleStyle|null $io = null;

    private array|null $oldStmts = null;

    /** @var Token[] $oldTokens */
    private array $oldTokens = [];

    /** @var Node[] $newStmts */
    private array $newStmts = [];

    /** @var string[] $pendingComments */
    private array $pendingComments = [];

    public function __construct(
        private string $sourceCode,
        private readonly bool $useAttributesForDoctrineMapping = true,
    ) {
        $this->lexer  = new Lexer\Emulative(
            PhpVersion::fromString('8.4'),
        );
        $this->parser = new Parser\Php7($this->lexer);

        $this->printer = new PrettyPrinter();

        $this->setSourceCode($sourceCode);
    }

    public function setIo(ConsoleStyle $io): void
    {
        $this->io = $io;
    }

    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /** @throws ReflectionException */
    public function addDocumentField(ClassProperty $mapping): void
    {
        $typeHint = MongoDBHelper::getPropertyTypeForField($mapping->type);
        if ($typeHint && MongoDBHelper::canFieldTypeBeInferredByPropertyType($mapping->type, $typeHint)) {
            $mapping->needsTypeHint = false;
        }

        if ($mapping->needsTypeHint) {
            $typeConstant = MongoDBHelper::getTypeConstant($mapping->type);
            if ($typeConstant) {
                $this->addUseStatementIfNecessary(Type::class);
                $mapping->type = $typeConstant;
            }
        }

        $nullable = $mapping->nullable ?? false;

        $attributes[] = $this->buildAttributeNode(Field::class, $mapping->getAttributes(), 'ODM');

        $defaultValue = null;

        if ($mapping->enumType !== null) {
            if ($typeHint === 'array') {
                // still need to add the use statement
                $this->addUseStatementIfNecessary($mapping->enumType);

                if (! $nullable) {
                    $defaultValue = new Node\Expr\Array_([], ['kind' => Node\Expr\Array_::KIND_SHORT]);
                }
            } else {
                $typeHint = $this->addUseStatementIfNecessary($mapping->enumType);
            }
        } elseif ($typeHint === 'array' && ! $nullable) {
            $defaultValue = new Node\Expr\Array_([], ['kind' => Node\Expr\Array_::KIND_SHORT]);
        } elseif ($typeHint && $typeHint[0] === '\\' && strpos($typeHint, '\\', 1) !== false) {
            $typeHint = $this->addUseStatementIfNecessary(substr($typeHint, 1));
        }

        $propertyType = $typeHint;
        if ($propertyType && ! $defaultValue && $propertyType !== 'mixed' && $nullable) {
            // all property types
            $propertyType .= '|null';
        }

        $this->addProperty(
            mapping: $mapping,
            defaultValue: $defaultValue,
            attributes: $attributes,
            comments: $mapping->comments,
            propertyType: $propertyType,
        );
    }

    /**
     * @param array<Node\Attribute|Node\AttributeGroup> $attributes
     * @param string[]                                  $comments
     */
    public function addProperty(ClassProperty $mapping, mixed $defaultValue, array $attributes = [], array $comments = [], string|null $propertyType = null): void
    {
        $name = $mapping->propertyName;
        if ($this->propertyExists($name)) {
            // we never overwrite properties
            return;
        }

        $newPropertyBuilder = (new Builder\Property($name))->makePublic();

        if ($propertyType !== null) {
            $newPropertyBuilder->setType($propertyType);
        }

        if ($this->useAttributesForDoctrineMapping) {
            foreach ($attributes as $attribute) {
                $newPropertyBuilder->addAttribute($attribute);
            }
        }

        if ($comments) {
            $newPropertyBuilder->setDocComment($this->createDocBlock($comments));
        }

        if ($defaultValue !== null || $mapping->nullable) {
            $newPropertyBuilder->setDefault($defaultValue);
        }

        $newPropertyNode = $newPropertyBuilder->getNode();

        $this->addNodeAfterProperties($newPropertyNode);
    }

    /** @return string The alias to use when referencing this class */
    public function addUseStatementIfNecessary(string $class): string
    {
        $shortClassName = Str::getShortClassName($class);
        if ($this->isInSameNamespace($class)) {
            return $shortClassName;
        }

        $namespaceNode = $this->getNamespaceNode();

        $targetIndex      = null;
        $addLineBreak     = false;
        $lastUseStmtIndex = null;
        foreach ($namespaceNode->stmts as $index => $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                // I believe this is an array to account for use statements with {}
                foreach ($stmt->uses as $use) {
                    $alias = $use->alias ? $use->alias->name : $use->name->getLast();

                    // the use statement already exists? Don't add it again
                    if ($class === (string) $use->name) {
                        return $alias;
                    }

                    if (str_starts_with($class, $alias)) {
                        return $class;
                    }

                    if ($alias === $shortClassName) {
                        // we have a conflicting alias!
                        // to be safe, use the fully-qualified class name
                        // everywhere and do not add another use statement
                        return '\\' . $class;
                    }
                }

                // if $class is alphabetically before this use statement, place it before
                // only set $targetIndex the first time you find it
                if ($targetIndex === null && Str::areClassesAlphabetical($class, (string) $stmt->uses[0]->name)) {
                    $targetIndex = $index;
                }

                $lastUseStmtIndex = $index;
            } elseif ($stmt instanceof Node\Stmt\Class_) {
                if ($targetIndex !== null) {
                    // we already found where to place the use statement

                    break;
                }

                // we hit the class! If there were any use statements,
                // then put this at the bottom of the use statement list
                if ($lastUseStmtIndex !== null) {
                    $targetIndex = $lastUseStmtIndex + 1;
                } else {
                    $targetIndex  = $index;
                    $addLineBreak = true;
                }

                break;
            }
        }

        if ($targetIndex === null) {
            throw new Exception('Could not find a class!');
        }

        $newUseNode = (new Builder\Use_($class, Node\Stmt\Use_::TYPE_NORMAL))->getNode();
        array_splice(
            $namespaceNode->stmts,
            $targetIndex,
            0,
            $addLineBreak ? [$newUseNode, $this->createBlankLineNode(self::CONTEXT_OUTSIDE_CLASS)] : [$newUseNode],
        );

        $this->updateSourceCodeFromNewStmts();

        return $shortClassName;
    }

    /**
     * Builds a PHPParser attribute node.
     *
     * @param string               $attributeClass  The attribute class which should be used for the attribute E.g. #[Column()]
     * @param array<string, mixed> $options         The named arguments for the attribute ($key = argument name, $value = argument value)
     * @param ?string              $attributePrefix If a prefix is provided, the node is built using the prefix. E.g. #[ORM\Column()]
     *
     * @throws Exception
     */
    public function buildAttributeNode(string $attributeClass, array $options, string|null $attributePrefix = null): Node\Attribute
    {
        $options = $this->sortOptionsByClassConstructorParameters($options, $attributeClass);

        $nodeArguments = array_map(function (string $option, mixed $value) {
            $context = $this;

            if ($value === null) {
                return new Node\NullableType(new Node\Identifier($option));
            }

            // Use the Doctrine Types constant
            if ($option === 'type' && str_starts_with($value, 'Type::')) {
                return new Node\Arg(
                    new Node\Expr\ConstFetch(new Node\Name($value)),
                    false,
                    false,
                    [],
                    new Node\Identifier($option),
                );
            }

            if ($option === 'enumType') {
                return new Node\Arg(
                    new Node\Expr\ConstFetch(new Node\Name(Str::getShortClassName($value) . '::class')),
                    false,
                    false,
                    [],
                    new Node\Identifier($option),
                );
            }

            return new Node\Arg($context->buildNodeExprByValue($value), false, false, [], new Node\Identifier($option));
        }, array_keys($options), array_values($options));

        $class = $attributePrefix ? sprintf('%s\\%s', $attributePrefix, Str::getShortClassName($attributeClass)) : Str::getShortClassName($attributeClass);

        return new Node\Attribute(
            new Node\Name($class),
            $nodeArguments,
        );
    }

    private function updateSourceCodeFromNewStmts(): void
    {
        $newCode = $this->printer->printFormatPreserving(
            $this->newStmts,
            $this->oldStmts,
            $this->oldTokens,
        );

        // replace the 3 "fake" items that may be in the code (allowing for different indentation)
        $newCode = preg_replace('/(\ |\t)*private\ \$__EXTRA__LINE;/', '', $newCode);
        $newCode = preg_replace('/use __EXTRA__LINE;/', '', $newCode);
        $newCode = preg_replace('/(\ |\t)*\$__EXTRA__LINE;/', '', $newCode);

        // process comment lines
        foreach ($this->pendingComments as $i => $comment) {
            // sanity check
            $placeholder = sprintf('$__COMMENT__VAR_%d;', $i);
            if (! str_contains($newCode, $placeholder)) {
                // this can happen if a comment is createSingleLineCommentNode()
                // is called, but then that generated code is ultimately not added
                continue;
            }

            $newCode = str_replace($placeholder, '// ' . $comment, $newCode);
        }

        $this->pendingComments = [];

        $this->setSourceCode($newCode);
    }

    private function setSourceCode(string $sourceCode): void
    {
        $this->sourceCode = $sourceCode;
        $this->oldStmts   = $this->parser->parse($sourceCode);

        $this->oldTokens = $this->parser->getTokens();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NodeVisitor\CloningVisitor());
        $traverser->addVisitor(new NodeVisitor\NameResolver(null, ['replaceNodes' => false]));
        $this->newStmts = $traverser->traverse($this->oldStmts);
    }

    private function getClassNode(): Node\Stmt\Class_
    {
        $node = $this->findFirstNode(static fn ($node) => $node instanceof Node\Stmt\Class_);

        if (! $node) {
            throw new Exception('Could not find class node');
        }

        return $node;
    }

    private function getNamespaceNode(): Node\Stmt\Namespace_
    {
        $node = $this->findFirstNode(static fn ($node) => $node instanceof Node\Stmt\Namespace_);

        if (! $node) {
            throw new Exception('Could not find namespace node');
        }

        return $node;
    }

    private function findFirstNode(callable $filterCallback): Node|null
    {
        $traverser = new NodeTraverser();
        $visitor   = new NodeVisitor\FirstFindingVisitor($filterCallback);
        $traverser->addVisitor($visitor);
        $traverser->traverse($this->newStmts);

        return $visitor->getFoundNode();
    }

    /** @param Node[] $ast */
    private function findLastNode(callable $filterCallback, array $ast): Node|null
    {
        $traverser = new NodeTraverser();
        $visitor   = new NodeVisitor\FindingVisitor($filterCallback);
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $nodes = $visitor->getFoundNodes();
        $node  = end($nodes);

        return $node === false ? null : $node;
    }

    private function createBlankLineNode(string $context): Node\Stmt\Use_|Node|Node\Stmt\Property|Node\Expr\Variable
    {
        return match ($context) {
            self::CONTEXT_OUTSIDE_CLASS => new Builder\Use_(
                '__EXTRA__LINE',
                Node\Stmt\Use_::TYPE_NORMAL,
            )
                ->getNode(),
            self::CONTEXT_CLASS => (new Builder\Property('__EXTRA__LINE'))
                ->makePrivate()
                ->getNode(),
            self::CONTEXT_CLASS_METHOD => new Node\Expr\Variable(
                '__EXTRA__LINE',
            ),
            default => throw new Exception('Unknown context: ' . $context),
        };
    }

    /** @param string[] $commentLines */
    private function createDocBlock(array $commentLines): string
    {
        $docBlock = "/**\n";
        foreach ($commentLines as $commentLine) {
            if ($commentLine) {
                $docBlock .= sprintf(" *%s\n", $commentLine);
            } else {
                // avoid the empty, extra space on blank lines
                $docBlock .= " *\n";
            }
        }

        return $docBlock . "\n */";
    }

    private function isInSameNamespace(string $class): bool
    {
        $namespace = substr($class, 0, strrpos($class, '\\'));

        return $this->getNamespaceNode()->name->toCodeString() === $namespace;
    }

    /**
     * Adds this new node where a new property should go.
     *
     * Useful for adding properties, or adding a constructor.
     */
    private function addNodeAfterProperties(Node $newNode): void
    {
        $classNode = $this->getClassNode();

        // try to add after last property
        $targetNode = $this->findLastNode(static fn ($node) => $node instanceof Node\Stmt\Property, [$classNode]);

        // otherwise, try to add after the last constant
        if (! $targetNode) {
            $targetNode = $this->findLastNode(static fn ($node) => $node instanceof Node\Stmt\ClassConst, [$classNode]);
        }

        // otherwise, try to add after the last trait
        if (! $targetNode) {
            $targetNode = $this->findLastNode(static fn ($node) => $node instanceof Node\Stmt\TraitUse, [$classNode]);
        }

        // add the new property after this node
        if ($targetNode) {
            $index = array_search($targetNode, $classNode->stmts);

            array_splice(
                $classNode->stmts,
                $index + 1,
                0,
                [$this->createBlankLineNode(self::CONTEXT_CLASS), $newNode],
            );

            $this->updateSourceCodeFromNewStmts();

            return;
        }

        // put right at the beginning of the class
        // add an empty line, unless the class is totally empty
        if (! empty($classNode->stmts)) {
            array_unshift($classNode->stmts, $this->createBlankLineNode(self::CONTEXT_CLASS));
        }

        array_unshift($classNode->stmts, $newNode);
        $this->updateSourceCodeFromNewStmts();
    }

    private function propertyExists(string $propertyName): bool
    {
        foreach ($this->getClassNode()->stmts as $i => $node) {
            if ($node instanceof Node\Stmt\Property && $node->props[0]->name->toString() === $propertyName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed[] $value
     *
     * @throws Exception
     */
    private function buildArrayNode(array $value): Node\Expr\Array_
    {
        $context    = $this;
        $arrayItems = array_map(static fn ($key, $value) => new Node\Expr\ArrayItem(
            $context->buildNodeExprByValue($value),
            ! is_int($key) ? $context->buildNodeExprByValue($key) : null,
        ), array_keys($value), array_values($value));

        return new Node\Expr\Array_($arrayItems, ['kind' => Node\Expr\Array_::KIND_SHORT]);
    }

    private function buildClassNameValueNode(mixed $value): Node\Expr\ConstFetch
    {
        if (! ($value instanceof ClassNameValue)) {
            throw new Exception(sprintf('Cannot build a node expr for value of type "%s"', get_debug_type($value)));
        }

        return new Node\Expr\ConstFetch(
            new Node\Name(
                sprintf('%s::class', $value->isSelf() ? 'self' : $value->getShortName()),
            ),
        );
    }

    /**
     * builds a PHPParser Expr Node based on the value given in $value
     * throws an Exception when the given $value is not resolvable by this method.
     *
     * @throws Exception
     */
    private function buildNodeExprByValue(mixed $value): Node\Expr
    {
        return match (gettype($value)) {
            'string' => new Node\Scalar\String_($value),
            'integer' => new Node\Scalar\LNumber($value),
            'double' => new Node\Scalar\DNumber($value),
            'boolean' => new Node\Expr\ConstFetch(new Node\Name($value ? 'true' : 'false')),
            'array' => $this->buildArrayNode($value),
            default => $this->buildClassNameValueNode($value),
        };
    }

    /**
     * sort the given options based on the constructor parameters for the given $classString
     * this prevents code inspections warnings for IDEs like intellij/phpstorm.
     *
     * option keys that are not found in the constructor will be added at the end of the sorted array
     *
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws ReflectionException
     */
    private function sortOptionsByClassConstructorParameters(array $options, string $classString): array
    {
        if (str_starts_with($classString, 'ODM\\')) {
            $classString = sprintf('Doctrine\\ODM\\MongoDB\\Mapping\\%s', substr($classString, 4));
        }

        $constructorParameterNames = array_map(static fn (ReflectionParameter $reflectionParameter) => $reflectionParameter->getName(), (new ReflectionClass($classString))->getConstructor()->getParameters());

        $sorted = [];
        foreach ($constructorParameterNames as $name) {
            if (! array_key_exists($name, $options)) {
                continue;
            }

            $sorted[$name] = $options[$name];
            unset($options[$name]);
        }

        return array_merge($sorted, $options);
    }
}
