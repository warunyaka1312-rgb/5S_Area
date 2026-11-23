<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2).'/includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function normDept(string $s): string {
    $s = trim($s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return mb_strtolower($s, 'UTF-8');
}
function blockedDept(string $s): bool {
    $k = normDept($s);
    return in_array($k, [
        'production','office','prod','สำนักงาน','โปรดักชัน',
        'production — แบบตรวจ 5 ส. production',
        'office — แบบตรวจ 5 ส. office'
    ], true);
}
function ensureDepartments(PDO $p): void {
    /* Table created by db/schema_mysql.sql via db(). */

    $fallback = [
        'PDC1','PPT1','Billet&Scarp','PURCHASING','EXT2400','EXT2000','MNTE','ANO','PDC 2','WHL',
        'PPT2','Die.Pc','R&D , HUB','CX','Die.Design','Die Making','QA','New Medel','MIS',
        'EXT. 880-1','EXT. 880-2','ACC&BUD','FIN','PLANNING','AO','IT','HRD','GA'
    ];

    // Seed once, then only add genuinely new source values. A user-deleted
    // department stays inactive and is not silently recreated.
    $count = (int)$p->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $source = $fallback;
    foreach (['audit_plans','areas','excel_monthly'] as $table) {
        try {
            $q = $p->query("SELECT DISTINCT TRIM(department) AS department
                               FROM {$table}
                               WHERE TRIM(COALESCE(department,'')) <> ''");
            $rows = $q ? $q->fetchAll(PDO::FETCH_COLUMN) : [];
            $source = array_merge($source, $rows);
        } catch (Throwable $e) {}
    }
    $ins = $p->prepare("INSERT IGNORE INTO departments(name,active) VALUES(?,1)");
    foreach ($source as $v) {
        $v = trim((string)$v);
        if ($v !== '' && !blockedDept($v)) $ins->execute([$v]);
    }
}
try {
    $p = db();
    ensureDepartments($p);

    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '' || blockedDept($name)) throw new RuntimeException('ชื่อแผนกไม่ถูกต้อง');
        $st = $p->prepare("SELECT id FROM departments WHERE name = ? LIMIT 1");
        $st->execute([$name]);
        $id = $st->fetchColumn();
        if ($id !== false) {
            $p->prepare("UPDATE departments SET active=1, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int)$id]);
        } else {
            $p->prepare("INSERT INTO departments(name,active) VALUES(?,1)")->execute([$name]);
            $id = (int)$p->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>(int)$id,'name'=>$name], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('ไม่พบแผนก');
        $p->prepare("UPDATE departments SET active=0, updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = $p->query("SELECT id,name FROM departments WHERE active=1 ORDER BY name COLLATE utf8mb4_general_ci")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'departments'=>array_column($rows,'name'),'items'=>$rows,'count'=>count($rows)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'departments'=>[],'items'=>[]], JSON_UNESCAPED_UNICODE);
}
