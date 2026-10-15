<?php

declare(strict_types=1);

namespace Celema\Container\Tests\Fixtures;

class TestClassContainerArgs
{
	public function __construct(
		public readonly TestClass $tc,
		public readonly string $test,
		public readonly ?TestClassApp $app = null,
	) {}

	public static function fromDefaults(): self
	{
		return new self(new TestClass(), 'fromDefaults', new TestClassApp('fromDefaults'));
	}

	public static function fromArgs(TestClass $tc, string $test, string $app): self
	{
		return new self($tc, $test, new TestClassApp($app));
	}
}
