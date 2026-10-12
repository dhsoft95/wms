<?php

/**
 * Concurrency tests live in tests/Integration/Inventory/StockConcurrencyTest.php.
 *
 * They require DatabaseMigrations (committed data, visible to raw PDO connections),
 * which conflicts with the global RefreshDatabase applied to all Feature tests.
 *
 * Run them with:
 *   php artisan test --configuration=phpunit.mysql.xml --group=mysql
 */
