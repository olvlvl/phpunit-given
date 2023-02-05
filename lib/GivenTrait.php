<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\Given;

use PHPUnit\Framework\TestCase;

/**
 * A trait meant to be used with a {@link TestCase}.
 */
trait GivenTrait
{
    /**
     * @param mixed ...$arguments
     *     Where each argument is a value or a {@link Constraint}.
     *     **Note:** Values are converted into constraints with {@link Assert::equalTo()}.
     */
    public function given(...$arguments): ReturnGiven
    {
        assert($this instanceof TestCase);

        return (new ReturnGiven($this))->given(...$arguments);
    }
}
