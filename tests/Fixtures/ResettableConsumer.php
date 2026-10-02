<?php

declare(strict_types=1);

namespace Celema\Container\Tests\Fixtures;

final class ResettableConsumer
{
	public function __construct(
		public readonly ResettableService $service,
	) {}
}
