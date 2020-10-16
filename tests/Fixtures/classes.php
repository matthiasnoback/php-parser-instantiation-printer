<?php

namespace InstantiationPrinter\Fixtures;

final class Foo extends Bar implements Baz
{
    use SomeTrait;

    public function __construct()
    {
    }

    public function getStuff(): void
    {
        $this->callStuff('yeah');
    }

    public function callStuff(string $parameter = 'default value'): void
    {

    }
}

class Simple
{

}
