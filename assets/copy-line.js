/* 5S SMART — copy any report as an image for LINE (Ctrl+V).
   Uses a Promise-based ClipboardItem so Chrome keeps the click permission
   while the image is being rendered. Falls back to a PNG download. */
(function () {
  function toast(msg, ok) {
    var t = document.getElementById('fsCopyToast');
    if (!t) {
      t = document.createElement('div'); t.id = 'fsCopyToast';
      var ps = document.createElement('style'); ps.textContent = '@media print{#fsCopyToast{display:none!important}}'; document.head.appendChild(ps);
      t.style.cssText = 'position:fixed;left:50%;bottom:28px;transform:translateX(-50%);z-index:99999;padding:12px 20px;border-radius:12px;font:600 15px "IBM Plex Sans Thai",Tahoma,sans-serif;color:#fff;box-shadow:0 10px 30px rgba(15,23,42,.3);transition:opacity .3s';
      document.body.appendChild(t);
    }
    t.style.background = ok === false ? '#B91C1C' : (ok === null ? '#0E1A2F' : '#06C755');
    t.textContent = msg; t.style.opacity = '1';
    clearTimeout(t._h); t._h = setTimeout(function () { t.style.opacity = '0'; }, ok === null ? 6000 : 3500);
  }
  function download(blob, name) {
    var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = name || '5S_SMART.png';
    document.body.appendChild(a); a.click(); a.remove(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
  }
  function canvasToBlob(canvas) {
    return new Promise(function (res, rej) { canvas.toBlob(function (b) { b ? res(b) : rej(new Error('สร้างรูปไม่สำเร็จ')); }, 'image/png'); });
  }

  /* html2canvas วาดตัวอักษรไทย/ฟอนต์ใหญ่เลื่อนลงจากตำแหน่งจริง
     จึงให้ html2canvas วาดเฉพาะพื้นหลัง/เส้น/กราฟ แล้ววาดตัวอักษรเองตามตำแหน่งจริงบนหน้าจอ */
  function collectText(el) {
    var o = el.getBoundingClientRect(), out = [];
    var tw = document.createTreeWalker(el, NodeFilter.SHOW_TEXT), node;
    while ((node = tw.nextNode())) {
      var raw = node.textContent; if (!raw || !raw.trim()) continue;
      var p = node.parentElement; if (!p) continue;
      if (p.closest('.no-copy,script,style,noscript,canvas,svg')) continue;
      var cs = getComputedStyle(p);
      if (cs.visibility === 'hidden' || cs.display === 'none' || parseFloat(cs.opacity) === 0) continue;
      var rg = document.createRange(); rg.selectNodeContents(node);
      var rects = rg.getClientRects(); if (!rects.length) continue;
      var base = { font: cs.fontStyle + ' ' + cs.fontWeight + ' ' + cs.fontSize + ' ' + cs.fontFamily, color: cs.color,
        ls: cs.letterSpacing === 'normal' ? '0px' : cs.letterSpacing, tt: cs.textTransform, op: parseFloat(cs.opacity) || 1 };
      var lines = [];
      if (rects.length === 1) lines.push({ t: raw, r: rects[0] });
      else { // ข้อความขึ้นหลายบรรทัด: แยกตามบรรทัดจริง
        var cur = null;
        for (var i = 0; i < raw.length; i++) {
          var cr = document.createRange(); cr.setStart(node, i); cr.setEnd(node, i + 1);
          var rr = cr.getClientRects()[0];
          if (!rr || (rr.width === 0 && cur)) { if (cur) cur.t += raw[i]; continue; }
          if (!cur || Math.abs(rr.top - cur.r.top) > rr.height / 2) { cur = { t: raw[i], r: { left: rr.left, top: rr.top, height: rr.height } }; lines.push(cur); }
          else cur.t += raw[i];
        }
      }
      lines.forEach(function (ln) {
        var t = ln.t.replace(/\s+/g, ' '); var lead = t.length - t.replace(/^\s+/, '').length; t = t.trim(); if (!t) return;
        if (base.tt === 'uppercase') t = t.toUpperCase();
        out.push({ t: t, lead: lead ? ln.t.match(/^\s+/)[0] : '', x: ln.r.left - o.left, y: ln.r.top - o.top, h: ln.r.height, s: base });
      });
    }
    return out;
  }
  function drawText(canvas, items, scale) {
    var c = canvas.getContext('2d'); c.save(); c.setTransform(scale, 0, 0, scale, 0, 0); c.textBaseline = 'alphabetic';
    items.forEach(function (it) {
      c.font = it.s.font; c.fillStyle = it.s.color; c.globalAlpha = it.s.op; try { c.letterSpacing = it.s.ls; } catch (e) {}
      var x = it.x; if (it.lead) x += c.measureText(it.lead).width;
      var m = c.measureText(it.t), asc = m.fontBoundingBoxAscent || m.actualBoundingBoxAscent, des = m.fontBoundingBoxDescent || m.actualBoundingBoxDescent;
      c.fillText(it.t, x, it.y + (it.h - (asc + des)) / 2 + asc);
    });
    c.restore(); return canvas;
  }
  function render(el, opts) {
    if (typeof el === 'string') el = document.querySelector(el);
    if (typeof html2canvas !== 'function') return Promise.reject(new Error('ยังโหลดตัวสร้างรูปไม่เสร็จ'));
    opts = Object.assign({ scale: 2, backgroundColor: '#ffffff', useCORS: true, logging: false }, opts || {});
    var scale = opts.scale, mark = 'fsr' + Date.now();
    var ready = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
    return ready.then(function () {
      var items = collectText(el);
      el.setAttribute('data-fs-render', mark);
      var userClone = opts.onclone;
      opts.onclone = function (doc) {
        var st = doc.createElement('style');
        st.textContent = '[data-fs-render="' + mark + '"], [data-fs-render="' + mark + '"] *{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important;text-decoration-color:transparent!important}';
        doc.head.appendChild(st); if (userClone) return userClone(doc);
      };
      var ign = opts.ignoreElements;
      opts.ignoreElements = function (n) { return (n.classList && n.classList.contains('no-copy')) || (ign ? ign(n) : false); };
      return html2canvas(el, opts).then(function (cv) { el.removeAttribute('data-fs-render'); return drawText(cv, items, scale); },
        function (e) { el.removeAttribute('data-fs-render'); throw e; });
    });
  }
  /* getCanvas: function returning a Promise<HTMLCanvasElement> (or a canvas) */
  function copyFrom(getCanvas, name) {
    toast('กำลังสร้างรูป…', null);
    var blobPromise = Promise.resolve().then(getCanvas).then(canvasToBlob);
    if (navigator.clipboard && window.ClipboardItem && window.isSecureContext) {
      navigator.clipboard.write([new ClipboardItem({ 'image/png': blobPromise })])
        .then(function () { toast('✓ คัดลอกรูปแล้ว — เปิด LINE แล้วกด Ctrl+V เพื่อวาง'); })
        .catch(function () { blobPromise.then(function (b) { download(b, name); toast('คัดลอกไม่ได้ในเบราว์เซอร์นี้ จึงบันทึกเป็นไฟล์ PNG ให้แทน', false); }); });
    } else {
      blobPromise.then(function (b) { download(b, name); toast('บันทึกรูปเป็น PNG แล้ว (เบราว์เซอร์ไม่รองรับการคัดลอก)', false); })
        .catch(function (e) { toast(e.message || 'สร้างรูปไม่สำเร็จ', false); });
    }
  }
  function copyElement(el, name, opts) {
    if (typeof el === 'string') el = document.querySelector(el);
    if (!el) { toast('ไม่พบส่วนที่ต้องการคัดลอก', false); return; }
    copyFrom(function () { return render(el, opts); }, name);
  }
  window.FiveSCopy = { render: render, copyElement: copyElement, copyCanvas: function (c, name) { copyFrom(function () { return c; }, name); }, copyFrom: copyFrom, toast: toast };
})();
