<?php
/**
 * 5S SMART — MySQL / MariaDB connection settings
 * ------------------------------------------------------------------
 * ค่าการเชื่อมต่ออ่านจากไฟล์ .env ที่ root ของแอพ (DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS / DB_CHARSET)
 * ให้แก้ที่ .env แทนการแก้ไฟล์นี้ — ดูตัวอย่างที่ .env.example
 * (Environment Variable รุ่นเก่า FIVE_S_DB_HOST / ... / FIVE_S_DB_PASS ยังใช้ได้ และมีผลก่อน .env)
 *
 * ถ้ายังไม่มีฐานข้อมูล ระบบจะพยายามสร้างให้เอง (ต้องใช้ user ที่มีสิทธิ์ CREATE DATABASE)
 * ไฟล์นี้อยู่ใน includes/ ซึ่งถูกห้ามเปิดจากเว็บด้วย .htaccess แล้ว
 */
require_once __DIR__.'/env.php';

$fiveSDb = static function (string $key, string $default): string {
    $legacy = getenv('FIVE_S_'.$key);
    if ($legacy !== false && $legacy !== '') return (string)$legacy;
    return five_s_env_str($key, $default);
};
// Password: an empty value is valid ("no password"), so it must not fall back to a default.
$fiveSDbPass = getenv('FIVE_S_DB_PASS');
return [
    'host'    => $fiveSDb('DB_HOST', '127.0.0.1'),
    'port'    => (int)$fiveSDb('DB_PORT', '3306'),
    'name'    => $fiveSDb('DB_NAME', 'five_s_smart'),
    'user'    => $fiveSDb('DB_USER', 'root'),
    'pass'    => $fiveSDbPass !== false ? (string)$fiveSDbPass : (string)five_s_env('DB_PASS', ''),
    'charset' => $fiveSDb('DB_CHARSET', 'utf8mb4'),
];
