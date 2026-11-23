<?php
/**
 * 5S SMART — .env loader
 * ------------------------------------------------------------------
 * อ่านค่าตั้งค่าจากไฟล์ .env ที่ root ของแอพ (รูปแบบ KEY=VALUE ทีละบรรทัด)
 * ลำดับความสำคัญ: Environment Variable จริงของเซิร์ฟเวอร์ > ค่าในไฟล์ .env > ค่า default ในโค้ด
 *
 *   five_s_env('DB_HOST', '127.0.0.1')      -> string
 *   five_s_env_int('AUTO_BACKUP_KEEP', 30)  -> int
 *   five_s_env_bool('APP_DEBUG', false)     -> bool
 *
 * รองรับ: บรรทัดว่าง, คอมเมนต์ (#), "export KEY=...", ค่าในเครื่องหมาย '...' หรือ "..."
 * และคอมเมนต์ท้ายบรรทัด ( KEY=value # comment ) เมื่อค่าไม่ได้อยู่ในเครื่องหมายคำพูด
 */

if (!function_exists('five_s_env_file_values')) {
    /** Parsed key/value pairs from the .env file (read once per request). */
    function five_s_env_file_values(): array {
        static $vals = null;
        if ($vals !== null) return $vals;
        $vals = [];
        $file = dirname(__DIR__).'/.env';
        if (!is_file($file) || !is_readable($file)) return $vals;
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $i => $line) {
            if ($i === 0) $line = preg_replace('/^\xEF\xBB\xBF/', '', $line); // strip UTF-8 BOM
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strncmp($line, 'export ', 7) === 0) $line = ltrim(substr($line, 7));
            $eq = strpos($line, '=');
            if ($eq === false) continue;
            $key = trim(substr($line, 0, $eq));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) continue;
            $val = trim(substr($line, $eq + 1));
            $q = $val[0] ?? '';
            if (($q === '"' || $q === "'") && ($end = strpos($val, $q, 1)) !== false) {
                $val = substr($val, 1, $end - 1);
                if ($q === '"') $val = strtr($val, ['\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\']);
            } else {
                $val = rtrim((string)preg_replace('/\s+#.*$/', '', $val));
            }
            $vals[$key] = $val;
        }
        return $vals;
    }
}

if (!function_exists('five_s_env')) {
    /** Setting value: real environment variable > .env file > $default. */
    function five_s_env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v !== false) return (string)$v;
        $file = five_s_env_file_values();
        return array_key_exists($key, $file) ? $file[$key] : $default;
    }
    /** Like five_s_env() but an empty value also falls back to $default. */
    function five_s_env_str(string $key, string $default): string {
        $v = five_s_env($key);
        return ($v === null || trim($v) === '') ? $default : $v;
    }
    function five_s_env_int(string $key, int $default): int {
        $v = five_s_env($key);
        return ($v !== null && is_numeric(trim($v))) ? (int)$v : $default;
    }
    function five_s_env_bool(string $key, bool $default): bool {
        $v = five_s_env($key);
        if ($v === null || trim($v) === '') return $default;
        return in_array(strtolower(trim($v)), ['1', 'true', 'yes', 'on'], true);
    }
}
