#!/usr/bin/env php
<?php

declare(strict_types=1);

use TaskFlow\Core\Database;
use TaskFlow\Core\Schema;

require dirname(__DIR__) . '/bootstrap.php';

$pdo = Database::connect();
Schema::migrate($pdo, dirname(__DIR__) . '/database/schema.sql');

fwrite(STDOUT, "Schema applied successfully.\n");
