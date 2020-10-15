<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\PrettyPrinter\Standard;
use ReflectionObject;
use RuntimeException;

final class InstantiationPrinter
{
    public function print(Node $node): string
    {
        $instantiationCodeNode = new Node\Stmt\Return_($this->createInstantiationNodeFor($node));

        $prettyPrinter = new Standard();

        return $prettyPrinter->prettyPrint([$instantiationCodeNode]);
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
                $arguments[] = new Arg($this->createExpressionNodeForValue($property->getValue($node)));
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

        if (is_array($value)) {
            return new Array_($value);
        }

        throw new RuntimeException('No support for type value "' . var_export($value, true) . '"');
    }
}
