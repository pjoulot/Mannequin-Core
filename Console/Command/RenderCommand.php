<?php

/*
 * This file is part of Mannequin.
 *
 * (c) 2017 Last Call Media, Rob Bayliss <rob@lastcallmedia.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace LastCall\Mannequin\Core\Console\Command;

use LastCall\Mannequin\Core\ConfigInterface;
use LastCall\Mannequin\Core\Ui\FileWriter;
use LastCall\Mannequin\Core\Ui\ManifestBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RenderCommand extends Command
{
    private $manifester;

    private $config;

    private $ui;

    public function __construct(
        $name = null,
        ManifestBuilder $manifester,
        ConfigInterface $config
    ) {
        parent::__construct($name);
        $this->manifester = $manifester;
        $this->config = $config;
    }

    public function configure()
    {
        $this->addOption(
            'output-dir',
            'o',
            InputOption::VALUE_OPTIONAL,
            'The directory to output the UI in',
            'mannequin'
        );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $outDir = $input->getOption('output-dir');

        $writer = new FileWriter($outDir);
        try {
            $collection = $this->config->getCollection();
            $engine = $this->config->getRenderer();
            $ui = $this->config->getUi();

            $manifest = $this->manifester->generate($collection);
            $writer->raw('manifest.json', json_encode($manifest));
            $rows[] = $this->getSuccessRow('Manifest');

            foreach ($manifest['patterns'] as $patternManifest) {
                try {
                    $pattern = $collection->get($patternManifest['id']);
                    $writer->raw(
                        $patternManifest['source'],
                        $engine->renderSource($pattern)
                    );

                    foreach ($patternManifest['sets'] as $setManifest) {
                        $set = $pattern->getVariableSets()[$setManifest['id']];
                        $rendered = $engine->render($pattern, $set);
                        $writer->raw(
                            $setManifest['source'],
                            $rendered->getMarkup()
                        );
                        $writer->raw(
                            $setManifest['rendered'],
                            $ui->decorateRendered($rendered)
                        );
                    }
                    $rows[] = $this->getSuccessRow($pattern->getName());
                } catch (\Exception $e) {
                    $rows[] = $this->getErrorRow($pattern->getName(), $e);
                }
            }
            try {
                foreach ($this->config->getAssetMappings() as $src => $dest) {
                    $writer->copy($src, $dest);
                }
                $rows[] = $this->getSuccessRow('Assets');
            } catch (\Exception $e) {
                $rows[] = $this->getErrorRow('Assets', $e);
            }
            try {
                foreach ($ui->files() as $dest => $src) {
                    $writer->copy($src, $dest);
                }
                $rows[] = $this->getSuccessRow('UI');
            } catch (\Exception $e) {
                $rows[] = $this->getErrorRow('UI', $e);
            }
        } catch (\Exception $e) {
            $rows[] = $this->getErrorRow('Manifest', $e);
        }

        $io->table(['', 'Name', 'Message'], $rows);
    }

    private function getSuccessRow($name)
    {
        return ['<info>✓</info>', $name, ''];
    }

    private function getErrorRow($name, \Exception $e)
    {
        return ['<error>x</error>', $name, $e->getMessage()];
    }
}
