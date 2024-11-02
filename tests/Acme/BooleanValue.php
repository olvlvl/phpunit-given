<?php

namespace Test\olvlvl\Given\Acme;

use LogicException;

class BooleanValue
{
    public function getValue(string $name): bool
    {
        return match ($name) {
            "yes" => true,
            "no" => false,
            default => throw new LogicException()
        };
    }
}
