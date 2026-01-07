<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * @phpstan-type ConfigArray array{
 *     generate_final_documents: bool,
 * }
 */
class MongoDBMakerBundle extends AbstractBundle
{
    protected string $extensionAlias = 'mongodb_maker';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->booleanNode('generate_final_documents')->defaultFalse()->end()
            ->end();
    }

    /** @param ConfigArray $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/makers.php');

        $container->parameters()
            ->set('doctrine_mongodb_maker.generate_final_documents', $config['generate_final_documents']);
    }
}
