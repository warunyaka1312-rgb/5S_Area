<?php
/**
 * V80 — Login & roles
 *   admin   : ทุกหน้า (admin/ + auditor/ + public/) และจัดการผู้ใช้
 *   auditor : บันทึกคะแนน / TAG / นำเข้า (auditor/) + ดูรายงาน
 *   viewer  : ดูรายงานอย่างเดียว (public/ เปิดดูได้โดยไม่ต้องล็อกอิน)
 * public/ และหน้าแรก: GET ดูได้ทุกคน, การบันทึก (POST) ต้องล็อกอินเป็น auditor ขึ้นไป
 */
if (!function_exists('five_s_session')) {
    function five_s_session(): void {
        if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') return;
        $name = five_s_env_str('SESSION_NAME', 'FIVES_SMART');
        session_name(preg_match('/^[A-Za-z0-9_]+$/', $name) && !ctype_digit($name) ? $name : 'FIVES_SMART');
        session_set_cookie_params(['lifetime' => 0, 'path' => FIVE_S_BASE, 'httponly' => true, 'samesite' => 'Lax']);
        @session_start();
    }
    function five_s_roles(): array { return ['admin' => 'ผู้ดูแลระบบ', 'auditor' => 'ผู้ตรวจ (Auditor)', 'viewer' => 'ดูอย่างเดียว']; }
    function five_s_rank(string $role): int { return ['viewer' => 1, 'auditor' => 2, 'admin' => 3][$role] ?? 0; }
    function five_s_users_init(PDO $p): void {
        /* Table `users` (incl. department) is created by db/schema_mysql.sql via db(). */
        if ((int)$p->query("SELECT COUNT(*) FROM users")->fetchColumn() === 0) {
            $st = $p->prepare("INSERT INTO users(username,password_hash,display_name,role,must_change) VALUES(?,?,?,?,1)");
            $st->execute(['admin', password_hash(five_s_admin_default_password(), PASSWORD_DEFAULT), 'สมเจต สุขประเสริฐ', 'admin']);
        }
    }
    function five_s_user(): ?array {
        static $u = false;
        if ($u !== false) return $u;
        five_s_session();
        $u = null;
        $id = (int)($_SESSION['five_s_uid'] ?? 0);
        if ($id > 0) {
            try {
                $p = db(); five_s_users_init($p);
                $q = $p->prepare("SELECT id,username,display_name,role,must_change FROM users WHERE id=? AND active=1");
                $q->execute([$id]); $u = $q->fetch() ?: null;
            } catch (Throwable $e) { $u = null; }
            if (!$u) unset($_SESSION['five_s_uid']);
        }
        return $u;
    }
    function five_s_role(): string { $u = five_s_user(); return $u['role'] ?? 'guest'; }
    function five_s_can(string $min): bool { return five_s_rank(five_s_role()) >= five_s_rank($min); }
    /** Minimum role needed to open a URL (used by the menu). */
    function five_s_need_for(string $href): string {
        $h = ltrim($href, '/');
        if (stripos($h, 'Page/') === 0) $h = substr($h, 5);
        if (strpos($h, 'admin/') === 0) return 'admin';
        if (strpos($h, 'auditor/') === 0) return 'auditor';
        return 'guest';
    }
    function five_s_wants_json(): bool {
        $acc = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (($_POST['ajax'] ?? '') === '1')
            || stripos($acc, 'application/json') !== false
            || in_array(basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')), ['department_list.php','import_criteria.php','import_save.php','unit_import_save.php','history_date_save.php'], true);
    }
    function five_s_deny(string $need): void {
        $logged = five_s_user() !== null;
        if (five_s_wants_json() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $logged) {
            http_response_code($logged ? 403 : 401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $logged ? 'สิทธิ์ของบัญชีนี้ทำรายการนี้ไม่ได้' : 'กรุณาเข้าสู่ระบบก่อนบันทึกข้อมูล', 'login' => FIVE_S_BASE . 'Page/public/login.php'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!$logged) {
            $next = (string)($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . FIVE_S_BASE . 'Page/public/login.php?next=' . rawurlencode($next));
            exit;
        }
        http_response_code(403);
        $roles = five_s_roles(); $u = five_s_user();
        echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>ไม่มีสิทธิ์เข้าถึง</title>'
           . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#F1F5F9;font-family:"IBM Plex Sans Thai",Tahoma,sans-serif;color:#0F172A}.c{background:#fff;border-radius:20px;padding:36px 40px;max-width:440px;text-align:center;box-shadow:0 20px 50px rgba(15,23,42,.12)}h1{margin:12px 0 6px;font-size:24px}p{color:#64748B}a{display:inline-block;margin:8px 4px 0;padding:10px 18px;border-radius:11px;text-decoration:none;font-weight:700}.o{background:#F26A21;color:#fff}.g{border:1px solid #CBD5E1;color:#0E1A2F}</style></head><body><div class="c">'
           . '<img src="' . htmlspecialchars(FIVE_S_BASE) . 'assets/alumet-logo.png" style="height:34px" alt="ALUMET"><h1>ไม่มีสิทธิ์เข้าถึงหน้านี้</h1>'
           . '<p>บัญชี <b>' . htmlspecialchars((string)$u['display_name']) . '</b> (' . htmlspecialchars($roles[$u['role']] ?? $u['role']) . ') ใช้หน้านี้ไม่ได้ ต้องเป็น ' . htmlspecialchars($roles[$need] ?? $need) . '</p>'
           . '<a class="o" href="' . htmlspecialchars(FIVE_S_BASE) . 'index.php">กลับหน้าหลัก</a><a class="g" href="' . htmlspecialchars(FIVE_S_BASE) . 'Page/public/logout.php">เปลี่ยนผู้ใช้</a></div></body></html>';
        exit;
    }
    /** Called from includes/config.php on every request. */
    function five_s_guard(): void {
        if (PHP_SAPI === 'cli' || defined('FIVE_S_NO_GUARD')) return;
        $need = 'guest';
        if (FIVE_S_AREA === 'admin') $need = 'admin';
        elseif (FIVE_S_AREA === 'auditor') $need = 'auditor';
        elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') $need = 'auditor';
        if ($need !== 'guest' && !five_s_can($need)) five_s_deny($need);
        /* Accounts flagged "must change password" may only use account/login/logout (those pages set FIVE_S_NO_GUARD). */
        $u = five_s_user();
        if ($u && (int)($u['must_change'] ?? 0) === 1) five_s_force_password_change();
    }
    function five_s_force_password_change(): void {
        if (five_s_wants_json() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => 'กรุณาเปลี่ยนรหัสผ่านก่อนใช้งานระบบ', 'login' => FIVE_S_BASE . 'Page/public/account.php?first=1'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: ' . FIVE_S_BASE . 'Page/public/account.php?first=1&next=' . rawurlencode((string)($_SERVER['REQUEST_URI'] ?? '')));
        exit;
    }
    /** True when the supplied password is the well-known first-install password. */
    /** Initial admin password (.env ADMIN_DEFAULT_PASSWORD). */
    function five_s_admin_default_password(): string { return five_s_env_str('ADMIN_DEFAULT_PASSWORD', 'admin1234'); }
    function five_s_is_default_password(string $password): bool { return hash_equals(five_s_admin_default_password(), $password); }
}
