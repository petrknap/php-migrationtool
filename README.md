# Migration tool for PHP

* [What is Migration?](#what-is-migration)
* [Usage of php-migrationtool](#usage-of-php-migrationtool)
    * [Migration files](#migration-files)
    * [Migration tools](#migration-tools)
        * [SQL migration tool](#sql-migration-tool)
* [How to install](#how-to-install)


## What is Migration?

> **Data migration** is the process of transferring data between storage types, formats, or computer systems. It is a key consideration for any system implementation, upgrade, or consolidation. Data migration is usually **performed programmatically to achieve an automated migration**, freeing up human resources from tedious tasks. Data migration occurs for a variety of reasons, including server or storage equipment replacements, maintenance or upgrades, application migration, website consolidation and data center relocation.
>
> -- [Data migration - Wikipedia, The Free Encyclopedia]


## Usage of php-migrationtool

### Migration files

Migration file is file placed in special directory like `/migrations`. Migration file name contains 3 parts: *migration id*, *description* separated by space (optional) and *extension* separated by dot (optional) - the valid names for migration files are `{id}`, `{id}.{extension}`, `{id} {description}` and `{id} {description}.{extension}`.

```
user@localhost:~/project/migrations$ ls
M0001  M0002.ext  M0003 - Third migration  M0004 - Fourth migration.ext
```

Migration tools process **all files located in directory in ascending order** (sorted by file names). If applying of any migration file throws exception, the changes invoked by this file will be canceled and migration tool will be stopped.

Migration tools also contain lists of applied migrations and guarantee that every file will be processed only once and only in case that there is not applied migration with higher id.

### Migration tools

All migration tools implement `MigrationToolInterface` with method `migrate()`.

```php
<?php

/**
 * @var $tool PetrKnap\Php\MigrationTool\MigrationToolInterface
 */
$tool->migrate();
```

#### SQL migration tool

**WARNING:** The SQL migration tool processes only files with extension `sql`.

```php
<?php

$tool = new PetrKnap\Php\MigrationTool\SqlMigrationTool(
    __DIR__ . '/migrations',
    new PDO('mysql:host=127.0.0.1;dbname=project', $_ENV['DB_USER'], $_ENV['DB_PASS'])
);
```

SQL migration tool **supports native SQL files** as migration file. You can simply copy and paste output from [orm:schema-tool:update --dump-sql], [phpMyAdmin], [Adminer] or whatever with SQL output.


## How to install

Run `composer require petrknap/php-migrationtool` or merge this JSON code with your project `composer.json` file manually and run `composer install`. Instead of `dev-master` you can use [one of released versions].

```json
{
    "require": {
        "petrknap/php-migrationtool": "dev-master"
    }
}
```

Or manually clone this repository via `git clone https://github.com/petrknap/php-migrationtool.git` or download [this repository as ZIP] and extract files into your project.



[one of released versions]:https://github.com/petrknap/php-migrationtool/releases
[this repository as ZIP]:https://github.com/petrknap/php-migrationtool/archive/master.zip




[Data migration - Wikipedia, The Free Encyclopedia]:https://en.wikipedia.org/w/index.php?title=Data_migration&oldid=716195543
[orm:schema-tool:update --dump-sql]:http://doctrine-orm.readthedocs.io/projects/doctrine-orm/en/latest/reference/tools.html#database-schema-generation
[phpMyAdmin]:https://www.phpmyadmin.net/
[Adminer]:https://www.adminer.org/
[one of released versions]:https://github.com/petrknap/php-migrationtool/releases
[this repository as ZIP]:https://github.com/petrknap/php-migrationtool/archive/master.zip
