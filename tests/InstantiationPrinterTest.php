<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use PhpParser\Node;
use PHPUnit\Framework\TestCase;

final class InstantiationPrinterTest extends TestCase
{
    private InstantiationPrinter $printer;

    protected function setUp(): void
    {
        $this->printer = new InstantiationPrinter();
    }

    /**
     * @test
     * @dataProvider nodes
     */
    public function it_prints_code_that_results_in_an_equal_object_after_running_it(
        Node $node
    ): void {
        $code = $this->printer->print($node);
        $recreatedNode = eval($code);
        self::assertEquals($node, $recreatedNode);
    }

    /**
     * @return array<array<Node>>
     */
    public function nodes(): array
    {
        return [
            [new Node\Scalar\String_('foo')],
            [new Node\Scalar\DNumber(0.12)],
            [new Node\Scalar\LNumber(123)],
        ];
    }
}
