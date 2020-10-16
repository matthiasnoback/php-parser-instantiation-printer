<?php

function greeting(string $name): string
{
    return 'Hi, ' . $name . '!';
}

function isItNow(): bool
{
    return true;
}

function passByRef(array &$array): void
{
    $array['foo'] = 'bar';
}
