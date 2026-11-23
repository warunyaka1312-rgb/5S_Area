<?php
require_once 'config.php';
require_once __DIR__.'/includes/automation.php';
$p = null; $dbOk=false; $integrity='ยังไม่ได้ตรวจ'; $counts=[]; $error='';
try {
  if (extension_loaded('pdo_sqlite') && file_exists(DB_PATH)) {
    $p=db(); $dbOk=true;
    $integrity=(string)$p->query('PRAGMA integrity_check')->fetchColumn();
    foreach ([['audits','รายการตรวจ'],['areas','พื้นที่/หน่วย'],['scores','คะแนน Production'],['office_scores','คะแนน Office'],['audit_plans','แผนตรวจ'],['tags','TAG 5 ส.']] as $item) {
      try { $counts[$item[1]]=(int)$p->query('SELECT COUNT(*) FROM "'.$item[0].'"')->fetchColumn(); }
      catch(Throwable $e) { $counts[$item[1]]=null; }
    }
  }
} catch(Throwable $e) { $error=$e->getMessage(); }
$backupDirOk = is_dir(__DIR__.'/backups') ? is_writable(__DIR__.'/backups') : is_writable(__DIR__);
$checks=[
 ['PHP',PHP_VERSION,'ok'],
 ['PDO SQLite',extension_loaded('pdo_sqlite')?'พร้อมใช้งาน':'ไม่พบส่วนขยาย',extension_loaded('pdo_sqlite')?'ok':'bad'],
 ['ไฟล์ฐานข้อมูล',file_exists(DB_PATH)?'พบฐานข้อมูล':'ไม่พบฐานข้อมูล',file_exists(DB_PATH)?'ok':'bad'],
 ['สิทธิ์เขียนโฟลเดอร์ฐานข้อมูล',is_writable(__DIR__.'/db')?'พร้อม':'ตรวจสิทธิ์โฟลเดอร์ db',is_writable(__DIR__.'/db')?'ok':'warn'],
 ['ตรวจความสมบูรณ์ SQLite',$integrity,$integrity==='ok'?'ok':'bad'],
 ['โฟลเดอร์ Backup อัตโนมัติ',$backupDirOk?'เขียนได้':'ตรวจสิทธิ์โฟลเดอร์ backups',$backupDirOk?'ok':'warn'],
];
$alerts = $dbOk ? five_s_alerts() : [];
$title='ตรวจความพร้อมระบบ';
require 'includes/header.php';
$cls=['ok'=>'good','warn'=>'warn','bad'=>'bad'];
?>
<div class="hero"><div class="hero-copy"><h1>ตรวจความพร้อม<span>ระบบ</span></h1><p>ศูนย์เริ่มต้นใช้งาน • สถานะฐานข้อมูล • งานอัตโนมัติ</p></div><div class="hero-right"><strong><?=$integrity==='ok'?'พร้อมใช้งาน':'ตรวจสอบ'?></strong><small>PHP <?=h(PHP_VERSION)?> • <?=h(FIVE_S_APP_VERSION)?></small></div></div>

<div class="card p-6 mb-5"><div class="section-title mb-4">1. สถานะระบบ</div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
  <?php foreach($checks as $c):?><div class="border rounded-xl p-4"><div class="text-sm text-slate-500 font-semibold"><?=h($c[0])?></div><div class="inline-block mt-2 px-3 py-1 rounded-lg font-bold <?=$cls[$c[2]]?>"><?=h($c[1])?></div></div><?php endforeach;?>
  </div>
  <?php if($error!==''):?><p class="mt-3 bad p-3 rounded-lg">รายละเอียด: <?=h($error)?></p><?php endif;?>
</div>

<div class="card p-6 mb-5"><div class="section-title mb-4">2. จำนวนข้อมูลที่ระบบอ่านได้</div>
  <div class="grid sm:grid-cols-3 lg:grid-cols-6 gap-3">
  <?php foreach($counts as $label=>$count):?><div class="border rounded-xl p-4"><div class="text-sm text-slate-500"><?=h($label)?></div><div class="text-2xl font-extrabold mt-1"><?=$count===null?'—':number_format($count)?></div></div><?php endforeach;?>
  </div>
  <p class="text-sm text-slate-500 mt-3">หน้านี้อ่านข้อมูลเพื่อตรวจสอบเท่านั้น ไม่แก้ไขหรือลบข้อมูลในฐานข้อมูล</p>
</div>

<div class="card p-6 mb-5"><div class="section-title mb-4">3. งานอัตโนมัติ (Automation)</div>
  <div class="overflow-auto"><table class="w-full text-sm">
    <thead><tr class="bg-slate-50 text-left"><th class="p-3">งาน</th><th class="p-3">ทำงานเมื่อ</th><th class="p-3">ล่าสุด</th><th class="p-3">ไปที่หน้า</th></tr></thead>
    <tbody>
      <tr class="border-t"><td class="p-3 font-semibold">สำรองฐานข้อมูลรายวัน</td><td class="p-3">เปิดหน้าใดก็ได้ครั้งแรกของวัน • เก็บ 30 วันล่าสุด</td><td class="p-3"><?=h($alerts['backup_last']??'—')?></td><td class="p-3"><a class="text-orange-600 font-semibold" href="backup.php">สำรอง / กู้คืน →</a></td></tr>
      <tr class="border-t"><td class="p-3 font-semibold">อัปเดตสถานะแผนตรวจจากคะแนน</td><td class="p-3">ทุก 10 นาที + ทุกครั้งที่บันทึกคะแนน (เดือนนี้และเดือนก่อน)</td><td class="p-3"><?=h($alerts['sync_at']??'—')?></td><td class="p-3"><a class="text-orange-600 font-semibold" href="schedule_manage.php">แผนตรวจ →</a></td></tr>
      <tr class="border-t"><td class="p-3 font-semibold">สร้างแผนตรวจเดือนใหม่</td><td class="p-3">เปิดหน้าแผนตรวจของเดือนที่ยังไม่มีแผน (ใช้รายชื่อเดิม สลับทีม)</td><td class="p-3">—</td><td class="p-3"><a class="text-orange-600 font-semibold" href="schedule_manage.php">แผนตรวจ →</a></td></tr>
      <tr class="border-t"><td class="p-3 font-semibold">แจ้งเตือน TAG เกินกำหนด / แผนวันนี้</td><td class="p-3">ทุกหน้า (กระดิ่งด้านบน + Dashboard)</td><td class="p-3">TAG เปิด <?=(int)($alerts['tag_open']??0)?> • เกินกำหนด <?=(int)($alerts['tag_overdue']??0)?></td><td class="p-3"><a class="text-orange-600 font-semibold" href="tag_manage.php">จัดการ TAG →</a></td></tr>
      <tr class="border-t"><td class="p-3 font-semibold">เลือกเดือนล่าสุดที่มีข้อมูล</td><td class="p-3">Dashboard, กราฟ, รายงาน, พิมพ์กราฟ</td><td class="p-3"><?=h($alerts['latest_audit']??'—')?></td><td class="p-3"><a class="text-orange-600 font-semibold" href="index.php">Dashboard →</a></td></tr>
    </tbody></table></div>
  <p class="text-sm text-slate-500 mt-3">เปิด/ปิดงานอัตโนมัติได้ที่ <a class="text-orange-600 font-semibold" href="settings.php">ตั้งค่าเป้าหมาย / ระบบอัตโนมัติ</a></p>
</div>

<div class="card p-6 mb-5"><div class="section-title mb-4">4. เริ่มใช้งานตามลำดับ</div>
  <div class="grid md:grid-cols-3 gap-3">
  <?php foreach([
    ['master_data.php','ตรวจข้อมูลแผนก กลุ่ม และหน่วยให้ถูกต้อง'],
    ['schedule_manage.php','จัดแผนตรวจและพิมพ์ใบตรวจตามรอบ'],
    ['production_score.php','บันทึกคะแนน Production (Office บันทึกแยก)'],
    ['completion_summary.php','ตรวจความครบถ้วน — สถานะแผนอัปเดตเอง'],
    ['tag_manage.php','ติดตาม TAG และปิดเมื่อแก้ไขเสร็จ'],
    ['org_3month_summary.php','สรุปผลองค์กร / พิมพ์บอร์ด'],
  ] as $i=>$s):?>
    <a href="<?=$s[0]?>" class="flex gap-3 items-start border rounded-xl p-4 hover:border-orange-500 no-underline"><span class="w-8 h-8 rounded-full bg-orange-500 text-white font-bold flex items-center justify-center shrink-0"><?=$i+1?></span><span class="font-semibold text-slate-800"><?=h($s[1])?></span></a>
  <?php endforeach;?>
  </div>
  <p class="text-sm text-slate-500 mt-3">สำรองข้อมูลก่อนนำเข้าไฟล์หรือกู้คืนข้อมูลทุกครั้ง (ระบบสำรองให้อัตโนมัติวันละครั้งอยู่แล้ว)</p>
</div>
<?php require 'includes/footer.php'; ?>
