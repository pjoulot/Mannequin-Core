<?php

/*
 * This file is part of Mannequin.
 *
 * (c) 2017 Last Call Media, Rob Bayliss <rob@lastcallmedia.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LastCall\Mannequin\Core\Tests;

use LastCall\Mannequin\Core\Cache\NullCacheItemPool;
use LastCall\Mannequin\Core\MannequinConfig;
use LastCall\Mannequin\Core\ConfigInterface;
use LastCall\Mannequin\Core\Discovery\ChainDiscovery;
use LastCall\Mannequin\Core\Discovery\DiscoveryInterface;
use LastCall\Mannequin\Core\Engine\DelegatingEngine;
use LastCall\Mannequin\Core\Extension\CoreExtension;
use LastCall\Mannequin\Core\Extension\ExtensionInterface;
use LastCall\Mannequin\Core\Pattern\PatternCollection;
use LastCall\Mannequin\Core\Variable\ResolverInterface;
use LastCall\Mannequin\Core\Variable\SetResolver;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class MannequinConfigTest extends TestCase
{
    public function testCanUseCreate()
    {
        $config = MannequinConfig::create();
        $this->assertInstanceOf(MannequinConfig::class, $config);
    }

    public function testSetsConfigWhenExtensionsAreUsed()
    {
        $extension = $this->prophesize(ExtensionInterface::class);
        $config = new MannequinConfig();
        $extension->setConfig($config)->shouldBeCalled();
        $config->addExtension($extension->reveal());
        $config->getExtensions();
    }

    public function testHasDiscovery()
    {
        $config = new MannequinConfig();
        $this->assertInstanceOf(ChainDiscovery::class, $config['discovery']);
    }

    public function testUsesExtensionDiscoverers()
    {
        $discoverer = $this->prophesize(DiscoveryInterface::class);
        $discoverer->discover()
            ->willReturn(new PatternCollection())
            ->shouldBeCalled();
        $extension = $this->getMockExtension();
        $extension->getDiscoverers()->willReturn([$discoverer]);
        $config = new MannequinConfig();
        $config->addExtension($extension->reveal());
        $config->getCollection();
    }

    private function getMockExtension()
    {
        $extension = $this->prophesize(ExtensionInterface::class);
        $extension->setConfig(Argument::type(ConfigInterface::class))->will(
            function () {
            }
        );
        $extension->attachToDispatcher(
            Argument::type(EventDispatcherInterface::class)
        )->will(
            function () {
            }
        );

        return $extension;
    }

    public function testUsesExtensionVariableResolvers()
    {
        $resolver = $this->prophesize(ResolverInterface::class);
        $resolver->resolves('foo')->willReturn(true);

        $extension = $this->getMockExtension();
        $extension->getVariableResolvers()
            ->willReturn([$resolver])
            ->shouldBeCalled();

        $extension->getVariableResolvers()->shouldBeCalled();
        $config = new MannequinConfig();
        $config->addExtension($extension->reveal());
        $this->assertTrue($config->getVariableResolver()->resolves('foo'));
    }

    public function testHasCoreExtension()
    {
        $config = new MannequinConfig();
        $extensions = $config->getExtensions();
        $this->assertCount(1, $extensions);
        $this->assertInstanceOf(CoreExtension::class, reset($extensions));
    }

    public function testHasRenderer()
    {
        $config = new MannequinConfig();
        $this->assertInstanceOf(
            DelegatingEngine::class,
            $config->getRenderer()
        );
    }

    public function testHasVariableFactory()
    {
        $config = new MannequinConfig();
        $this->assertInstanceOf(
            SetResolver::class,
            $config->getVariableResolver()
        );
    }

    public function testCallsExtensionAttachToDispatcher()
    {
        $config = new MannequinConfig();
        $extension = $this->getMockExtension();
        $extension->attachToDispatcher(
            Argument::type(EventDispatcherInterface::class)
        )->will(
            function ($args) {
                $args[0]->addListener(
                    'foo',
                    function () {
                    }
                );
            }
        )->shouldBeCalled();
        $config->addExtension($extension->reveal());
        $dispatcher = $config->getDispatcher();
        $this->assertTrue($dispatcher->hasListeners('foo'));
    }

    public function testHasDefaultCache()
    {
        $config = new MannequinConfig();
        $this->assertEquals(
            new NullCacheItemPool(),
            $config->getCache()
        );
    }

    public function testCacheCanBeOverridden()
    {
        $cache = $this->prophesize(CacheItemPoolInterface::class)->reveal();
        $config = new MannequinConfig(['cache' => $cache]);
        $this->assertEquals($cache, $config->getCache());
    }

    public function testHasDefaultStyles()
    {
        $config = new MannequinConfig();
        $this->assertEquals([], $config->getStyles());
    }

    public function testCanOverrideStyles()
    {
        $config = new MannequinConfig(['styles' => ['foo']]);
        $this->assertEquals(['foo'], $config->getStyles());
    }

    public function testHasDefaultScripts()
    {
        $config = new MannequinConfig();
        $this->assertEquals([], $config->getScripts());
    }

    public function testCanOverrideScripts()
    {
        $config = new MannequinConfig(['scripts' => ['foo']]);
        $this->assertEquals(['foo'], $config->getScripts());
    }

    public function testHasDefaultAssetMapping()
    {
        $config = new MannequinConfig();
        $this->assertEquals([], $config->getAssetMappings());
    }

    public function testCanAddAssetMappings()
    {
        $config = new MannequinConfig();
        $config->addAssetMapping('foo', __DIR__);
        $this->assertEquals(['foo' => __DIR__], $config->getAssetMappings());
    }
}