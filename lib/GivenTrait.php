<?php

namespace olvlvl\Given;

use PHPUnit\Framework\TestCase;

/**
 * A trait meant to be used with a {@see TestCase}.
 */
trait GivenTrait
{
    /**
     * @param mixed ...$arguments
     *     Where each argument is a value or a {@see Constraint}.
     *     **Note:** Values are converted into constraints with {@see Assert::equalTo()}.
     */
    public function given(...$arguments): ReturnGiven
    {
        assert($this instanceof TestCase);

        return (new ReturnGiven($this))->given(...$arguments);
    }
}
