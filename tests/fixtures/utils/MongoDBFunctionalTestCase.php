<?php

declare(strict_types=1);

namespace App;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\ServerException;

/**
 * This trait can be used inside generated tests.
 * Copy it over to the test file system and use it in the generated test class.
 */
trait MongoDBFunctionalTestCase
{
    public static function skipIfSearchIndexesNotSupported(DocumentManager $dm): void
    {
        try {
            $db = $dm->getClient()->selectDatabase($dm->getConfiguration()->getDefaultDB());
            $db->dropCollection(__METHOD__);
            $db->createCollection(__METHOD__);
            $db->getCollection(__METHOD__)->dropSearchIndex('nonexistent-index');
        } catch (ServerException $exception) {
            // Code 27 = Search index does not exist, which indicates that the feature is supported
            if ($exception->getCode() === 27) {
                return;
            }

            self::markTestSkipped($exception->getMessage());
        }
    }
}
