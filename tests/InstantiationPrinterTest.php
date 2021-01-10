<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use Generator;
use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class InstantiationPrinterTest extends TestCase
{
    private InstantiationPrinter $printer;
    private Standard $prettyPrinter;
    private $parser;

    protected function setUp(): void
    {
        $this->prettyPrinter = new Standard(
            [
                'shortArraySyntax' => true
            ]
        );
        $parserFactory = new ParserFactory();
        $this->parser = $parserFactory->create(ParserFactory::ONLY_PHP7);

        $this->printer = new InstantiationPrinter(
            $this->parser,
            $this->prettyPrinter
        );
    }

    /**
     * @test
     * @dataProvider nodes
     */
    public function it_prints_code_that_results_in_an_equal_object_after_running_it(
        Node $originalNode,
        ?Node $expectedNode = null
    ): void {
        $expectedNode = $expectedNode ?? $originalNode;

        $code = $this->printer->printInstantiationNodeFor($originalNode);

        try {
            $recreatedNode = eval($code);
        } catch (Throwable $previous) {
            throw new RuntimeException('Could not execute the generated code: ' . $code, 0, $previous);
        }

        self::assertEquals($expectedNode, $recreatedNode);
    }

    /**
     * @test
     * @dataProvider fixtureFiles
     */
    public function it_prints_code_that_instantiates_statements(string $phpFile): void
    {
        $originalCode = file_get_contents($phpFile);

        try {
            $generatedCode = $this->printer->printInstantiationCodeFor($originalCode);
        } catch (Error $parseError) {
            throw new RuntimeException('Could not parse fixture: ' . $originalCode, 0, $parseError);
        }

        try {
            $nodes = eval($generatedCode);
        } catch (Throwable $previous) {
            throw new RuntimeException('Could not execute the generated code: ' . $generatedCode, 0, $previous);
        }

        $resultingCode = $this->printer->print($nodes);

        self::assertEquals($this->reformatCode($originalCode), $resultingCode);
    }

    /**
     * @test
     */
    public function it_uses_default_arguments_where_possible(): void
    {
        $generatedCode = $this->printer->printInstantiationCodeFor('<?php "foo";');
        self::assertEquals(
            "return new PhpParser\Node\Stmt\Expression(new PhpParser\Node\Scalar\String_('foo'));",
            $generatedCode
        );
    }

    /**
     * @test
     */
    public function it_strips_unnecessary_sub_nodes(): void
    {
        $generatedCode = $this->printer->printInstantiationCodeFor('<?php class Simple {};');
        self::assertEquals(
            "return new PhpParser\Node\Stmt\Class_(new PhpParser\Node\Identifier('Simple'));",
            $generatedCode
        );
    }

    /**
     * @return Generator<array{string}>
     */
    public function fixtureFiles(): Generator
    {
        foreach(glob(__DIR__ . '/Fixtures/*.php') as $file) {
            yield [$file];
        }
    }

    /**
     * @return array<array<Node>>
     */
    public function nodes(): array
    {
        return [
            'strip attributes' => [
                new Node\Scalar\String_('foo', ['startLine' => 1, 'endLine' => 2, 'kind' => 3]),
                new Node\Scalar\String_('foo', [])
            ],
            [new Node\Scalar\String_('foo')],
            [new Node\Scalar\DNumber(0.12)],
            [new Node\Scalar\LNumber(123)],
            [new Node\Expr\Assign(new Node\Expr\Variable('foo'), new Node\Scalar\String_('bar'))],
            [new Node\Expr\ConstFetch(new Node\Name('true'))]
        ];
    }

    private function reformatCode(string $code): string
    {
        return $this->prettyPrinter->prettyPrint($this->parser->parse($code));
    }
}
