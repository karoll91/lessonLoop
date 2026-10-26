<?php

/*
 * Plain-PHP SQL dump of one MySQL database (tables + rows), for when
 * mysqldump is not available.
 *
 *   php deploy/dump-database.php <host> <port> <user> <password> <database> <output.sql>
 *
 * Restore:  mysql -u root <database> < output.sql
 */

[$_, $host, $port, $user, $password, $database, $output] = $argv + array_fill(0, 7, null);
if ($output === null) {
    fwrite(STDERR, "usage: php dump-database.php host port user password database output.sql\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
]);
$pdo->exec("SET time_zone = '+00:00'");

$out = fopen($output, 'wb');
fwrite($out, "-- LessonLoop dump of `{$database}`, ".gmdate('Y-m-d H:i:s')." UTC\n");
fwrite($out, "SET NAMES utf8mb4;\nSET time_zone = '+00:00';\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n\n");

$tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
$counts = [];

foreach ($tables as $table) {
    $create = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM)[1];
    fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n{$create};\n\n");

    $rows = $pdo->query("SELECT * FROM `{$table}`", PDO::FETCH_ASSOC);
    $batch = [];
    $columns = null;
    $counts[$table] = 0;
    foreach ($rows as $row) {
        $columns ??= '`'.implode('`, `', array_keys($row)).'`';
        $batch[] = '('.implode(', ', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), $row)).')';
        $counts[$table]++;
        if (count($batch) === 200) {
            fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
            $batch = [];
        }
    }
    if ($batch !== []) {
        fwrite($out, "INSERT INTO `{$table}` ({$columns}) VALUES\n".implode(",\n", $batch).";\n");
    }
    fwrite($out, "\n");
}

fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
fclose($out);

foreach ($counts as $table => $count) {
    echo str_pad($table, 32), $count, "\n";
}
echo count($tables), " tables -> {$output}\n";
