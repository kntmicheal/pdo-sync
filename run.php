<?php
/**
 * Created by PhpStorm.
 * User: Mathieu
 * Date: 2017-03-23
 * Time: 9:03 AM
 */

use Mducharme\PDOSync\Synchronizer;

include 'vendor/autoload.php';

$sync = new Synchronizer([
    'logger' => new \Psr\Log\NullLogger(),
    'source_database' => new PDO('mysql:host=localhost;dbname=memo_pincourt;', 'root', ''),
    'target_database' => new PDO('mysql:host=localhost;dbname=test;', 'root', '')
]);
$sync();