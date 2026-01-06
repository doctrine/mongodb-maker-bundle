<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

final class MakeDocument extends AbstractMaker implements InputAwareMakerInterface
{
    public static function getCommandName(): string
    {
        return 'doctrine:mongodb:make:document';
    }

    public static function getCommandDescription(): string
    {
        return 'Creates a new MongoDB ODM document class';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        // TODO: Implement configureCommand() method.
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface|null $input = null): void
    {
        // TODO: Implement configureDependencies() method.
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        // TODO: Implement generate() method.
    }
}
