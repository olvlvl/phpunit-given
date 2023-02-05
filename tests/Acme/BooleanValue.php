<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
