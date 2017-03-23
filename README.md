pdo-sync
==========

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

$sync = new \Mducharme\PDOSync\Synchronizer([
    'logger' => new \Psr\Log\NullLogger(),
    'source_database' => new \PDO(sprintf'mysql:host=%s;dbname=%s;', $source['host'], $source['database']), $source['username'], $source['password']),
    'target_database' => new \PDO(sprintf('mysql:host=%s;dbname=%s;', $target['host'], $target['database']), $target['username'], $target['password'])
]);

// Synchronize all tables
$results = $sync();

// Only synchronize 2 explicit tables
$results = $sync(['table1', 'table2']);
```

## Authors

-   Mathieu Ducharme <mat@locomotive.ca>


## TODOs

-   Test / support other database drivers and synchronization across different drivers.

# License

**The MIT License (MIT)**

_Copyright © 2017 Locomotive inc._
> See [Authors](#authors).

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.