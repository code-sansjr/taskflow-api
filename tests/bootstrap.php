<?php

declare(strict_types=1);

use TaskFlow\Core\Database;
use TaskFlow\Core\Schema;

$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_DEBUG'] = 'true';
$_ENV['DB_DATABASE'] = 'taskflow_test';
$_ENV['DB_USERNAME'] = 'taskflow';
$_ENV['DB_PASSWORD'] = 'taskflow';
$_ENV['JWT_SECRET'] = 'testing-secret-key-with-at-least-32-chars';
$_ENV['JWT_TTL'] = '3600';
$_ENV['PASSWORD_HASH_COST'] = '4';

$_SERVER['APP_ENV'] = 'testing';
$_SERVER['APP_DEBUG'] = 'true';
$_SERVER['DB_DATABASE'] = 'taskflow_test';
$_SERVER['DB_USERNAME'] = 'taskflow';
$_SERVER['DB_PASSWORD'] = 'taskflow';
$_SERVER['JWT_SECRET'] = 'testing-secret-key-with-at-least-32-chars';
$_SERVER['JWT_TTL'] = '3600';
$_SERVER['PASSWORD_HASH_COST'] = '4';

putenv('APP_ENV=testing');
putenv('APP_DEBUG=true');
putenv('DB_DATABASE=taskflow_test');
putenv('DB_USERNAME=taskflow');
putenv('DB_PASSWORD=taskflow');
putenv('JWT_SECRET=testing-secret-key-with-at-least-32-chars');
putenv('JWT_TTL=3600');
putenv('PASSWORD_HASH_COST=4');

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::connect();
Schema::migrate($pdo, dirname(__DIR__) . '/database/schema.sql');

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach (['tasks', 'projects', 'users'] as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
