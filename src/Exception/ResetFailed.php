<?php

declare(strict_types=1);

namespace Celema\Container\Exception;

use Throwable;

/**
 * Thrown by `Container::reset()` after every reset hook was attempted and the
 * scope was cleared. The first failure is the previous exception.
 *
 * @psalm-api
 */
final class ResetFailed extends ContainerException
{
	/** @param non-empty-list<Throwable> $failures */
	public function __construct(
		public readonly array $failures,
	) {
		$count = count($failures);

		parent::__construct(
			sprintf(
				'%d reset hook%s failed while resetting a container scope. First failure: %s',
				$count,
				$count === 1 ? '' : 's',
				$failures[0]->getMessage(),
			),
			previous: $failures[0],
		);
	}
}
