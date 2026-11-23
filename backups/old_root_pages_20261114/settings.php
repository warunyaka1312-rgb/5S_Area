<?php
require_once 'config.php';
$title='ตั้งค่าเป้าหมาย / ระบบอัตโนมัติ';
require_once __DIR__.'/includes/automation.php';
$p=db();$msg='';
if($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='automation'){
 foreach(['automation_enabled','auto_backup_enabled','auto_sync_enabled'] as $k) save_app_setting($k, isset($_POST[$k])?'1':'0');
 $msg='บันทึกการตั้งค่าระบบอัตโนมัติแล้ว';
}elseif($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='run_now'){
 $b=five_s_auto_backup(); $r=five_s_auto_sync_plans(true);
 $msg='รันงานอัตโนมัติแล้ว • Backup: '.($b?:'ไม่สำเร็จ').' • อัปเดตสถานะแผน '.$r['updated'].' รายการ';
}elseif($_SERVER['REQUEST_METHOD']==='POST'){
 $prod=(float)($_POST['production_target_pct']??85); $off=(float)($_POST['office_target_pct']??87);
 if($prod<1||$prod>100||$off<1||$off>100)$msg='เป้าหมายต้องอยู่ระหว่าง 1–100%';
 else{save_app_setting('production_target_pct',number_format($prod,2,'.',''));save_app_setting('office_target_pct',number_format($off,2,'.',''));save_app_setting('target_pct',number_format($prod,2,'.',''));$msg='บันทึกเป้าหมาย Production และ Office เรียบร้อยแล้ว';}
}
$prodTarget=target_pct('Production');$officeTarget=target_pct('Office');
require 'includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>ตั้งค่า <span>เป้าหมาย</span></h1><p>กำหนดเป้าหมายแยกกันระหว่าง Production และ Office</p></div><div class="hero-right"><strong>P <?=number_format($prodTarget,1)?>% / O <?=number_format($officeTarget,1)?>%</strong><small>เป้าหมายปัจจุบัน</small></div></div>
<?php if($msg):?><div class="card p-4 mb-5 bg-emerald-50 text-emerald-800 font-bold">✅ <?=h($msg)?></div><?php endif;?>
<div class="grid md:grid-cols-2 gap-5">
 <div class="card p-6 border-l-8 border-orange-500"><div class="section-title mb-4">🏭 Production</div><form method="post"><input type="hidden" name="office_target_pct" value="<?=h($officeTarget)?>"><label class="font-bold">เป้าหมาย (%)</label><input type="number" name="production_target_pct" step="0.1" min="1" max="100" value="<?=h($prodTarget)?>" class="w-full border rounded-xl p-4 text-2xl font-black mt-2"><p class="text-sm text-slate-500 mt-2">ค่าเริ่มต้น 85%</p><button class="bg-orange-500 text-white rounded-xl px-7 py-3 font-black mt-4">💾 บันทึก</button></form></div>
 <div class="card p-6 border-l-8 border-blue-500"><div class="section-title mb-4">🏢 Office</div><form method="post"><input type="hidden" name="production_target_pct" value="<?=h($prodTarget)?>"><label class="font-bold">เป้าหมาย (%)</label><input type="number" name="office_target_pct" step="0.1" min="1" max="100" value="<?=h($officeTarget)?>" class="w-full border rounded-xl p-4 text-2xl font-black mt-2"><p class="text-sm text-slate-500 mt-2">ค่าเริ่มต้น 87%</p><button class="bg-blue-600 text-white rounded-xl px-7 py-3 font-black mt-4">💾 บันทึก</button></form></div>
</div>
<div class="card p-5 mt-5 bg-blue-50 text-blue-900">ระบบจะใช้เป้าหมายของแต่ละประเภทกับหน้า Manual, Import, Dashboard, Ranking, รายงาน และกราฟแยกประเภท</div>
<?php $__on=fn($k)=>app_setting($k,'1')==='1'; $__al=five_s_alerts(); ?>
<div class="card p-6 mt-5" id="automation">
  <div class="flex flex-wrap items-center justify-between gap-3 mb-4"><div class="section-title">⚡ ระบบอัตโนมัติ</div>
    <form method="post"><input type="hidden" name="action" value="run_now"><button class="bg-slate-800 text-white rounded-xl px-5 py-2 font-semibold">▶ รันตอนนี้ (Backup + ซิงก์แผน)</button></form></div>
  <form method="post" class="grid md:grid-cols-3 gap-3"><input type="hidden" name="action" value="automation">
    <?php foreach([
      ['automation_enabled','เปิดใช้งานระบบอัตโนมัติทั้งหมด','สวิตช์หลักของงานอัตโนมัติ'],
      ['auto_backup_enabled','สำรองฐานข้อมูลรายวัน','ล่าสุด: '.($__al['backup_last']?:'—').' • เก็บ 30 วัน'],
      ['auto_sync_enabled','อัปเดตสถานะแผนตรวจจากคะแนน','ล่าสุด: '.($__al['sync_at']?:'—').' • ทุก 10 นาที'],
    ] as $o):?>
    <label class="flex gap-3 items-start border rounded-xl p-4 cursor-pointer hover:border-orange-500"><input type="checkbox" name="<?=$o[0]?>" value="1" <?=$__on($o[0])?'checked':''?> class="mt-1 w-5 h-5 accent-orange-600"><span><b class="block"><?=h($o[1])?></b><small class="text-slate-500"><?=h($o[2])?></small></span></label>
    <?php endforeach;?>
    <div class="md:col-span-3"><button class="bg-orange-500 text-white rounded-xl px-6 py-3 font-semibold">💾 บันทึกการตั้งค่าอัตโนมัติ</button> <a class="ml-3 text-orange-600 font-semibold" href="setup_check.php">ดูสถานะงานอัตโนมัติ →</a></div>
  </form>
</div>
<?php require 'includes/footer.php';?>