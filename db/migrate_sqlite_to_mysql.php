<?php
/**
 * 5S SMART — ย้ายข้อมูลจาก SQLite (db/database.sqlite) ไป MySQL / MariaDB
 * ------------------------------------------------------------------
 * วิธีใช้ (Command Prompt):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\5S_area_auto\db\migrate_sqlite_to_mysql.php
 *   C:\xampp\php\php.exe ...\migrate_sqlite_to_mysql.php "D:\path\other.sqlite"   (ระบุไฟล์อื่น)
 *
 * - ตั้งค่าการเชื่อมต่อที่ไฟล์ .env (DB_HOST / DB_NAME / DB_USER / DB_PASS ...) ก่อน
 * - สร้างฐานข้อมูล + ตารางให้อัตโนมัติ (db/schema_mysql.sql)
 * - ตารางที่มีอยู่ใน MySQL จะถูกล้างแล้วเขียนข้อมูลจาก SQLite ทับ (id เดิมคงไว้ทั้งหมด)
 * - ไม่แก้ไขไฟล์ SQLite ต้นฉบับ
 * - รันได้จาก Command line เท่านั้น (โฟลเดอร์ db/ ถูกห้ามเปิดจากเว็บ)
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once dirname(__DIR__).'/includes/db_tools.php';
require_once dirname(__DIR__).'/includes/env.php';
if (!@date_default_timezone_set(five_s_env_str('APP_TIMEZONE', 'Asia/Bangkok'))) date_default_timezone_set('Asia/Bangkok');

$source = $argv[1] ?? dirname(__DIR__).'/db/database.sqlite';
$cfg = five_s_db_config();

echo "SQLite  : $source\n";
echo "MySQL   : {$cfg['user']}@{$cfg['host']}:{$cfg['port']} / {$cfg['name']}\n\n";

try {
    $my = five_s_connect($cfg, true);
    echo 'Server  : '.$my->query('SELECT VERSION()')->fetchColumn()."\n\n";
    $t0 = microtime(true);
    $report = five_s_import_sqlite($my, $source);
    $allOk = true;
    printf("%-34s %10s %10s\n", 'table', 'sqlite', 'mysql');
    echo str_repeat('-', 56)."\n";
    foreach ($report['tables'] as $t => $c) {
        $ok = $c['sqlite'] === $c['mysql'];
        $allOk = $allOk && $ok;
        printf("%-34s %10d %10d  %s\n", $t, $c['sqlite'], $c['mysql'], $ok ? 'OK' : 'MISMATCH');
    }
    if ($report['skipped']) echo "\nSkipped (not in MySQL schema): ".implode(', ', $report['skipped'])."\n";

    // Foreign-key sanity check (SQLite may have allowed orphans if foreign_keys was ever OFF).
    $orphans = [
        'audits.area_id'            => 'SELECT COUNT(*) FROM audits x LEFT JOIN areas a ON a.id=x.area_id WHERE a.id IS NULL',
        'scores.audit_id'           => 'SELECT COUNT(*) FROM scores x LEFT JOIN audits a ON a.id=x.audit_id WHERE a.id IS NULL',
        'scores.criterion_id'       => 'SELECT COUNT(*) FROM scores x LEFT JOIN criteria c ON c.id=x.criterion_id WHERE c.id IS NULL',
        'office_scores.audit_id'    => 'SELECT COUNT(*) FROM office_scores x LEFT JOIN audits a ON a.id=x.audit_id WHERE a.id IS NULL',
        'office_scores.criterion_id'=> 'SELECT COUNT(*) FROM office_scores x LEFT JOIN office_criteria c ON c.id=x.criterion_id WHERE c.id IS NULL',
        'legacy_scores.area_id'     => 'SELECT COUNT(*) FROM legacy_scores x LEFT JOIN areas a ON a.id=x.area_id WHERE a.id IS NULL',
    ];
    $lite = new PDO('sqlite:'.$source);
    foreach ($orphans as $label => $sql) {
        $n = (int)$my->query($sql)->fetchColumn();
        if ($n === 0) continue;
        $before = (int)$lite->query($sql)->fetchColumn();
        echo $n === $before
            ? "NOTE: $n row(s) in $label point to a deleted parent — already like this in SQLite, copied as-is\n"
            : "WARNING: $n orphan row(s) in $label (SQLite had $before)\n";
    }
    printf("\n%s in %.1fs\n", $allOk ? 'MIGRATION COMPLETE' : 'MIGRATION FINISHED WITH MISMATCHES', microtime(true) - $t0);
    exit($allOk ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: '.$e->getMessage()."\n");
    exit(1);
}
