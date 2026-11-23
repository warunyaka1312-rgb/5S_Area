<?php
require_once 'config.php';
$title='ภาพรวมคะแนนทุกกลุ่ม — Production + Office';
$p=db();
$months=[1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
$latestDate=(string)$p->query("SELECT MAX(audit_date) FROM audits WHERE department IN ('Production','Office')")->fetchColumn();
$defaultYear=$latestDate!==''?(int)substr($latestDate,0,4):(int)date('Y');
$defaultMonth=$latestDate!==''?(int)substr($latestDate,5,2):(int)date('n');
$month=(int)($_GET['month']??$defaultMonth);
$yearInput=(int)($_GET['year']??$defaultYear);
$year=$yearInput>=2400?$yearInput-543:$yearInput;
if($month<1||$month>12)$month=$defaultMonth;
if($year<2000||$year>2200)$year=$defaultYear;
$monthName=$months[$month]??$months[$defaultMonth];
$start=sprintf('%04d-%02d-01',$year,$month);
$end=date('Y-m-t',strtotime($start));
$prodTarget=target_pct('Production');
$officeTarget=target_pct('Office');

/** Return all active areas for a department, including those not yet scored in the selected period. */
function overview_group_rows(PDO $p,string $dept,string $start,string $end):array{
    $scoreTable=$dept==='Office'?'office_scores':'scores';
    $critTable=$dept==='Office'?'office_criteria':'criteria';
    $q=$p->prepare("SELECT a.id AS area_id,a.group_name,a.unit_no,a.area_name,
                           au.id AS audit_id,s.score1,s.score2,s.score3,c.max_score
                    FROM areas a
                    LEFT JOIN audits au ON au.area_id=a.id AND au.department=? AND au.audit_date BETWEEN ? AND ?
                    LEFT JOIN {$scoreTable} s ON s.audit_id=au.id
                    LEFT JOIN {$critTable} c ON c.id=s.criterion_id
                    WHERE a.active=1 AND a.department=?
                    ORDER BY a.group_name COLLATE NOCASE,CAST(a.unit_no AS INTEGER),a.unit_no");
    $q->execute([$dept,$start,$end,$dept]);
    $acc=[];
    foreach($q as $r){
        $id=(int)$r['area_id'];
        if(!isset($acc[$id])){
            $group=display_area_group((string)($r['group_name']??''));
            $unit=trim((string)($r['unit_no']??''));
            $name=trim((string)($r['area_name']??''));
            if($name==='')$name=$group.($unit!==''?' / หน่วย '.$unit:'');
            $acc[$id]=['area_id'=>$id,'department'=>$dept,'group'=>$group,'unit'=>$unit,'name'=>$name,
                       'label'=>$dept.' • '.$name,'score'=>0.0,'max'=>0.0,'audits'=>[],'target'=>target_pct($dept)];
        }
        if($r['audit_id']!==null){
            $v=score_avg($r['score1'],$r['score2'],$r['score3']);
            if($v!==null && $r['max_score']!==null){
                $acc[$id]['score']+=(float)$v;
                $acc[$id]['max']+=(float)$r['max_score'];
                $acc[$id]['audits'][(int)$r['audit_id']]=1;
            }
        }
    }
    $rows=[];
    foreach($acc as $r){
        $r['avg']=$r['max']>0?round($r['score']/$r['max']*100,2):null;
        $r['audit_count']=count($r['audits']);
        unset($r['audits']);
        $rows[]=$r;
    }
    // Put scored units from low to high first for action, then show every unscored active unit.
    usort($rows,function($a,$b){
        if($a['avg']===null && $b['avg']!==null)return 1;
        if($a['avg']!==null && $b['avg']===null)return -1;
        if($a['avg']!==null && $b['avg']!==null && $a['avg']!=$b['avg'])return $a['avg']<=>$b['avg'];
        $g=strnatcasecmp((string)$a['group'],(string)$b['group']);
        return $g!==0?$g:strnatcasecmp((string)$a['unit'],(string)$b['unit']);
    });
    return $rows;
}
$prodRows=overview_group_rows($p,'Production',$start,$end);
$officeRows=overview_group_rows($p,'Office',$start,$end);
$allRows=array_merge($prodRows,$officeRows);
$totalUnits=count($allRows);
$prodScored=count(array_filter($prodRows,fn($r)=>$r['avg']!==null));
$officeScored=count(array_filter($officeRows,fn($r)=>$r['avg']!==null));
$scored=$prodScored+$officeScored;
$missing=$totalUnits-$scored;
$prodTotalScore=array_sum(array_column(array_filter($prodRows,fn($r)=>$r['avg']!==null),'score'));
$prodTotalMax=array_sum(array_column(array_filter($prodRows,fn($r)=>$r['avg']!==null),'max'));
$officeTotalScore=array_sum(array_column(array_filter($officeRows,fn($r)=>$r['avg']!==null),'score'));
$officeTotalMax=array_sum(array_column(array_filter($officeRows,fn($r)=>$r['avg']!==null),'max'));
$prodAvg=$prodTotalMax>0?$prodTotalScore/$prodTotalMax*100:null;
$officeAvg=$officeTotalMax>0?$officeTotalScore/$officeTotalMax*100:null;
$labels=array_column($allRows,'label');
$values=array_map(fn($r)=>$r['avg'],$allRows);
$targets=array_column($allRows,'target');
$departments=array_column($allRows,'department');
$chartRows=array_map(fn($r)=>['label'=>$r['label'],'name'=>$r['name'],'department'=>$r['department'],'avg'=>$r['avg'],'target'=>$r['target']],$allRows);
require 'includes/header.php';
?>
<?php
$ovDept=[
 'Production'=>['rows'=>$prodRows,'avg'=>$prodAvg,'target'=>$prodTarget,'scored'=>$prodScored],
 'Office'=>['rows'=>$officeRows,'avg'=>$officeAvg,'target'=>$officeTarget,'scored'=>$officeScored],
];
foreach($ovDept as $k=>&$d){
  $d['total']=count($d['rows']);
  $d['pass']=count(array_filter($d['rows'],fn($r)=>$r['avg']!==null && $r['avg']>=$r['target']));
  $d['below']=count(array_filter($d['rows'],fn($r)=>$r['avg']!==null && $r['avg']<$r['target']));
  $d['missingRows']=array_values(array_filter($d['rows'],fn($r)=>$r['avg']===null));
  $d['chart']=array_map(fn($r)=>['name'=>str_replace(' / ',' • ',(string)$r['name']),'avg'=>$r['avg'],'target'=>$r['target']],$d['rows']);
} unset($d);
$ovBelow=$ovDept['Production']['below']+$ovDept['Office']['below'];
$ovCoverage=$totalUnits>0?round($scored/$totalUnits*100,1):0;
?>
<style>
.ov{--p:#F26A21;--o:#2563EB;--ok:#16A34A;--bad:#DC2626;--navy:#0E1A2F;--ink:#0F172A;--muted:#64748B;--line:#E4E8EF}
.ov-bar{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;background:#fff;border:1px solid var(--line);border-radius:16px;padding:12px 14px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05);margin-bottom:16px}
.ov-bar form{display:flex;align-items:flex-end;gap:8px;flex-wrap:wrap}
.fld{display:flex;flex-direction:column;gap:2px}.fld label{font-size:11px;font-weight:600;color:var(--muted);padding-left:2px}
.fld select,.fld input{height:40px;border:1px solid #D5DBE5;border-radius:10px;padding:0 12px;background:#fff;font-weight:600;font-size:14px;color:var(--ink);min-width:130px}.fld input{width:100px;min-width:0}
.btn-x{display:inline-flex;align-items:center;gap:7px;height:40px;padding:0 16px;border-radius:10px;font-weight:600;font-size:14px;text-decoration:none;cursor:pointer;border:1px solid transparent;white-space:nowrap}
.btn-x .ico{width:16px;height:16px}
.btn-x.primary{background:var(--p);color:#fff}.btn-x.primary:hover{background:#DB5512}
.btn-x.ghost{background:#fff;border-color:#D5DBE5;color:#1E293B}.btn-x.ghost:hover{border-color:var(--p);color:var(--p)}
.ov-actions{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}
.ov-period{display:flex;flex-direction:column;justify-content:center;padding:0 6px}
.ov-period small{font-size:11px;color:var(--muted);font-weight:600}.ov-period b{font-size:18px;color:var(--navy)}
.dept-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.dept{position:relative;border-radius:18px;padding:20px 22px;color:#fff;overflow:hidden;display:grid;grid-template-columns:auto 1fr;gap:22px;align-items:center;box-shadow:0 10px 24px rgba(15,23,42,.10)}
.dept.prod{background:linear-gradient(120deg,#E8540F 0%,#F26A21 45%,#F7A21B 100%)}
.dept.off{background:linear-gradient(120deg,#1E3A8A 0%,#2563EB 55%,#3B82F6 100%)}
.dept::after{content:"";position:absolute;right:-50px;top:-50px;width:220px;height:220px;border-radius:50%;background:rgba(255,255,255,.12)}
.ring{--v:0;width:120px;height:120px;border-radius:50%;background:conic-gradient(#fff calc(var(--v)*1%),rgba(255,255,255,.28) 0);display:flex;align-items:center;justify-content:center;position:relative;z-index:1;box-shadow:0 6px 18px rgba(0,0,0,.15)}
.ring>div{width:98px;height:98px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center}
.ring b{font:800 25px/1 "Inter",sans-serif;letter-spacing:-.5px}.dept.prod .ring b{color:#C2410C}.dept.off .ring b{color:#1D4ED8}.ring small{font-size:11.5px;color:#64748B;font-weight:600;margin-top:4px}
.dept-info{position:relative;z-index:1}
.dept-info .k{font-size:13px;font-weight:600;letter-spacing:1.5px;text-transform:uppercase;opacity:.85}
.dept-info h3{margin:2px 0 10px;font-size:24px;font-weight:700}
.dept-stats{display:flex;gap:8px;flex-wrap:wrap}
.dept-stats span{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);border-radius:10px;padding:6px 12px;font-size:13.5px;font-weight:500}
.dept-stats span b{font:800 17px "Inter",sans-serif;margin-right:3px}
.dept-status{display:inline-flex;align-items:center;gap:6px;background:#fff;border-radius:999px;padding:4px 12px;font-size:13px;font-weight:700;margin-left:8px;vertical-align:middle}
.dept.prod .dept-status{color:#C2410C}.dept.off .dept-status{color:#1D4ED8}
.ov-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.ov-kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;display:flex;gap:14px;align-items:center;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.04)}
.ov-kpi .ib{width:48px;height:48px;border-radius:13px;display:flex;align-items:center;justify-content:center;flex:0 0 auto;background:var(--bgc);color:var(--fc)}.ov-kpi .ib .ico{width:24px;height:24px}
.ov-kpi .lbl{font-size:13px;color:var(--muted);font-weight:600}
.ov-kpi .val{font:800 30px/1.1 "Inter",sans-serif;color:var(--ink);margin-top:2px}.ov-kpi .val small{font-size:16px;color:var(--muted)}
.ov-kpi .sub{font-size:12px;color:var(--muted)}
.ov-kpi .mbar{height:6px;border-radius:9px;background:#EEF1F5;margin-top:6px;overflow:hidden;width:150px;max-width:100%}.ov-kpi .mbar i{display:block;height:100%;background:var(--fc)}
.ov-charts{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start}
.ov-card{background:#fff;border:1px solid var(--line);border-radius:18px;padding:20px 22px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 18px rgba(15,23,42,.05)}
.ov-card h2{margin:0;font-size:19px;font-weight:700;color:var(--navy);display:flex;align-items:center;gap:10px}
.ov-card p.sub{margin:4px 0 10px;font-size:13px;color:var(--muted)}
.badge{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.4px}
.badge.p{background:#FFF1E7;color:#C2410C}.badge.o{background:#EAF1FF;color:#1D4ED8}
.legend{display:flex;gap:14px;flex-wrap:wrap;font-size:13px;color:#475569;margin-bottom:6px}
.legend span{display:inline-flex;align-items:center;gap:6px}.legend i{width:12px;height:12px;border-radius:3px;display:inline-block}
.ov-chart{position:relative}
.ov-missing{margin-top:16px;background:#FFF8EE;border:1px solid #F8D9A6;border-radius:16px;padding:16px 18px}
.ov-missing h3{margin:0 0 10px;font-size:16px;font-weight:700;color:#92400E;display:flex;align-items:center;gap:8px}
.ov-missing .grp{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:6px}
.ov-missing .grp b{font-size:13.5px;color:#7C2D12;margin-right:4px}
.ov-missing .grp span{background:#fff;border:1px solid #F3D19B;border-radius:999px;padding:4px 11px;font-size:13.5px;font-weight:600;color:#7C2D12}
.ov-note{margin-top:14px;font-size:12.5px;color:var(--muted);line-height:1.6}
@media(max-width:1280px){.ov-charts{grid-template-columns:1fr}}
@media(max-width:900px){.dept-grid{grid-template-columns:1fr}.ov-kpis{grid-template-columns:1fr 1fr}.ov-actions{margin-left:0}}
@media(max-width:560px){.dept{grid-template-columns:1fr}.ring{margin:auto}.ov-kpis{grid-template-columns:1fr}.ov-kpi{min-width:0}}
@media print{.ov-bar{display:none!important}.ov-card,.ov-kpi,.dept{box-shadow:none!important;break-inside:avoid}body{-webkit-print-color-adjust:exact;print-color-adjust:exact}.ov-charts{grid-template-columns:1fr 1fr}}
</style>
<div class="ov">
  <div class="ov-bar no-print">
    <a class="btn-x ghost" href="index.php"><?=five_s_icon('arrow-left')?> Dashboard</a>
    <form method="get">
      <div class="fld"><label>เดือน</label><select name="month"><?php foreach($months as $n=>$th): ?><option value="<?=$n?>" <?=$month===$n?'selected':''?>><?=$th?></option><?php endforeach; ?></select></div>
      <div class="fld"><label>ปี (ค.ศ.)</label><input type="number" name="year" min="2000" max="2200" value="<?=$year?>"></div>
      <button class="btn-x primary" type="submit"><?=five_s_icon('search')?> แสดงภาพรวม</button>
    </form>
    <div class="ov-period"><small>ช่วงข้อมูล</small><b><?=h($monthName)?> <?=$year+543?></b></div>
    <div class="ov-actions">
      <button class="btn-x ghost" type="button" onclick="downloadOverview()"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button>
      <button class="btn-x ghost" type="button" onclick="window.print()"><?=five_s_icon('printer')?> พิมพ์ / PDF</button>
    </div>
  </div>

  <div class="dept-grid">
  <?php foreach($ovDept as $k=>$d): $ok=$d['avg']!==null && $d['avg']>=$d['target']; ?>
    <div class="dept <?=$k==='Production'?'prod':'off'?>">
      <div class="ring" style="--v:<?=max(0,min(100,(float)($d['avg']??0)))?>"><div><b><?=$d['avg']===null?'—':number_format($d['avg'],1).'%'?></b><small>คะแนนเฉลี่ย</small></div></div>
      <div class="dept-info">
        <div class="k"><?=$k==='Production'?'Production • ฝ่ายผลิต':'Office • สำนักงาน'?></div>
        <h3><?=h($monthName)?> <?=$year+543?> <span class="dept-status"><?=$d['avg']===null?'ยังไม่มีข้อมูล':($ok?'✓ ผ่านเป้าหมาย':'! ต่ำกว่าเป้าหมาย')?></span></h3>
        <div class="dept-stats"><span><b><?=number_format($d['target'],0)?>%</b>เป้าหมาย</span><span><b><?=$d['scored']?>/<?=$d['total']?></b>หน่วยที่ตรวจ</span><span><b><?=$d['pass']?></b>ผ่าน</span><span><b><?=$d['below']?></b>ต่ำกว่าเป้า</span></div>
      </div>
    </div>
  <?php endforeach; ?>
  </div>

  <div class="ov-kpis">
    <div class="ov-kpi" style="--bgc:#F1F5F9;--fc:#334155"><div class="ib"><?=five_s_icon('building')?></div><div><div class="lbl">หน่วยทั้งหมดใน Master</div><div class="val"><?=$totalUnits?></div><div class="sub">Production <?=count($prodRows)?> • Office <?=count($officeRows)?></div></div></div>
    <div class="ov-kpi" style="--bgc:#E0F2FE;--fc:#0284C7"><div class="ib"><?=five_s_icon('check')?></div><div><div class="lbl">ตรวจแล้วในเดือนนี้</div><div class="val"><?=$scored?><small> / <?=$totalUnits?></small></div><div class="mbar"><i style="width:<?=$ovCoverage?>%"></i></div><div class="sub">ครอบคลุม <?=number_format($ovCoverage,1)?>%</div></div></div>
    <div class="ov-kpi" style="--bgc:#FDEDED;--fc:#DC2626"><div class="ib"><?=five_s_icon('bolt')?></div><div><div class="lbl">ต่ำกว่าเป้าหมาย</div><div class="val" style="color:#DC2626"><?=$ovBelow?></div><div class="sub">Production <?=$ovDept['Production']['below']?> • Office <?=$ovDept['Office']['below']?></div></div></div>
    <div class="ov-kpi" style="--bgc:#FFF4E2;--fc:#C2410C"><div class="ib"><?=five_s_icon('clock')?></div><div><div class="lbl">ยังไม่มีคะแนน</div><div class="val" style="color:#C2410C"><?=$missing?></div><div class="sub">แสดงเป็นแท่งสีเทาในกราฟ</div></div></div>
  </div>

  <div class="ov-charts">
  <?php foreach($ovDept as $k=>$d): $cid=$k==='Production'?'ovProd':'ovOffice'; ?>
    <section class="ov-card">
      <h2><span class="badge <?=$k==='Production'?'p':'o'?>"><?=strtoupper($k)?></span>คะแนนรายหน่วย</h2>
      <p class="sub"><?=h($monthName)?> <?=$year+543?> • เรียงจากคะแนนต่ำไปสูง (หน่วยที่ต้องแก้ไขอยู่ด้านบน) • <?=$d['total']?> หน่วย</p>
      <div class="legend"><span><i style="background:#16A34A"></i>ผ่านเป้า</span><span><i style="background:#DC2626"></i>ต่ำกว่าเป้า</span><span><i style="background:#CBD5E1"></i>ยังไม่มีคะแนน</span><span><i style="background:transparent;border-top:3px dashed #DC2626;height:0;width:18px;border-radius:0"></i>เป้าหมาย <?=number_format($d['target'],0)?>%</span></div>
      <div class="ov-chart" id="<?=$cid?>Wrap"><canvas id="<?=$cid?>"></canvas></div>
    </section>
  <?php endforeach; ?>
  </div>

  <?php if($missing>0): ?>
  <div class="ov-missing"><h3><?=five_s_icon('clock')?> หน่วยที่ยังไม่มีคะแนนในเดือนนี้ — <?=$missing?> หน่วย</h3>
    <?php foreach($ovDept as $k=>$d): if(!$d['missingRows']) continue; ?>
      <div class="grp"><b><?=h($k)?>:</b><?php foreach($d['missingRows'] as $r):?><span><?=h(str_replace(' / ',' • ',$r['name']))?></span><?php endforeach;?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="ov-note">คะแนนคำนวณจากใบตรวจที่มีคะแนนจริงในเดือนที่เลือก • หน่วยที่ยังไม่มีคะแนนแสดงเป็นสีเทา ไม่นับเป็น 0% • หน้านี้อ่านข้อมูลอย่างเดียว ไม่แก้ไขข้อมูลต้นทาง</div>
</div>
<script>
Chart.defaults.font.family='"IBM Plex Sans Thai","Inter",Tahoma,sans-serif';Chart.defaults.color='#475569';
const OV_DATA=<?=json_encode(['Production'=>$ovDept['Production']['chart'],'Office'=>$ovDept['Office']['chart']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const OV_CHARTS={};
function drawOv(id,rows){
  const canvas=document.getElementById(id),wrap=document.getElementById(id+'Wrap');
  wrap.style.height=Math.max(260,rows.length*30+50)+'px';
  const target=rows.length?Number(rows[0].target):85;
  const ctx0=canvas.getContext('2d');const W=wrap.clientWidth||700;
  const grad=(a,b)=>{const g=ctx0.createLinearGradient(0,0,W,0);g.addColorStop(0,a);g.addColorStop(1,b);return g;};
  const gOk=grad('#4ADE80','#15803D'),gBad=grad('#F87171','#B91C1C');
  const vals=rows.map(r=>r.avg===null?100:Number(r.avg));
  const cols=rows.map(r=>r.avg===null?'#E2E8F0':(Number(r.avg)>=target?gOk:gBad));
  OV_CHARTS[id]=new Chart(canvas,{type:'bar',
    data:{labels:rows.map(r=>r.name),datasets:[{data:vals,backgroundColor:cols,borderWidth:0,borderRadius:{topRight:8,bottomRight:8},borderSkipped:false,barThickness:18,maxBarThickness:20}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,animation:false,layout:{padding:{right:16,top:4}},
      scales:{x:{min:0,max:100,border:{display:false},grid:{color:'rgba(148,163,184,.18)'},ticks:{color:'#64748B',callback:v=>v+'%'}},
              y:{border:{display:false},grid:{display:false},ticks:{autoSkip:false,padding:8,color:'#1E293B',font:{size:13,weight:'600'}},afterFit:a=>{a.width=Math.min(230,Math.max(a.width,150));}}},
      plugins:{legend:{display:false},tooltip:{callbacks:{label:c=>{const r=rows[c.dataIndex];return r.avg===null?' ยังไม่มีคะแนน':' คะแนน '+Number(r.avg).toFixed(2)+'% (เป้า '+target+'%)';}}}}},
    plugins:[{id:'ovTarget',beforeDatasetsDraw(ch){const ctx=ch.ctx,ar=ch.chartArea,tx=ch.scales.x.getPixelForValue(target);ctx.save();ctx.setLineDash([6,5]);ctx.strokeStyle='#DC2626';ctx.lineWidth=2;ctx.beginPath();ctx.moveTo(tx,ar.top);ctx.lineTo(tx,ar.bottom);ctx.stroke();ctx.restore();}},{id:'ovLabels',afterDatasetsDraw(ch){
      const ctx=ch.ctx,meta=ch.getDatasetMeta(0),ar=ch.chartArea;
      ctx.save();
      ctx.textBaseline='middle';
      meta.data.forEach((b,i)=>{const r=rows[i];
        if(r.avg===null){ctx.font='500 12.5px "IBM Plex Sans Thai",sans-serif';ctx.fillStyle='#64748B';ctx.textAlign='left';ctx.fillText('ยังไม่มีคะแนน',ar.left+10,b.y);return;}
        const t=Number(r.avg).toFixed(2)+'%';ctx.font='700 12.5px Inter,sans-serif';ctx.textAlign='right';ctx.fillStyle='#fff';ctx.fillText(t,b.x-9,b.y);});
      ctx.restore();}}]
  });
}
drawOv('ovProd',OV_DATA.Production);drawOv('ovOffice',OV_DATA.Office);
function buildOverview(){
  const a=OV_CHARTS.ovProd.canvas,b=OV_CHARTS.ovOffice.canvas,pad=30,head=110;
  const out=document.createElement('canvas');out.width=a.width+b.width+pad*3;out.height=Math.max(a.height,b.height)+head+pad;
  const c=out.getContext('2d');c.fillStyle='#fff';c.fillRect(0,0,out.width,out.height);c.fillStyle='#F26A21';c.fillRect(0,0,out.width,10);
  c.fillStyle='#0E1A2F';c.font='700 34px "IBM Plex Sans Thai",sans-serif';c.textBaseline='top';c.fillText('ภาพรวมคะแนน 5 ส. ทุกกลุ่ม — <?=h($monthName)?> <?=$year+543?>',pad,30);
  c.fillStyle='#64748B';c.font='500 20px "IBM Plex Sans Thai",sans-serif';c.fillText('Production (ซ้าย) • Office (ขวา) • เส้นประแดง = เป้าหมาย • 5S SMART | ALUMET',pad,74);
  c.drawImage(a,pad,head);c.drawImage(b,a.width+pad*2,head);
  return out;
}
function downloadOverview(){FiveSCopy.copyFrom(buildOverview,'5S_Overview_<?=$year?>_<?=sprintf('%02d',$month)?>.png');}
</script>
<?php require 'includes/footer.php'; ?>
