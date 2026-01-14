<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Tests;

use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDBMakerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MakerBundle\DependencyInjection\CompilerPass\MakeCommandRegistrationPass;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class TestKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineMongoDBBundle(),
            new MakerBundle(),
            new MongoDBMakerBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', ['secret' => 'S0ME_SECRET']);
            $container->loadFromExtension('doctrine_mongodb', [
                'default_connection' => 'default',
                'connections' => [
                    'default' => ['server' => 'mongodb://localhost:27017'],
                ],
                'document_managers' => [
                    'default' => ['auto_mapping' => true],
                ],
            ]);
            $container->loadFromExtension('maker', []);
            $container->loadFromExtension('mongodb_maker', ['generate_final_documents' => true]);
        });
    }

    public function process(ContainerBuilder $container): void
    {
        /**
         * Makes all makers public to help the tests
         *
         * @see \Symfony\Bundle\MakerBundle\Test\MakerTestKernel::process()
         */
        foreach ($container->findTaggedServiceIds(MakeCommandRegistrationPass::MAKER_TAG) as $id => $tags) {
            $defn = $container->getDefinition($id);
            $defn->setPublic(true);
        }
    }
}
