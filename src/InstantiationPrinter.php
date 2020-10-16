<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;
use ReflectionObject;
use RuntimeException;

final class InstantiationPrinter
{
    private Parser $parser;
    private Standard $prettyPrinter;

    public function __construct(Parser $parser, Standard $prettyPrinter)
    {
        $this->parser = $parser;
        $this->prettyPrinter = $prettyPrinter;
    }

    public function printInstantiationCodeFor(string $code): string
    {
        $statements = $this->parser->parse($code);

        $instantiationNodes = array_map(fn(Node\Stmt $stmt) => $this->createInstantiationNodeFor($stmt), $statements);

        $wrappedInArray = $this->wrapNodesInArray($instantiationNodes);
        $return = new Node\Stmt\Return_($wrappedInArray);

        return $this->printNode($return);
    }

    public function printInstantiationNodeFor(Node $node): string
    {
        return $this->printNode(
            new Node\Stmt\Return_(
                $this->createInstantiationNodeFor($node)
            )
        );
    }

    public function printNode(Node $node): string
    {
        return $this->prettyPrinter->prettyPrint([$node]);
    }

    /**
     * @param array<Node> $nodes
     */
    public function printNodes(array $nodes): string
    {
        return $this->prettyPrinter->prettyPrint($nodes);
    }

    private function createInstantiationNodeFor(Node $node): Node\Expr\New_
    {
        return new Node\Expr\New_(
            new Node\Name(get_class($node)),
            $this->createArgumentsForInstantiationOfNode($node)
        );
    }

    /**
     * @return array<Arg>
     */
    private function createArgumentsForInstantiationOfNode(Node $node): array
    {
        $reflection = new ReflectionObject($node);

        $arguments = [];

        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            if ($reflection->hasProperty($parameter->getName())) {
                $property = $reflection->getProperty($parameter->getName());
                $property->setAccessible(true);


                $value = $property->getValue($node);
                if ($parameter->getName() === 'attributes') {
                    // Strip existing nodes from their attributes, which only make sense in the context of parsing the nodes
                    $value = [];
                }

                $arguments[] = new Arg($this->createExpressionNodeForValue($value));
            }
        }

        return $arguments;
    }

    private function createExpressionNodeForValue($value): Expr
    {
        if ($value instanceof Node) {
            return $this->createInstantiationNodeFor($value);
        }

        if (is_string($value)) {
            return new String_($value);
        }
        if (is_float($value)) {
            return new Node\Scalar\DNumber($value);
        }
        if (is_int($value)) {
            return new Node\Scalar\LNumber($value);
        }
        if (is_bool($value)) {
            return new Expr\ConstFetch(new Node\Name($value ? 'true' : 'false'));
        }
        if (is_null($value)) {
            return new Expr\ConstFetch(new Node\Name('null'));
        }

        if (is_array($value)) {
            return $this->wrapNodesInArray(array_map([$this, 'createExpressionNodeForValue'], $value));
        }

        throw new RuntimeException('No support for type value "' . var_export($value, true) . '"');
    }

    /**
     * @param array<Expr> $nodes
     */
    private function wrapNodesInArray(array $nodes): Array_
    {
        $isAssociativeArray = count(array_filter(array_keys($nodes), fn($key) => is_string($key))) > 0;

        $items = [];
        foreach ($nodes as $key => $node) {
            $key = $isAssociativeArray ? new String_($key) : null;
            $items[] = new Expr\ArrayItem($node, $key);
        }

        return new Array_($items);
    }
}
