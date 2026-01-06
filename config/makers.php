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

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('doctrine_mongodb_maker.maker.make_document', MakeDocument::class)
        ->args([])
        ->tag('maker.command');
};
