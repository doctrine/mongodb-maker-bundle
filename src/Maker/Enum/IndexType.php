<?php

declare(strict_types=1);

namespace Doctrine\Bundle\MongoDBMakerBundle\Maker\Enum;

use function array_map;

enum IndexType: string
{
    case Regular = 'Regular';
    case Unique = 'Unique';
    case Search = 'Search';

    /** @return string[] */
    public static function stringValues(): array
    {
        return array_map(static fn ($case) => $case->value, self::cases());
    }
}
