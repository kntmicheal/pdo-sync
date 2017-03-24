PDO-Sync
========

Synchronize between 2 databases with PDO.

## Example

The following example will copy all tables

```php

$source = [
    'host'=>'localhost',
    'username'=>'username',
    'password'=>'P4SSW0RD',
    'database'=>'localdb'
];

$target = [
    'host'=>'remote.example.com',
    'username'=>'remote_user',
    'password'=>'P4SSW0RD',
    'database'=>'remotedb'
];

$sourcePDO = new \PDO(sprintf('mysql:host=%s;dbname=%s;', $source['host'], $source['database']), $source['username'], $source['password']);
$targetPDO = new \PDO(sprintf('mysql:host=%s;dbname=%s;', $target['host'], $target['database']), $target['username'], $target['password']);
$logger = new \Psr\Log\NullLogger();

$sync = new \Mducharme\PDOSync\Synchronizer($sourcePDO, $targetPDO, $logger);

// Synchronize all tables
$results = $sync();

// Only synchronize 2 explicit tables
$results = $sync(['table1', 'table2']);
```

> Note that although this example demonstrates that the `Synchronizer` object is invokable,
> it can be also run with a method: `$results = $sync->run($tables);`.

## How it works

This script copies all table data from a database (`source`) to another database (`target`).

> Because it uses PDO for all operations, it can synchronize databases across 2 servers. As long as it can connect, it should be able to synchronize. 

> Note that synchronizations across 2 different drivers might work but is **unsupported**.

It first ensures that the table structure of the tables across both databases match, 
then it creates a backup of the target's table and then deletes all the data (`truncate`). 
It then fetch all the data from the source and inserts it in the target.
If any error occurs, it restores the data from the backup, otherwise it deletes the backup.

It returns an array containing the results as `skipped`, `errored` or `synced` (success).

 - If a target table does not exist, it will be created.
 - If a source table does not exist, it will be skipped.
 - If a target table's structure is not exactly like the source's, it will be skipped.

> This class **can** contain bugs that result in loss of data in certain scenarios.
> Test thoroughly before using in a live environment; **at your own risk, there is no warranty**.


## Authors

-   Mathieu Ducharme <mat@locomotive.ca>


## TODOs

-   Test / support other database drivers and synchronization across different drivers.
-   Unit tests using dbunit

# License

**The MIT License (MIT)**

_Copyright © 2017 Locomotive inc._
> See [Authors](#authors).

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.