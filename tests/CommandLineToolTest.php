<?php
declare(strict_types=1);

namespace InstantiationPrinter;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class CommandLineToolTest extends TestCase
{
    /**
     * @test
     */
    public function it_takes_input_from_the_provided_file_and_generates_the_instantiation_code(): void
    {
        $process = new Process(
            [
                'bin/print-node-instantiation-code',
                __DIR__ . '/Fixtures/simple-class.php' // Let's just take a single example
            ]
        );
        $process->run();

        $expectedOutput = <<<'EOD'
<?php

return new PhpParser\Node\Stmt\Class_(new PhpParser\Node\Identifier('Simple'));


EOD;

        self::assertEquals($expectedOutput, $process->getOutput());
    }
}
