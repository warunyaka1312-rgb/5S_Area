<?php
require_once 'config.php';
require_once __DIR__.'/includes/automation.php';
$action=$_GET['action']??'';
if($action==='download_file'){
  $f=basename((string)($_GET['f']??''));
  $path=__DIR__.'/backups/'.$f;
  if(!preg_match('/^[A-Za-z0-9_.\-]+\.(sqlite|json)$/',$f)||!is_file($path)){http_response_code(404);exit('ไม่พบไฟล์สำรอง');}
  header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$f.'"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
if($action==='backup_now'){five_s_auto_backup();$name='manual_'.date('Ymd_His').'.sqlite';@copy(DB_PATH,__DIR__.'/backups/'.$name);go('backup.php?done=1');}
if($action==='download_db'){if(!is_file(DB_PATH)){http_response_code(404);exit('ไม่พบฐานข้อมูล');}$name='5S_SMART_backup_'.date('Ymd_His').'.sqlite';header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize(DB_PATH));readfile(DB_PATH);exit;}
if($action==='json'){ $p=db();$tables=[];foreach($p->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $t){$tn=$t['name'];$tables[$tn]=$p->query('SELECT * FROM "'.str_replace('"','""',$tn).'"')->fetchAll();} $data=['app'=>'5S SMART','created_at'=>date('c'),'tables'=>$tables];header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="5S_SMART_backup_'.date('Ymd_His').'.json"');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);exit;}
$msg=isset($_GET['done'])?'สร้างไฟล์สำรองในเครื่องเรียบร้อยแล้ว (โฟลเดอร์ backups)':'';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_FILES['dbfile'])){if($_FILES['dbfile']['error']!==UPLOAD_ERR_OK){$msg='อัปโหลดไฟล์ไม่สำเร็จ';}else{$tmp=$_FILES['dbfile']['tmp_name'];try{$test=new PDO('sqlite:'.$tmp);$test->query('PRAGMA integrity_check')->fetchColumn();$has=$test->query("SELECT name FROM sqlite_master WHERE type='table' AND name='audits'")->fetchColumn();if(!$has)throw new Exception('ไฟล์ไม่ใช่ฐานข้อมูล 5S SMART');$backupDir=__DIR__.'/backups';if(!is_dir($backupDir))mkdir($backupDir,0775,true);copy(DB_PATH,$backupDir.'/before_restore_'.date('Ymd_His').'.sqlite');if(!copy($tmp,DB_PATH))throw new Exception('ไม่สามารถเขียนฐานข้อมูลได้');$msg='กู้คืนฐานข้อมูลเรียบร้อยแล้ว';}catch(Throwable $e){$msg='กู้คืนไม่สำเร็จ: '.$e->getMessage();}}}
$title='สำรอง / กู้คืนข้อมูล';require 'includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>สำรอง<span>ข้อมูล 5 ส.</span></h1><p>Backup / Restore ฐานข้อมูล SQLite เพื่อป้องกันข้อมูลสูญหาย</p></div><div class="hero-right"><strong>BACKUP</strong><small>SQLite + JSON</small></div></div>
<?php if($msg):?><div class="card p-4 mb-5 bg-emerald-50 text-emerald-800 font-bold">✅ <?=h($msg)?></div><?php endif;?>
<div class="grid md:grid-cols-2 gap-5">
<div class="card p-6"><div class="section-title mb-3">💾 สำรองข้อมูล</div><p class="text-sm text-slate-600 mb-4">แนะนำให้สำรองก่อนนำเข้าข้อมูลหรือแก้ไขคะแนนจำนวนมาก</p><div class="flex flex-wrap gap-3"><a class="bg-blue-600 text-white px-5 py-3 rounded-xl font-bold" href="backup.php?action=download_db">⬇️ Backup SQLite</a><a class="bg-slate-800 text-white px-5 py-3 rounded-xl font-bold" href="backup.php?action=json">⬇️ Backup JSON</a></div></div>
<div class="card p-6"><div class="section-title mb-3">♻️ กู้คืนข้อมูล</div><p class="text-sm text-slate-600 mb-4">เลือกไฟล์ `.sqlite` ที่เคยสำรองไว้ ระบบจะสำรองฐานข้อมูลปัจจุบันก่อนกู้คืน</p><form method="post" enctype="multipart/form-data"><input type="file" name="dbfile" accept=".sqlite,.db" required class="w-full border rounded-xl p-3 mb-3"><button class="bg-orange-500 text-white px-5 py-3 rounded-xl font-bold">♻️ กู้คืนฐานข้อมูล</button></form></div>
</div>
<?php $__bk=five_s_auto_backup_list(); ?>
<div class="card p-6 mt-5"><div class="flex flex-wrap items-center justify-between gap-3 mb-3"><div class="section-title">🗂️ ไฟล์สำรองในเครื่อง (อัตโนมัติ + ก่อนกู้คืน)</div><a class="bg-orange-500 text-white px-5 py-2 rounded-xl font-semibold" href="backup.php?action=backup_now">＋ สำรองตอนนี้</a></div>
<p class="text-sm text-slate-600 mb-3">ระบบสำรองฐานข้อมูลอัตโนมัติวันละ 1 ครั้ง (ไฟล์ <code>auto_daily_YYYYMMDD.sqlite</code>) และเก็บไว้ 30 วันล่าสุด • ตั้งค่าได้ที่ <a class="text-orange-600 font-semibold" href="settings.php#automation">ระบบอัตโนมัติ</a></p>
<div class="overflow-auto"><table class="w-full text-sm"><thead><tr class="bg-slate-50 text-left"><th class="p-3">ไฟล์</th><th class="p-3">ประเภท</th><th class="p-3">วันที่</th><th class="p-3 text-right">ขนาด</th><th class="p-3"></th></tr></thead><tbody>
<?php foreach($__bk as $b): $kind=str_starts_with($b['name'],'auto_daily_')?'อัตโนมัติรายวัน':(str_starts_with($b['name'],'before_restore_')?'ก่อนกู้คืน':(str_starts_with($b['name'],'manual_')?'สำรองด้วยตนเอง':'อื่น ๆ')); ?>
<tr class="border-t"><td class="p-3 font-mono text-xs"><?=h($b['name'])?></td><td class="p-3"><?=h($kind)?></td><td class="p-3"><?=date('d/m/Y H:i',$b['time'])?></td><td class="p-3 text-right"><?=number_format($b['size']/1024,0)?> KB</td><td class="p-3 text-right"><a class="text-orange-600 font-semibold" href="backup.php?action=download_file&f=<?=urlencode($b['name'])?>">ดาวน์โหลด</a></td></tr>
<?php endforeach; if(!$__bk):?><tr><td colspan="5" class="p-4 text-slate-500">ยังไม่มีไฟล์สำรอง</td></tr><?php endif;?>
</tbody></table></div></div>
<div class="card p-6 mt-5"><div class="section-title mb-3">📁 ไฟล์นำเข้าที่เก็บไว้</div><div class="text-sm text-slate-600">ไฟล์ต้นฉบับที่อัปโหลดจะถูกเก็บไว้ในโฟลเดอร์ <code>uploads/</code> เพื่อย้อนตรวจสอบภายหลัง</div></div>
<?php require 'includes/footer.php'; ?>
