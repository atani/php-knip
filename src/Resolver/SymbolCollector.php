<?php
/**
 * Symbol Collector
 *
 * Collects symbols from AST nodes
 */

namespace PhpKnip\Resolver;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\NodeVisitorAbstract;

/**
 * AST visitor that collects symbol definitions
 */
class SymbolCollector extends NodeVisitorAbstract
{
    /**
     * @var SymbolTable
     */
    private $symbolTable;

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
     * Constructor
     *
     * @param SymbolTable|null $symbolTable Symbol table to populate
     */
    public function __construct(SymbolTable $symbolTable = null)
    {
        $this->symbolTable = $symbolTable ?: new SymbolTable();
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
     * Get collected symbol table
     *
     * @return SymbolTable
     */
    public function getSymbolTable()
    {
        return $this->symbolTable;
    }

    /**
     * Reset state for new file
     */
    public function reset()
    {
        $this->currentNamespace = null;
        $this->currentClass = null;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        // Track namespace
        if ($node instanceof Stmt\Namespace_) {
            $this->currentNamespace = $node->name !== null ? $this->getNameString($node->name) : null;
            return null;
        }

        // Collect class
        if ($node instanceof Stmt\Class_) {
            $this->collectClass($node);
            return null;
        }

        // Collect interface
        if ($node instanceof Stmt\Interface_) {
            $this->collectInterface($node);
            return null;
        }

        // Collect trait
        if ($node instanceof Stmt\Trait_) {
            $this->collectTrait($node);
            return null;
        }

        // Collect enum (PHP 8.1+)
        if ($node instanceof Stmt\Enum_) {
            $this->collectEnum($node);
            return null;
        }

        // Collect function
        if ($node instanceof Stmt\Function_) {
            $this->collectFunction($node);
            return null;
        }

        // Collect method
        if ($node instanceof Stmt\ClassMethod) {
            $this->collectMethod($node);
            return null;
        }

        // Collect property
        if ($node instanceof Stmt\Property) {
            $this->collectProperty($node);
            return null;
        }

        // Collect class constant
        if ($node instanceof Stmt\ClassConst) {
            $this->collectClassConstant($node);
            return null;
        }

        // Collect global constant (define)
        if ($node instanceof Expr\FuncCall) {
            $this->collectDefineConstant($node);
            return null;
        }

        // Collect const statement
        if ($node instanceof Stmt\Const_) {
            $this->collectConstStatement($node);
            return null;
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {
        // Exit class scope
        if ($node instanceof Stmt\Class_ ||
            $node instanceof Stmt\Interface_ ||
            $node instanceof Stmt\Trait_ ||
            $node instanceof Stmt\Enum_) {
            $this->currentClass = null;
        }

        // Exit namespace scope (only for bracketed namespaces)
        if ($node instanceof Stmt\Namespace_ && $node->stmts !== null) {
            $this->currentNamespace = null;
        }

        return null;
    }

    /**
     * Collect class definition
     *
     * @param Stmt\Class_ $node Class node
     */
    private function collectClass(Stmt\Class_ $node)
    {
        // Anonymous class
        if ($node->name === null) {
            return;
        }

        $symbol = Symbol::createClass($this->getNameString($node->name), $this->currentNamespace);
        $this->setCommonAttributes($symbol, $node);

        // Set class-specific attributes
        $symbol->setAbstract($node->isAbstract());
        $symbol->setFinal($node->isFinal());

        // Collect extends
        if ($node->extends !== null) {
            $symbol->setExtends(array($this->getNameString($node->extends)));
        }

        // Collect implements
        $implements = array();
        foreach ($node->implements as $interface) {
            $implements[] = $this->getNameString($interface);
        }
        $symbol->setImplements($implements);

        // Collect used traits
        $uses = $this->collectTraitUses($node->stmts);
        $symbol->setUses($uses);

        $this->symbolTable->add($symbol);
        $this->currentClass = $symbol->getFullyQualifiedName();
    }

    /**
     * Collect interface definition
     *
     * @param Stmt\Interface_ $node Interface node
     */
    private function collectInterface(Stmt\Interface_ $node)
    {
        $symbol = Symbol::createInterface($this->getNameString($node->name), $this->currentNamespace);
        $this->setCommonAttributes($symbol, $node);

        // Collect extends
        $extends = array();
        foreach ($node->extends as $interface) {
            $extends[] = $this->getNameString($interface);
        }
        $symbol->setExtends($extends);

        $this->symbolTable->add($symbol);
        $this->currentClass = $symbol->getFullyQualifiedName();
    }

    /**
     * Collect trait definition
     *
     * @param Stmt\Trait_ $node Trait node
     */
    private function collectTrait(Stmt\Trait_ $node)
    {
        $symbol = Symbol::createTrait($this->getNameString($node->name), $this->currentNamespace);
        $this->setCommonAttributes($symbol, $node);

        $this->symbolTable->add($symbol);
        $this->currentClass = $symbol->getFullyQualifiedName();
    }

    /**
     * Collect enum definition (PHP 8.1+)
     *
     * @param Stmt\Enum_ $node Enum node
     */
    private function collectEnum(Stmt\Enum_ $node)
    {
        $symbol = new Symbol(Symbol::TYPE_ENUM, $this->getNameString($node->name));
        $symbol->setNamespace($this->currentNamespace);
        $this->setCommonAttributes($symbol, $node);

        // Collect implements
        $implements = array();
        foreach ($node->implements as $interface) {
            $implements[] = $this->getNameString($interface);
        }
        $symbol->setImplements($implements);

        $this->symbolTable->add($symbol);
        $this->currentClass = $symbol->getFullyQualifiedName();
    }

    /**
     * Collect function definition
     *
     * @param Stmt\Function_ $node Function node
     */
    private function collectFunction(Stmt\Function_ $node)
    {
        $symbol = Symbol::createFunction($this->getNameString($node->name), $this->currentNamespace);
        $this->setCommonAttributes($symbol, $node);

        $this->symbolTable->add($symbol);
    }

    /**
     * Collect method definition
     *
     * @param Stmt\ClassMethod $node Method node
     */
    private function collectMethod(Stmt\ClassMethod $node)
    {
        if ($this->currentClass === null) {
            return;
        }

        $visibility = $this->getVisibility($node);
        $methodName = $this->getNameString($node->name);
        $symbol = Symbol::createMethod($methodName, $this->currentClass, $visibility);
        $this->setCommonAttributes($symbol, $node);

        $symbol->setStatic($node->isStatic());
        $symbol->setAbstract($node->isAbstract());
        $symbol->setFinal($node->isFinal());

        // Mark magic methods
        if (strpos($methodName, '__') === 0) {
            $symbol->setMetadata('isMagic', true);
        }

        $this->symbolTable->add($symbol);
    }

    /**
     * Collect property definition
     *
     * @param Stmt\Property $node Property node
     */
    private function collectProperty(Stmt\Property $node)
    {
        if ($this->currentClass === null) {
            return;
        }

        $visibility = $this->getVisibility($node);

        foreach ($node->props as $prop) {
            $symbol = Symbol::createProperty($this->getNameString($prop->name), $this->currentClass, $visibility);
            $symbol->setFilePath($this->currentFile);
            $symbol->setStartLine($node->getLine());
            $symbol->setEndLine($node->getAttribute('endLine', $node->getLine()));
            $symbol->setStatic($node->isStatic());

            $this->symbolTable->add($symbol);
        }
    }

    /**
     * Collect class constant definition
     *
     * @param Stmt\ClassConst $node Class constant node
     */
    private function collectClassConstant(Stmt\ClassConst $node)
    {
        if ($this->currentClass === null) {
            return;
        }

        $visibility = $this->getVisibility($node);

        foreach ($node->consts as $const) {
            $symbol = Symbol::createClassConstant($this->getNameString($const->name), $this->currentClass);
            $symbol->setFilePath($this->currentFile);
            $symbol->setStartLine($node->getLine());
            $symbol->setEndLine($node->getAttribute('endLine', $node->getLine()));
            $symbol->setVisibility($visibility);

            $this->symbolTable->add($symbol);
        }
    }

    /**
     * Collect define() constant
     *
     * @param Expr\FuncCall $node Function call node
     */
    private function collectDefineConstant(Expr\FuncCall $node)
    {
        // Check if it's a define() call
        if (!$node->name instanceof Node\Name) {
            return;
        }

        if (strtolower($this->getNameString($node->name)) !== 'define') {
            return;
        }

        // Get constant name from first argument
        if (count($node->args) < 2) {
            return;
        }

        $firstArg = $node->args[0];
        if (!$firstArg instanceof Node\Arg) {
            return;
        }

        if (!$firstArg->value instanceof Node\Scalar\String_) {
            return;
        }

        $constantName = $firstArg->value->value;
        $symbol = Symbol::createConstant($constantName, $this->currentNamespace);
        $symbol->setFilePath($this->currentFile);
        $symbol->setStartLine($node->getLine());
        $symbol->setEndLine($node->getAttribute('endLine', $node->getLine()));
        $symbol->setMetadata('definedWith', 'define');

        $this->symbolTable->add($symbol);
    }

    /**
     * Collect const statement
     *
     * @param Stmt\Const_ $node Const node
     */
    private function collectConstStatement(Stmt\Const_ $node)
    {
        foreach ($node->consts as $const) {
            $symbol = Symbol::createConstant($this->getNameString($const->name), $this->currentNamespace);
            $symbol->setFilePath($this->currentFile);
            $symbol->setStartLine($node->getLine());
            $symbol->setEndLine($node->getAttribute('endLine', $node->getLine()));
            $symbol->setMetadata('definedWith', 'const');

            $this->symbolTable->add($symbol);
        }
    }

    /**
     * Collect trait uses from class statements
     *
     * @param array $stmts Class statements
     *
     * @return array Trait names
     */
    private function collectTraitUses(array $stmts)
    {
        $uses = array();

        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $uses[] = $this->getNameString($trait);
                }
            }
        }

        return $uses;
    }

    /**
     * Set common attributes on symbol
     *
     * @param Symbol $symbol Symbol to update
     * @param Node $node AST node
     */
    private function setCommonAttributes(Symbol $symbol, Node $node)
    {
        $symbol->setFilePath($this->currentFile);
        $symbol->setStartLine($node->getLine());
        $symbol->setEndLine($node->getAttribute('endLine', $node->getLine()));
    }

    /**
     * Get visibility from node
     *
     * @param Node $node Node with visibility flags
     *
     * @return string Visibility constant
     */
    private function getVisibility(Node $node)
    {
        if (method_exists($node, 'isPrivate') && $node->isPrivate()) {
            return Symbol::VISIBILITY_PRIVATE;
        }
        if (method_exists($node, 'isProtected') && $node->isProtected()) {
            return Symbol::VISIBILITY_PROTECTED;
        }
        return Symbol::VISIBILITY_PUBLIC;
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
