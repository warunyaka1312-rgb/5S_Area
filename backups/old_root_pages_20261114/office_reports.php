<?php
require_once 'config.php';
$title='กราฟ / Ranking Office';
$p=db();
$year=(int)($_GET['year']??date('Y'));
$target=target_pct('Office');
$rows=[];
$areas=$p->query("SELECT id,area_name,group_name,unit_no FROM areas WHERE active=1 AND department='Office' ORDER BY CAST(unit_no AS INTEGER),group_name")->fetchAll();
foreach($areas as $a){
  $q=$p->prepare("SELECT au.id audit_id,s.score1,s.score2,s.score3,c.max_score
                  FROM audits au JOIN office_scores s ON s.audit_id=au.id
                  JOIN office_criteria c ON c.id=s.criterion_id
                  WHERE au.area_id=? AND au.department='Office' AND strftime('%Y',au.audit_date)=?");
  $q->execute([$a['id'],sprintf('%04d',$year)]);
  $sum=0;$max=0;$auditIds=[];
  foreach($q as $r){
    $v=score_avg($r['score1'],$r['score2'],$r['score3']);
    if($v===null) continue;
    $sum+=(float)$v; $max+=(float)$r['max_score']; $auditIds[(int)$r['audit_id']]=1;
  }
  if($max>0) $rows[]=['name'=>$a['group_name'].' / หน่วย '.$a['unit_no'],'pct'=>round($sum/$max*100,2),'audits'=>count($auditIds)];
}
usort($rows,fn($a,$b)=>$b['pct']<=>$a['pct']);
$cat=[];
/* Office criteria ในฐานข้อมูลรุ่นนี้ไม่มีคอลัมน์ category
   จึงสรุปกราฟด้านขวาเป็นคะแนนรายข้อแทน เพื่อไม่ให้รายงานล่ม */
$q=$p->prepare("SELECT cr.item_no, s.score1,s.score2,s.score3,cr.max_score
                FROM audits au
                JOIN office_scores s ON s.audit_id=au.id
                JOIN office_criteria cr ON cr.id=s.criterion_id
                WHERE au.department='Office' AND strftime('%Y',au.audit_date)=?
                ORDER BY cr.item_no");
$q->execute([sprintf('%04d',$year)]);
$byItem=[];
foreach($q as $r){
  $v=score_avg($r['score1'],$r['score2'],$r['score3']);
  if($v===null) continue;
  $k=(int)$r['item_no'];
  if(!isset($byItem[$k])) $byItem[$k]=['sum'=>0.0,'max'=>0.0];
  $byItem[$k]['sum']+=(float)$v;
  $byItem[$k]['max']+=(float)$r['max_score'];
}
foreach($byItem as $item=>$v){
  $cat[]=['cat'=>'ข้อ '.$item,'pct'=>$v['max']>0?round($v['sum']/$v['max']*100,2):0];
}
require 'includes/header.php';
?>
<div class="hero"><div class="hero-copy"><h1>กราฟ / <span>Ranking Office</span></h1><p>คะแนน Office จากข้อมูลที่บันทึกจริง • N/A ไม่ถูกนำมาคิด</p></div><div class="hero-right"><strong><?=number_format($target,1)?>%</strong><small>เป้าหมาย Office</small></div></div>
<div class="card p-5 mb-5 no-print"><form class="flex flex-wrap gap-3 items-end"><div><label class="text-xs font-bold">ปี</label><input name="year" type="number" value="<?=$year?>" class="border rounded-xl p-3 w-32"></div><button class="bg-blue-600 text-white rounded-xl px-5 py-3 font-bold">🔎 แสดงข้อมูล</button><button type="button" onclick="window.print()" class="border rounded-xl px-5 py-3 font-bold">🖨 พิมพ์รายงาน A4 / PDF</button><a target="_blank" href="graph_print.php?department=Office&view=year&year=<?=$year?>&chart=split&size=A4" class="border border-orange-300 text-orange-700 rounded-xl px-5 py-3 font-bold">📊 กราฟ A4</a><a target="_blank" href="graph_print.php?department=Office&view=year&year=<?=$year?>&chart=split&size=A3" class="border border-orange-300 text-orange-700 rounded-xl px-5 py-3 font-bold">📊 กราฟ A3</a><button type="button" onclick="copyChart()" class="border border-green-500 text-green-700 rounded-xl px-5 py-3 font-bold">📋 คัดลอกรูปส่ง LINE</button></form></div>
<div class="grid xl:grid-cols-2 gap-5">
 <div class="card p-5"><h2 class="font-bold mb-3">🏢 คะแนนรายพื้นที่ Office <?=$year?></h2><div style="height:420px;position:relative"><canvas id="rank"></canvas></div></div>
 <div class="card p-5"><h2 class="font-bold mb-3">📊 คะแนนรายข้อ Office <?=$year?></h2><div style="height:420px;position:relative"><canvas id="cat"></canvas></div></div>
</div>
<div class="card p-5 mt-5"><h2 class="font-bold text-lg mb-3">ตารางสรุป Office — เป้าหมาย <?=number_format($target,1)?>%</h2><div class="overflow-x-auto"><table class="w-full border-collapse"><thead><tr class="bg-slate-100"><th class="p-3 border">อันดับ</th><th class="p-3 border text-left">พื้นที่</th><th class="p-3 border">ครั้งตรวจ</th><th class="p-3 border">คะแนน %</th><th class="p-3 border">สถานะ</th></tr></thead><tbody><?php foreach($rows as $i=>$r):?><tr><td class="p-3 border text-center"><?=$i+1?></td><td class="p-3 border"><?=h($r['name'])?></td><td class="p-3 border text-center"><?=$r['audits']?></td><td class="p-3 border text-center font-bold"><?=number_format($r['pct'],2)?>%</td><td class="p-3 border text-center"><?=h(status_text((float)$r['pct'],'Office'))?></td></tr><?php endforeach; if(!$rows):?><tr><td colspan="5" class="p-6 text-center text-slate-500">ยังไม่มีคะแนน Office ในปี <?=$year?></td></tr><?php endif;?></tbody></table></div></div>
<script>
const ROWS=<?=json_encode($rows,JSON_UNESCAPED_UNICODE)?>, CAT=<?=json_encode($cat,JSON_UNESCAPED_UNICODE)?>, TARGET=<?=json_encode($target)?>;
const rankChart=new Chart(document.getElementById('rank'),{type:'bar',data:{labels:ROWS.map(x=>x.name),datasets:[{label:'คะแนนเฉลี่ย %',data:ROWS.map(x=>Number(x.pct)),borderRadius:6},{label:'เป้าหมาย '+TARGET+'%',type:'line',data:ROWS.map(()=>TARGET),borderColor:'#ef4444',borderDash:[6,4],pointRadius:0}]},options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',scales:{x:{min:0,max:100,ticks:{callback:v=>v+'%'}},y:{ticks:{font:{size:10}}}},plugins:{legend:{position:'top'}}}});
new Chart(document.getElementById('cat'),{type:'bar',data:{labels:CAT.map(x=>x.cat),datasets:[{label:'คะแนน %',data:CAT.map(x=>Number(x.pct)),borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,scales:{y:{min:0,max:100,ticks:{callback:v=>v+'%'}}},plugins:{legend:{display:false}}}});
function canvasWithHeading(source,title,subtitle=''){
  if(!source || !source.width || !source.height) throw new Error('ไม่พบภาพกราฟ');
  const out=document.createElement('canvas');
  const w=source.width, pad=Math.max(18,Math.round(w*0.024));
  const titleSize=Math.max(19,Math.min(34,Math.round(w/34)));
  const subSize=Math.max(12,Math.min(21,Math.round(w/58)));
  const measure=document.createElement('canvas').getContext('2d');
  const maxW=w-pad*2;
  const wrap=(text,font)=>{
    measure.font=font; const lines=[]; let line='';
    String(text||'').split(/\s+/).forEach(word=>{
      const candidate=line?line+' '+word:word;
      if(line && measure.measureText(candidate).width>maxW){lines.push(line);line=word;}else{line=candidate;}
    });
    if(line)lines.push(line); return lines;
  };
  const titleLines=wrap(title,'900 '+titleSize+'px Tahoma, Arial, sans-serif');
  const subLines=subtitle?wrap(subtitle,'600 '+subSize+'px Tahoma, Arial, sans-serif'):[];
  const titleLineH=Math.round(titleSize*1.25), subLineH=Math.round(subSize*1.35);
  const headH=Math.max(82,18+titleLines.length*titleLineH+(subLines.length?8+subLines.length*subLineH:0)+20);
  out.width=w;out.height=source.height+headH;
  const ctx=out.getContext('2d');
  ctx.fillStyle='#ffffff';ctx.fillRect(0,0,out.width,out.height);
  ctx.fillStyle='#ff5a00';ctx.fillRect(0,0,out.width,9);
  ctx.fillStyle='#13213a';ctx.textBaseline='top';ctx.font='900 '+titleSize+'px Tahoma, Arial, sans-serif';
  let y=17;titleLines.forEach(line=>{ctx.fillText(line,pad,y,maxW);y+=titleLineH;});
  if(subLines.length){y+=4;ctx.fillStyle='#64748b';ctx.font='600 '+subSize+'px Tahoma, Arial, sans-serif';subLines.forEach(line=>{ctx.fillText(line,pad,y,maxW);y+=subLineH;});}
  ctx.strokeStyle='#dbe3ee';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(pad,headH-4);ctx.lineTo(w-pad,headH-4);ctx.stroke();
  ctx.drawImage(source,0,headH);
  return out;
}

function downloadChart(){try{const title=document.getElementById('rank').closest('.card').querySelector('h2').textContent.trim();const image=canvasWithHeading(rankChart.canvas,title,'5S SMART | ALUMET • Office <?=$year?>');const a=document.createElement('a');a.href=image.toDataURL('image/png',1);a.download='5S_Office_Ranking_<?=$year?>_พร้อมหัวข้อ.png';document.body.appendChild(a);a.click();a.remove();}catch(e){alert('ไม่สามารถบันทึกกราฟพร้อมหัวข้อได้: '+e.message);}}
function copyChart(){FiveSCopy.copyFrom(()=>{const title=document.getElementById('rank').closest('.card').querySelector('h2').textContent.trim();return canvasWithHeading(rankChart.canvas,title,'5S SMART | ALUMET • Office <?=$year?>');},'5S_Office_Ranking_<?=$year?>.png');}
</script>
<?php require 'includes/footer.php';?>
