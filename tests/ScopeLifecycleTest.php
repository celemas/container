<?php

declare(strict_types=1);

namespace Celema\Container\Tests;

use Celema\Container\Container;
use Celema\Container\Exception\ContainerException;
use Celema\Container\Exception\ResetFailed;
use Celema\Container\Tests\Fixtures\CallCounter;
use Celema\Container\Tests\Fixtures\FailingResettable;
use Celema\Container\Tests\Fixtures\ResettableConsumer;
use Celema\Container\Tests\Fixtures\ResettableService;
use Celema\Wire\Creator;
use RuntimeException;
use stdClass;

/**
 * The container used the way a long-running process does: register at boot,
 * then one scope per unit of work, reset at its end.
 */
final class ScopeLifecycleTest extends TestCase
{
	public function testEachUnitOfWorkGetsFreshScopedServices(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->add('shared', static fn() => new stdClass());
		$services = [];
		$shared = [];

		for ($i = 0; $i < 3; $i++) {
			$scope = $container->scope();
			$service = $scope->get(ResettableService::class);
			$services[] = $service;
			$shared[] = $scope->get('shared');

			$this->assertSame($service, $scope->get(ResettableService::class));

			$scope->reset();

			$this->assertSame(1, $service->resetCalls);
		}

		$this->assertCount(3, array_unique(array_map(spl_object_id(...), $services)));
		$this->assertCount(1, array_unique(array_map(spl_object_id(...), $shared)));
	}

	public function testCreatorResolveHonorsEntryLifetimes(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->add(CallCounter::class);
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$creator1 = new Creator($scope1);
		$service11 = $creator1->resolve(ResettableService::class);
		$service12 = $creator1->resolve(ResettableService::class);
		$service2 = new Creator($scope2)->resolve(ResettableService::class);
		$counter = $creator1->resolve(CallCounter::class);

		$this->assertSame($service11, $service12);
		$this->assertSame($service11, $scope1->get(ResettableService::class));
		$this->assertNotSame($service11, $service2);
		$this->assertSame($counter, $scope2->get(CallCounter::class));
		$this->assertSame(1, $counter->calls);
	}

	public function testNestedDependenciesUseTheScopeInstance(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$scope = $container->scope();
		$consumer = new Creator($scope)->create(ResettableConsumer::class);

		$this->assertSame($scope->get(ResettableService::class), $consumer->service);
	}

	public function testScopedTagEntriesSeeScopeOverrides(): void
	{
		$container = new Container();
		$container->add('name', 'root')->value();
		$container
			->tag('api')
			->add('scoped', static fn(Container $c): mixed => $c->get('name'))
			->scoped();
		$container->tag('api')->add('shared', static fn(Container $c): mixed => $c->get('name'));
		$scope = $container->scope();
		$scope->add('name', 'request')->value();

		$this->assertSame('request', $scope->tag('api')->get('scoped'));
		$this->assertSame('root', $scope->tag('api')->get('shared'));
	}

	public function testTagContainersResolveUntaggedIdsThroughTheirScope(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->tag('api')->add('handler', stdClass::class);
		$scope = $container->scope();
		$tag = $scope->tag('api');

		$this->assertSame(true, $tag->has(ResettableService::class));
		$this->assertSame($scope->get(ResettableService::class), $tag->get(ResettableService::class));
	}

	public function testScopedEntryCannotBeCapturedBySharedEntry(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->add(ResettableConsumer::class);
		$scope = $container->scope();

		try {
			$scope->get(ResettableConsumer::class);
			$this->fail('Expected the captive scoped dependency to be rejected');
		} catch (ContainerException $e) {
			$this->assertStringContainsString(ResettableService::class, $e->getMessage());
			$this->assertStringContainsString('shared entry', $e->getMessage());
		}
	}

	public function testScopedConsumerMayDependOnScopedEntry(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->add(ResettableConsumer::class)->scoped();
		$scope = $container->scope();

		$this->assertSame(
			$scope->get(ResettableService::class),
			$scope->get(ResettableConsumer::class)->service,
		);
	}

	public function testSealedRootRejectsScopedEntries(): void
	{
		$this->throws(ContainerException::class, 'cannot be resolved by the root container');

		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->scope();
		$container->get(ResettableService::class);
	}

	public function testSealedRootRejectsScopedEntriesResolvedDuringBoot(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$bootService = $container->get(ResettableService::class);
		$scope = $container->scope();
		$service = $scope->get(ResettableService::class);

		$this->assertNotSame($bootService, $service);
		$this->assertSame($service, $scope->get(ResettableService::class));
		$this->throws(ContainerException::class, 'cannot be resolved by the root container');
		$container->get(ResettableService::class);
	}

	public function testSharedEntryCannotCaptureScopedDependencyResolvedDuringBoot(): void
	{
		$container = new Container();
		$container->add(ResettableService::class)->scoped();
		$container->add(ResettableConsumer::class);
		$container->get(ResettableService::class);
		$scope = $container->scope();

		$this->throws(ContainerException::class, 'cannot be resolved by the root container');
		$scope->get(ResettableConsumer::class);
	}

	public function testSealedRootTagRejectsScopedEntriesResolvedDuringBoot(): void
	{
		$container = new Container();
		$tag = $container->tag('api');
		$tag->add(ResettableService::class)->scoped();
		$bootService = $tag->get(ResettableService::class);
		$scopeTag = $container->scope()->tag('api');
		$service = $scopeTag->get(ResettableService::class);

		$this->assertNotSame($bootService, $service);
		$this->assertSame($service, $scopeTag->get(ResettableService::class));
		$this->throws(ContainerException::class, 'cannot be resolved by the root container');
		$tag->get(ResettableService::class);
	}

	public function testResetAttemptsEveryHookAndClearsTheScope(): void
	{
		$container = new Container();
		$container->add('first', static fn() => new FailingResettable('first failed'))->scoped();
		$container->add('second', static fn() => new FailingResettable('second failed'))->scoped();
		$container->add(ResettableService::class)->scoped();
		$scope = $container->scope();
		$scope->add('local', 'value')->value();
		$scope->get('first');
		$scope->get('second');
		$service = $scope->get(ResettableService::class);

		try {
			$scope->reset();
			$this->fail('Expected the failing reset hooks to be reported');
		} catch (ResetFailed $e) {
			$this->assertCount(2, $e->failures);
			$this->assertSame('first failed', $e->getPrevious()?->getMessage());
			$this->assertStringContainsString('2 reset hooks failed', $e->getMessage());
		}

		$this->assertSame(1, $service->resetCalls);
		$this->assertSame(false, $scope->has('local'));
		$this->assertNotSame($service, $scope->get(ResettableService::class));
	}

	public function testSingleResetFailureKeepsItsException(): void
	{
		$container = new Container();
		$container->add('failing', static fn() => new FailingResettable())->scoped();
		$scope = $container->scope();
		$scope->get('failing');

		try {
			$scope->reset();
			$this->fail('Expected the failing reset hook to be reported');
		} catch (ResetFailed $e) {
			$this->assertInstanceOf(RuntimeException::class, $e->getPrevious());
			$this->assertStringContainsString('1 reset hook failed', $e->getMessage());
		}
	}

	public function testPrebuiltObjectsCanOnlyBeShared(): void
	{
		$this->throws(ContainerException::class, 'prebuilt object');

		new Container()->add('service', new stdClass())->scoped();
	}

	public function testClosuresMayHaveAnyLifetime(): void
	{
		$container = new Container();
		$container->add('service', static fn() => new stdClass())->transient();

		$this->assertNotSame($container->get('service'), $container->get('service'));
	}
}
