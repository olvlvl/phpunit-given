# olvlvl/phpunit-given

[![Packagist](https://img.shields.io/packagist/v/olvlvl/phpunit-given.svg)](https://packagist.org/packages/olvlvl/phpunit-given)
[![Code Quality](https://img.shields.io/scrutinizer/g/olvlvl/phpunit-given.svg)](https://scrutinizer-ci.com/g/olvlvl/phpunit-given)
[![Code Coverage](https://img.shields.io/coveralls/olvlvl/phpunit-given.svg)](https://coveralls.io/r/olvlvl/phpunit-given)
[![Downloads](https://img.shields.io/packagist/dt/olvlvl/phpunit-given.svg)](https://packagist.org/packages/olvlvl/phpunit-given)

_olvlvl/phpunit-given_ provides an alternative to [PHPUnit](https://phpunit.de/)'s [ReturnValueMap][] and [ReturnCallback][], as well as a convenient solution to migrate from [Prophecy][].

#### Disclaimer

In most cases `ReturnCallback` with `match` can be used effectively. Don't use this package if you're comfortable with these and don't need extra features.

#### Usage

This is a simple example, more [use cases](#use-cases) below.

```php
use olvlvl\Given\GivenTrait;
use PHPUnit\Framework\TestCase;

final class IntegerNameTest extends TestCase
{
    use GivenTrait; // <-- adds the method 'given'

    public function testName(): void
    {
        $mock = $this->createMock(IntegerName::class);
        $mock->method('name')->will($this
            ->given(new Integer(6))->return("six")
            ->given(new Integer(12))->return("twelve")
            ->default()->throw(LogicException::class)
        );

        $this->assertEquals("six", $mock->name(new Integer(6)));
        $this->assertEquals("twelve", $mock->name(new Integer(12)));

        $this->expectException(LogicException::class);
        $mock->name(new Integer(99));
    }
}
```



#### Installation

```bash
composer require olvlvl/phpunit-given
```



## Motivation

Coming from [Prophecy][], [C# Moq](), [Golang Mock](https://github.com/golang/mock), or [Kotlin Mockk](https://mockk.io/), one would expect at least one of the following examples to work, but they do not.

```php
$mock = $this->createMock(IntegerName::class);
$mock
    ->method('name')
    ->with(new Integer(6))
    ->willReturn("six");
$mock
    ->method('name')
    ->with(new Integer(12))
    ->willReturn("twelve");

// the next line crashes with: Expectation failed
$this->assertEquals("six", $mock->name(new Integer(6)));
```

```php
$mock = $this->createMock(IntegerName::class);
$mock
    ->method('name')
    ->with(new Integer(6))->willReturn("six");
    // the next line crashes with: Method parameters already configured
    ->with(new Integer(12))->willReturn("twelve");

$this->assertEquals("six", $mock->name(new Integer(6)));
```

To return a value given certain arguments, one is expected to use [ReturnValueMap][] or [ReturnCallback][]. `ReturnValueMap` seems simple enough, but because it looks for [exact matches](https://github.com/sebastianbergmann/phpunit/blob/39efa00da7afd8460975f8532eb2687288472c27/src/Framework/MockObject/Stub/ReturnValueMap.php#L40) it fails when objects are included in the arguments, unless they are the same instances. Besides, `ReturnValueMap` does not support constraints, you can forget doing anything fancy with it. That leaves us with `ReturnCallback`, which can be used effectively with `match` but requires the introduction of logic in the test, [a practice that is discouraged](https://learn.microsoft.com/en-us/dotnet/core/testing/unit-testing-best-practices#avoid-logic-in-tests).

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->willReturnCallback(fn (Integer $int) => match ($int) {
    new Integer(6) => 'six',
    new Integer(12) => 'twelve',
    default => throw new Exception
}));
```

My motivation creating _olvlvl/phpunit-given_, is to have an alternative to [ReturnValueMap][] and [ReturnCallback][], that looks similar to what we find in other testing frameworks, and that allows easy migration from [Prophecy][].

Some PHPUnit issues, for reference:

- [Feature similar to withConsecutive(), but without checking order](https://github.com/sebastianbergmann/phpunit/issues/4026)
- [Improvements on withConsecutive with return](https://github.com/sebastianbergmann/phpunit/issues/4255)
- [Remove withConsecutive()](https://github.com/sebastianbergmann/phpunit/issues/4565)

## Use cases

### Comparing objects

[ReturnValueMap][] doesn't work with objects because it [uses strict equality when comparing
arguments](https://github.com/sebastianbergmann/phpunit/blob/39efa00da7afd8460975f8532eb2687288472c27/src/Framework/MockObject/Stub/ReturnValueMap.php#L40). The following code throws a `TypeError` exception because `ReturnValueMap` cannot find a match and defaults to a `null` value.

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->will($this->returnValueMap([
    [ new Integer(6), "six" ],
    [ new Integer(12), "twelve" ],
]));

$mock->name(new Integer(6)); // throws TypeError
```

_olvlvl/phpunit-given_ substitutes values with `Assert::equalTo()` and compares arguments using constraints. Having objects in the arguments is not a problem.

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->will($this
    ->given(new Integer(6))->return("six")
    ->given(new Integer(12))->return("twelve")
);

$this->assertEquals("six", $mock->name(new Integer(6)));
$this->assertEquals("twelve", $mock->name(new Integer(12)));
```

**Note:** You can use `Assert::identicalTo()` to check for the same instance.



### Using constraints

We established that values are substituted with `Assert::equalTo()` internally. Instead of values, you can also use constraints:

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->will($this
    ->given(Assert::lessThan(new Integer(6)))->return('too small')
    ->given(Assert::greaterThan(new Integer(9)))->return('too big')
    ->default()->return('just right') // `default()` is a shortcut for `given(Assert::anything())`
);

$this->assertEquals("too small", $mock->name(new Integer(5)));
$this->assertEquals("too big", $mock->name(new Integer(10)));
$this->assertEquals("just right", $mock->name(new Integer(6)));
$this->assertEquals("just right", $mock->name(new Integer(9)));
```

Of course, you could use `ReturnCallback`, although it adds logic to the test. Use whatever you feel more comfortable with.

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->willReturnCallback(fn (Integer $int) => match (true) {
    $int < new Integer(6) => 'too small',
    $int > new Integer(9) => 'too big',
    default => 'just right';
}));
```



### Migrating from Prophecy

_olvlvl/phpunit-given_ is a convenient solution to migrate from Prophecy because the code is quite similar:

```php
$container = $this->prophesize(ContainerInterface::class);
$container->has('serviceA')->willReturn(true);
$container->has('serviceB')->willReturn(false);
```
```php
$container = $this->createMock(ContainerInterface::class);
$container->method('has')->will($this
    ->given('serviceA')->return(true)
    ->given('serviceB')->return(false)
);
```

`throw()` is an alternative to `willThrow()`, and you can mismatch `return()` and `throw()`:

```php
$container = $this->prophesize(ContainerInterface::class);
$container->get('serviceA')->willReturn($serviceA);
$container->get('serviceB')->willThrow(new LogicException());
```
```php
$container = $this->createMock(ContainerInterface::class);
$container->method('get')->will($this
    ->given('serviceA')->return($serviceA)
    ->given('serviceB')->throw(LogicException::class)
);
```

Contrary to Prophecy, _olvlvl/phpunit-given_ does not return `null` by default, instead it throws an exception:

```php
$mock = $this->createMock(IntegerName::class);
$mock->method('name')->will($this
    ->given(new Integer(6))->return("six")
    ->given(new Integer(12))->return("twelve")
);

$mock->name(new Integer(13)); // throws an exception
```
```text
LogicException : Unexpected invocation: Test\olvlvl\Given\Acme\IntegerName::name(Test\olvlvl\Given\Acme\Integer Object (...)): string, didn't match any of the constraints: [ [ is equal to Test\olvlvl\Given\Acme\Integer Object &000000000000000c0000000000000000 (
'value' => 6
) ], [ is equal to Test\olvlvl\Given\Acme\Integer Object &00000000000001af0000000000000000 (
'value' => 12
) ] ]
```



----------



## Continuous Integration

The project is continuously tested by [GitHub actions](https://github.com/olvlvl/phpunit-given/actions).

[![Tests](https://github.com/olvlvl/phpunit-given/workflows/test/badge.svg?branch=main)](https://github.com/olvlvl/phpunit-given/actions?query=workflow%3Atest)
[![Static Analysis](https://github.com/olvlvl/phpunit-given/workflows/static-analysis/badge.svg?branch=main)](https://github.com/olvlvl/phpunit-given/actions?query=workflow%3Astatic-analysis)
[![Code Style](https://github.com/olvlvl/phpunit-given/workflows/code-style/badge.svg?branch=main)](https://github.com/olvlvl/phpunit-given/actions?query=workflow%3Acode-style)



## Code of Conduct

This project adheres to a [Contributor Code of Conduct](CODE_OF_CONDUCT.md). By participating in
this project and its community, you are expected to uphold this code.



## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.



## License

**olvlvl/phpunit-given** is released under the [BSD-3-Clause](LICENSE).



[ReturnValueMap]: https://github.com/sebastianbergmann/phpunit/blob/39efa00da7afd8460975f8532eb2687288472c27/src/Framework/MockObject/Stub/ReturnValueMap.php
[ReturnCallback]: https://github.com/sebastianbergmann/phpunit/blob/39efa00da7afd8460975f8532eb2687288472c27/src/Framework/MockObject/Stub/ReturnCallback.php
[Prophecy]: https://github.com/phpspec/prophecy/
