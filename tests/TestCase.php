<?php

declare(strict_types=1);

namespace Celema\Container\Tests;

use Celema\Container\Container;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Throwable;

/**
 * @internal
 *
 * @coversNothing
 */
class TestCase extends BaseTestCase
{
	public function container(
		bool $autowire = true,
	): Container {
		$container = new Container(autowire: $autowire);
		$container->add(Container::class, $container);

		return $container;
	}

	/** @param class-string<Throwable> $exception */
	public function throws(string $exception, ?string $message = null): void
	{
		$this->expectException($exception);

		if ($message !== null) {
			$this->expectExceptionMessage($message);
		}
	}
}
