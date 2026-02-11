<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Doctrine\Bundle\MongoDBMakerBundle\Maker\MakeDocument;
use Doctrine\Bundle\MongoDBMakerBundle\Maker\MakeIndex;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\DocumentClassGenerator;
use Doctrine\Bundle\MongoDBMakerBundle\MongoDB\MongoDBHelper;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('doctrine_mongodb_maker.mongodb_helper', MongoDBHelper::class)
        ->args([
            service('doctrine_mongodb'),
        ]);

    $services->set('doctrine_mongodb_maker.document_class_generator', DocumentClassGenerator::class)
        ->args([
            service('maker.generator'),
            service('doctrine_mongodb_maker.mongodb_helper'),
        ]);

    $services->set('doctrine_mongodb_maker.maker.make_document', MakeDocument::class)
        ->args([
            service('maker.file_manager'),
            service('doctrine_mongodb_maker.mongodb_helper'),
            service('maker.generator'),
            service('doctrine_mongodb_maker.document_class_generator'),
        ])
        ->tag('maker.command');

    $services->set('doctrine_mongodb_maker.maker.make_index', MakeIndex::class)
        ->args([
            service('maker.file_manager'),
            service('doctrine_mongodb_maker.mongodb_helper'),
        ])
        ->tag('maker.command');
};
