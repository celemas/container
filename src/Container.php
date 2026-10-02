<?php

declare(strict_types=1);

namespace Celema\Container;

use Celema\Container\Exception\ContainerException;
use Celema\Container\Exception\NotFoundException;
use Celema\Container\Exception\ResetFailed;
use Celema\Wire\CallableResolver;
use Celema\Wire\Creator;
use Celema\Wire\Exception\WireException;
use Closure;
use Override;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as PsrContainer;
use Throwable;

/** @psalm-api */
class Container implements PsrContainer
{
	protected Creator $creator;
	protected readonly ?PsrContainer $wrappedContainer;
	protected bool $sealed = false;

	/** @var array<string, Entry> */
	protected array $entries = [];

	/** @var array<string, mixed> */
	protected array $instances = [];

	/** @var array<non-empty-string, self> */
	protected array $tags = [];

	/** @var array<int, Resettable> */
	protected array $usedResettables = [];

	/** The root tag whose registrations a scope's tag container lists and resolves. */
	protected ?Container $inherits = null;

	public function __construct(
		public readonly bool $autowire = true,
		?PsrContainer $container = null,
		protected readonly string $tag = '',
		protected readonly ?Container $parent = null,
		protected readonly bool $isScope = false,
	) {
		if ($container) {
			$this->wrappedContainer = $container;
			$this->add(PsrContainer::class, $container);
			$this->add($container::class, $container);
		} else {
			$this->wrappedContainer = null;
			$this->add(PsrContainer::class, $this);
		}
		$this->add(Container::class, $this);
		$this->creator = new Creator($this);
	}

	public function scope(): Container
	{
		$root = $this->root();

		if (!$root->sealed) {
			$root->seal();
		}

		return new self(
			autowire: $root->autowire,
			parent: $root,
			isScope: true,
		);
	}

	public function reset(): void
	{
		if (!$this->isScope) {
			return;
		}

		$resetIds = [];
		$failures = [];
		$this->resetScope($resetIds, $failures);

		if ($failures !== []) {
			throw new ResetFailed($failures);
		}
	}

	#[Override]
	public function has(string $id): bool
	{
		return (
			isset($this->entries[$id])
				|| isset($this->inherits?->entries[$id])
				|| $this->parent?->has($id)
				|| $this->wrappedContainer?->has($id)
		);
	}

	/** @return list<string> */
	public function entries(bool $includeContainer = false): array
	{
		$inherited = $this->inherits?->entries(true) ?? [];
		$keys = $inherited === []
			? array_keys($this->entries)
			: array_values(array_unique([...array_keys($this->entries), ...$inherited]));

		if ($includeContainer) {
			return $keys;
		}

		return array_values(array_filter(
			$keys,
			static fn($item) => $item !== PsrContainer::class && !is_subclass_of($item, PsrContainer::class),
		));
	}

	public function entry(string $id): Entry
	{
		return (
			$this->entries[$id]
				?? $this->inherits?->entries[$id]
				?? throw new NotFoundException('Unresolvable entry - id: ' . $id)
		);
	}

	#[Override]
	public function get(string $id): mixed
	{
		try {
			if (array_key_exists($id, $this->instances)) {
				return $this->trackAndReturn($this->instances[$id]);
			}

			$resolved = $this->findEntry($id);

			if ($resolved !== null) {
				return $this->trackAndReturn(
					$this->resolveEntry(
						entryOwner: $resolved[0],
						entry: $resolved[1],
						id: $id,
						requester: $this,
					),
				);
			}

			if ($this->tag !== '' && $this->parent !== null) {
				// Ids that were not tagged belong to the container that owns the
				// tag. Resolving them there keeps their lifetime caches and the
				// overrides of an enclosing scope.
				return $this->parent->get($id);
			}

			$wrappedContainer = $this->root()->wrappedContainer;

			if ($wrappedContainer?->has($id)) {
				return $this->trackAndReturn($wrappedContainer->get($id));
			}

			// Autowiring: $id does not exists as an entry in the container
			if ($this->autowire && class_exists($id)) {
				return $this->trackAndReturn($this->creator->create($id));
			}
		} catch (WireException $e) {
			throw new NotFoundException(
				'Unresolvable id: ' . $id . ' - Details: ' . $e->getMessage(),
				previous: $e,
			);
		} catch (ContainerExceptionInterface $e) {
			throw $e;
		} catch (Throwable $e) {
			throw new ContainerException(
				'Unresolvable id: ' . $id . ' - ' . $e->getMessage(),
				previous: $e,
			);
		}

		throw new NotFoundException('Unresolvable id: ' . $id);
	}

	/**
	 * @param non-empty-string $id
	 */
	public function add(
		string $id,
		mixed $value = null,
	): Entry {
		$this->assertMutable();
		$entry = new Entry($id, $value ?? $id);
		$this->entries[$id] = $entry;
		unset($this->instances[$id]);

		return $entry;
	}

	public function addEntry(
		Entry $entry,
	): Entry {
		$this->assertMutable();
		$this->entries[$entry->id] = $entry;
		unset($this->instances[$entry->id]);

		return $entry;
	}

	/** @param non-empty-string $tag */
	public function tag(string $tag): Container
	{
		if (isset($this->tags[$tag])) {
			return $this->tags[$tag];
		}

		if ($this->sealed) {
			// Reading a tag that was never registered is not a structural change.
			// The empty view is not stored, so the sealed container stays as it is.
			$view = new self(autowire: $this->autowire, tag: $tag, parent: $this);
			$view->seal();

			return $view;
		}

		$tagContainer = new self(
			autowire: $this->autowire,
			tag: $tag,
			parent: $this,
			isScope: $this->isScope,
		);

		// A scope's tag lists the root tag's registrations and adds its own,
		// while untagged ids resolve through the scope.
		if ($this->isScope) {
			$tagContainer->inherits = $this->root()->tags[$tag] ?? null;
		}

		return $this->tags[$tag] = $tagContainer;
	}

	public function new(string $id, mixed ...$args): object
	{
		$entry = $this->entries[$id] ?? null;

		if ($entry) {
			/** @var mixed $value */
			$value = $entry->definition();

			if (is_string($value)) {
				if (interface_exists($value)) {
					return $this->new($value, ...$args);
				}

				if (class_exists($value)) {
					/** @psalm-suppress MixedMethodCall */
					return new $value(...$args);
				}
			}
		}

		if (class_exists($id)) {
			/** @psalm-suppress MixedMethodCall */
			return new $id(...$args);
		}

		throw new NotFoundException('Cannot instantiate ' . $id);
	}

	protected function resolveEntry(
		Container $entryOwner,
		Entry $entry,
		string $id,
		Container $requester,
	): mixed {
		if ($entry->shouldReturnValue()) {
			return $entry->definition();
		}

		if ($entry->getLifetime() === Lifetime::Scoped && !$requester->isScope && $requester->sealed) {
			throw new ContainerException(
				"Scoped entry '{$id}' cannot be resolved by the root container after scope() was called, "
					. 'as every scope would share that instance. It is most likely a dependency of a shared '
					. 'entry: make that entry scoped or transient, or resolve the entry from a scope.',
			);
		}

		[$cacheContainer, $resolutionContext] = $this->resolutionContainers(
			$entry,
			$entryOwner,
			$requester,
		);

		if ($cacheContainer !== null && array_key_exists($id, $cacheContainer->instances)) {
			return $cacheContainer->instances[$id];
		}

		// materialize() intentionally returns mixed because container entries may resolve to any value type.
		/** @psalm-suppress MixedAssignment */
		$result = $this->materialize($entry, $resolutionContext);

		if ($cacheContainer !== null) {
			$cacheContainer->instances[$id] = $result;
		}

		return $result;
	}

	/**
	 * @return array{0: null|Container, 1: Container}
	 */
	protected function resolutionContainers(
		Entry $entry,
		Container $entryOwner,
		Container $requester,
	): array {
		return match ($entry->getLifetime()) {
			Lifetime::Shared => [$entryOwner, $entryOwner],
			Lifetime::Scoped => [$requester, $requester],
			Lifetime::Transient => [null, $requester],
		};
	}

	protected function materialize(Entry $entry, Container $context): mixed
	{
		/** @var mixed $value */
		$value = $entry->definition();

		if (is_string($value)) {
			if (class_exists($value)) {
				$constructor = $entry->getConstructor();
				$args = $entry->getArgs();

				if (isset($args)) {
					// Don't autowire if $args are given
					if ($args instanceof Closure) {
						$args = $args(...new CallableResolver($context->creator)->resolve($args));

						return $this->applyCalls($entry, $context->creator->create($value, $args), $context);
					}

					return $this->applyCalls(
						$entry,
						$context->creator->create(
							$value,
							predefinedArgs: $args,
							constructor: $constructor ?? '',
						),
						$context,
					);
				}

				return $this->applyCalls(
					$entry,
					$context->creator->create($value, constructor: $constructor ?? ''),
					$context,
				);
			}

			if ($context->has($value)) {
				return $context->get($value);
			}
		}

		if ($value instanceof Closure) {
			$args = $entry->getArgs();

			if (is_null($args)) {
				$args = new CallableResolver($context->creator)->resolve($value);
			} elseif ($args instanceof Closure) {
				$args = $args();
			}

			return $this->applyCalls($entry, $value(...$args), $context);
		}

		if (is_object($value)) {
			return $value;
		}

		throw new NotFoundException('Unresolvable id: ' . (string) $value);
	}

	protected function applyCalls(Entry $entry, mixed $value, Container $context): mixed
	{
		foreach ($entry->getCalls() as $call) {
			$methodToResolve = $call->method;

			/** @var callable */
			$callable = [$value, $methodToResolve];
			$args = new CallableResolver($context->creator)->resolve($callable, $call->args);
			$callable(...$args);
		}

		return $value;
	}

	/**
	 * Attempts every reset hook and clears the scope even if hooks fail; the
	 * failures are collected for the caller.
	 *
	 * @param array<int, true> $resetIds
	 * @param list<Throwable> $failures
	 */
	protected function resetScope(array &$resetIds, array &$failures): void
	{
		// $instances stores mixed values by design, so foreach assigns mixed to $instance.
		/** @psalm-suppress MixedAssignment */
		foreach ($this->instances as $instance) {
			$this->resetIfNeeded($instance, $resetIds, $failures);
		}

		foreach ($this->usedResettables as $usedResettable) {
			$this->resetIfNeeded($usedResettable, $resetIds, $failures);
		}

		foreach ($this->tags as $tagContainer) {
			if (!$tagContainer->isScope) {
				continue;
			}

			$tagContainer->resetScope($resetIds, $failures);
		}

		$this->instances = [];
		$this->entries = [];
		$this->tags = [];
		$this->usedResettables = [];
		$this->add(PsrContainer::class, $this);
		$this->add(Container::class, $this);
	}

	/**
	 * @param array<int, true> $resetIds
	 * @param list<Throwable> $failures
	 */
	protected function resetIfNeeded(mixed $value, array &$resetIds, array &$failures): void
	{
		if (!$value instanceof Resettable) {
			return;
		}

		$objectId = spl_object_id($value);

		if (isset($resetIds[$objectId])) {
			return;
		}

		$resetIds[$objectId] = true;

		try {
			$value->reset();
		} catch (Throwable $e) {
			$failures[] = $e;
		}
	}

	protected function trackAndReturn(mixed $value): mixed
	{
		if ($this->isScope && $value instanceof Resettable) {
			$this->usedResettables[spl_object_id($value)] = $value;
		}

		return $value;
	}

	/** @return array{Container, Entry}|null */
	protected function findEntry(string $id): ?array
	{
		$entry = $this->entries[$id] ?? null;

		if ($entry !== null) {
			return [$this, $entry];
		}

		$inherits = $this->inherits;

		if ($inherits !== null && isset($inherits->entries[$id])) {
			// Inherited shared entries are cached by the root tag that defines them.
			return [$inherits, $inherits->entries[$id]];
		}

		// A tag container only finds tagged entries; get() hands other ids to
		// the container owning the tag.
		return $this->tag === '' ? $this->parent?->findEntry($id) : null;
	}

	protected function root(): Container
	{
		$container = $this;

		while ($container->parent !== null) {
			$container = $container->parent;
		}

		return $container;
	}

	protected function assertMutable(): void
	{
		if ($this->sealed) {
			throw new ContainerException('The root container is sealed after scope() was called');
		}
	}

	protected function seal(): void
	{
		$this->sealed = true;

		// Scoped entries resolved before the first scope must not outlive it:
		// the sealed container rejects them from now on.
		foreach ($this->entries as $id => $entry) {
			if ($entry->getLifetime() === Lifetime::Scoped) {
				unset($this->instances[$id]);
			}
		}

		foreach ($this->tags as $tagContainer) {
			$tagContainer->seal();
		}
	}
}
