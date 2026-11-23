<?php
$title = 'ป้ายชื่อบอร์ดกิจกรรม 5ส.';
$thaiMonths = [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
require_once dirname(__DIR__, 2).'/includes/config.php';
// V63: ไม่ระบุเดือน -> ใช้เดือนล่าสุดที่มีคะแนนในระบบอัตโนมัติ
[$ldY, $ldM] = function_exists('five_s_latest_data_month') ? five_s_latest_data_month() : [(int)date('Y'), (int)date('n')];
$month = isset($_GET['month']) ? (int)$_GET['month'] : $ldM;
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : $ldY + 543;
if ($month < 1 || $month > 12) $month = $ldM;
if ($year < 2560 || $year > 2585) $year = $ldY + 543;
require_once FIVE_S_ROOT.'/includes/header.php';
?>
<style>
.bt-page{max-width:1500px;margin:0 auto;padding-bottom:24px}
.bt-controls{display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap;padding:18px 20px;margin-bottom:14px}
.bt-ctl{display:flex;flex-direction:column;gap:6px;min-width:160px}
.bt-ctl label{font-size:13px;font-weight:700;color:#475569}
.bt-ctl select{border:1px solid #CBD5E1;border-radius:12px;background:#fff;padding:0 14px;height:46px;color:#0E1A2F;font-weight:700;font-size:16px}
.bt-btn{display:inline-flex;align-items:center;gap:8px;height:46px;border:0;border-radius:12px;padding:0 18px;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 4px 12px rgba(15,23,42,.12);transition:transform .15s}
.bt-btn:hover{transform:translateY(-1px)}
.bt-navy{background:#0E1A2F;color:#fff}.bt-blue{background:#2563EB;color:#fff}.bt-line{background:#06C755;color:#fff}
.bt-hint{font-size:14px;color:#475569;padding:0 4px 14px}
.bt-section{background:#fff;border:1px solid #E2E8F0;border-radius:20px;padding:22px;box-shadow:0 10px 30px rgba(15,23,42,.06)}
.bt-head{display:flex;justify-content:space-between;gap:12px;margin-bottom:14px;color:#0E1A2F;font-weight:700}
.bt-head small{color:#64748B;font-weight:500}
/* Stage: poster is designed at a fixed 2000x500 and scaled to fit */
.bt-stage{width:100%;position:relative;overflow:hidden;aspect-ratio:4/1;border-radius:6px}
.bt-scale{position:absolute;left:0;top:0;transform-origin:0 0}

.bp{position:relative;width:2000px;height:500px;overflow:hidden;font-family:"IBM Plex Sans Thai","Inter",Tahoma,sans-serif;color:#fff;
  background:linear-gradient(120deg,#0A1630 0%,#122A52 55%,#0B1A36 100%)}
.bp-topbar{position:absolute;left:0;right:0;top:0;height:14px;background:linear-gradient(90deg,#E5484D,#F26A21 30%,#F7A21B 60%,#16A34A 80%,#2563EB)}
.bp-glow1{position:absolute;width:760px;height:760px;border-radius:50%;right:-260px;top:-330px;background:radial-gradient(circle,rgba(242,106,33,.38) 0%,rgba(242,106,33,0) 68%)}
.bp-glow2{position:absolute;width:620px;height:620px;border-radius:50%;left:-240px;bottom:-360px;background:radial-gradient(circle,rgba(37,99,235,.40) 0%,rgba(37,99,235,0) 70%)}
.bp-stripes{position:absolute;right:430px;top:14px;width:260px;height:386px;background:repeating-linear-gradient(115deg,rgba(255,255,255,.05) 0 16px,transparent 16px 40px)}
.bp-tile{position:absolute;left:64px;top:56px;width:300px;height:300px;border-radius:44px;background:linear-gradient(145deg,#FFB020 0%,#F26A21 55%,#E0461B 100%);box-shadow:0 22px 40px rgba(0,0,0,.35);text-align:center}
.bp-tile b{display:block;font:800 178px/1 "Inter",sans-serif;letter-spacing:-6px;margin-top:38px;color:#fff;text-shadow:0 6px 0 rgba(120,30,0,.25)}
.bp-tile span{display:block;font:800 34px/1 "Inter",sans-serif;letter-spacing:12px;margin-top:14px;padding-left:12px;color:#0E1A2F}
.bp-center{position:absolute;left:420px;top:44px;width:1080px}
.bp-eyebrow{font:700 28px/1 "Inter",sans-serif;letter-spacing:7px;color:#FFB020}
.bp-title{font-weight:700;font-size:120px;line-height:1.2;margin-top:10px;color:#fff;text-shadow:0 6px 18px rgba(0,0,0,.35);white-space:nowrap}
.bp-pill{display:inline-flex;align-items:center;gap:22px;margin-top:16px;background:#fff;border-radius:999px;padding:6px 44px 10px;box-shadow:0 12px 26px rgba(0,0,0,.28);font-weight:700;font-size:60px;line-height:1.3;color:#0E1A2F;white-space:nowrap}
.bp-pill .m{color:#F26A21}.bp-pill .y{font-family:"Inter",sans-serif;font-weight:800;color:#0E1A2F}
.bp-right{position:absolute;right:60px;top:62px;width:370px;text-align:center}
.bp-logo{background:#fff;border-radius:26px;padding:26px 30px;box-shadow:0 16px 34px rgba(0,0,0,.3)}
.bp-logo img{width:100%;height:auto;display:block}
.bp-sys{margin-top:22px;font:800 46px/1 "Inter",sans-serif;letter-spacing:2px}.bp-sys i{font-style:normal;color:#FFB020}
.bp-tag{margin-top:16px;font:700 17px/1 "Inter",sans-serif;letter-spacing:3px;color:#CBD5E1;white-space:nowrap}
.bp-strip{position:absolute;left:0;right:0;bottom:0;height:100px;display:flex}
.bp-strip div{flex:1;display:flex;align-items:center;justify-content:center;gap:16px;border-right:2px solid rgba(255,255,255,.18)}
.bp-strip div:last-child{border-right:0}
.bp-strip .n{width:56px;height:56px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;font:800 30px/1 "Inter",sans-serif}
.bp-strip .t{font-weight:700;font-size:40px;line-height:1.15}
.bp-strip .e{display:block;font:600 17px/1 "Inter",sans-serif;letter-spacing:3px;opacity:.9;margin-top:9px}
.s1{background:#E5484D}.s1 .n{color:#E5484D}.s2{background:#F26A21}.s2 .n{color:#F26A21}.s3{background:#E9A800}.s3 .n{color:#C98F00}.s4{background:#16A34A}.s4 .n{color:#16A34A}.s5{background:#2563EB}.s5 .n{color:#2563EB}
@media(max-width:700px){.bt-section{padding:10px}.bt-controls{padding:12px}.bt-btn{flex:1;justify-content:center}}
@media print{
  html,body{background:#fff!important;margin:0!important;padding:0!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  .no-print,.topbar,.sidebar,.footer,.crumbs,.sec-tabs,.pager,.fs-pager{display:none!important}
  .main{margin:0!important;padding:0!important;width:100%!important;min-height:0!important}
  .main>.wrap{max-width:none!important;width:100%!important;margin:0!important;padding:0!important}
  .bt-page,.bt-section{max-width:none!important;margin:0!important;padding:0!important;border:0!important;box-shadow:none!important;border-radius:0!important}
  .bt-head{display:none!important}
  .bt-print-wrap{display:flex;align-items:center;justify-content:center;height:calc(var(--paper-h,297mm) - 17mm)}
  .bt-stage{border-radius:0;width:100%}
  *{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
}
</style>
<div class="bt-page">
  <section class="card bt-controls no-print">
    <div class="bt-ctl"><label for="monthSelect">เดือนที่ต้องการแสดง</label><select id="monthSelect" onchange="updateBoardText()">
      <?php foreach($thaiMonths as $m=>$name): ?><option value="<?=$m?>" <?=$m===$month?'selected':''?>><?=$name?></option><?php endforeach; ?>
    </select></div>
    <div class="bt-ctl"><label for="yearSelect">ปี พ.ศ.</label><select id="yearSelect" onchange="updateBoardText()">
      <?php for($y=2565;$y<=2580;$y++): ?><option value="<?=$y?>" <?=$y===$year?'selected':''?>><?=$y?></option><?php endfor; ?>
    </select></div>
    <button type="button" class="bt-btn bt-navy" onclick="printBanner('A3')"><?=five_s_icon('printer')?> พิมพ์ป้าย A3 แนวนอน</button>
    <button type="button" class="bt-btn bt-blue" onclick="printBanner('A4')"><?=five_s_icon('printer')?> พิมพ์ A4</button>
    <button type="button" class="bt-btn bt-line" onclick="copyBannerPng()"><?=five_s_icon('file')?> คัดลอกรูปส่ง LINE</button>
  </section>
  <div class="bt-hint no-print">เลือกเดือนและปี ป้ายจะเปลี่ยนข้อความให้อัตโนมัติ • ค่าเริ่มต้นคือเดือนล่าสุดที่มีคะแนนในระบบ • กด “คัดลอกรูปส่ง LINE” แล้วกด Ctrl+V ในแชท LINE</div>
  <section class="bt-section">
    <div class="bt-head no-print"><span>ตัวอย่างป้ายชื่อบอร์ด 5ส.</span><small>สัดส่วน 4 : 1 • พิมพ์เต็มความกว้างกระดาษแนวนอน</small></div>
    <div class="bt-print-wrap" id="printStage">
    <div class="bt-stage" id="btStage"><div class="bt-scale" id="btScale">
      <article id="boardPoster" class="bp" aria-label="ป้ายชื่อบอร์ดกิจกรรม 5ส.">
        <div class="bp-glow1"></div><div class="bp-glow2"></div><div class="bp-stripes"></div>
        <div class="bp-topbar"></div>
        <div class="bp-tile"><b>5S</b><span>SMART</span></div>
        <div class="bp-center">
          <div class="bp-eyebrow">5S ACTIVITY REPORT</div>
          <div class="bp-title">สรุปผลกิจกรรม 5ส.</div>
          <div class="bp-pill"><span>ประจำเดือน</span><span class="m" id="posterMonth"><?=h($thaiMonths[$month])?></span><span class="y" id="posterYear"><?=$year?></span></div>
        </div>
        <div class="bp-right">
          <div class="bp-logo"><img src="assets/alumet-logo.png" alt="ALUMET"></div>
          <div class="bp-sys">5S <i>SMART</i></div>
          <div class="bp-tag">CLEAN TODAY • BETTER TOMORROW</div>
        </div>
        <div class="bp-strip">
          <div class="s1"><span class="n">1</span><span class="t">สะสาง<span class="e">SORT</span></span></div>
          <div class="s2"><span class="n">2</span><span class="t">สะดวก<span class="e">SET IN ORDER</span></span></div>
          <div class="s3"><span class="n">3</span><span class="t">สะอาด<span class="e">SHINE</span></span></div>
          <div class="s4"><span class="n">4</span><span class="t">สุขลักษณะ<span class="e">STANDARDIZE</span></span></div>
          <div class="s5"><span class="n">5</span><span class="t">สร้างนิสัย<span class="e">SUSTAIN</span></span></div>
        </div>
      </article>
    </div></div>
    </div>
  </section>
</div>
<style id="dynamicPrintPage">@page{size:A3 landscape;margin:8mm}</style>
<script>
const monthNamesTh=['','มกราคม','กุมภาพันธ์','มีนาคม','เมษายน','พฤษภาคม','มิถุนายน','กรกฎาคม','สิงหาคม','กันยายน','ตุลาคม','พฤศจิกายน','ธันวาคม'];
function fitPoster(){const st=document.getElementById('btStage');const k=st.clientWidth/2000;document.getElementById('btScale').style.transform='scale('+k+')';}
window.addEventListener('resize',fitPoster);window.addEventListener('beforeprint',()=>setTimeout(fitPoster,0));window.addEventListener('afterprint',fitPoster);
if(window.matchMedia){matchMedia('print').addListener(fitPoster);}
function updateBoardText(){
  const month=parseInt(document.getElementById('monthSelect').value,10);
  const year=parseInt(document.getElementById('yearSelect').value,10);
  document.getElementById('posterMonth').textContent=monthNamesTh[month];
  document.getElementById('posterYear').textContent=year;
  const u=new URL(window.location.href);u.searchParams.set('month',month);u.searchParams.set('year',year);history.replaceState({},'',u.toString());
}
function printBanner(paper){
  updateBoardText();
  document.getElementById('dynamicPrintPage').textContent='@page{size:'+paper+' landscape;margin:8mm}';
  document.getElementById('printStage').style.setProperty('--paper-h',paper==='A3'?'297mm':'210mm');
  document.title='5S SMART ALUMET - '+document.getElementById('posterMonth').textContent+' '+document.getElementById('posterYear').textContent;
  window.print();
}
/* วาดป้ายลง canvas เอง (ตำแหน่งตัวอักษรตรงกับหน้าจอ 100% ไม่เพี้ยนแบบ html2canvas) */
function rr(c,x,y,w,h,r){r=Math.min(r,h/2,w/2);c.beginPath();c.moveTo(x+r,y);c.arcTo(x+w,y,x+w,y+h,r);c.arcTo(x+w,y+h,x,y+h,r);c.arcTo(x,y+h,x,y,r);c.arcTo(x,y,x+w,y,r);c.closePath();}
function loadImg(src){return new Promise((res,rej)=>{const i=new Image();i.onload=()=>res(i);i.onerror=rej;i.src=src;});}
async function renderBannerCanvas(){
  updateBoardText();
  if(document.fonts&&document.fonts.ready){try{await document.fonts.ready;}catch(e){}}
  const host=document.createElement('div');host.style.cssText='position:fixed;left:-30000px;top:0;width:2000px;height:500px';
  const P=document.getElementById('boardPoster').cloneNode(true);P.removeAttribute('id');host.appendChild(P);document.body.appendChild(host);
  try{
    const W=2000,H=500,K=1.6,o=P.getBoundingClientRect();
    const cv=document.createElement('canvas');cv.width=W*K;cv.height=H*K;const c=cv.getContext('2d');c.scale(K,K);
    const box=el=>{const r=el.getBoundingClientRect();return {x:r.left-o.left,y:r.top-o.top,w:r.width,h:r.height};};
    // background
    let g=c.createLinearGradient(0,0,W,H);g.addColorStop(0,'#0A1630');g.addColorStop(.55,'#122A52');g.addColorStop(1,'#0B1A36');c.fillStyle=g;c.fillRect(0,0,W,H);
    const glow=(cx,cy,r,col)=>{const q=c.createRadialGradient(cx,cy,0,cx,cy,r);q.addColorStop(0,col);q.addColorStop(.68,'rgba(0,0,0,0)');c.fillStyle=q;c.fillRect(cx-r,cy-r,r*2,r*2);};
    glow(W+260-380,-330+380,380,'rgba(242,106,33,.38)');glow(-240+310,H+360-310,310,'rgba(37,99,235,.40)');
    c.save();c.beginPath();c.rect(W-430-260,14,260,386);c.clip();c.strokeStyle='rgba(255,255,255,.05)';c.lineWidth=16;for(let i=-400;i<700;i+=40){c.beginPath();c.moveTo(W-690+i,400);c.lineTo(W-690+i+180,14);c.stroke();}c.restore();
    g=c.createLinearGradient(0,0,W,0);[['#E5484D',0],['#F26A21',.3],['#F7A21B',.6],['#16A34A',.8],['#2563EB',1]].forEach(([k,p])=>g.addColorStop(p,k));c.fillStyle=g;c.fillRect(0,0,W,14);
    // boxes (tile, pill, logo card, strip)
    const shadow=(b,y)=>{c.shadowColor='rgba(0,0,0,.33)';c.shadowBlur=b;c.shadowOffsetY=y;},noShadow=()=>{c.shadowColor='transparent';c.shadowBlur=0;c.shadowOffsetY=0;};
    let t=box(P.querySelector('.bp-tile'));g=c.createLinearGradient(t.x,t.y,t.x+t.w,t.y+t.h);g.addColorStop(0,'#FFB020');g.addColorStop(.55,'#F26A21');g.addColorStop(1,'#E0461B');
    shadow(40,22);c.fillStyle=g;rr(c,t.x,t.y,t.w,t.h,44);c.fill();noShadow();
    t=box(P.querySelector('.bp-pill'));shadow(26,12);c.fillStyle='#fff';rr(c,t.x,t.y,t.w,t.h,999);c.fill();noShadow();
    t=box(P.querySelector('.bp-logo'));shadow(34,16);c.fillStyle='#fff';rr(c,t.x,t.y,t.w,t.h,26);c.fill();noShadow();
    const img=P.querySelector('.bp-logo img');try{const im=await loadImg(img.src);const b=box(img);c.drawImage(im,b.x,b.y,b.w,b.h);}catch(e){}
    P.querySelectorAll('.bp-strip>div').forEach((d,i)=>{const b=box(d);c.fillStyle=getComputedStyle(d).backgroundColor;c.fillRect(b.x,b.y,b.w,b.h);if(i){c.fillStyle='rgba(255,255,255,.18)';c.fillRect(b.x,b.y,2,b.h);}
      const n=box(d.querySelector('.n'));c.fillStyle='#fff';c.beginPath();c.arc(n.x+n.w/2,n.y+n.h/2,n.w/2,0,Math.PI*2);c.fill();});
    // text: every text node at its real on-screen position
    const tw=document.createTreeWalker(P,NodeFilter.SHOW_TEXT);let node;
    while((node=tw.nextNode())){
      const txt=node.textContent;if(!txt.trim())continue;
      const el=node.parentElement,cs=getComputedStyle(el);if(cs.display==='none'||cs.visibility==='hidden')continue;
      const rg=document.createRange();rg.selectNodeContents(node);const rs=rg.getClientRects();if(!rs.length)continue;const r=rs[0];
      c.font=cs.fontStyle+' '+cs.fontWeight+' '+cs.fontSize+' '+cs.fontFamily;c.fillStyle=cs.color;c.textBaseline='alphabetic';
      try{c.letterSpacing=cs.letterSpacing==='normal'?'0px':cs.letterSpacing;}catch(e){}
      const m=c.measureText(txt.trim()),asc=m.fontBoundingBoxAscent,des=m.fontBoundingBoxDescent;
      const x=r.left-o.left+(txt.length-txt.trimStart().length?0:0),y=r.top-o.top+(r.height-(asc+des))/2+asc;
      if(el.classList.contains('bp-title')){c.shadowColor='rgba(0,0,0,.35)';c.shadowBlur=18;c.shadowOffsetY=6;}
      else if(el.closest('.bp-tile')&&el.tagName==='B'){c.shadowColor='rgba(120,30,0,.25)';c.shadowBlur=0;c.shadowOffsetY=6;}
      c.fillText(txt.trim(),x,y);noShadow();
    }
    return cv;
  }finally{host.remove();}
}
function copyBannerPng(){
  FiveSCopy.copyFrom(renderBannerCanvas,'5S_SMART_ALUMET_'+document.getElementById('posterMonth').textContent+'_'+document.getElementById('posterYear').textContent+'.png');
}
updateBoardText();fitPoster();
if(document.fonts&&document.fonts.ready)document.fonts.ready.then(fitPoster);
</script>
<?php require_once FIVE_S_ROOT.'/includes/footer.php'; ?>
