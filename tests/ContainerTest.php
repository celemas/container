<?php

declare(strict_types=1);

namespace Celema\Container\Tests;

use Celema\Container\Container;
use Celema\Container\Entry;
use Celema\Container\Exception\ContainerException;
use Celema\Container\Exception\NotFoundException;
use Celema\Container\Tests\Fixtures\ResettableService;
use Celema\Container\Tests\Fixtures\TestClass;
use Celema\Container\Tests\Fixtures\TestClassApp;
use Celema\Container\Tests\Fixtures\TestClassContainerArgs;
use Celema\Container\Tests\Fixtures\TestClassContainerSingleArg;
use Celema\Container\Tests\Fixtures\TestClassWithConstructor;
use Celema\Container\Tests\Fixtures\TestContainer;
use Closure;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;

final class ContainerTest extends TestCase
{
	public function testAddKeyWithoutValue(): void
	{
		$container = new Container();
		$container->add('value');

		$this->assertSame('value', $container->entry('value')->definition());
	}

	public function testAddEntryInstance(): void
	{
		$container = new Container();
		$container->addEntry(new Entry('key', 'value'));

		$this->assertSame('value', $container->entry('key')->definition());
	}

	public function testEntryInstanceAndValue(): void
	{
		$container = new Container();
		$container->add(stdClass::class);

		$this->assertSame(stdClass::class, $container->entry(stdClass::class)->definition());

		$obj1 = $container->get(stdClass::class);
		$obj2 = $container->get(stdClass::class);

		$this->assertSame(true, $obj1 instanceof stdClass);
		$this->assertSame(stdClass::class, $container->entry(stdClass::class)->definition());
		$this->assertSame($obj1, $obj2);
	}

	public function testCheckIfRegistered(): void
	{
		$container = new Container();
		$container->add('container', $container);

		$this->assertSame(true, $container->has('container'));
		$this->assertSame(false, $container->has('wrong'));
	}

	public function testCheckIfRegisteredOnParentFromTag(): void
	{
		$container = new Container();
		$container->add('container', $container);
		$tag = $container->tag('test');

		$this->assertSame(true, $tag->has('container'));
		$this->assertSame(false, $tag->has('wrong'));
	}

	public function testInstantiate(): void
	{
		$container = new Container();
		$container->add('container', Container::class);
		$container->add('test', TestClass::class);
		$con = $container->new('container');
		$test = $container->new('test');

		$this->assertSame(true, $con instanceof Container);
		$this->assertSame(true, $test instanceof TestClass);
	}

	public function testInstantiateWithCall(): void
	{
		$container = new Container();
		$container->add(TestClass::class)->call('init', value: 'testvalue');
		$tc = $container->get(TestClass::class);

		$this->assertSame(true, $tc instanceof TestClass);
		$this->assertSame('testvalue', $tc->value);
	}

	public function testChainedInstantiation(): void
	{
		$container = new Container();
		$container->add(
			\Psr\Container\ContainerExceptionInterface::class,
			\Psr\Container\NotFoundExceptionInterface::class,
		);
		$container->add(
			\Psr\Container\NotFoundExceptionInterface::class,
			NotFoundException::class,
		);
		$exception = $container->new(
			\Psr\Container\ContainerExceptionInterface::class,
			'The message',
			13,
		);

		$this->assertSame(true, $exception instanceof NotFoundException);
		$this->assertSame('The message', $exception->getMessage());
		$this->assertSame(13, $exception->getCode());
	}

	public function testFactoryMethodInstantiation(): void
	{
		$container = new Container();
		$container->add(TestClassContainerArgs::class)->constructor('fromDefaults');
		$instance = $container->get(TestClassContainerArgs::class);

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('fromDefaults', $instance->app->app());
		$this->assertSame('fromDefaults', $instance->test);
	}

	public function testFactoryMethodInstantiationWithArgs(): void
	{
		$container = new Container();
		$container
			->add(TestClassContainerArgs::class)
			->constructor('fromArgs')
			->args(test: 'passed', app: 'passed');
		$instance = $container->get(TestClassContainerArgs::class);

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('passed', $instance->app->app());
		$this->assertSame('passed', $instance->test);
	}

	public function testAutowiredInstantiation(): void
	{
		$container = new Container();

		$this->assertSame(true, $container->new(NotFoundException::class) instanceof NotFoundException);
	}

	public function testAutowiredInstantiationFails(): void
	{
		$this->throws(
			NotFoundException::class,
			'Cannot instantiate Celema\Container\Tests\NoValidClass',
		);

		$container = new Container();

		$this->assertSame(true, $container->new(NoValidClass::class) instanceof NotFoundException);
	}

	public function testResolveInstance(): void
	{
		$container = new Container();
		$object = new stdClass();
		$container->add('object', $object);

		$this->assertSame($object, $container->get('object'));
	}

	public function testResolveSimpleClass(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class);

		$this->assertSame(true, $container->get('class') instanceof stdClass);
	}

	public function testResolveChainedEntry(): void
	{
		$container = new Container();
		$container->add(
			Psr\Container\ContainerExceptionInterface::class,
			Psr\Container\NotFoundExceptionInterface::class,
		);
		$container->add(
			Psr\Container\NotFoundExceptionInterface::class,
			NotFoundException::class,
		);

		$this->assertSame(
			true,
			$container->get(Psr\Container\ContainerExceptionInterface::class) instanceof NotFoundException,
		);
	}

	public function testResolveClassWithConstructor(): void
	{
		$container = new Container();

		$object = $container->get(TestClassWithConstructor::class);

		$this->assertSame($object::class, TestClassWithConstructor::class);
		$this->assertSame(TestClass::class, $object->tc::class);
	}

	public function testResolveClosureClass(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add(
			'class',
			static fn(TestClassApp $app) => new TestClassContainerArgs(new TestClass(), 'chuck', $app),
		);
		$instance = $container->get('class');

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testEntryOnTagFromScopeResolvesRootRegistration(): void
	{
		$container = new Container();
		$container->tag('api')->add('handle', TestClass::class);
		$scopeTag = $container->scope()->tag('api');

		$this->assertSame(TestClass::class, $scopeTag->entry('handle')->definition());
	}

	public function testEntriesOnTagFromScopeListRootRegistrations(): void
	{
		$container = new Container();
		$container->tag('api')->add('first', TestClass::class);
		$container->tag('api')->add('second', TestClass::class);
		$scopeTag = $container->scope()->tag('api');
		$scopeTag->add('third', TestClass::class);

		$entries = $scopeTag->entries();
		sort($entries);

		$this->assertSame(['first', 'second', 'third'], $entries);
	}

	public function testEntriesOnTagDoNotLeakContainerRegistrations(): void
	{
		$container = new Container();
		$container->add('service', TestClass::class);
		$container->tag('api')->add('handle', TestClass::class);

		// A tag lists what was tagged; the owning container is its parent, and
		// inheriting from that would report every service under the tag.
		$this->assertSame(['handle'], $container->tag('api')->entries());
		$this->assertSame(['handle'], $container->scope()->tag('api')->entries());
	}

	public function testFailingEntry(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable entry - id: missing');

		new Container()->tag('api')->entry('missing');
	}

	public function testRejectUnresolvableClass(): void
	{
		$this->throws(ContainerException::class, 'Unresolvable');

		$container = new Container();
		$container->get(GdImage::class);
	}

	public function testGetWrapsUnexpectedThrowableInContainerException(): void
	{
		$this->throws(ContainerException::class, 'Unresolvable id: broken - boom');

		$container = new Container();
		$container->add('broken', static function (): never {
			throw new RuntimeException('boom');
		});
		$container->get('broken');
	}

	public function testGettingNonExistentClassFails(): void
	{
		$this->throws(NotFoundException::class, 'NonExistent');

		$container = new Container();
		$container->get('NonExistent');
	}

	public function testGettingNonResolvableEntryFails(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable id: Celema\Container\Tests\InvalidClass');

		$container = new Container();
		$container->add('unresolvable', InvalidClass::class);
		$container->get('unresolvable');
	}

	public function testGettingNonResolvableAutowiringFails(): void
	{
		$this->expectException(NotFoundException::class);
		$this->expectExceptionMessageMatches(
			'/^Unresolvable id: Celema\\\\Container\\\\Tests\\\\Fixtures\\\\TestClassContainerArgs - Details: \S/',
		);

		$container = new Container(autowire: true);
		$container->get(TestClassContainerArgs::class);
	}

	public function testRejectingClassWithNonResolvableParams(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable');

		$container = new Container();
		$container->add('unresolvable', TestClassContainerArgs::class);
		$container->get('unresolvable');
	}

	public function testResolveWithArgsArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerArgs::class)->args([
			'test' => 'chuck',
			'tc' => new TestClass(),
		]);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithSingleNamedArgArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerSingleArg::class)->args(
			test: 'chuck',
		);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerSingleArg);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithNamedArgsArray(): void
	{
		$container = new Container();
		$container->add('class', TestClassContainerArgs::class)->args(
			test: 'chuck',
			tc: new TestClass(),
		);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveClosureClassWithArgs(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add(
			'class',
			static fn(TestClassApp $app, string $name, TestClass $tc) => new TestClassContainerArgs(
				$tc,
				$name,
				$app,
			),
		)
			->args(app: new TestClassApp('chuck'), tc: new TestClass(), name: 'chuck');
		$instance = $container->get('class');

		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveWithArgsClosure(): void
	{
		$container = new Container();
		$container->add(TestClassApp::class, new TestClassApp('chuck'));
		$container->add('class', TestClassContainerArgs::class)->args(static fn(TestClassApp $app) => [
			'test' => 'chuck',
			'tc' => new TestClass(),
			'app' => $app,
		]);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testResolveClosureClassWithArgsClosure(): void
	{
		$container = new Container();
		$container->add(
			'class',
			static fn(TestClassApp $app, string $name, TestClass $tc) => new TestClassContainerArgs(
				$tc,
				$name,
				$app,
			),
		)
			->args(static fn() => [
				'app' => new TestClassApp('chuck'),
				'tc' => new TestClass(),
				'name' => 'chuck',
			]);
		$instance = $container->get('class');

		$this->assertSame(true, $instance instanceof TestClassContainerArgs);
		$this->assertSame(true, $instance->tc instanceof TestClass);
		$this->assertSame(true, $instance->app instanceof TestClassApp);
		$this->assertSame('chuck', $instance->test);
	}

	public function testRejectMultipleUnnamedArgs(): void
	{
		$this->throws(
			ContainerException::class,
			'Container entry arguments can be passed as a single associative array, as named arguments, or as a Closure',
		);

		$container = new Container();
		$container->add('class', static fn() => new stdClass())->args('chuck', 13);
	}

	public function testRejectSingleUnnamedArgWithWrongType(): void
	{
		$this->throws(
			ContainerException::class,
			'Container entry arguments can be passed as a single associative array, as named arguments, or as a Closure',
		);

		$container = new Container();
		$container->add('class', static fn() => new stdClass())->args('chuck');
	}

	public function testSharedLifetimeCachesObjectsByDefault(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class);
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame($obj1 === $obj2, true);
	}

	public function testValue(): void
	{
		$container = new Container();
		$container->add('closure1', static fn() => 'called');
		$container->add('closure2', static fn() => 'notcalled')->value();
		$value1 = $container->get('closure1');
		$value2 = $container->get('closure2');

		$this->assertSame('called', $value1);
		$this->assertSame(true, $value2 instanceof Closure);
	}

	public function testTransientLifetimeReturnsFreshInstances(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->transient();
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame(false, $obj1 === $obj2);
	}

	public function testScopedLifetimeCachesObjectsInSameContainer(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->scoped();
		$obj1 = $container->get('class');
		$obj2 = $container->get('class');

		$this->assertSame(true, $obj1 === $obj2);
	}

	public function testAutowiringUsesTransientLifetime(): void
	{
		$container = new Container();
		$obj1 = $container->get(stdClass::class);
		$obj2 = $container->get(stdClass::class);

		$this->assertSame(false, $obj1 === $obj2);
	}

	public function testScopeSealsRootForAddedEntries(): void
	{
		$this->throws(ContainerException::class, 'The root container is sealed after scope() was called');

		$container = new Container();
		$container->scope();
		$container->addEntry(new Entry('new-entry', stdClass::class));
	}

	public function testDisabledAutowiringRejectsUnregisteredClasses(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable id: stdClass');

		new Container(autowire: false)->get(stdClass::class);
	}

	public function testResetResetsAutowiredScopeServices(): void
	{
		$scope = new Container()->scope();
		$service = $scope->get(ResettableService::class);

		$scope->reset();

		$this->assertSame(1, $service->resetCalls);
	}

	public function testResetKeepsScopeAsItsOwnContainer(): void
	{
		$scope = new Container()->scope();

		$scope->reset();

		$this->assertSame($scope, $scope->get(Container::class));
		$this->assertSame($scope, $scope->get(ContainerInterface::class));
	}

	public function testScopeSealsRoot(): void
	{
		$this->throws(ContainerException::class, 'sealed');

		$container = new Container();
		$container->scope();
		$container->add('new-entry', stdClass::class);
	}

	public function testScopeHasOwnLocalEntries(): void
	{
		$container = new Container();
		$scope = $container->scope();
		$scope->add('request', new stdClass());

		$this->assertSame(true, $scope->has('request'));
		$this->assertSame(false, $container->has('request'));
	}

	public function testScopeStartsEmptyEveryTime(): void
	{
		$container = new Container();
		$scope1 = $container->scope();
		$scope1->add('request', new stdClass());
		$scope2 = $container->scope();

		$this->assertSame(true, $scope1->has('request'));
		$this->assertSame(false, $scope2->has('request'));
	}

	public function testScopeRebindsContainerEntriesToItself(): void
	{
		$container = new Container();
		$scope = $container->scope();

		$this->assertSame($container, $container->get(Container::class));
		$this->assertSame($container, $container->get(ContainerInterface::class));
		$this->assertSame($scope, $scope->get(Container::class));
		$this->assertSame($scope, $scope->get(ContainerInterface::class));
	}

	public function testRootSharedLifetimeIsReusedAcrossScopes(): void
	{
		$container = new Container();
		$container->add('service', static fn() => new stdClass())->shared();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service1 = $scope1->get('service');
		$service2 = $scope2->get('service');

		$this->assertSame(true, $service1 === $service2);
	}

	public function testRootScopedLifetimeCreatesOneInstancePerScope(): void
	{
		$container = new Container();
		$container->add('service', static fn() => new stdClass())->scoped();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service11 = $scope1->get('service');
		$service12 = $scope1->get('service');
		$service2 = $scope2->get('service');

		$this->assertSame(true, $service11 === $service12);
		$this->assertSame(false, $service11 === $service2);
	}

	public function testRootTransientLifetimeStaysTransientInScope(): void
	{
		$container = new Container();
		$container->add('service', static fn() => new stdClass())->transient();
		$scope = $container->scope();
		$service1 = $scope->get('service');
		$service2 = $scope->get('service');

		$this->assertSame(false, $service1 === $service2);
	}

	public function testSharedServicesResolveInOwnerContext(): void
	{
		$container = new Container();
		$container->add('name', 'root')->value();
		$container->add('service', static fn(Container $resolvedContainer): string => $resolvedContainer->get(
			'name',
		));
		$scope = $container->scope();
		$scope->add('name', 'scope')->value();

		$this->assertSame('root', $scope->get('service'));
	}

	public function testScopedServicesResolveInRequesterContext(): void
	{
		$container = new Container();
		$container->add('name', 'root')->value();
		$container
			->add('service', static fn(Container $resolvedContainer): string => $resolvedContainer->get(
				'name',
			))
			->scoped();
		$scope = $container->scope();
		$scope->add('name', 'scope')->value();

		$this->assertSame('scope', $scope->get('service'));
	}

	public function testResetCallsLocalResettableServices(): void
	{
		$container = new Container();
		$scope = $container->scope();
		$scope->add('service', ResettableService::class)->scoped();
		$service = $scope->get('service');

		$scope->reset();

		$this->assertSame(1, $service->resetCalls);
	}

	public function testResetCallsBorrowedRootResettableServicesOnce(): void
	{
		$container = new Container();
		$container->add('service', ResettableService::class)->shared();
		$scope = $container->scope();
		$service1 = $scope->get('service');
		$service2 = $scope->get('service');

		$scope->reset();

		$this->assertSame(true, $service1 === $service2);
		$this->assertSame(1, $service1->resetCalls);
	}

	public function testResetIgnoresNonResettableServices(): void
	{
		$container = new Container();
		$scope = $container->scope();
		$scope->add('service', stdClass::class)->scoped();
		$scope->get('service');
		$scope->reset();

		$this->assertSame(false, $scope->has('service'));
	}

	public function testResetClearsScopeLocalDefinitionsAndCaches(): void
	{
		$container = new Container();
		$container->add('service', static fn() => new stdClass())->scoped();
		$scope = $container->scope();
		$scope->add('local-value', 'value')->value();
		$service1 = $scope->get('service');
		$scope->reset();
		$service2 = $scope->get('service');

		$this->assertSame(false, $scope->has('local-value'));
		$this->assertSame(false, $service1 === $service2);
	}

	public function testResetResetsScopeTagsRecursively(): void
	{
		$container = new Container();
		$container->tag('api')->add('shared', ResettableService::class)->scoped();
		$scope = $container->scope();
		$scopeTag = $scope->tag('api');
		$scopeTag->add('local', ResettableService::class)->scoped();
		$shared = $scopeTag->get('shared');
		$local = $scopeTag->get('local');

		$scope->reset();

		$this->assertSame(1, $shared->resetCalls);
		$this->assertSame(1, $local->resetCalls);
		$this->assertSame(false, $scope->tag('api')->has('local'));
	}

	public function testResetOnRootContainerKeepsDefinitions(): void
	{
		$container = new Container();
		$container->add('service', stdClass::class)->shared();

		$container->reset();

		$this->assertSame(true, $container->has('service'));
	}

	public function testResetSkipsNonScopeTagsInScopeTagMap(): void
	{
		$container = new Container();
		$rootTag = $container->tag('api');
		$rootTag->add('shared', 'root')->value();
		$scope = $container->scope();
		$tagsProperty = new ReflectionProperty(Container::class, 'tags');
		$scopeTags = ['injected-root-tag' => $rootTag];
		$tagsProperty->setValue($scope, $scopeTags);

		$scope->reset();

		$this->assertSame('root', $rootTag->get('shared'));
	}

	public function testFetchEntriesList(): void
	{
		$container = new Container();
		$container->add('class', stdClass::class)->transient();

		$this->assertSame(['class'], $container->entries());
		$this->assertSame(
			['Psr\Container\ContainerInterface', 'Celema\Container\Container', 'class'],
			$container->entries(includeContainer: true),
		);
	}

	public function testAddAndReceiveTaggedEntries(): void
	{
		$container = new Container();
		$container->tag('tag')->add('class', stdClass::class);
		$container->tag('tag')->add('container', Container::class);
		$obj = $container->tag('tag')->get('class');
		$entry = $container->tag('tag')->entry('class');
		$entryCon = $container->tag('tag')->entry('container');

		$this->assertSame(['class', 'container'], $container->tag('tag')->entries());
		$this->assertSame(
			[
				'Psr\Container\ContainerInterface',
				'Celema\Container\Container',
				'class',
				'container',
			],
			$container->tag('tag')->entries(true),
		);
		$this->assertSame(true, $obj instanceof stdClass);
		$this->assertSame(stdClass::class, $entry->definition());
		$this->assertSame(Container::class, $entryCon->definition());
		$this->assertSame(true, $obj === $container->tag('tag')->get('class'));
		$this->assertSame(true, $container->tag('tag')->has('class'));
		$this->assertSame(true, $container->tag('tag')->has('container'));
		$this->assertSame(false, $container->tag('tag')->has('wrong'));
		$this->assertSame(false, $container->has('class'));
		$this->assertSame(false, $container->has('container'));
	}

	public function testAddTaggedKeyWithoutValue(): void
	{
		$container = new Container();
		$container->tag('tag')->add(TestClassApp::class);

		$this->assertSame(
			TestClassApp::class,
			$container->tag('tag')->entry(TestClassApp::class)->definition(),
		);
	}

	public function testUnknownRootTagIsAnEmptySealedViewAfterFirstScope(): void
	{
		$container = new Container();
		$container->add('service', stdClass::class);
		$container->scope();
		$tag = $container->tag('new-tag');

		$this->assertSame([], $tag->entries());
		$this->assertSame(false, $tag->has('missing'));
		$this->assertSame(true, $tag->get('service') instanceof stdClass);

		$this->throws(ContainerException::class, 'sealed');
		$tag->add('new-entry', stdClass::class);
	}

	public function testKnownRootTagIsSealedAfterFirstScope(): void
	{
		$this->throws(ContainerException::class, 'sealed');

		$container = new Container();
		$tag = $container->tag('api');
		$container->scope();

		$this->assertSame($tag, $container->tag('api'));
		$tag->add('new-entry', stdClass::class);
	}

	public function testScopeTagListsOverriddenEntriesOnce(): void
	{
		$container = new Container();
		$container->tag('api')->add('service', stdClass::class);
		$container->tag('api')->add('other', stdClass::class);
		$tag = $container->scope()->tag('api');
		$tag->add('service', stdClass::class);

		$this->assertSame(['service', 'other'], $tag->entries());
		$this->assertSame(
			[ContainerInterface::class, Container::class, 'service', 'other'],
			$tag->entries(includeContainer: true),
		);
	}

	public function testScopeTagEntryPrefersOwnDefinition(): void
	{
		$container = new Container();
		$container->tag('api')->add('service', stdClass::class);
		$tag = $container->scope()->tag('api');
		$own = $tag->add('service', stdClass::class);

		$this->assertSame($own, $tag->entry('service'));
	}

	public function testScopeTagInheritsRootTagDefinitions(): void
	{
		$container = new Container();
		$container
			->tag('api')
			->add('service', static fn() => new stdClass())
			->scoped();
		$scope = $container->scope();
		$tag = $scope->tag('api');
		$service1 = $tag->get('service');
		$service2 = $tag->get('service');

		$this->assertSame(true, $service1 instanceof stdClass);
		$this->assertSame(true, $service1 === $service2);
	}

	public function testScopeTagsKeepOwnScopedCaches(): void
	{
		$container = new Container();
		$container
			->tag('api')
			->add('service', static fn() => new stdClass())
			->scoped();
		$scope1 = $container->scope();
		$scope2 = $container->scope();
		$service1 = $scope1->tag('api')->get('service');
		$service2 = $scope2->tag('api')->get('service');

		$this->assertSame(false, $service1 === $service2);
	}

	public function testThirdPartyContainer(): void
	{
		$testContainer = new TestContainer();
		$testContainer->add('external', new stdClass());
		$container = new Container(container: $testContainer);
		$container->add('internal', new Container());

		$this->assertSame(true, $container->get('external') instanceof stdClass);
		$this->assertSame(true, $container->get('internal') instanceof Container);
		$this->assertSame(true, $container->get(ContainerInterface::class) instanceof TestContainer);
		$this->assertSame($testContainer, $container->get(ContainerInterface::class));
		$this->assertSame($testContainer, $container->get(TestContainer::class));
	}

	public function testScopeResolvesWrappedEntriesViaRoot(): void
	{
		$testContainer = new TestContainer();
		$external = new stdClass();
		$testContainer->add('external', $external);
		$container = new Container(container: $testContainer);
		$scope = $container->scope();

		$this->assertSame($external, $scope->get('external'));
	}

	public function testScopePrefersParentEntriesOverWrappedContainer(): void
	{
		$testContainer = new TestContainer();
		$testContainer->add('shared-key', 'wrapped');
		$container = new Container(container: $testContainer);
		$container->add('shared-key', 'root')->value();
		$scope = $container->scope();

		$this->assertSame('root', $scope->get('shared-key'));
	}

	public function testGettingNonExistentTaggedEntryFails(): void
	{
		$this->throws(NotFoundException::class, 'Unresolvable id: NonExistent');

		$container = new Container();

		$container->tag('tag')->get('NonExistent');
	}
}
