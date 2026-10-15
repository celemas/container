<?php

declare(strict_types=1);

namespace Celema\Container\Tests\Fixtures;

use Exception;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface as NotFoundException;

class TestContainer implements ContainerInterface
{
	/** @var array<string, mixed> */
	protected array $entries = [];

	public function add(string $id, mixed $entry = null): void
	{
		$this->entries[$id] = $entry ?? $id;
	}

	public function has(string $id): bool
	{
		return isset($this->entries[$id]);
	}

	public function get(string $id): mixed
	{
		if ($this->has($id)) {
			return $this->entries[$id];
		}

		throw new class extends Exception implements NotFoundException {};
	}
}
