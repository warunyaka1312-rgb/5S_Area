<?php
$__navF = function_exists('five_s_nav_current') ? five_s_nav_current() : ['prev'=>null,'next'=>null];
?>
<?php if(empty($hidePageNav) && ($__navF['prev'] || $__navF['next'])): ?>
<div class="page-steps no-print">
  <?php if($__navF['prev']): ?>
    <a class="step prev" href="<?=h($__navF['prev']['href'])?>"><?=five_s_icon('arrow-left')?><span><small>ก่อนหน้า</small><?=h($__navF['prev']['label'])?></span></a>
  <?php else: ?><span></span><?php endif; ?>
  <?php if($__navF['next']): ?>
    <a class="step next" href="<?=h($__navF['next']['href'])?>"><span><small>ถัดไป</small><?=h($__navF['next']['label'])?></span><?=five_s_icon('arrow-right')?></a>
  <?php endif; ?>
</div>
<?php endif; ?>
</div>
<footer class="footer mt-8 no-print">
  <div class="ft-row">
    <div class="ft-brand"><img src="assets/alumet-logo.png" alt="ALUMET"><span>5S SMART Management System</span></div>
    <div class="ft-links">
      <a href="index.php">Dashboard</a><a href="Page/admin/schedule_manage.php">แผนตรวจ</a><a href="Page/public/completion_summary.php">สรุปผลการตรวจ</a><a href="Page/public/tag_dashboard.php">TAG</a><a href="Page/admin/backup.php">Backup</a>
    </div>
    <div class="ft-meta">© <?=date('Y')?> ALUMET • <?=h(defined('FIVE_S_APP_VERSION')?FIVE_S_APP_VERSION:'')?> • <span class="ft-tag">Clean Today • Better Tomorrow</span></div>
  </div>
</footer>
</main>
</div>

<!-- Ctrl+K quick navigation -->
<div class="palette no-print" id="fsPalette" hidden>
  <div class="palette-box" role="dialog" aria-label="ค้นหาเมนู">
    <div class="palette-in"><?=five_s_icon('search')?><input id="fsPaletteInput" type="text" placeholder="พิมพ์ชื่อหน้า เช่น TAG, แผนตรวจ, Office, Backup…" autocomplete="off"><kbd>Esc</kbd></div>
    <div class="palette-list" id="fsPaletteList"></div>
  </div>
</div>
<script>
window.FiveS = (function(){
  const pages = <?=five_s_nav_json()?>;
  const pal = document.getElementById('fsPalette'), inp = document.getElementById('fsPaletteInput'), list = document.getElementById('fsPaletteList');
  let sel = 0, rows = [];
  function esc(s){return String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));}
  function render(){
    const q = inp.value.trim().toLowerCase();
    rows = pages.filter(p => !q || (p.t+' '+p.s+' '+p.d+' '+p.h).toLowerCase().includes(q));
    if(sel >= rows.length) sel = 0;
    list.innerHTML = rows.length ? rows.map((p,i)=>`<a href="${esc(p.h)}" ${p.b?'target="_blank"':''} class="${i===sel?'on':''}" data-i="${i}"><b>${esc(p.t)}</b><small>${esc(p.s)}${p.d?' • '+esc(p.d):''}</small></a>`).join('') : '<div class="palette-empty">ไม่พบหน้าที่ค้นหา</div>';
  }
  function open(){ pal.hidden = false; inp.value=''; sel=0; render(); setTimeout(()=>inp.focus(),10); }
  function close(){ pal.hidden = true; }
  function go(){ const p = rows[sel]; if(!p) return; if(p.b) window.open(p.h,'_blank'); else location.href = p.h; close(); }
  inp.addEventListener('input', ()=>{sel=0;render();});
  inp.addEventListener('keydown', e=>{
    if(e.key==='ArrowDown'){sel=Math.min(sel+1,rows.length-1);render();e.preventDefault();}
    else if(e.key==='ArrowUp'){sel=Math.max(sel-1,0);render();e.preventDefault();}
    else if(e.key==='Enter'){go();e.preventDefault();}
    else if(e.key==='Escape'){close();}
  });
  pal.addEventListener('click', e=>{ if(e.target===pal) close(); });
  document.addEventListener('keydown', e=>{
    if((e.ctrlKey||e.metaKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); pal.hidden?open():close(); }
    else if(e.key==='Escape' && !pal.hidden) close();
  });
  // Live clock in the top bar (Thai Buddhist year)
  const clock = document.getElementById('tbClock');
  const mTh = ['ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'];
  function tick(){ if(!clock) return; const d=new Date(); clock.textContent = d.getDate()+' '+mTh[d.getMonth()]+' '+(d.getFullYear()+543)+' • '+String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0'); }
  tick(); setInterval(tick, 15000);
  // Close the mobile drawer after choosing a menu item
  document.querySelectorAll('.sidebar .nav a').forEach(a=>a.addEventListener('click',()=>document.body.classList.remove('nav-open')));
  // Keep the active menu item visible inside the sidebar
  const act = document.querySelector('.sidebar .nav a.active'), sb = document.querySelector('.sidebar'); if(act && sb) sb.scrollTop = Math.max(0, act.offsetTop - sb.clientHeight/2);
  return {openPalette: open};
})();
</script>
</body></html>
