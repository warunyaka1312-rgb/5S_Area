<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$p=db();
$type=$_GET['type']??'Production';
if(!in_array($type,['Production','Office'],true))$type='Production';
$group=trim($_GET['group']??'');
$groupsQ=$p->prepare("SELECT DISTINCT group_name FROM areas WHERE active=1 AND department=? AND TRIM(group_name)<>'' ORDER BY group_name COLLATE NOCASE");
$groupsQ->execute([$type]);
$groups=$groupsQ->fetchAll(PDO::FETCH_COLUMN);
$areas=[];
if($group!==''){
  $q=$p->prepare("SELECT id,unit_no,area_name FROM areas WHERE active=1 AND department=? AND group_name=? ORDER BY CAST(unit_no AS INTEGER),unit_no");
  $q->execute([$type,$group]);
  $areas=$q->fetchAll();
}
$title='พิมพ์ใบตรวจ 5 ส.';
require FIVE_S_ROOT.'/includes/header.php';
?>
<style>
.inspect-wrap{max-width:1500px;margin:auto}
.inspect-hero{background:linear-gradient(135deg,#fff 0%,#fff7f1 52%,#172238 52%,#0b1220 100%);border:1px solid #e5e7eb;border-radius:22px;padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;box-shadow:0 12px 35px rgba(15,23,42,.08)}
.inspect-title{font-size:30px;font-weight:900;color:#172238;margin:0}.inspect-title span{color:#ff5a00}.inspect-sub{color:#64748b;margin-top:6px}
.inspect-badge{background:#fff;border-radius:16px;padding:12px 18px;text-align:center;box-shadow:0 8px 20px rgba(0,0,0,.12);min-width:150px}.inspect-badge strong{display:block;font-size:24px;color:#ff5a00}.inspect-badge small{color:#64748b;font-weight:700}
.type-card{border:2px solid #e5e7eb;border-radius:16px;padding:16px;cursor:pointer;background:#fff;transition:.18s}.type-card:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(15,23,42,.08)}.type-card.active{border-color:#ff5a00;background:#fff7f1;box-shadow:0 8px 20px rgba(255,90,0,.12)}
.area-card{position:relative;border:2px solid #e2e8f0;background:#fff;border-radius:14px;padding:14px 10px;text-align:center;cursor:pointer;min-height:82px;transition:.18s}.area-card:hover{border-color:#ff8a4c;transform:translateY(-1px)}.area-card.selected{border-color:#ff5a00;background:#fff3eb;box-shadow:0 5px 15px rgba(255,90,0,.13)}.area-no{font-size:27px;font-weight:900;color:#172238;line-height:1}.area-name{font-size:11px;color:#64748b;margin-top:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.area-check{position:absolute;right:7px;top:7px;width:18px;height:18px;border-radius:50%;background:#e2e8f0;color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center}.selected .area-check{background:#ff5a00}
@media(max-width:900px){.inspect-hero{background:#fff;align-items:flex-start}.inspect-badge{display:none}}
</style>
<div class="inspect-wrap">
  <div class="inspect-hero mb-5">
    <div><h1 class="inspect-title">📝 ใบตรวจ <span>5 ส.</span></h1><div class="inspect-sub">เลือกประเภท → เลือกแผนก → ระบบดึงเลข <b>หน่วยพื้นที่</b> ของแผนกนั้นขึ้นมาให้เอง</div></div>
    <div class="inspect-badge"><strong><?=h($type)?></strong><small><?=count($groups)?> แผนก</small></div>
  </div>

  <div class="card p-5 mb-5">
    <div class="flex items-center justify-between gap-3 mb-4"><div><div class="section-title">1. เลือกประเภทแบบตรวจ</div><div class="text-sm text-slate-500">Production และ Office แยกข้อมูลพื้นที่ออกจากกัน</div></div></div>
    <div class="grid sm:grid-cols-2 gap-4">
      <a href="Page/public/inspection_form.php?type=Production" class="type-card <?=$type==='Production'?'active':''?> no-underline text-inherit"><div class="text-3xl mb-2">🏭</div><div class="font-black text-lg">Production</div><div class="text-sm text-slate-500">เลือกเฉพาะพื้นที่ Production</div></a>
      <a href="Page/public/inspection_form.php?type=Office" class="type-card <?=$type==='Office'?'active':''?> no-underline text-inherit"><div class="text-3xl mb-2">🏢</div><div class="font-black text-lg">Office</div><div class="text-sm text-slate-500">เลือกเฉพาะพื้นที่ Office</div></a>
    </div>
  </div>

  <div class="card p-5 mb-5 border-2 border-dashed border-blue-300 bg-blue-50">
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div><div class="section-title text-blue-900">📄 ฟอร์มเปล่าสำหรับเขียนหน่วยเอง</div><div class="text-sm text-slate-600 mt-1">ไม่ต้องเลือกแผนกหรือหน่วย ระบบจะพิมพ์หัวข้อมาตรฐาน <?=h($type)?> พร้อมช่องพื้นที่/หน่วยว่าง 4 ช่อง ให้เขียนข้อมูลเอง</div></div>
      <button type="button" id="printBlankStandalone" class="bg-blue-700 hover:bg-blue-800 text-white rounded-xl px-6 py-3 font-black shadow-lg">🖨 พิมพ์ฟอร์มเปล่า 4 หน่วย</button>
    </div>
  </div>

  <div class="card p-5 mb-5">
    <div class="section-title mb-1">2. เลือกแผนก / Section</div><div class="text-sm text-slate-500 mb-4">เมื่อเลือกแผนกแล้ว ระบบจะดึง <b>เลขหน่วยพื้นที่</b> ของแผนกนั้นขึ้นให้อัตโนมัติ โดยแยก Production / Office</div>
    <select id="group" class="w-full border-2 border-slate-200 rounded-xl p-3.5 text-base font-bold focus:border-orange-500">
      <option value="">-- เลือกแผนก / Section --</option>
      <?php foreach($groups as $g): ?><option value="<?=h($g)?>" <?=$group===$g?'selected':''?>><?=h($g)?></option><?php endforeach; ?>
    </select>
  </div>

  <?php if($group!==''): ?>
  <div class="card p-5 mb-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
      <div><div class="section-title">3. หน่วยพื้นที่ 5 ส. ของ <?=h($group)?></div><div class="text-sm text-slate-500">ระบบดึงเลขหน่วยจากฐานข้อมูล <?=h($type)?> อัตโนมัติ — แผนกนี้มี <b><?=count($areas)?> หน่วย</b></div></div>
      <div class="flex gap-2"><button type="button" id="selectAll" class="px-3 py-2 rounded-lg bg-slate-100 font-bold">เลือกทั้งหมด</button><button type="button" id="clearAll" class="px-3 py-2 rounded-lg bg-slate-100 font-bold">ล้าง</button></div>
    </div>
    <?php if($areas): ?>
      <div id="areaGrid" class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-3">
      <?php foreach($areas as $a): ?><button type="button" class="area-card selected" data-id="<?=$a['id']?>"><span class="area-check">✓</span><div class="area-no"><?=h($a['unit_no'])?></div><div class="area-name" title="<?=h($a['area_name'])?>"><?=h($a['area_name'])?></div></button><?php endforeach; ?>
      </div>
      <div class="mt-5 flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200"><div class="font-bold">หน่วยพื้นที่ของแผนกนี้: <span class="text-orange-600"><?php foreach($areas as $i=>$a): ?><?=($i?', ':'').h($a['unit_no'])?><?php endforeach; ?></span> <span class="text-slate-500">(<?=count($areas)?> หน่วย)</span></div><button type="button" id="printBtn" class="bg-orange-500 hover:bg-orange-600 text-white rounded-xl px-6 py-3 font-black shadow-lg">🖨 พิมพ์ใบตรวจของแผนกนี้</button><button type="button" id="printBlankBtn" class="bg-blue-700 hover:bg-blue-800 text-white rounded-xl px-6 py-3 font-black shadow-lg">📄 พิมพ์ฟอร์มเปล่า 4 หน่วย</button></div>
    <?php else: ?><div class="p-5 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 font-bold">ยังไม่มีข้อมูลพื้นที่ของแผนกนี้ในประเภท <?=h($type)?></div><?php endif; ?>
  </div>
  <?php else: ?>
  <div class="card p-8 text-center"><div class="text-5xl mb-3">📍</div><div class="font-black text-xl text-slate-700">เลือกแผนกก่อน</div><div class="text-slate-500 mt-1">ระบบจะแสดงเลขหน่วยพื้นที่ของแผนกนั้นให้เอง</div></div>
  <?php endif; ?>
</div>
<script>
const type='<?=h($type)?>';
document.getElementById('printBlankStandalone')?.addEventListener('click',()=>{window.open('Page/public/inspection_form_print.php?type='+encodeURIComponent(type)+'&blank_units=4','_blank');});
const groupEl=document.getElementById('group');
if(groupEl) groupEl.addEventListener('change',()=>{const g=groupEl.value; location.href='Page/public/inspection_form.php?type='+encodeURIComponent(type)+(g?'&group='+encodeURIComponent(g):'');});
const cards=[...document.querySelectorAll('.area-card')],countEl=document.getElementById('selectedCount');
function updateCount(){if(countEl)countEl.textContent=cards.filter(x=>x.classList.contains('selected')).length;}
cards.forEach(c=>c.addEventListener('click',()=>{c.classList.toggle('selected');c.querySelector('.area-check').textContent=c.classList.contains('selected')?'✓':'';updateCount();}));
document.getElementById('selectAll')?.addEventListener('click',()=>{cards.forEach(c=>{c.classList.add('selected');c.querySelector('.area-check').textContent='✓'});updateCount()});
document.getElementById('clearAll')?.addEventListener('click',()=>{cards.forEach(c=>{c.classList.remove('selected');c.querySelector('.area-check').textContent=''});updateCount()});
document.getElementById('printBtn')?.addEventListener('click',()=>{const ids=cards.filter(c=>c.classList.contains('selected')).map(c=>c.dataset.id);if(!ids.length){alert('กรุณาเลือกอย่างน้อย 1 หน่วยพื้นที่');return;}const groupName=<?=json_encode($group, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;const u='Page/public/inspection_form_print.php?type='+encodeURIComponent(type)+'&group='+encodeURIComponent(groupName)+'&area_ids='+encodeURIComponent(ids.join(','));window.open(u,'_blank');});
document.getElementById('printBlankBtn')?.addEventListener('click',()=>{const groupName=<?=json_encode($group, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;const u='Page/public/inspection_form_print.php?type='+encodeURIComponent(type)+'&group='+encodeURIComponent(groupName)+'&blank_units=4';window.open(u,'_blank');});
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
