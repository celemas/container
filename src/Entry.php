<?php

declare(strict_types=1);

namespace Celema\Container;

use Celema\Container\Exception\ContainerException;
use Celema\Wire\Call;
use Closure;

/** @psalm-api */
class Entry
{
	/** @var array<array-key, mixed>|Closure(mixed...): array<array-key, mixed>|null */
	protected array|Closure|null $args = null;

	protected ?string $constructor = null;
	protected bool $value = false;
	protected Lifetime $lifetime = Lifetime::Shared;

	/** @var list<Call> */
	protected array $calls = [];

	/**
	 * @param non-empty-string $id
	 */
	public function __construct(
		public readonly string $id,
		protected mixed $definition,
	) {}

	public function lifetime(Lifetime $lifetime): static
	{
		if ($lifetime !== Lifetime::Shared && is_object($this->definition) && !$this->definition instanceof Closure) {
			throw new ContainerException(
				"Entry '{$this->id}' is a prebuilt object, which can only be shared. Register a class name "
					. 'or a closure to create one instance per scope or per resolution.',
			);
		}

		$this->lifetime = $lifetime;

		return $this;
	}

	public function shared(): static
	{
		return $this->lifetime(Lifetime::Shared);
	}

	public function scoped(): static
	{
		return $this->lifetime(Lifetime::Scoped);
	}

	public function transient(): static
	{
		return $this->lifetime(Lifetime::Transient);
	}

	public function getLifetime(): Lifetime
	{
		return $this->lifetime;
	}

	public function value(bool $value = true): static
	{
		$this->value = $value;

		return $this;
	}

	public function shouldReturnValue(): bool
	{
		return $this->value;
	}

	public function args(mixed ...$args): static
	{
		$numArgs = count($args);

		if ($numArgs === 1) {
			if (is_string(array_key_first($args))) {
				$this->args = $args;
			} elseif (is_array($args[0]) || $args[0] instanceof Closure) {
				/** @var array<array-key, mixed>|Closure(mixed...): array<array-key, mixed> */
				$this->args = $args[0];
			} else {
				throw new ContainerException(
					'Container entry arguments can be passed as a single associative array, '
						. 'as named arguments, or as a Closure',
				);
			}
		} elseif ($numArgs > 1) {
			if (!is_string(array_key_first($args))) {
				throw new ContainerException(
					'Container entry arguments can be passed as a single associative array, '
						. 'as named arguments, or as a Closure',
				);
			}

			$this->args = $args;
		}

		return $this;
	}

	/** @return array<array-key, mixed>|Closure(mixed...): array<array-key, mixed>|null */
	public function getArgs(): array|Closure|null
	{
		return $this->args;
	}

	public function constructor(string $methodName): static
	{
		$this->constructor = $methodName;

		return $this;
	}

	public function getConstructor(): ?string
	{
		return $this->constructor;
	}

	public function call(string $method, mixed ...$args): static
	{
		$this->calls[] = new Call($method, ...$args);

		return $this;
	}

	/** @return list<Call> */
	public function getCalls(): array
	{
		return $this->calls;
	}

	public function definition(): mixed
	{
		return $this->definition;
	}
}
