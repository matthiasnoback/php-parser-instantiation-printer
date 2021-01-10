<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Parser;
use PhpParser\PrettyPrinter\Standard;
use ReflectionObject;
use ReflectionProperty;
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

        $instantiationNodes = array_map(fn(Stmt $stmt) => $this->createInstantiationNodeFor($stmt), $statements);

        if (count($instantiationNodes) === 1) {
            $return = $instantiationNodes[0];
        } else {
            $return = $this->wrapNodesInArray($instantiationNodes);
        }

        return $this->print(new Return_($return));
    }

    public function printInstantiationNodeFor(Node $node): string
    {
        return $this->print(
            new Return_(
                $this->createInstantiationNodeFor($node)
            )
        );
    }

    /**
     * @param array<Node> | Node $nodeOrNodes
     */
    public function print($nodeOrNodes): string
    {
        $nodes = is_array($nodeOrNodes) ? $nodeOrNodes : [$nodeOrNodes];

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

        $constructorParameters = [];
        foreach ($reflection->getConstructor()->getParameters() as $parameter) {
            $constructorParameters[$parameter->getName()] = $parameter;
        }

        $subNodeNamesThatAreNotConstructorArgumentsAsWell = array_diff(
            $node->getSubNodeNames(),
            array_keys($constructorParameters)
        );

        foreach ($constructorParameters as $parameter) {
            if ($parameter->getName() === 'subNodes') {
                /*
                 * When instantiating a node, subNodes will be an associative array where each value will be copied to
                 * a property that has the name of they key.
                 * Now we need to invert the process so we create an associate array.
                 */
                $value = [];
                foreach ($subNodeNamesThatAreNotConstructorArgumentsAsWell as $subNodeName) {
                    $property = new ReflectionProperty(get_class($node), $subNodeName);

                    $propertyValue = $property->getValue($node);

                    /*
                     * Node constructors do things like `$this->extends = $subNodes['extends'] ?? null;`
                     * So if the value from the subNodes array is the same as the default value then we don't have to
                     * explicitly set it as a key on the $subNodes constructor argument
                     */
                    if ($node instanceof Node\Stmt\Class_) {
                        if (
                            ($subNodeName === 'flags' && $propertyValue === 0)
                            || ($subNodeName === 'extends' && $propertyValue === null)
                            || ($subNodeName === 'implements' && $propertyValue === [])
                            || ($subNodeName === 'stmts' && $propertyValue === [])
                            || ($subNodeName === 'attrGroups' && $propertyValue === [])
                        ) {
                            continue;
                        }
                    }

                    $value[$subNodeName] = $propertyValue;
                }
            }
            elseif ($node instanceof Node\Name && $parameter->getName() === 'name') {
                /*
                 * The $name parameter of the Name node is a string that will be split into parts, so we have to turn it
                 * into a string again:
                 */
                $value = $node->toString();
            } elseif ($parameter->getName() === 'attributes') {
                // Strip existing nodes from their attributes, which only make sense in the context of parsing the nodes
                $value = [];
            } elseif ($reflection->hasProperty($parameter->getName())) {
                $property = $reflection->getProperty($parameter->getName());
                $property->setAccessible(true);

                $value = $property->getValue($node);
            } else {
                throw new RuntimeException(
                    'Could not come up with an argument for constructor parameter "' . $parameter->getName() . '"' .
                    ' of class ' . get_class($node)
                );
            }

            $arguments[$parameter->getName()] = $value;
        }

        // Remove arguments that are optional and that would be the same as the parameter's default value
        foreach (array_reverse($arguments, true) as $parameterName => $argumentValue) {
            $parameter = $constructorParameters[$parameterName];
            if (!$parameter->isOptional() || !$parameter->isDefaultValueAvailable()) {
                // stop trying
                break;
            }

            if ($parameter->getDefaultValue() !== $argumentValue) {
                // stop trying as soon as we want to pass a specific argument that is not the default argument value
                break;
            }

            // It's safe to unset this argument; PHP will provide the default argument instead
            unset($arguments[$parameterName]);
        }

        return array_map(
            fn($value) => new Arg($this->createExpressionNodeForValue($value)),
            array_values($arguments)
        );
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
