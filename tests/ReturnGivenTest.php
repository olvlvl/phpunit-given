<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Test\olvlvl\Given;

use BadMethodCallException;
use Exception;
use LogicException;
use olvlvl\Given\GivenTrait;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\Invocation;
use PHPUnit\Framework\TestCase;
use Test\olvlvl\Given\Acme\BooleanValue;
use Test\olvlvl\Given\Acme\Integer;
use Test\olvlvl\Given\Acme\IntegerName;
use Throwable;
use TypeError;

use function uniqid;

final class ReturnGivenTest extends TestCase
{
    use GivenTrait;

    public function testToString(): void
    {
        $actual = $this
            ->given(Assert::greaterThan(13), Assert::anything())->return(1)
            ->default()->throw(LogicException::class)
            ->toString();

        $this->assertEquals("[ [ is greater than 13, is anything ], [ is anything ] ]", $actual);
    }

    public function testFailOnMissingTerminator(): void
    {
        $given = $this->given(1);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("given should be terminated with one of: return, throw");
        $given->given(2);
    }

    public function testFailOnReturnTwice(): void
    {
        $given = $this->given(1)->return(2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("cannot have return twice");
        $given->return(3);
    }

    public function testFailOnReturnAfterThrow(): void
    {
        $given = $this->given(1)->throw(Exception::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("cannot use return after throw");
        $given->return(2);
    }

    public function testFailOnThrowTwice(): void
    {
        $given = $this->given(1)->throw(Exception::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("cannot have throw twice");
        $given->throw(Exception::class);
    }

    public function testFailOnThrowAfterReturn(): void
    {
        $given = $this->given(1)->return(2);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("cannot have throw after return");
        $given->throw(Exception::class);
    }

    /**
     * @dataProvider providePassingConstraints
     * @throws Throwable
     */
    public function testPassingConstraints(array $givenArguments, array $invokeArguments): void
    {
        $return = uniqid();
        $given = $this->given(...$givenArguments)->return($return);
        $invocation = $this->makeInvocation(...$invokeArguments);

        $actual = $given->invoke($invocation);
        $this->assertSame($return, $actual);
    }

    public static function providePassingConstraints(): array
    {
        return [

            "same arguments" => [
                [ 1, true, "yes" ],
                [ 1, true, "yes" ]
            ],

            "same arguments, with objects" => [
                [ new Integer(1), true, "yes" ],
                [ new Integer(1), true, "yes" ]
            ],

            "using constraints" => [
                [ Assert::greaterThan(12), Assert::isTrue(), Assert::stringStartsWith("y") ],
                [ 13, true, "yes" ]
            ],

        ];
    }

    /**
     * @throws Throwable
     */
    public function testUnexpectedInvoke(): void
    {
        $given = $this->given(1, true, "yes")->return(uniqid());
        $invocation = $this->makeInvocation(1, true, "no");

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            "Unexpected invocation: SampleClass::SampleMethod(1, true, 'no'): SampleReturnType, didn't match any of the constraints: [ [ is equal to 1, is equal to true, is equal to 'yes' ] ]"
        );
        $given->invoke($invocation);
    }

    /**
     * @dataProvider provideFailingConstraints
     * @throws Throwable
     */
    public function testFailingConstraints(array $givenArguments, array $invokeArguments): void
    {
        $return = uniqid();
        $given = $this->given(...$givenArguments)->return($return);
        $invocation = $this->makeInvocation(...$invokeArguments);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/^Unexpected invocation/');
        $given->invoke($invocation);
    }

    public static function provideFailingConstraints(): array
    {
        return [

            "less parameters" => [
                [ 1, true, "yes" ],
                [ 1, true ]
            ],

            "more parameters" => [
                [ 1, true, "yes" ],
                [ 1, true, "yes", "extra" ]
            ],

            "scalars" => [
                [ 1, true, "yes" ],
                [ 1, false, "yes" ]
            ],

            "scalars and objects" => [
                [ new Integer(1), true, "yes" ],
                [ new Integer(2), true, "yes" ]
            ],

            "constraints" => [
                [ Assert::greaterThan(13), Assert::isTrue(), Assert::stringStartsWith("y") ],
                [ 13, true, "yes" ]
            ],

        ];
    }

    /**
     * Illustrates how `ReturnValueMap` fails with objects.
     */
    public function testReturnValueMapFailure(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will(
            $this->returnValueMap([
                [ new Integer(6), "six" ],
                [ new Integer(12), "twelve" ],
            ])
        );

        $this->expectException(TypeError::class);
        $this->expectExceptionMessageMatches('/Return value must be of type string, null returned/');
        $mock->name(new Integer(6));
    }

    /**
     * Illustrates how `ReturnValueMap` fails with objects.
     */
    public function testExpectationCrash(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock
            ->method('name')
            ->with(new Integer(6))
            ->willReturn("six");
        $mock
            ->method('name')
            ->with(new Integer(12))
            ->willReturn("twelve");

        try {
            $this->assertEquals("six", $mock->name(new Integer(6)));
        } catch (Throwable $e) {
            $this->assertStringStartsWith("Expectation failed", $e->getMessage());

            return;
        }

        $this->fail("Expected failure");
    }

    /**
     * Illustrates how `ReturnValueMap` fails with objects.
     */
    public function testAlreadyConfiguredCrash(): void
    {
        $mock = $this->createMock(IntegerName::class);

        try {
            $mock
                ->method('name')
                ->with(new Integer(6))->willReturn("six")
                ->with(new Integer(12))->willReturn("twelve");
        } catch (Throwable $e) {
            $this->assertStringStartsWith("Method parameters already configured", $e->getMessage());

            return;
        }

        $this->fail("Expected failure");
    }

    public function testObjectsAreSupported(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will(
            $this
                ->given(new Integer(6))->return("six")
                ->given(new Integer(12))->return("twelve")
        );

        $this->assertEquals("six", $mock->name(new Integer(6)));
        $this->assertEquals("twelve", $mock->name(new Integer(12)));
    }

    /**
     * Illustrates how `ReturnCallback` requires the introduction of conditionals in the test.
     */
    public function testMultipleConstraintsUsingCallback(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will(
            $this
                ->returnCallback(function (Integer $int) {
                    if ($int < new Integer(6)) {
                        return 'too small';
                    }
                    if ($int > new Integer(9)) {
                        return 'too big';
                    }
                    return 'just right';
                })
        );

        $this->assertEquals("too small", $mock->name(new Integer(5)));
        $this->assertEquals("too big", $mock->name(new Integer(10)));
        $this->assertEquals("just right", $mock->name(new Integer(6)));
        $this->assertEquals("just right", $mock->name(new Integer(9)));
    }

    public function testMultipleConstraints(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will(
            $this
                ->given(Assert::lessThan(new Integer(6)))->return('too small')
                ->given(Assert::greaterThan(new Integer(9)))->return('too big')
                ->default()->return('just right')
        );

        $this->assertEquals("too small", $mock->name(new Integer(5)));
        $this->assertEquals("too big", $mock->name(new Integer(10)));
        $this->assertEquals("just right", $mock->name(new Integer(6)));
        $this->assertEquals("just right", $mock->name(new Integer(9)));
    }

    public function testMultipleConstraintsWithMatch(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->willReturnCallback(
            fn (Integer $int) => match (true) {
                $int < new Integer(6) => 'too small',
                $int > new Integer(9) => 'too big',
                default => 'just right'
            }
        );

        $this->assertEquals("too small", $mock->name(new Integer(5)));
        $this->assertEquals("too big", $mock->name(new Integer(10)));
        $this->assertEquals("just right", $mock->name(new Integer(6)));
        $this->assertEquals("just right", $mock->name(new Integer(9)));
    }

    public function testThrow(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will(
            $this
                ->given(new Integer(6))->return("six")
                ->given(new Integer(12))->throw(LogicException::class)
        );

        $this->assertEquals("six", $mock->name(new Integer(6)));
        $this->expectException(LogicException::class);
        $mock->name(new Integer(12));
    }

    /**
     * Make sure termination check works with "empty" values.
     */
    public function testBoolean(): void
    {
        $mock = $this->createMock(BooleanValue::class);
        $mock->method('getValue')->will(
            $this
                ->given("yes")->return(true)
                ->given("no")->return(false)
        );

        $this->assertTrue($mock->getValue("yes"));
        $this->assertFalse($mock->getValue("no"));
    }

    private function makeInvocation(mixed ...$parameters): Invocation
    {
        return new Invocation(
            'SampleClass',
            'SampleMethod',
            $parameters,
            'SampleReturnType',
            new class () {
            }
        );
    }
}
