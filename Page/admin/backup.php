<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
require_once FIVE_S_ROOT.'/includes/automation.php';
$action=$_GET['action']??'';
if($action==='download_file'){
  $f=basename((string)($_GET['f']??''));
  $path=FIVE_S_ROOT.'/backups/'.$f;
  if(!preg_match('/^[A-Za-z0-9_.\-]+\.(sql|sqlite|json)$/',$f)||!is_file($path)){http_response_code(404);exit('ไม่พบไฟล์สำรอง');}
  header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.$f.'"');header('Content-Length: '.filesize($path));readfile($path);exit;
}
if($action==='backup_now'){five_s_auto_backup();$name='manual_'.date('Ymd_His').'.sql';five_s_backup_to_file(FIVE_S_ROOT.'/backups/'.$name);go('Page/admin/backup.php?done=1');}
if($action==='download_db'){
  $tmp=tempnam(sys_get_temp_dir(),'5s_');
  if(!$tmp||!five_s_backup_to_file($tmp)){http_response_code(500);exit('สำรองฐานข้อมูลไม่สำเร็จ');}
  $name='5S_SMART_backup_'.date('Ymd_His').'.sql';
  header('Content-Type: application/sql; charset=utf-8');header('Content-Disposition: attachment; filename="'.$name.'"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
}
if($action==='json'){ $p=db();$tables=[];foreach(db_list_tables($p) as $tn){$tables[$tn]=$p->query('SELECT * FROM '.db_qi($tn))->fetchAll();} $data=['app'=>'5S SMART','created_at'=>date('c'),'tables'=>$tables];header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="5S_SMART_backup_'.date('Ymd_His').'.json"');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);exit;}
$msg=isset($_GET['done'])?'สร้างไฟล์สำรองในเครื่องเรียบร้อยแล้ว (โฟลเดอร์ backups)':'';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_FILES['dbfile'])){
  if($_FILES['dbfile']['error']!==UPLOAD_ERR_OK){$msg='อัปโหลดไฟล์ไม่สำเร็จ';}
  else{
    $tmp=$_FILES['dbfile']['tmp_name'];
    $ext=strtolower(pathinfo((string)$_FILES['dbfile']['name'],PATHINFO_EXTENSION));
    try{
      if(!in_array($ext,['sql','sqlite','db'],true)) throw new Exception('รองรับเฉพาะไฟล์ .sql (MySQL) หรือ .sqlite (ไฟล์สำรองรุ่นเดิม)');
      $backupDir=FIVE_S_ROOT.'/backups';if(!is_dir($backupDir))mkdir($backupDir,0775,true);
      // Always keep a copy of the current MySQL data before restoring.
      if(!five_s_backup_to_file($backupDir.'/before_restore_'.date('Ymd_His').'.sql')) throw new Exception('สำรองข้อมูลปัจจุบันก่อนกู้คืนไม่สำเร็จ');
      if($ext==='sql'){
        five_s_mysql_restore_file(db(),$tmp);
      }else{
        $r=five_s_import_sqlite(db(),$tmp);
        foreach($r['tables'] as $t=>$c){ if($c['sqlite']!==$c['mysql']) throw new Exception("จำนวนแถวตาราง $t ไม่ตรงกัน ({$c['sqlite']} / {$c['mysql']})"); }
      }
      $msg='กู้คืนฐานข้อมูลเรียบร้อยแล้ว';
    }catch(Throwable $e){$msg='กู้คืนไม่สำเร็จ: '.$e->getMessage();}
  }
}
$title='สำรอง / กู้คืนข้อมูล';require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>สำรอง<span>ข้อมูล 5 ส.</span></h1><p>Backup / Restore ฐานข้อมูล MySQL เพื่อป้องกันข้อมูลสูญหาย</p></div><div class="hero-right"><strong>BACKUP</strong><small>MySQL (.sql) + JSON</small></div></div>
<?php if($msg):?><div class="card p-4 mb-5 bg-emerald-50 text-emerald-800 font-bold">✅ <?=h($msg)?></div><?php endif;?>
<div class="grid md:grid-cols-2 gap-5">
<div class="card p-6"><div class="section-title mb-3">💾 สำรองข้อมูล</div><p class="text-sm text-slate-600 mb-4">แนะนำให้สำรองก่อนนำเข้าข้อมูลหรือแก้ไขคะแนนจำนวนมาก</p><div class="flex flex-wrap gap-3"><a class="bg-blue-600 text-white px-5 py-3 rounded-xl font-bold" href="Page/admin/backup.php?action=download_db">⬇️ Backup MySQL (.sql)</a><a class="bg-slate-800 text-white px-5 py-3 rounded-xl font-bold" href="Page/admin/backup.php?action=json">⬇️ Backup JSON</a></div></div>
<div class="card p-6"><div class="section-title mb-3">♻️ กู้คืนข้อมูล</div><p class="text-sm text-slate-600 mb-4">เลือกไฟล์ <code>.sql</code> ที่เคยสำรองไว้ (หรือไฟล์ <code>.sqlite</code> จากระบบรุ่นเดิม) ระบบจะสำรองฐานข้อมูลปัจจุบันก่อนกู้คืน</p><form method="post" enctype="multipart/form-data"><input type="file" name="dbfile" accept=".sql,.sqlite,.db" required class="w-full border rounded-xl p-3 mb-3"><button class="bg-orange-500 text-white px-5 py-3 rounded-xl font-bold">♻️ กู้คืนฐานข้อมูล</button></form></div>
</div>
<?php $__bk=five_s_auto_backup_list(); ?>
<div class="card p-6 mt-5"><div class="flex flex-wrap items-center justify-between gap-3 mb-3"><div class="section-title">🗂️ ไฟล์สำรองในเครื่อง (อัตโนมัติ + ก่อนกู้คืน)</div><a class="bg-orange-500 text-white px-5 py-2 rounded-xl font-semibold" href="Page/admin/backup.php?action=backup_now">＋ สำรองตอนนี้</a></div>
<p class="text-sm text-slate-600 mb-3">ระบบสำรองฐานข้อมูลอัตโนมัติวันละ 1 ครั้ง (ไฟล์ <code>auto_daily_YYYYMMDD.sql</code>) และเก็บไว้ 30 วันล่าสุด • ตั้งค่าได้ที่ <a class="text-orange-600 font-semibold" href="Page/admin/settings.php#automation">ระบบอัตโนมัติ</a></p>
<div class="overflow-auto"><table class="w-full text-sm"><thead><tr class="bg-slate-50 text-left"><th class="p-3">ไฟล์</th><th class="p-3">ประเภท</th><th class="p-3">วันที่</th><th class="p-3 text-right">ขนาด</th><th class="p-3"></th></tr></thead><tbody>
<?php foreach($__bk as $b): $kind=str_starts_with($b['name'],'auto_daily_')?'อัตโนมัติรายวัน':(str_starts_with($b['name'],'before_restore_')?'ก่อนกู้คืน':(str_starts_with($b['name'],'manual_')?'สำรองด้วยตนเอง':'อื่น ๆ')); ?>
<tr class="border-t"><td class="p-3 font-mono text-xs"><?=h($b['name'])?></td><td class="p-3"><?=h($kind)?></td><td class="p-3"><?=date('d/m/Y H:i',$b['time'])?></td><td class="p-3 text-right"><?=number_format($b['size']/1024,0)?> KB</td><td class="p-3 text-right"><a class="text-orange-600 font-semibold" href="Page/admin/backup.php?action=download_file&f=<?=urlencode($b['name'])?>">ดาวน์โหลด</a></td></tr>
<?php endforeach; if(!$__bk):?><tr><td colspan="5" class="p-4 text-slate-500">ยังไม่มีไฟล์สำรอง</td></tr><?php endif;?>
</tbody></table></div></div>
<div class="card p-6 mt-5"><div class="section-title mb-3">📁 ไฟล์นำเข้าที่เก็บไว้</div><div class="text-sm text-slate-600">ไฟล์ต้นฉบับที่อัปโหลดจะถูกเก็บไว้ในโฟลเดอร์ <code>uploads/</code> เพื่อย้อนตรวจสอบภายหลัง</div></div>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
