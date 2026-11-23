<?php
declare(strict_types=1);
/**
 * 5S SMART — MySQL helpers
 * ------------------------------------------------------------------
 *  - five_s_connect()          : open the PDO MySQL connection (creates the DB if missing)
 *  - five_s_ensure_schema()    : run db/schema_mysql.sql once per schema version
 *  - db_table_exists() / db_table_columns() : replacements for sqlite_master / PRAGMA table_info
 *  - five_s_mysql_dump_to_file() / five_s_mysql_restore_file() : .sql backup + restore (pure PHP)
 *  - five_s_import_sqlite()    : copy every table from an old SQLite file into MySQL
 */

if (!defined('FIVE_S_SCHEMA_VERSION')) define('FIVE_S_SCHEMA_VERSION', 'mysql-1');
if (!defined('FIVE_S_SCHEMA_FILE')) define('FIVE_S_SCHEMA_FILE', dirname(__DIR__).'/db/schema_mysql.sql');

if (!function_exists('five_s_db_config')) {
    function five_s_db_config(): array {
        static $cfg = null;
        if ($cfg === null) $cfg = require __DIR__.'/db_config.php';
        return $cfg;
    }
}

if (!function_exists('five_s_connect')) {
    /** Open a PDO connection to MySQL/MariaDB with SQLite-compatible session settings. */
    function five_s_connect(?array $cfg = null, bool $createDatabase = true): PDO {
        $cfg = $cfg ?? five_s_db_config();
        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            // rowCount() of UPDATE = matched rows (same as SQLite), not only changed rows.
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            // * time_zone +00:00 : CURRENT_TIMESTAMP stays UTC exactly like SQLite did.
            // * sql_mode without STRICT / ONLY_FULL_GROUP_BY : tolerant like SQLite.
            // * group_concat_max_len : GROUP_CONCAT is unlimited in SQLite.
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone='+00:00', "
                ."sql_mode='NO_ENGINE_SUBSTITUTION', group_concat_max_len=1048576",
        ];
        $base = 'mysql:host='.$cfg['host'].';port='.(int)$cfg['port'].';charset=utf8mb4';
        try {
            return new PDO($base.';dbname='.$cfg['name'], (string)$cfg['user'], (string)$cfg['pass'], $opts);
        } catch (PDOException $e) {
            // 1049 = Unknown database -> create it once, then reconnect.
            if (!$createDatabase || (int)($e->errorInfo[1] ?? 0) !== 1049) throw $e;
            $tmp = new PDO($base, (string)$cfg['user'], (string)$cfg['pass'], $opts);
            $tmp->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', (string)$cfg['name']).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $tmp = null;
            return new PDO($base.';dbname='.$cfg['name'], (string)$cfg['user'], (string)$cfg['pass'], $opts);
        }
    }
}

if (!function_exists('five_s_sql_split')) {
    /** Split a SQL script into statements (respects quotes, backticks and comments). */
    function five_s_sql_split(string $sql): array {
        $out = []; $buf = ''; $len = strlen($sql); $q = null;
        for ($i = 0; $i < $len; $i++) {
            $c = $sql[$i];
            if ($q !== null) {
                $buf .= $c;
                if ($c === '\\' && $q !== '`' && $i + 1 < $len) { $buf .= $sql[++$i]; continue; }
                if ($c === $q) {
                    if ($i + 1 < $len && $sql[$i + 1] === $q) { $buf .= $sql[++$i]; continue; } // doubled quote
                    $q = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') { $q = $c; $buf .= $c; continue; }
            if ($c === '-' && substr($sql, $i, 3) === '-- ' || ($c === '#')) { // line comment
                $nl = strpos($sql, "\n", $i); $i = $nl === false ? $len : $nl; $buf .= "\n"; continue;
            }
            if ($c === '/' && ($sql[$i + 1] ?? '') === '*') { // block comment
                $end = strpos($sql, '*/', $i + 2); $i = $end === false ? $len : $end + 1; continue;
            }
            if ($c === ';') { if (trim($buf) !== '') $out[] = trim($buf); $buf = ''; continue; }
            $buf .= $c;
        }
        if (trim($buf) !== '') $out[] = trim($buf);
        return $out;
    }
}

if (!function_exists('five_s_ensure_schema')) {
    /** Create missing tables. Runs the schema file only when the stored version differs. */
    function five_s_ensure_schema(PDO $p, bool $force = false): void {
        if (!$force) {
            try {
                $v = $p->query("SELECT `value` FROM app_settings WHERE `key`='mysql_schema_version'")->fetchColumn();
                if ((string)$v === FIVE_S_SCHEMA_VERSION) return;
            } catch (Throwable $e) { /* table missing -> create */ }
        }
        foreach (five_s_sql_split((string)file_get_contents(FIVE_S_SCHEMA_FILE)) as $stmt) $p->exec($stmt);
        five_s_mark_schema_version($p);
    }
}

if (!function_exists('five_s_mark_schema_version')) {
    function five_s_mark_schema_version(PDO $p): void {
        $p->prepare("INSERT INTO app_settings(`key`,`value`) VALUES('mysql_schema_version',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")
          ->execute([FIVE_S_SCHEMA_VERSION]);
    }
}

if (!function_exists('db_table_exists')) {
    /** Replacement for: SELECT name FROM sqlite_master WHERE type='table' AND name=? */
    function db_table_exists(string $table, ?PDO $p = null): bool {
        $p = $p ?? db();
        $q = $p->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $q->execute([$table]);
        return (int)$q->fetchColumn() > 0;
    }
}

if (!function_exists('db_table_columns')) {
    /** Replacement for PRAGMA table_info(): list of column names (empty if the table is missing). */
    function db_table_columns(string $table, ?PDO $p = null): array {
        $p = $p ?? db();
        $q = $p->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $q->execute([$table]);
        return $q->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('db_list_tables')) {
    function db_list_tables(?PDO $p = null): array {
        $p = $p ?? db();
        return $p->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")
                 ->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('db_qi')) {
    /** Quote an identifier with backticks. */
    function db_qi(string $name): string { return '`'.str_replace('`', '``', $name).'`'; }
}

if (!function_exists('five_s_sql_value')) {
    function five_s_sql_value(PDO $p, $v): string {
        if ($v === null) return 'NULL';
        if (is_int($v)) return (string)$v;
        if (is_float($v)) return is_finite($v) ? var_export($v, true) : 'NULL';
        if (is_bool($v)) return $v ? '1' : '0';
        return $p->quote((string)$v);
    }
}

if (!function_exists('five_s_mysql_dump_to_file')) {
    /** Write a complete, restorable .sql dump of every table (structure + data). */
    function five_s_mysql_dump_to_file(PDO $p, string $file): bool {
        $fh = @fopen($file, 'wb');
        if (!$fh) return false;
        try {
            fwrite($fh, "-- 5S SMART MySQL backup\n-- created_at: ".date('c')."\n-- schema: ".FIVE_S_SCHEMA_VERSION."\n"
                ."SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET time_zone='+00:00';\nSET sql_mode='NO_ENGINE_SUBSTITUTION';\n\n");
            foreach (db_list_tables($p) as $t) {
                $create = $p->query('SHOW CREATE TABLE '.db_qi($t))->fetch(PDO::FETCH_NUM);
                fwrite($fh, "DROP TABLE IF EXISTS ".db_qi($t).";\n".$create[1].";\n");
                $rows = $p->query('SELECT * FROM '.db_qi($t), PDO::FETCH_ASSOC);
                $batch = []; $cols = null;
                foreach ($rows as $r) {
                    if ($cols === null) $cols = '('.implode(',', array_map('db_qi', array_keys($r))).')';
                    $batch[] = '('.implode(',', array_map(fn($v) => five_s_sql_value($p, $v), array_values($r))).')';
                    if (count($batch) >= 200) { fwrite($fh, 'INSERT INTO '.db_qi($t).' '.$cols." VALUES\n".implode(",\n", $batch).";\n"); $batch = []; }
                }
                if ($batch) fwrite($fh, 'INSERT INTO '.db_qi($t).' '.$cols." VALUES\n".implode(",\n", $batch).";\n");
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($fh);
            return filesize($file) > 0;
        } catch (Throwable $e) {
            @fclose($fh); @unlink($file);
            throw $e;
        }
    }
}

if (!function_exists('five_s_mysql_restore_file')) {
    /** Restore a .sql dump created by five_s_mysql_dump_to_file(). Returns number of statements run. */
    function five_s_mysql_restore_file(PDO $p, string $file): int {
        $sql = (string)file_get_contents($file);
        if (!preg_match('/CREATE TABLE\s+`?audits`?\s*\(/i', $sql)) throw new RuntimeException('ไฟล์ไม่ใช่ฐานข้อมูล 5S SMART (ไม่พบตาราง audits)');
        $n = 0;
        $p->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (five_s_sql_split($sql) as $stmt) { $p->exec($stmt); $n++; }
        } finally {
            $p->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        five_s_ensure_schema($p, true);
        return $n;
    }
}

if (!function_exists('five_s_import_sqlite')) {
    /**
     * Copy all data from a SQLite database file into the MySQL database.
     * Existing rows in the MySQL tables that also exist in the SQLite file are REPLACED.
     * IDs are preserved, AUTO_INCREMENT continues after the old SQLite sequence.
     * @return array{tables:array<string,array{sqlite:int,mysql:int}>,skipped:array<int,string>}
     */
    function five_s_import_sqlite(PDO $my, string $sqlitePath): array {
        if (!is_file($sqlitePath)) throw new RuntimeException('ไม่พบไฟล์ SQLite: '.$sqlitePath);
        if (!extension_loaded('pdo_sqlite')) throw new RuntimeException('ต้องเปิดส่วนขยาย pdo_sqlite เพื่ออ่านไฟล์ SQLite');
        $lite = new PDO('sqlite:'.$sqlitePath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $ok = (string)$lite->query('PRAGMA integrity_check')->fetchColumn();
        if ($ok !== 'ok') throw new RuntimeException('ไฟล์ SQLite เสียหาย: '.$ok);
        $liteTables = $lite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('audits', $liteTables, true)) throw new RuntimeException('ไฟล์ไม่ใช่ฐานข้อมูล 5S SMART (ไม่พบตาราง audits)');
        $seq = [];
        try { $seq = $lite->query('SELECT name,seq FROM sqlite_sequence')->fetchAll(PDO::FETCH_KEY_PAIR); } catch (Throwable $e) {}

        five_s_ensure_schema($my, true);
        $myTables = db_list_tables($my);
        $report = ['tables' => [], 'skipped' => []];
        $plan = [];
        foreach ($liteTables as $t) {
            if (!in_array($t, $myTables, true)) { $report['skipped'][] = $t; continue; }
            $liteCols = array_column($lite->query('PRAGMA table_info('.$lite->quote($t).')')->fetchAll(), 'name');
            $cols = array_values(array_intersect($liteCols, db_table_columns($t, $my)));
            if ($cols) $plan[$t] = $cols;
        }

        $my->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $my->beginTransaction();
            foreach ($plan as $t => $cols) {
                $my->exec('DELETE FROM '.db_qi($t));
                $colSql = implode(',', array_map('db_qi', $cols));
                $sel = $lite->query('SELECT '.implode(',', array_map(fn($c) => '"'.str_replace('"', '""', $c).'"', $cols)).' FROM "'.str_replace('"', '""', $t).'"');
                $batch = [];
                $flush = function () use (&$batch, $my, $t, $colSql, $cols) {
                    if (!$batch) return;
                    $one = '('.implode(',', array_fill(0, count($cols), '?')).')';
                    $st = $my->prepare('INSERT INTO '.db_qi($t).' ('.$colSql.') VALUES '.implode(',', array_fill(0, count($batch), $one)));
                    $st->execute(array_merge(...$batch));
                    $batch = [];
                };
                foreach ($sel as $r) {
                    $vals = [];
                    foreach ($cols as $c) $vals[] = $r[$c];
                    $batch[] = $vals;
                    if (count($batch) >= 200) $flush();
                }
                $flush();
            }
            five_s_mark_schema_version($my);
            $my->commit();
        } catch (Throwable $e) {
            if ($my->inTransaction()) $my->rollBack();
            throw $e;
        } finally {
            $my->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        foreach ($plan as $t => $cols) {
            $liteCount = (int)$lite->query('SELECT COUNT(*) FROM "'.str_replace('"', '""', $t).'"')->fetchColumn();
            $myCount = (int)$my->query('SELECT COUNT(*) FROM '.db_qi($t))->fetchColumn();
            $report['tables'][$t] = ['sqlite' => $liteCount, 'mysql' => $myCount];
            if (in_array('id', $cols, true)) {
                $next = max((int)($seq[$t] ?? 0), (int)$my->query('SELECT COALESCE(MAX(id),0) FROM '.db_qi($t))->fetchColumn()) + 1;
                $my->exec('ALTER TABLE '.db_qi($t).' AUTO_INCREMENT='.$next);
            }
        }
        return $report;
    }
}
