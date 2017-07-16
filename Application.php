<?php

/*
 * This file is part of Mannequin.
 *
 * (c) 2017 Last Call Media, Rob Bayliss <rob@lastcallmedia.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LastCall\Mannequin\Core;

use LastCall\Mannequin\Core\Console\Application as ConsoleApplication;
use LastCall\Mannequin\Core\Console\Command\RenderCommand;
use LastCall\Mannequin\Core\Console\Command\ServerCommand;
use LastCall\Mannequin\Core\MimeType\ExtensionMimeTypeGuesser;
use LastCall\Mannequin\Core\Ui\Controller\ManifestController;
use LastCall\Mannequin\Core\Ui\Controller\RenderController;
use LastCall\Mannequin\Core\Ui\Controller\UiController;
use LastCall\Mannequin\Core\Ui\ManifestBuilder;
use Silex\Provider\ServiceControllerServiceProvider;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

class Application extends \Silex\Application
{
    const APP_NAME = 'Mannequin';

    const APP_VERSION = '0.0.0';

    public function __construct(array $values = [])
    {
        parent::__construct($values);
        $this['console'] = function () {
            $app = new ConsoleApplication(self::APP_NAME, self::APP_VERSION);
            $app->setDispatcher($this['dispatcher']);
            $app->addCommands(
                [
                    new RenderCommand(
                        'render',
                        $this['manifest.builder'],
                        $this['config']
                    ),
                    new ServerCommand(
                        'server',
                        $this['config_file'],
                        $this['autoload_path'],
                        $this['debug']
                    ),
                ]
            );

            return $app;
        };
        $this['config'] = function () {
            $filename = $this['config_file'];
            if (!file_exists($filename)) {
                throw new \RuntimeException(
                    sprintf(
                        'Expected config in %s, but the file does not exist.',
                        $filename
                    )
                );
            }
            $config = require $filename;
            if (!$config instanceof ConfigInterface) {
                throw new \RuntimeException(
                    sprintf('Config was not returned or not an instance of Config in %s', $filename)
                );
            }

            return $config;
        };
        $this['manifest.builder'] = function () {
            $this->flush();

            return new ManifestBuilder($this['url_generator']);
        };

        $this->register(new ServiceControllerServiceProvider());
        $this['controller.ui'] = function () {
            return new UiController($this['config']->getUi());
        };
        $this['controller.manifest'] = function () {
            return new ManifestController(
                $this['manifest.builder'],
                $this['config']->getCollection()
            );
        };
        $this['controller.render'] = function () {
            $collection = $this['config']->getCollection();

            return new RenderController(
                $collection,
                $this['config']->getRenderer(),
                $this['config']->getUi()
            );
        };

        $this->get('/manifest.json', 'controller.manifest:getManifestAction')
            ->bind('manifest');
        $this->get(
            '/m-render/{pattern}/{set}.html',
            'controller.render:renderAction'
        )->bind('pattern_render');
        $this->get(
            '/m-source/raw/{pattern}.txt',
            'controller.render:renderSourceAction'
        )->bind('pattern_render_source_raw');
        $this->get(
            '/m-source/html/{pattern}/{set}.txt',
            'controller.render:renderRawAction'
        )->bind('pattern_render_raw');
        $this->match('/{name}', 'controller.ui:staticAction')
            ->value('name', 'index.html')
            ->assert('name', '.+');
    }

    public function boot()
    {
        // Guess file extensions for CSS and JS files.
        MimeTypeGuesser::getInstance()->register(
            new ExtensionMimeTypeGuesser()
        );

        return parent::boot();
    }

    public function getConsole(): ConsoleApplication
    {
        return $this['console'];
    }
}
