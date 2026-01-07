<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Tests;

use Doctrine\Bundle\MongoDBBundle\DoctrineMongoDBBundle;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDBMakerBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;

class BundleTest extends TestCase
{
    public function testBundleRegistration(): void
    {
        $kernel = new class ('test', true) extends Kernel {
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
        };

        $kernel->boot();

        $commandLoader = $kernel->getContainer()->get('console.command_loader');
        self::assertInstanceOf(CommandLoaderInterface::class, $commandLoader);
        self::assertTrue($commandLoader->has('doctrine:mongodb:make:document'));

        $kernel->shutdown();
    }
}
