<?php
/**
 * 5S SMART — MySQL / MariaDB connection settings
 * ------------------------------------------------------------------
 * แก้ค่าด้านล่างให้ตรงกับเซิร์ฟเวอร์ MySQL ของคุณ
 * (หรือกำหนดผ่าน Environment Variable FIVE_S_DB_HOST / FIVE_S_DB_PORT /
 *  FIVE_S_DB_NAME / FIVE_S_DB_USER / FIVE_S_DB_PASS ซึ่งจะมีผลก่อนค่าในไฟล์นี้)
 *
 * ถ้ายังไม่มีฐานข้อมูล ระบบจะพยายามสร้างให้เอง (ต้องใช้ user ที่มีสิทธิ์ CREATE DATABASE)
 * ไฟล์นี้อยู่ใน includes/ ซึ่งถูกห้ามเปิดจากเว็บด้วย .htaccess แล้ว
 */
$fiveSEnv = static function (string $name, string $default): string {
    $v = getenv($name);
    return ($v === false || $v === '') ? $default : (string)$v;
};
return [
    'host'    => $fiveSEnv('FIVE_S_DB_HOST', '127.0.0.1'),
    'port'    => (int)$fiveSEnv('FIVE_S_DB_PORT', '3306'),
    'name'    => $fiveSEnv('FIVE_S_DB_NAME', 'five_s_smart'),
    'user'    => $fiveSEnv('FIVE_S_DB_USER', 'root'),
    // Password: env var wins; an empty env var cannot express "no password", so use the file value then.
    'pass'    => getenv('FIVE_S_DB_PASS') !== false ? (string)getenv('FIVE_S_DB_PASS') : '',
    'charset' => 'utf8mb4',
];
