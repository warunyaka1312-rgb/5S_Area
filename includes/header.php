<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/automation.php';
require_once __DIR__.'/navigation.php';
five_s_run_automation();
$__alerts = five_s_alerts();
$__nav = five_s_nav_current();
$__crumbTitle = $__nav['item']['label'] ?? ($title ?? 'Dashboard');
$__alertCount = (int)$__alerts['tag_overdue'] + (int)$__alerts['plan_today'] + ((int)$__alerts['tag_open'] > 0 ? 1 : 0);
$__thMonthsShort = [1=>'ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
$__todayTh = (int)date('j').' '.$__thMonthsShort[(int)date('n')].' '.((int)date('Y') + 543);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><base href="<?=htmlspecialchars(FIVE_S_BASE,ENT_QUOTES)?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title??"Dashboard 5 ส.")?> | 5S SMART • ALUMET</title>
<link rel="icon" type="image/png" href="assets/alumet-mark.png">
<link rel="apple-touch-icon" href="assets/alumet-mark-180.png">
<!-- V80: UI libraries via CDN (Tailwind v3, Alpine v3, Chart.js v4, DataTables 1.13) with local fallback for offline use -->
<link rel="stylesheet" href="assets/vendor/fonts.css">
<script>window.tailwind=window.tailwind||{};tailwind.config={theme:{extend:{fontFamily:{sans:['"IBM Plex Sans Thai"','Inter','Tahoma','sans-serif']}}}};</script>
<script src="https://cdn.tailwindcss.com/3.4.16" onload="window.__twCDN=1"></script>
<script>window.__twCDN||document.write('<link rel="stylesheet" href="assets/vendor/tailwind.min.css">')</script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css" onerror="this.href='assets/vendor/css/jquery.dataTables.min.css'">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>window.jQuery||document.write('<script src="assets/vendor/jquery.min.js"><\/script>')</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>window.Chart||document.write('<script src="assets/vendor/chart.umd.min.js"><\/script>')</script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script>(window.jQuery&&jQuery.fn.dataTable)||document.write('<script src="assets/vendor/jquery.dataTables.min.js"><\/script>')</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" onerror="var s=document.createElement('script');s.src='assets/vendor/alpine.min.js';document.head.appendChild(s)"></script>
<script src="assets/copy-line.js?v=64"></script>
<?php
/* Backward-compatible helper still used by some pages. */
$currentPage=basename($_SERVER['PHP_SELF']??'');
$currentDepartment=$_GET['department']??'';
if(!function_exists('nav_active')){
function nav_active(string $page, ?string $department=null):string{
    global $currentPage,$currentDepartment;
    if($currentPage!==$page) return '';
    if($department!==null && $currentDepartment!==$department) return '';
    return 'active';
}}
?>
<link rel="stylesheet" href="assets/alumet-theme.css?v=<?=h(FIVE_S_APP_VERSION)?>">
</head>
<body>
<div class="app">
<header class="topbar no-print">
  <div class="tb-left">
    <button class="mobile-menu tb-icon" type="button" onclick="document.body.classList.toggle('nav-open')" aria-label="เมนู">
      <?=five_s_icon('menu')?>
    </button>
    <a href="index.php" class="brand-mini" title="กลับหน้าหลัก">
      <img src="assets/alumet-logo.png" alt="ALUMET" class="brand-logo">
      <span class="brand-divider"></span>
      <span class="brand-app"><b>5S SMART</b><small>Management System</small></span>
    </a>
  </div>
  <button type="button" class="tb-search" onclick="FiveS.openPalette()">
    <?=five_s_icon('search')?><span>ค้นหาเมนู / ไปยังหน้า…</span><kbd>Ctrl K</kbd>
  </button>
  <div class="tb-right">
    <a class="tb-chip hide-sm" href="<?=five_s_can('admin')?'Page/admin/schedule_manage.php':'Page/public/schedule_print.php'?>?month=<?=$__alerts['month']?>&year=<?=$__alerts['year_be']?>" title="สถานะแผนตรวจ (อัปเดตอัตโนมัติจากคะแนนที่บันทึก)">
      <?=five_s_icon('calendar')?><span>แผน <?=h($__alerts['plan_label'])?> <b><?=$__alerts['plan_done']?>/<?=$__alerts['plan_total']?></b></span>
    </a>
    <div class="tb-bell" x-data="{o:false}" @click.outside="o=false">
      <button type="button" class="tb-icon" @click="o=!o" aria-label="การแจ้งเตือน">
        <?=five_s_icon('bell')?>
        <?php if($__alertCount>0):?><span class="tb-badge"><?=$__alertCount?></span><?php endif;?>
      </button>
      <div class="tb-pop" x-show="o" x-transition x-cloak>
        <div class="tb-pop-h">การแจ้งเตือนอัตโนมัติ</div>
        <a href="Page/auditor/tag_manage.php" class="tb-pop-i"><span class="dot <?=$__alerts['tag_overdue']?'d-red':'d-green'?>"></span><span>TAG เกินกำหนด<small>ยังไม่ปิดและเลยวันกำหนดแก้ไข</small></span><b><?=$__alerts['tag_overdue']?></b></a>
        <a href="Page/public/tag_dashboard.php" class="tb-pop-i"><span class="dot <?=$__alerts['tag_open']?'d-amber':'d-green'?>"></span><span>TAG ที่ยังเปิดอยู่<small>รอแก้ไข / รอตรวจปิด</small></span><b><?=$__alerts['tag_open']?></b></a>
        <a href="Page/admin/schedule_manage.php" class="tb-pop-i"><span class="dot <?=$__alerts['plan_today']?'d-amber':'d-green'?>"></span><span>แผนตรวจวันนี้<small>ที่ยังไม่บันทึกคะแนน</small></span><b><?=$__alerts['plan_today']?></b></a>
        <a href="Page/public/completion_summary.php" class="tb-pop-i"><span class="dot <?=$__alerts['plan_wait']?'d-amber':'d-green'?>"></span><span>แผน <?=h($__alerts['plan_label'])?> ที่ยังไม่ได้ตรวจ<small>ตรวจแล้ว <?=$__alerts['plan_done']?> • กำลังตรวจ <?=$__alerts['plan_partial']?></small></span><b><?=$__alerts['plan_wait']?></b></a>
        <a href="Page/admin/backup.php" class="tb-pop-i"><span class="dot d-green"></span><span>สำรองข้อมูลอัตโนมัติล่าสุด<small><?=h($__alerts['backup_last']?:'—')?></small></span><b>✓</b></a>
      </div>
    </div>
    <span class="tb-date hide-sm"><?=five_s_icon('clock')?><span id="tbClock"><?=h($__todayTh)?></span></span>
    <?php $__u=five_s_user(); $__roles=five_s_roles(); ?>
    <?php if($__u): ?>
    <div class="tb-bell" x-data="{o:false}" @click.outside="o=false">
      <button type="button" class="tb-user" @click="o=!o" title="บัญชีผู้ใช้"><span class="avatar"><?=h(mb_substr(trim((string)$__u['display_name'])?:$__u['username'],0,1))?></span><span class="hide-sm"><?=h($__u['display_name']?:$__u['username'])?><small style="display:block;font-size:11px;color:#64748B;font-weight:500;line-height:1.1"><?=h($__roles[$__u['role']]??$__u['role'])?></small></span></button>
      <div class="tb-pop" x-show="o" x-transition x-cloak style="min-width:240px">
        <div class="tb-pop-h"><?=h($__u['display_name']?:$__u['username'])?> • <?=h($__u['username'])?></div>
        <a href="Page/public/account.php" class="tb-pop-i"><span class="dot d-green"></span><span>เปลี่ยนรหัสผ่าน<small>บัญชีของฉัน</small></span></a>
        <?php if(five_s_can('admin')):?><a href="Page/admin/users.php" class="tb-pop-i"><span class="dot d-amber"></span><span>ผู้ใช้งานและสิทธิ์<small>เพิ่ม / แก้ไขผู้ใช้</small></span></a><?php endif;?>
        <a href="Page/public/logout.php" class="tb-pop-i"><span class="dot d-red"></span><span>ออกจากระบบ<small>Log out</small></span></a>
      </div>
    </div>
    <?php else: ?>
    <a class="tb-user" href="Page/public/login.php?next=<?=h(rawurlencode((string)($_SERVER['REQUEST_URI']??'')))?>" style="text-decoration:none"><span class="avatar">→</span><span>เข้าสู่ระบบ</span></a>
    <?php endif; ?>
  </div>
</header>

<?php if(!empty($__u['must_change']) || ($__u && !empty($_SESSION['five_s_default_pw']))):?><div class="no-print" style="background:#FEF3C7;color:#92400E;border-bottom:1px solid #FCD34D;padding:8px 18px;font-weight:600;font-size:14px;text-align:center">⚠ บัญชีนี้ยังใช้รหัสผ่านเริ่มต้น กรุณา <a href="Page/public/account.php" style="color:#B45309;text-decoration:underline">เปลี่ยนรหัสผ่าน</a> เพื่อความปลอดภัย</div><?php endif;?>
<div class="nav-backdrop no-print" onclick="document.body.classList.remove('nav-open')"></div>
<aside class="sidebar no-print" id="mobileNav">
  <div class="side-head">
    <div class="side-logo"><img src="assets/alumet-logo.png" alt="ALUMET"></div>
    <div class="side-sub">5S SMART • CLEAN WORKPLACE, BRIGHT TOMORROW</div>
  </div>
  <nav class="nav">
  <?php foreach(five_s_nav() as $sec): ?>
    <div class="nav-title"><?=h($sec['title'])?></div>
    <?php foreach($sec['items'] as $it): if(!empty($it['hidden'])) continue; $act = five_s_nav_is_active($it); ?>
      <a class="<?=$act?'active':''?>" href="<?=h($it['href'])?>"<?=!empty($it['blank'])?' target="_blank" rel="noopener"':''?>>
        <?=five_s_icon($it['icon'])?><span><?=h($it['label'])?></span>
        <?php if(($it['badge']??'')==='tag' && $__alerts['tag_open']>0):?><em class="nav-badge"><?=$__alerts['tag_open']?></em><?php endif;?>
        <?php if(($it['badge']??'')==='plan' && $__alerts['plan_wait']>0):?><em class="nav-badge nb-amber"><?=$__alerts['plan_wait']?></em><?php endif;?>
        <?php if(!empty($it['blank'])):?><i class="ext"><?=five_s_icon('external')?></i><?php endif;?>
      </a>
    <?php endforeach; ?>
  <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <div><span class="pulse"></span> ระบบอัตโนมัติทำงาน</div>
    <small>ซิงก์แผนตรวจ: <?=h($__alerts['sync_at']?substr($__alerts['sync_at'],11,5):'—')?> • Backup: <?=h($__alerts['backup_last']?substr($__alerts['backup_last'],0,10):'—')?></small>
  </div>
</aside>

<main class="main"><div class="wrap">
<?php if(empty($hideBreadcrumb)): ?>
<div class="page-context no-print">
  <nav class="crumbs" aria-label="breadcrumb">
    <a href="index.php"><?=five_s_icon('home')?> หน้าหลัก</a>
    <?php if(!empty($__nav['section'])):?><span class="sep">/</span><span><?=h($__nav['section']['title'])?></span><?php endif;?>
    <span class="sep">/</span><b><?=h($__crumbTitle)?></b>
  </nav>
  <?php if(!empty($__nav['section']) && count(array_filter($__nav['section']['items'],fn($i)=>empty($i['hidden'])))>1): ?>
  <div class="sec-tabs">
    <?php foreach($__nav['section']['items'] as $it): if(!empty($it['hidden'])) continue; ?>
      <a class="<?=five_s_nav_is_active($it)?'on':''?>" href="<?=h($it['href'])?>"<?=!empty($it['blank'])?' target="_blank" rel="noopener"':''?>><?=five_s_icon($it['icon'])?><?=h($it['short']??$it['label'])?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
