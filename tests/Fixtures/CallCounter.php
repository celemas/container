<?php

declare(strict_types=1);

namespace Celema\Container\Tests\Fixtures;

use Celema\Wire\Call;

#[Call('initialize')]
final class CallCounter
{
	public int $calls = 0;

	public function initialize(): void
	{
		++$this->calls;
	}
}
