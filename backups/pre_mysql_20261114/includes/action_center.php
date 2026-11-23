<?php
/* V60 Dashboard action center — every card links to the page that resolves it. */
$__a = five_s_alerts();
$__planPct = $__a['plan_total'] > 0 ? round($__a['plan_done'] / $__a['plan_total'] * 100) : 0;
$__nextPlan = $__a['plan_next'] ? date('d/m/', strtotime($__a['plan_next'])).((int)date('Y', strtotime($__a['plan_next'])) + 543) : '—';
$__latest = $__a['latest_audit'] ? date('d/m/', strtotime($__a['latest_audit'])).((int)date('Y', strtotime($__a['latest_audit'])) + 543) : '—';
?>
<div class="ac-head no-print">
  <h2><?=five_s_icon('bolt')?> ศูนย์ติดตามอัตโนมัติ</h2>
  <small>อัปเดตทุกครั้งที่เปิดหน้า • ซิงก์สถานะแผนตรวจล่าสุด <?=h($__a['sync_at'] ? substr($__a['sync_at'], 11, 5).' น.' : '—')?></small>
</div>
<div class="ac-grid no-print">
  <a class="ac-card" href="Page/admin/schedule_manage.php?month=<?=$__a['month']?>&year=<?=$__a['year_be']?>">
    <div class="ac-ic <?=$__planPct>=100?'ok':'warn'?>"><?=five_s_icon('calendar')?></div>
    <div style="flex:1"><small>แผนตรวจ <?=h($__a['plan_label'])?></small><b><?=$__a['plan_done']?> / <?=$__a['plan_total']?></b>
      <span class="sub">ตรวจแล้ว • บางส่วน <?=$__a['plan_partial']?> • รอตรวจ <?=$__a['plan_wait']?></span>
      <div class="ac-bar"><i style="width:<?=$__planPct?>%"></i></div></div>
  </a>
  <a class="ac-card" href="Page/public/completion_summary.php?month=<?=$__a['month']?>&year=<?=$__a['year_be']?>">
    <div class="ac-ic navy"><?=five_s_icon('check')?></div>
    <div><small>ตรวจครั้งถัดไป</small><b><?=h($__nextPlan)?></b><span class="sub">วันนี้ค้างตรวจ <?=$__a['plan_today']?> แผน • ดูความครบถ้วน</span></div>
  </a>
  <a class="ac-card" href="Page/auditor/tag_manage.php">
    <div class="ac-ic <?=$__a['tag_overdue']?'bad':($__a['tag_open']?'warn':'ok')?>"><?=five_s_icon('tag')?></div>
    <div><small>TAG ที่ยังเปิด</small><b><?=$__a['tag_open']?> <span style="font-size:13px;font-weight:600;color:var(--muted)">จาก <?=$__a['tag_total']?></span></b><span class="sub">เกินกำหนด <?=$__a['tag_overdue']?> รายการ</span></div>
  </a>
  <a class="ac-card" href="Page/auditor/history.php">
    <div class="ac-ic"><?=five_s_icon('edit')?></div>
    <div><small>บันทึกคะแนนล่าสุด</small><b><?=h($__latest)?></b><span class="sub">เดือนนี้บันทึกแล้ว <?=$__a['audits_month']?> ใบตรวจ</span></div>
  </a>
  <a class="ac-card" href="Page/admin/backup.php">
    <div class="ac-ic ok"><?=five_s_icon('database')?></div>
    <div><small>Backup อัตโนมัติ</small><b><?=h($__a['backup_last'] ? substr($__a['backup_last'], 0, 10) : '—')?></b><span class="sub">สำรองฐานข้อมูลวันละ 1 ครั้ง เก็บ 30 วัน</span></div>
  </a>
</div>
