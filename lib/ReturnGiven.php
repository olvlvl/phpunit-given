<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\Given;

use BadMethodCallException;
use LogicException;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\MockObject\Builder\Stub as StubBuilder;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\MockObject\Stub\ReturnCallback;
use PHPUnit\Framework\MockObject\Stub\ReturnValueMap;
use PHPUnit\Framework\MockObject\Stub\Stub;
use PHPUnit\Framework\TestCase;
use Throwable;

use function array_key_exists;
use function array_map;
use function array_values;
use function implode;
use function is_string;

use const PHP_EOL;

/**
 * A {@link Stub} meant to be used with {@link StubBuilder::will}
 * as an alternative to {@link ReturnValueMap} or {@link ReturnCallback}.
 *
 * @internal
 */
final class ReturnGiven implements Stub
{
    private int $i = 0;

    /**
     * @var array<int , Constraint[]>
     */
    private array $given = [];

    /**
     * @var array<int, mixed>
     */
    private array $return = [];

    /**
     * @var array<int, Throwable>
     */
    private array $throw = [];

    /**
     * @var array<int, int>
     */
    private array $called = [];

    public function __construct(
        private TestCase $testCase
    ) {
    }

    /**
     * @param mixed ...$arguments
     *     Where each argument is a value or a {@link Constraint}.
     *     **Note:** Values are converted into constraints with {@link Assert::equalTo()}.
     *
     * @return $this
     */
    public function given(mixed ...$arguments): self
    {
        $i = &$this->i;
        $this->assertTermination($i);

        $i++;
        $this->given[$i] = $this->ensureConstraints($arguments);

        return $this;
    }

    /**
     * Shortcut for `given(Assert::anything())`.
     */
    public function default(): self
    {
        return $this->given(Assert::anything());
    }

    private function assertTermination(int $i): void
    {
        if ($i && !array_key_exists($i, $this->return) && !array_key_exists($i, $this->throw)) {
            throw new LogicException("given should be terminated with one of: return, throw");
        }
    }

    /**
     * @param mixed[] $arguments
     *
     * @return Constraint[]
     */
    private function ensureConstraints(array $arguments): array
    {
        return array_map(
            fn(mixed $value): Constraint => $value instanceof Constraint ? $value : Assert::equalTo($value),
            array_values($arguments)
        );
    }

    public function return(mixed $return): self
    {
        $i = $this->i;

        if (array_key_exists($i, $this->return)) {
            throw new LogicException("cannot have return twice");
        }

        if (array_key_exists($i, $this->throw)) {
            throw new LogicException("cannot use return after throw");
        }

        $this->return[$i] = $return;

        return $this;
    }

    /**
     * @param class-string|Throwable $exception
     *
     * @return $this
     */
    public function throw(string|Throwable $exception): self
    {
        $i = $this->i;

        if (array_key_exists($i, $this->throw)) {
            throw new LogicException("cannot have throw twice");
        }

        if (array_key_exists($i, $this->return)) {
            throw new LogicException("cannot have throw after return");
        }

        if (is_string($exception)) {
            $exception = $this->testCase
                ->getMockBuilder($exception)
                ->disableOriginalConstructor()
                ->getMock();
        }

        assert($exception instanceof Throwable);

        $this->throw[$this->i] = $exception;

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function invoke(Invocation $invocation): mixed
    {
        $params = array_values($invocation->getParameters());

        foreach ($this->given as $i => $constraints) {
            $this->assertTermination($i);

            if (!$this->matches($constraints, $params)) {
                continue;
            }

            $this->called[$i] = ($this->called[$i] ?? 0) + 1;

            $exception = $this->throw[$i] ?? null;

            if ($exception) {
                throw $exception;
            }

            return $this->return[$i];
        }

        $constraints = implode(
            "; ",
            array_map(
                fn($a) => implode(
                    ', ',
                    array_map(fn(Constraint $c) => $c->toString(), $a)
                ),
                $this->given
            )
        );

        throw new LogicException(
            "Unexpected invocation: {$invocation->toString()}"
            . ", didn't match any of the constraints: {$this->toString()}"
        );
    }

    public function toString(): string
    {
        return '[ ' . implode(
            ", ",
            array_map(
                fn($a) => '[ ' . implode(
                    ', ',
                    array_map(fn(Constraint $c) => $c->toString(), $a)
                )  . ' ]',
                $this->given
            )
        ) . ' ]';
    }

    /**
     * @param Constraint[] $constraints
     * @param mixed[] $values
     */
    private function matches(array $constraints, array $values): bool
    {
        if (count($constraints) !== count($values)) {
            return false;
        }

        foreach ($constraints as $i => $constraint) {
            if ($constraint->evaluate($values[$i], returnResult: true)) {
                continue;
            }

            return false;
        }

        return true;
    }
}
