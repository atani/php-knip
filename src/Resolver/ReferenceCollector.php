<?php
/**
 * Reference Collector
 *
 * Collects references to symbols from AST nodes
 */

namespace PhpKnip\Resolver;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects references to symbols
 */
class ReferenceCollector extends NodeVisitorAbstract
{
    /**
     * @var array<Reference> Collected references
     */
    private $references = array();

    /**
     * @var string|null Current file path
     */
    private $currentFile;

    /**
     * @var string|null Current namespace
     */
    private $currentNamespace;

    /**
     * @var string|null Current class name
     */
    private $currentClass;

    /**
     * @var string|null Current function/method name
     */
    private $currentFunction;

    /**
     * @var array<string, string> Use statement aliases (alias => fqn)
     */
    private $useAliases = array();

    /**
     * @var array Use statements for current file
     */
    private $useStatements = array();

    /**
     * Constructor
     *
     * @param string|null $filePath Current file path
     */
    public function __construct($filePath = null)
    {
        $this->currentFile = $filePath;
    }

    /**
     * Set current file being analyzed
     *
     * @param string $filePath File path
     *
     * @return $this
     */
    public function setCurrentFile($filePath)
    {
        $this->currentFile = $filePath;
        return $this;
    }

    /**
     * Get collected references
     *
     * @return array<Reference>
     */
    public function getReferences()
    {
        return $this->references;
    }

    /**
     * Get collected use statements
     *
     * @return array
     */
    public function getUseStatements()
    {
        return $this->useStatements;
    }

    /**
     * Reset state for new file
     */
    public function reset()
    {
        $this->references = array();
        $this->currentNamespace = null;
        $this->currentClass = null;
        $this->currentFunction = null;
        $this->useAliases = array();
        $this->useStatements = array();
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        // Track namespace
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name !== null ? $this->getNameString($node->name) : null;
            $this->useAliases = array(); // Reset use aliases for new namespace
            return null;
        }

        // Collect use statements
        if ($node instanceof Stmt\Use_) {
            $this->collectUseStatement($node);
            return null;
        }

        // Track class context
        if ($node instanceof Stmt\Class_) {
            if ($node->name !== null) {
                $this->currentClass = $this->buildFqn($this->getNameString($node->name));
            }
            $this->collectClassReferences($node);
            return null;
        }

        if ($node instanceof Stmt\Interface_) {
            $this->currentClass = $this->buildFqn($this->getNameString($node->name));
            $this->collectInterfaceReferences($node);
            return null;
        }

        if ($node instanceof Stmt\Trait_) {
            $this->currentClass = $this->buildFqn($this->getNameString($node->name));
            return null;
        }

        // Track function/method context
        if ($node instanceof Stmt\Function_) {
            $this->currentFunction = $this->getNameString($node->name);
            $this->collectFunctionReferences($node);
            return null;
        }

        if ($node instanceof Stmt\ClassMethod) {
            $this->currentFunction = $this->getNameString($node->name);
            $this->collectMethodReferences($node);
            return null;
        }

        // Collect trait uses
        if ($node instanceof Stmt\TraitUse) {
            $this->collectTraitUseReferences($node);
            return null;
        }

        // Collect new expressions
        if ($node instanceof Expr\New_) {
            $this->collectNewReference($node);
            return null;
        }

        // Collect static calls
        if ($node instanceof Expr\StaticCall) {
            $this->collectStaticCallReference($node);
            return null;
        }

        // Collect static property access
        if ($node instanceof Expr\StaticPropertyFetch) {
            $this->collectStaticPropertyReference($node);
            return null;
        }

        // Collect class constant access
        if ($node instanceof Expr\ClassConstFetch) {
            $this->collectClassConstReference($node);
            return null;
        }

        // Collect function calls
        if ($node instanceof Expr\FuncCall) {
            $this->collectFunctionCallReference($node);
            return null;
        }

        // Collect method calls
        if ($node instanceof Expr\MethodCall) {
            $this->collectMethodCallReference($node);
            return null;
        }

        // Collect instanceof
        if ($node instanceof Expr\Instanceof_) {
            $this->collectInstanceofReference($node);
            return null;
        }

        // Collect catch blocks
        if ($node instanceof Stmt\Catch_) {
            $this->collectCatchReference($node);
            return null;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        if ($node instanceof Stmt\Class_ ||
            $node instanceof Stmt\Interface_ ||
            $node instanceof Stmt\Trait_) {
            $this->currentClass = null;
        }

        if ($node instanceof Stmt\Function_ || $node instanceof Stmt\ClassMethod) {
            $this->currentFunction = null;
        }

        if ($node instanceof Stmt\Namespace_ && $node->stmts !== null) {
            $this->currentNamespace = null;
            $this->useAliases = array();
        }

        return null;
    }

    /**
     * Collect use statement
     *
     * @param Stmt\Use_ $node Use statement node
     */
    private function collectUseStatement(Stmt\Use_ $node)
    {
        // Determine use type
        $useType = 'class';
        if ($node->type === Stmt\Use_::TYPE_FUNCTION) {
            $useType = 'function';
        } elseif ($node->type === Stmt\Use_::TYPE_CONSTANT) {
            $useType = 'constant';
        }

        foreach ($node->uses as $use) {
            $name = $this->getNameString($use->name);
            $alias = $use->alias !== null ? $this->getNameString($use->alias) : $use->name->getLast();

            $this->useAliases[$alias] = $name;

            // Store use statement for UseStatementAnalyzer
            $this->useStatements[] = array(
                'fqn' => $name,
                'alias' => $alias,
                'line' => $node->getLine(),
                'type' => $useType,
            );

            $ref = Reference::createUseImport($name, $alias);
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect class references (extends, implements)
     *
     * @param Stmt\Class_ $node Class node
     */
    private function collectClassReferences(Stmt\Class_ $node)
    {
        // Extends
        if ($node->extends !== null) {
            $ref = Reference::createExtends($this->resolveName($node->extends));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }

        // Implements
        foreach ($node->implements as $interface) {
            $ref = Reference::createImplements($this->resolveName($interface));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect interface references (extends)
     *
     * @param Stmt\Interface_ $node Interface node
     */
    private function collectInterfaceReferences(Stmt\Interface_ $node)
    {
        foreach ($node->extends as $interface) {
            $ref = Reference::createExtends($this->resolveName($interface));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect function type references
     *
     * @param Stmt\Function_ $node Function node
     */
    private function collectFunctionReferences(Stmt\Function_ $node)
    {
        $this->collectParameterTypes($node->params, $node);
        $this->collectReturnType($node->returnType, $node);
    }

    /**
     * Collect method type references
     *
     * @param Stmt\ClassMethod $node Method node
     */
    private function collectMethodReferences(Stmt\ClassMethod $node)
    {
        $this->collectParameterTypes($node->params, $node);
        $this->collectReturnType($node->returnType, $node);
    }

    /**
     * Collect parameter type hints
     *
     * @param array $params Parameters
     * @param Node $node Parent node for location
     */
    private function collectParameterTypes(array $params, Node $node)
    {
        foreach ($params as $param) {
            if ($param->type !== null) {
                $this->collectTypeReference($param->type, $node, Reference::TYPE_TYPE_HINT);
            }
        }
    }

    /**
     * Collect return type
     *
     * @param Node|null $returnType Return type node
     * @param Node $node Parent node for location
     */
    private function collectReturnType($returnType, Node $node)
    {
        if ($returnType !== null) {
            $this->collectTypeReference($returnType, $node, Reference::TYPE_RETURN_TYPE);
        }
    }

    /**
     * Collect type reference
     *
     * @param Node $type Type node
     * @param Node $node Parent node for location
     * @param string $refType Reference type
     */
    private function collectTypeReference($type, Node $node, $refType)
    {
        // Handle nullable types
        if ($type instanceof Node\NullableType) {
            $type = $type->type;
        }

        // Handle union types (PHP 8+)
        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $subType) {
                $this->collectTypeReference($subType, $node, $refType);
            }
            return;
        }

        // Handle intersection types (PHP 8.1+)
        if ($type instanceof Node\IntersectionType) {
            foreach ($type->types as $subType) {
                $this->collectTypeReference($subType, $node, $refType);
            }
            return;
        }

        // Skip built-in types
        if ($type instanceof Node\Identifier) {
            $name = $this->getNameString($type);
            if ($this->isBuiltinType($name)) {
                return;
            }
        }

        // Handle class names
        if ($type instanceof Name) {
            $ref = new Reference($refType, $this->resolveName($type));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect trait use references
     *
     * @param Stmt\TraitUse $node Trait use node
     */
    private function collectTraitUseReferences(Stmt\TraitUse $node)
    {
        foreach ($node->traits as $trait) {
            $ref = Reference::createUseTrait($this->resolveName($trait));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect new expression reference
     *
     * @param Expr\New_ $node New expression node
     */
    private function collectNewReference(Expr\New_ $node)
    {
        if ($node->class instanceof Name) {
            $ref = Reference::createNew($this->resolveName($node->class));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        } elseif ($node->class instanceof Expr) {
            // Dynamic class instantiation: new $className()
            $ref = Reference::createNew('(dynamic)');
            $ref->setDynamic(true);
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect static call reference
     *
     * @param Expr\StaticCall $node Static call node
     */
    private function collectStaticCallReference(Expr\StaticCall $node)
    {
        $className = $this->resolveClassReference($node->class);
        $methodName = (is_string($node->name) || $node->name instanceof Node\Identifier) ? $this->getNameString($node->name) : '(dynamic)';

        $ref = Reference::createStaticCall($className, $methodName);
        if ($className === '(dynamic)' || $methodName === '(dynamic)') {
            $ref->setDynamic(true);
        }
        $this->setReferenceLocation($ref, $node);
        $this->references[] = $ref;
    }

    /**
     * Collect static property reference
     *
     * @param Expr\StaticPropertyFetch $node Static property node
     */
    private function collectStaticPropertyReference(Expr\StaticPropertyFetch $node)
    {
        $className = $this->resolveClassReference($node->class);

        $ref = new Reference(Reference::TYPE_STATIC_PROPERTY, $className);
        if ($className === '(dynamic)') {
            $ref->setDynamic(true);
        }
        $this->setReferenceLocation($ref, $node);
        $this->references[] = $ref;
    }

    /**
     * Collect class constant reference
     *
     * @param Expr\ClassConstFetch $node Class constant node
     */
    private function collectClassConstReference(Expr\ClassConstFetch $node)
    {
        $className = $this->resolveClassReference($node->class);
        $constName = (is_string($node->name) || $node->name instanceof Node\Identifier) ? $this->getNameString($node->name) : '(dynamic)';

        // Handle ::class
        if ($constName === 'class') {
            $ref = Reference::createClassString($className);
        } else {
            $ref = Reference::createConstant($constName, $className);
        }

        if ($className === '(dynamic)' || $constName === '(dynamic)') {
            $ref->setDynamic(true);
        }
        $this->setReferenceLocation($ref, $node);
        $this->references[] = $ref;
    }

    /**
     * Collect function call reference
     *
     * @param Expr\FuncCall $node Function call node
     */
    private function collectFunctionCallReference(Expr\FuncCall $node)
    {
        if ($node->name instanceof Name) {
            $name = $this->getNameString($node->name);

            // Skip built-in functions (simple heuristic)
            if ($this->isBuiltinFunction($name)) {
                return;
            }

            $ref = Reference::createFunctionCall($this->resolveName($node->name));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        } else {
            // Dynamic function call: $func()
            $ref = Reference::createFunctionCall('(dynamic)');
            $ref->setDynamic(true);
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect method call reference
     *
     * @param Expr\MethodCall $node Method call node
     */
    private function collectMethodCallReference(Expr\MethodCall $node)
    {
        $methodName = (is_string($node->name) || $node->name instanceof Node\Identifier) ? $this->getNameString($node->name) : '(dynamic)';

        $ref = Reference::createMethodCall($methodName);
        if ($methodName === '(dynamic)') {
            $ref->setDynamic(true);
        }
        $this->setReferenceLocation($ref, $node);
        $this->references[] = $ref;
    }

    /**
     * Collect instanceof reference
     *
     * @param Expr\Instanceof_ $node Instanceof node
     */
    private function collectInstanceofReference(Expr\Instanceof_ $node)
    {
        if ($node->class instanceof Name) {
            $ref = Reference::createInstanceof($this->resolveName($node->class));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Collect catch reference
     *
     * @param Stmt\Catch_ $node Catch node
     */
    private function collectCatchReference(Stmt\Catch_ $node)
    {
        foreach ($node->types as $type) {
            $ref = Reference::createCatch($this->resolveName($type));
            $this->setReferenceLocation($ref, $node);
            $this->references[] = $ref;
        }
    }

    /**
     * Resolve class reference (Name or Expr)
     *
     * @param Node $class Class node
     *
     * @return string Class name
     */
    private function resolveClassReference($class)
    {
        if ($class instanceof Name) {
            return $this->resolveName($class);
        }
        return '(dynamic)';
    }

    /**
     * Resolve name with use aliases
     *
     * @param Name|string $name Name node or string
     *
     * @return string Resolved name
     */
    private function resolveName($name)
    {
        // Handle string input for compatibility
        if (is_string($name)) {
            $lowerName = strtolower($name);
            if ($lowerName === 'self' || $lowerName === 'static') {
                return $this->currentClass !== null ? $this->currentClass : $name;
            }
            if ($lowerName === 'parent') {
                return 'parent';
            }
            // Check if already fully qualified
            if (strpos($name, '\\') === 0) {
                return ltrim($name, '\\');
            }
            // Check use aliases
            $parts = explode('\\', $name);
            $first = $parts[0];
            if (isset($this->useAliases[$first])) {
                if (count($parts) === 1) {
                    return $this->useAliases[$first];
                }
                array_shift($parts);
                return $this->useAliases[$first] . '\\' . implode('\\', $parts);
            }
            return $this->buildFqn($name);
        }

        // Handle Name object
        $nameStr = $this->getNameString($name);
        $lowerName = strtolower($nameStr);
        if ($lowerName === 'self' || $lowerName === 'static') {
            return $this->currentClass !== null ? $this->currentClass : $nameStr;
        }
        if ($lowerName === 'parent') {
            return 'parent'; // Would need class hierarchy to resolve
        }

        // Fully qualified names
        if ($name->isFullyQualified()) {
            return $this->getNameString($name);
        }

        // Check use aliases
        $first = $name->getFirst();
        if (isset($this->useAliases[$first])) {
            if ($name->isUnqualified()) {
                return $this->useAliases[$first];
            }
            // Replace first part with aliased name
            $parts = $name->parts;
            array_shift($parts);
            return $this->useAliases[$first] . '\\' . implode('\\', $parts);
        }

        // Relative to current namespace
        return $this->buildFqn($this->getNameString($name));
    }

    /**
     * Build fully qualified name
     *
     * @param string $name Short name
     *
     * @return string Fully qualified name
     */
    private function buildFqn($name)
    {
        if ($this->currentNamespace !== null && $this->currentNamespace !== '') {
            return $this->currentNamespace . '\\' . $name;
        }
        return $name;
    }

    /**
     * Set reference location
     *
     * @param Reference $ref Reference
     * @param Node $node AST node
     */
    private function setReferenceLocation(Reference $ref, Node $node)
    {
        $ref->setFilePath($this->currentFile);
        // Use getLine() for php-parser v3 compatibility (v4+ has getStartLine())
        $ref->setLine($node->getLine());

        // Set context
        $context = '';
        if ($this->currentClass !== null) {
            $context = $this->currentClass;
            if ($this->currentFunction !== null) {
                $context .= '::' . $this->currentFunction;
            }
        } elseif ($this->currentFunction !== null) {
            $context = $this->currentFunction;
        }
        if ($context !== '') {
            $ref->setContext($context);
        }
    }

    /**
     * Check if name is a built-in type
     *
     * @param string $name Type name
     *
     * @return bool
     */
    private function isBuiltinType($name)
    {
        $builtins = array(
            'int', 'integer', 'float', 'double', 'string', 'bool', 'boolean',
            'array', 'object', 'callable', 'iterable', 'void', 'null',
            'mixed', 'never', 'true', 'false', 'self', 'static', 'parent',
        );
        return in_array(strtolower($name), $builtins, true);
    }

    /**
     * Check if name is likely a built-in function (basic check)
     *
     * @param string $name Function name
     *
     * @return bool
     */
    private function isBuiltinFunction($name)
    {
        // Only skip define() as we handle it specially
        // Other built-in functions should be tracked as they might be user-defined
        return $name === 'define';
    }

    /**
     * Get string from name (handles both string and object types for php-parser v3 compatibility)
     *
     * @param mixed $name Name (string or object with toString method)
     *
     * @return string
     */
    private function getNameString($name)
    {
        if (is_string($name)) {
            return $name;
        }
        if (is_object($name) && method_exists($name, 'toString')) {
            return $name->toString();
        }
        if (is_object($name) && method_exists($name, '__toString')) {
            return (string) $name;
        }
        return (string) $name;
    }
}
