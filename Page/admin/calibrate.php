<?php
require_once dirname(__DIR__, 2).'/includes/config.php';
$title='ตรวจสอบพิกัดแบบฟอร์ม';
require FIVE_S_ROOT.'/includes/header.php';
?>
<div class="card p-5 mb-5">
  <div class="text-xl font-black">ตรวจสอบ Template แบบ Production</div>
  <p class="text-sm text-slate-600 mt-2">หน้านี้ใช้ตรวจว่ากรอบ 35 แถว × 6 ช่องคะแนนตรงกับแบบกระดาษหรือไม่ โดยไม่แก้ข้อมูลคะแนน</p>
  <input id="file" type="file" accept=".pdf,.png,.jpg,.jpeg" class="w-full border rounded-xl p-3 mt-3">
  <button id="show" class="mt-3 bg-orange-500 text-white rounded-xl px-6 py-3 font-black">แสดงกรอบ Template</button>
</div>
<div class="card p-4 overflow-auto"><canvas id="cv" style="max-width:100%;height:auto;border:1px solid #ddd"></canvas></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const rowBounds=[178,208,235,262,286,314,341,373,425,449,477,501,522,549,573,598,623,647,672,698,725,750,777,800,829,855,881,907,929,955,976,1000,1023,1048,1071,1087];
const xCells=[[1152,1176],[1179,1204],[1207,1228],[1258,1282],[1285,1309],[1312,1336]];
async function renderFile(f){
  const pdf=await pdfjsLib.getDocument({data:await f.arrayBuffer()}).promise; const p=await pdf.getPage(1);
  const vp0=p.getViewport({scale:1}); const scale=1755/vp0.width; const vp=p.getViewport({scale});
  const c=document.getElementById('cv'); c.width=Math.round(vp.width); c.height=Math.round(vp.height); await p.render({canvasContext:c.getContext('2d'),viewport:vp}).promise;
  const ctx=c.getContext('2d'); ctx.strokeStyle='#ff0000'; ctx.lineWidth=2; ctx.font='bold 11px Arial';
  rowBounds.slice(0,-1).forEach((y,i)=>{ctx.strokeRect(0,y,c.width,rowBounds[i+1]-y);ctx.fillStyle='#d00';ctx.fillText(String(i+1),1085,y+15);});
  xCells.forEach((b,j)=>{ctx.strokeStyle='#00a000';ctx.strokeRect(b[0],rowBounds[0],b[1]-b[0],rowBounds.at(-1)-rowBounds[0]);ctx.fillStyle='#006000';ctx.fillText(String(j+1),b[0]+3,rowBounds[0]-4);});
}
document.getElementById('show').onclick=()=>{const f=document.getElementById('file').files[0];if(f)renderFile(f).catch(e=>alert(e.message));else alert('เลือกไฟล์ก่อน');};
</script>
<?php require FIVE_S_ROOT.'/includes/footer.php'; ?>
