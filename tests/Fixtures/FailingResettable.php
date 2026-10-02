<?php

declare(strict_types=1);

namespace Celema\Container\Tests\Fixtures;

use Celema\Container\Resettable;
use RuntimeException;

final class FailingResettable implements Resettable
{
	public function __construct(
		public readonly string $message = 'reset failed',
	) {}

	public function reset(): void
	{
		throw new RuntimeException($this->message);
	}
}
