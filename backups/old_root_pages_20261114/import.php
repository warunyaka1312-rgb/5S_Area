<?php
require_once 'config.php';
$title='นำเข้าผลตรวจ 5 ส.';
require 'includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>นำเข้า<span>ผลตรวจ 5 ส.</span></h1><p id="formTitle">PRODUCTION — 1 ใบ = ผู้ตรวจ 1 คน • อ่านตามช่องจริงของแบบกระดาษ</p></div><div class="hero-right"><strong>V30 REAL</strong><small>SAFE OCR • VERIFY BEFORE SAVE</small></div></div>

<div class="card p-5 mb-5">
 <div class="section-title mb-4">1. ประเภทแบบตรวจ + ข้อมูลเอกสาร</div>
 <div class="grid md:grid-cols-5 gap-4">
  <div><label class="text-sm font-bold">หัวกระดาษ / แบบตรวจ</label><select id="kind" class="w-full border rounded-xl p-3 mt-1"><option value="production">PRODUCTION — แบบตรวจ 5 ส. Production</option><option value="office">OFFICE — แบบตรวจ 5 ส. Office</option></select></div>
  <div><label class="text-sm font-bold">วันที่ตรวจ</label><input id="audit_date" type="date" value="<?=date('Y-m-d')?>" class="w-full border rounded-xl p-3 mt-1"></div>
  <div><label class="text-sm font-bold">แผนก</label><div class="flex gap-2 mt-1"><select id="section" class="w-full border rounded-xl p-3"><option value="">-- เลือกแผนก --</option></select><button type="button" id="loadDeptBtn" class="shrink-0 bg-blue-600 text-white rounded-xl px-4 py-2 font-black">🔽 เลือก</button></div><p class="text-xs text-slate-500 mt-1">เลือก “แผนก” เท่านั้น</p></div>
  <div><label class="text-sm font-bold">ผู้ตรวจ (1 คนต่อ 1 ใบ)</label><input id="auditor" class="w-full border rounded-xl p-3 mt-1" placeholder="ชื่อผู้ตรวจ"></div>
  <div><label class="text-sm font-bold">รอบ / กะ</label><input id="period" class="w-full border rounded-xl p-3 mt-1" placeholder="เช่น 16.00-17.00"></div>
 </div>
</div>

<div class="card p-5 mb-5">
 <div class="section-title mb-2">2. เลือกพื้นที่ / หน่วยจากหัวกระดาษ</div>
 <p class="text-sm text-slate-600 mb-4">เลขที่เขียนด้านบนตารางคือ <b>พื้นที่/หน่วย</b> ส่วนตัวเลขในช่อง 35 แถวคือ <b>คะแนน</b> — ขีด/ช่องที่ไม่มีพื้นที่ตรวจจะไม่ถูกคิดเป็น 0</p>
 <div class="grid grid-cols-2 md:grid-cols-8 gap-3" id="unitInputs"><?php for($i=0;$i<8;$i++):?><div class="unit-wrap" data-i="<?=$i?>"><label class="text-xs font-bold">พื้นที่/หน่วย <?=($i+1)?></label><input class="unit-input w-full border-2 rounded-xl p-3 text-center font-black" data-i="<?=$i?>" placeholder="เช่น 12"></div><?php endfor;?></div>
 <p class="mt-3 text-sm text-slate-600">กรอกเฉพาะพื้นที่/หน่วยที่มีจริงในใบ ไม่จำเป็นต้องครบ 8 ช่อง</p>
</div>

<div class="card p-5 mb-5">
 <div class="section-title mb-3">3. ไฟล์ต้นฉบับ (เลือก 1 ไฟล์)</div>
 <input id="file" type="file" accept=".pdf,.png,.jpg,.jpeg" class="w-full border rounded-xl p-3">
 <div class="mt-3 flex gap-3 flex-wrap"><button id="readBtn" type="button" class="bg-orange-500 text-white rounded-xl px-6 py-3 font-black">🔎 อ่านไฟล์อัตโนมัติ</button><button id="clearBtn" type="button" class="border rounded-xl px-6 py-3 font-bold">ล้างข้อมูล</button><a href="backup.php" class="border rounded-xl px-6 py-3 font-bold">💾 สำรองข้อมูล</a><a href="settings.php" class="border rounded-xl px-6 py-3 font-bold">🎯 เป้าหมาย</a></div>
 <div id="progress" class="hidden mt-4 p-4 rounded-xl bg-blue-50 text-blue-800 font-bold"></div>
 <div class="mt-3 p-3 rounded-xl bg-amber-50 text-amber-800 text-sm">ระบบจะไม่เอา OCR ที่เป็นตัวเลขหลายหลักมาเดาเป็นคะแนน เช่น “1734” จะปล่อยว่างให้ตรวจเอง เพื่อป้องกันคะแนนผิด</div>
</div>

<div class="card overflow-auto">
 <div class="p-5 border-b bg-slate-50 flex justify-between items-center"><div class="section-title">4. ตรวจสอบคะแนนก่อนบันทึก</div><span id="readInfo" class="text-sm text-slate-500">ยังไม่ได้อ่านไฟล์</span></div>
 <table class="min-w-[1200px] w-full" id="scoreTable"><thead class="bg-slate-800 text-white"><tr><th class="p-2">ข้อ</th><th class="p-2 text-left">หัวข้อตรวจ</th><?php for($i=0;$i<8;$i++):?><th class="p-2 unit-head" data-i="<?=$i?>">หน่วย —</th><?php endfor;?></tr></thead><tbody></tbody></table>
 <div class="p-5 bg-slate-50 flex justify-end gap-3"><button id="saveBtn" disabled class="bg-blue-600 disabled:bg-slate-300 text-white rounded-xl px-7 py-3 font-black">💾 ยืนยันบันทึก</button></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
let criteria=[];let extracted=[];let cellMeta=[];const $=id=>document.getElementById(id);
function esc(v){return String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
function units(){return [...document.querySelectorAll('.unit-input')].map(x=>x.value.trim());}
function updateUnitSlots(){const prod=$('kind').value==='production';document.querySelectorAll('.unit-wrap').forEach((e,i)=>{e.style.opacity=(prod&&i>=6)?'.45':'1';e.querySelector('input').disabled=prod&&i>=6;if(prod&&i>=6)e.querySelector('input').value='';});render();}
async function loadDepartments(){const btn=$('loadDeptBtn'),sel=$('section');btn.disabled=true;btn.textContent='กำลังโหลด...';try{const r=await fetch('department_list.php?kind='+encodeURIComponent($('kind').value),{cache:'no-store'});const j=await r.json();if(!j.ok)throw new Error(j.error||'โหลดรายชื่อแผนกไม่สำเร็จ');const cur=sel.value;sel.innerHTML='<option value="">-- เลือกแผนก --</option>';j.departments.forEach(d=>{const o=document.createElement('option');o.value=d;o.textContent=d;sel.appendChild(o)});if(cur&&j.departments.includes(cur))sel.value=cur;}catch(e){alert('ดึงรายชื่อแผนกไม่สำเร็จ: '+e.message)}finally{btn.disabled=false;btn.textContent='🔽 เลือก';}}
async function loadCriteria(){updateUnitSlots();const kind=$('kind').value;const r=await fetch('import_criteria.php?kind='+encodeURIComponent(kind),{cache:'no-store'});criteria=await r.json();extracted=[];cellMeta=[];render();$('formTitle').textContent=(kind==='office'?'OFFICE':'PRODUCTION')+' — 1 ใบ = ผู้ตรวจ 1 คน • คะแนนแยกตามพื้นที่/หน่วย';$('readInfo').textContent='ยังไม่ได้อ่านไฟล์';$('saveBtn').disabled=true;}
$('kind').addEventListener('change',async()=>{await loadCriteria();await loadDepartments();});
$('loadDeptBtn').addEventListener('click',loadDepartments);
window.addEventListener('DOMContentLoaded',async()=>{await loadDepartments();await loadCriteria();});
document.querySelectorAll('.unit-input').forEach(x=>x.addEventListener('input',render));
function render(){
 const u=units();document.querySelectorAll('.unit-head').forEach((e,i)=>e.textContent=u[i]?'หน่วย '+u[i]:'ไม่ใช้');
 const tb=document.querySelector('#scoreTable tbody');tb.innerHTML='';
 criteria.forEach((c,i)=>{const x=extracted[i]||[];let html=`<tr class="border-t"><td class="p-2 text-center font-bold">${c.item_no}</td><td class="p-2 text-sm">${esc(c.description)}</td>`;
 for(let j=0;j<8;j++){const meta=(cellMeta[i]||[])[j]||{};const disabled=!u[j];const val=x[j]??'';html+=`<td class="p-2 align-top"><div class="flex flex-col items-center gap-1"><input type="number" step="0.5" min="0" max="${c.max_score}" class="score-cell border rounded-lg p-2 w-20 text-center font-bold ${meta.conf!==undefined&&meta.conf<85?'border-amber-400 bg-amber-50':''}" data-r="${i}" data-c="${j}" value="${val}" ${disabled?'disabled':''}>${meta.thumb?`<button type="button" class="cell-preview text-[10px] text-blue-700 underline" data-r="${i}" data-c="${j}">ดูช่องที่อ่าน</button>`:''}${meta.raw?`<span class="text-[10px] text-slate-500">OCR: ${esc(meta.raw)}${meta.conf?` • ${Math.round(meta.conf)}%`:''}</span>`:''}</div></td>`;}
 html+='</tr>';tb.insertAdjacentHTML('beforeend',html);});
}
function collect(){return criteria.map((c,i)=>({criterion_id:c.id,scores:Array(8).fill(null),notes:Array(8).fill('')})).map((row,i)=>{document.querySelectorAll(`.score-cell[data-r="${i}"]`).forEach(e=>{const v=e.value.trim();row.scores[+e.dataset.c]=v===''?null:parseFloat(v);});return row;});}
function show(msg){$('progress').classList.remove('hidden');$('progress').textContent=msg;}
async function pdfCanvas(file){if(!window.pdfjsLib)throw new Error('ไม่พบ PDF.js');pdfjsLib.GlobalWorkerOptions.workerSrc='https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';const pdf=await pdfjsLib.getDocument({data:await file.arrayBuffer()}).promise;if(!pdf.numPages)throw new Error('PDF ไม่มีหน้า');const page=await pdf.getPage(1);const vp0=page.getViewport({scale:1});const scale=Math.max(2.5,2400/vp0.width);const vp=page.getViewport({scale});const c=document.createElement('canvas');c.width=Math.ceil(vp.width);c.height=Math.ceil(vp.height);await page.render({canvasContext:c.getContext('2d',{willReadFrequently:true}),viewport:vp}).promise;return c;}
function crop(src,x1,y1,x2,y2){const c=document.createElement('canvas');const X1=Math.max(0,Math.floor(src.width*x1)),Y1=Math.max(0,Math.floor(src.height*y1)),X2=Math.min(src.width,Math.ceil(src.width*x2)),Y2=Math.min(src.height,Math.ceil(src.height*y2));c.width=Math.max(4,X2-X1);c.height=Math.max(4,Y2-Y1);c.getContext('2d').drawImage(src,X1,Y1,c.width,c.height,0,0,c.width,c.height);return c;}
function prep(src,variant=0){const c=document.createElement('canvas');const scale=8;c.width=Math.max(32,Math.round(src.width*scale));c.height=Math.max(32,Math.round(src.height*scale));const ctx=c.getContext('2d',{willReadFrequently:true});ctx.fillStyle='#fff';ctx.fillRect(0,0,c.width,c.height);ctx.drawImage(src,0,0,c.width,c.height);const d=ctx.getImageData(0,0,c.width,c.height);for(let i=0;i<d.data.length;i+=4){const r=d.data[i],g=d.data[i+1],b=d.data[i+2];const blue=(b-r)>22&&b>70;const blue2=(b-r)>12&&b>80;let keep=variant===0?blue:(variant===1?blue2:blue||(r<100&&g<100&&b<100));if(keep){d.data[i]=0;d.data[i+1]=0;d.data[i+2]=0}else{d.data[i]=255;d.data[i+1]=255;d.data[i+2]=255;}}ctx.putImageData(d,0,0);return c;}
function norm(t){t=(t||'').replace(/\s/g,'').replace(/,/g,'.');if(!t||/^[\-—–_]+$/.test(t))return null;if(!/^(0|1|2|3)(\.5)?$/.test(t))return null;return parseFloat(t);}
function geometry(kind,count){
 if(kind==='production'&&count===35)return {refW:1755,refH:1241,rowBounds:[178,208,235,262,286,314,341,373,425,449,477,501,522,549,573,598,623,647,672,698,725,750,777,800,829,855,881,907,929,955,976,1000,1023,1048,1071,1087],xCells:[[1152,1176],[1179,1204],[1207,1228],[1258,1282],[1285,1309],[1312,1336]]};
 return null;
}
function cropActualCell(pageCanvas,r,physicalIndex){const g=geometry($('kind').value,criteria.length);if(!g||r<0||r>=g.rowBounds.length-1||physicalIndex>=g.xCells.length)return null;const box=g.xCells[physicalIndex],y1=g.rowBounds[r],y2=g.rowBounds[r+1],padX=1.2,padY=Math.max(1,(y2-y1)*.10);return crop(pageCanvas,(box[0]+padX)/g.refW,(y1+padY)/g.refH,(box[1]-padX)/g.refW,(y2-padY)/g.refH);}
function addMeta(r,j,cell,best){cellMeta[r]=cellMeta[r]||[];cellMeta[r][j]={thumb:cell.toDataURL('image/jpeg',.78),raw:best?.text||'',conf:best?.conf||0};}
async function readCells(file){
 if($('kind').value==='office')throw new Error('แบบ OFFICE ให้ใช้ตารางด้านล่างกรอก/ตรวจคะแนนด้วยมือก่อนบันทึก เนื่องจากตำแหน่งช่องของแบบ Office ต่างจาก Production');
 const c=await pdfCanvas(file);const active=units().map((v,i)=>({v,i})).filter(x=>x.v).slice(0,6);if(!active.length)throw new Error('กรุณากรอกเลขพื้นที่/หน่วยอย่างน้อย 1 ช่อง');
 const g=geometry('production',criteria.length);if(!g)throw new Error('ไม่พบพิกัดแบบ Production 35 ข้อ');
 show(`กำลังอ่าน Cell จริง ${criteria.length} ข้อ × ${active.length} พื้นที่/หน่วย ...`);
 const worker=await Tesseract.createWorker('eng',1,{logger:m=>{if(m.status)show('OCR: '+m.status+' '+(m.progress?Math.round(m.progress*100)+'%':''));}});
 const out=Array.from({length:criteria.length},()=>Array(8).fill(null));cellMeta=Array.from({length:criteria.length},()=>Array(8).fill(null));
 try{
  for(let r=0;r<criteria.length;r++){
   for(let k=0;k<active.length;k++){
    const j=active[k].i,cell=cropActualCell(c,r,k);let best=null;
    for(const variant of [0,1,2])for(const psm of [10,13,8]){
      const pc=prep(cell,variant);const res=await worker.recognize(pc,{tessedit_char_whitelist:'0123456789.-',psm});
      const raw=(res?.data?.text||'').trim().replace(/\s/g,'');const v=norm(raw);const cf=Number(res?.data?.confidence||0);
      if(v!==null){const quality=(cf)+(raw.length<=3?20:0);if(!best||quality>best.quality)best={value:v,conf:cf,text:raw,quality};}
    }
    const accepted=best&&best.conf>=85&&best.text.length<=3;
    out[r][j]=accepted?best.value:null;addMeta(r,j,cell,best);
   }
   show(`อ่านข้อ ${r+1}/${criteria.length} — สร้างภาพ Cell ให้ตรวจสอบแล้ว`);
  }
 }finally{await worker.terminate();}
 return out;
}
document.addEventListener('click',e=>{const b=e.target.closest('.cell-preview');if(!b)return;const m=cellMeta[+b.dataset.r]?.[+b.dataset.c];if(!m?.thumb)return;const w=window.open('','_blank','width=460,height=500');w.document.write(`<html><body style="font-family:Tahoma;padding:20px"><h3>ภาพ Cell จริง — ข้อ ${criteria[+b.dataset.r].item_no} / หน่วย ${esc(units()[+b.dataset.c])}</h3><img src="${m.thumb}" style="width:360px;border:1px solid #ccc"><p>OCR: <b>${esc(m.raw||'อ่านไม่ได้')}</b> • ความมั่นใจ: ${m.conf?Math.round(m.conf)+'%':'-'}</p><p>กรุณาตรวจภาพจริงก่อนบันทึก</p></body></html>`);});
$('readBtn').onclick=async()=>{try{if($('file').files.length!==1)return alert('กรุณาเลือกไฟล์ 1 ไฟล์');if(!units().some(x=>x))return alert('กรุณากรอกเลขพื้นที่/หน่วยอย่างน้อย 1 ช่อง');$('readBtn').disabled=true;show('กำลังเตรียมอ่านไฟล์...');extracted=await readCells($('file').files[0]);render();let total=units().slice(0,6).filter(Boolean).length*criteria.length,got=0;extracted.forEach((row)=>row.forEach((v,j)=>{if(units()[j]&&v!==null)got++;}));$('readInfo').textContent=`อ่าน ${total} ช่อง — OCR ยืนยันได้ ${got} ช่อง / ต้องตรวจสอบ ${total-got} ช่อง`;$('saveBtn').disabled=false;}catch(e){console.error(e);alert('อ่านไฟล์ไม่สำเร็จ: '+(e.message||e));show('เกิดข้อผิดพลาด: '+(e.message||e));}finally{$('readBtn').disabled=false;}};
$('clearBtn').onclick=()=>{extracted=[];cellMeta=[];render();$('saveBtn').disabled=true;$('readInfo').textContent='ยังไม่ได้อ่านไฟล์';$('progress').classList.add('hidden');};
$('saveBtn').onclick=async()=>{const u=units();const f=$('file').files[0];const payload={kind:$('kind').value,audit_date:$('audit_date').value,section:$('section').value,auditor:$('auditor').value,period:$('period').value,units:u,rows:collect(),filename:f?.name||''};const fd=new FormData();fd.append('payload',JSON.stringify(payload));if(f)fd.append('file',f);$('saveBtn').disabled=true;try{const r=await fetch('import_save.php',{method:'POST',body:fd});const j=await r.json();if(j.ok){alert('บันทึกข้อมูลเรียบร้อยแล้ว');location.href=j.url;}else throw new Error(j.error||'บันทึกไม่สำเร็จ');}catch(e){alert(e.message);$('saveBtn').disabled=false;}};
</script>
<?php require 'includes/footer.php';?>
