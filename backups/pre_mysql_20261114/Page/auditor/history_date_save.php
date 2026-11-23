<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2).'/includes/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function json_out(bool $ok, string $message, array $extra=[]): never {
    http_response_code($ok ? 200 : 400);
    echo json_encode(array_merge(['ok'=>$ok,'message'=>$message],$extra), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(false,'กรุณาใช้การบันทึกจากช่องวันที่');

    $id=(int)($_POST['audit_id'] ?? 0);
    $newDate=trim((string)($_POST['new_audit_date'] ?? ''));
    if($id<=0) json_out(false,'ไม่พบรหัสรายการตรวจ');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$newDate)) json_out(false,'รูปแบบวันที่ไม่ถูกต้อง');
    $dt=DateTime::createFromFormat('!Y-m-d',$newDate);
    $de=DateTime::getLastErrors();
    if(!$dt || ($de!==false && ($de['warning_count']>0 || $de['error_count']>0)) || $dt->format('Y-m-d')!==$newDate) json_out(false,'วันที่ไม่ถูกต้อง');

    $p=db();
    try{$p->exec('PRAGMA busy_timeout=10000');}catch(Throwable $e){}

    $st=$p->prepare('SELECT id,area_id,auditor,audit_date FROM audits WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $cur=$st->fetch(PDO::FETCH_ASSOC);
    if(!$cur) json_out(false,'ไม่พบรายการตรวจ ID '.$id);

    $oldDate=(string)$cur['audit_date'];
    if($oldDate===$newDate) json_out(true,'วันที่เดิม ไม่มีการเปลี่ยนแปลง',['audit_id'=>$id,'audit_date'=>$newDate]);

    // ตรวจเฉพาะกรณีมีรายการซ้ำจริง ๆ เท่านั้น
    $dup=$p->prepare('SELECT id FROM audits WHERE area_id=? AND audit_date=? AND auditor=? AND id<>? LIMIT 1');
    $dup->execute([(int)$cur['area_id'],$newDate,(string)$cur['auditor'],$id]);
    if($dup->fetchColumn()!==false) json_out(false,'ไม่สามารถบันทึกได้ เพราะพื้นที่นี้มีรายการของผู้ตรวจคนเดิมในวันที่ '.$newDate.' อยู่แล้ว');

    // อัปเดตเฉพาะ audit_date เพื่อไม่กระทบข้อมูลอื่น
    $p->beginTransaction();
    try {
        $up=$p->prepare('UPDATE audits SET audit_date=? WHERE id=?');
        $up->execute([$newDate,$id]);
        $check=$p->prepare('SELECT audit_date FROM audits WHERE id=? LIMIT 1');
        $check->execute([$id]);
        $saved=$check->fetchColumn();
        if((string)$saved!==$newDate) throw new RuntimeException('SQLite ยืนยันวันที่ใหม่ไม่ได้');
        $p->commit();
    } catch(Throwable $e) {
        if($p->inTransaction()) $p->rollBack();
        throw $e;
    }

    five_s_sync_after_save($p,$oldDate,$newDate);
    json_out(true,'✓ บันทึกวันที่เรียบร้อย',['audit_id'=>$id,'audit_date'=>$newDate,'old_date'=>$oldDate]);
} catch(Throwable $e) {
    json_out(false,'บันทึกวันที่ไม่สำเร็จ: '.$e->getMessage());
}
