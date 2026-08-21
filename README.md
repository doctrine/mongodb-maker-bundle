# Doctrine MongoDB MakerBundle

A Symfony MakerBundle extension to generate MongoDB ODM code.

## Description

The MongoDB MakerBundle extends Symfony's MakerBundle to provide code generators tailored for MongoDB Object Document Mapper (ODM). It streamlines the process of creating MongoDB documents, repositories, and fixtures within your Symfony application.

This bundle integrates seamlessly with:
- Symfony 7.4+
- Doctrine MongoDB ODM 5.5+
- MongoDB PHP Driver 2.1+

## Installation

Install the bundle via Composer:

```bash
composer require doctrine/mongodb-maker-bundle
```

Then, register the bundle in your `config/bundles.php`:

```php
return [
    // ...
    Doctrine\Bundle\MongoDBMakerBundle\MongoDBMakerBundle::class => ['all' => true],
];
```

## Usage

Once installed, the bundle provides Maker commands to generate MongoDB-specific code.

### Available Commands

Run `php bin/console list make` to see all available makers:

```bash
php bin/console list make
```

The MongoDB MakerBundle provides the following commands:
- `make:document`: create or update a MongoDB ODM document class
- `make:document:index`: add an index (regular, unique, or search) to a document

### Examples

#### Create a MongoDB Document

Generate a new MongoDB document with properties:

```bash
php bin/console make:document
```

Follow the interactive prompts. Example output:

```
Class name of the document to create or update (e.g. GoodUser):
> Product

New property name (press <return> to stop adding fields):
> name
 Field type (enter ? to see all types) [string]:
 > string
 Can this field be null in the database (nullable) (yes/no) [no]:
 > no

Add another property? Enter the property name (or press <return> to stop adding fields):
> price
 Field type (enter ? to see all types) [string]:
 > float
 Can this field be null in the database (nullable) (yes/no) [no]:
 > no

Add another property? Enter the property name (or press <return> to stop adding fields):
 >
```

This generates a `Product` document class in `src/Document/Product.php`:

```php
<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

#[ODM\Document(collection: 'product', repositoryClass: ProductRepository::class)]
class Product
{
    #[ODM\Id]
    public ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'float')]
    private float $price;

    // Getters and setters...
}
```

You can also add relations (`ReferenceOne`, `ReferenceMany`, `EmbedOne`, `EmbedMany`) between documents by entering `relation` as the field type and following the prompts.

Running `make:document` again on an existing document lets you add more fields to it.

#### Add an Index to a Document

Add a regular, unique, or search index to an existing document:

```bash
php bin/console make:document:index
```

Follow the interactive prompts to select the document, the index type, and the fields to index. After adding an index, remember to run:

```bash
php bin/console doctrine:mongodb:schema:update
```

to create the index in the database.

## License

The MongoDB MakerBundle is licensed under the MIT License. See the LICENSE file for details.

## Support

For issues, feature requests, or questions, please open an issue on the GitHub repository or reach out to the Doctrine community.
