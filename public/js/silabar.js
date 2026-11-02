/* SILABAR front-end: small, dependency-free. QR drawing uses qrcode-generator (CDN) when available. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  /* ---------- QR ---------- */
  function qrSvg(text, px) {
    px = px || 80;
    try {
      if (typeof qrcode === 'function') {
        var q = qrcode(0, 'M'); q.addData(String(text)); q.make();
        var n = q.getModuleCount(), d = '';
        for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (q.isDark(r, c)) d += 'M' + c + ',' + r + 'h1v1h-1z';
        return '<svg viewBox="-1 -1 ' + (n + 2) + ' ' + (n + 2) + '" width="' + px + '" height="' + px + '" shape-rendering="crispEdges"><path d="' + d + '" fill="#111"/></svg>';
      }
    } catch (e) { /* fall through */ }
    return '<div style="width:' + px + 'px;height:' + px + 'px;display:grid;place-items:center;font:700 11px monospace;color:#111;text-align:center;word-break:break-all">' + String(text) + '</div>';
  }
  function paint(root) {
    $$('[data-qr]', root).forEach(function (el) { el.innerHTML = qrSvg(el.dataset.qr, +el.dataset.size || 80); });
    $$('.gauge .fg[data-off]', root).forEach(function (c) { requestAnimationFrame(function () { requestAnimationFrame(function () { c.style.strokeDashoffset = c.dataset.off; }); }); });
    $$('[data-count]', root).forEach(function (el) {
      var to = +el.dataset.count, fmt = el.dataset.fmt, t0 = performance.now();
      (function step(t) {
        var k = Math.min(1, (t - t0) / 750), v = to * (1 - Math.pow(1 - k, 3));
        el.textContent = fmt === 'pct' ? Math.round(v) + '%' : fmt === 'rp' ? 'Rp' + Math.round(v).toLocaleString('id-ID') : Math.round(v);
        if (k < 1) requestAnimationFrame(step);
      })(t0);
    });
  }

  /* ---------- toasts ---------- */
  function toast(msg, bad) {
    var t = document.createElement('div');
    t.className = 'toast' + (bad ? ' bad' : '');
    t.textContent = msg;
    $('#toasts').appendChild(t);
    setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(function () { t.remove(); }, 320); }, 3400);
  }
  window.silabarToast = toast;

  /* ---------- modal ---------- */
  function openModal(html, cls) {
    closeModal();
    var o = document.createElement('div'); o.className = 'ov'; o.id = 'ov';
    o.innerHTML = '<div class="modal ' + (cls || '') + '" role="dialog" aria-modal="true">' + html + '</div>';
    o.addEventListener('mousedown', function (e) { if (e.target === o) closeModal(); });
    $('#modal-root').appendChild(o);
    paint(o);
    return o;
  }
  function closeModal() { var o = $('#ov'); if (o) o.remove(); if (window.__scanner) { try { window.__scanner.stop(); } catch (e) {} window.__scanner = null; } }
  var X = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>';
  function head(t, sub) { return '<div class="mh"><div style="flex:1"><h3>' + t + '</h3>' + (sub ? '<div class="muted sm">' + sub + '</div>' : '') + '</div><button class="ibtn" data-close aria-label="Tutup">' + X + '</button></div>'; }

  /* ---------- item availability calendar ---------- */
  function calendar(url, title, offset) {
    fetch(url + '?month=' + offset, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      var cells = ''; for (var i = 0; i < j.first; i++) cells += '<div class="cd blank"></div>';
      for (var d = 1; d <= j.days_in_month; d++) {
        var cls = j.loads[d] || 'past';
        cells += '<div class="cd ' + cls + (j.today === d ? ' today' : '') + '">' + d + '</div>';
      }
      var names = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].map(function (n) { return '<div class="dn">' + n + '</div>'; }).join('');
      openModal(head(title, 'Ketersediaan per hari') + '<div class="row" style="margin-bottom:8px"><button class="ibtn" data-cal="' + (offset - 1) + '" ' + (offset <= 0 ? 'disabled style="opacity:.4"' : '') + '>‹</button><b class="sp" style="text-align:center">' + j.title + '</b><button class="ibtn" data-cal="' + (offset + 1) + '" ' + (offset >= 5 ? 'disabled style="opacity:.4"' : '') + '>›</button></div>' +
        '<div class="cal">' + names + cells + '</div><div class="legend" style="margin-top:10px"><span><i style="background:var(--moss-l)"></i>longgar</span><span><i style="background:var(--sun-l)"></i>terbatas</span><span><i style="background:var(--brick-l)"></i>penuh</span></div>' +
        '<div class="row" style="justify-content:flex-end;margin-top:14px"><button class="btn" data-close>Tutup</button></div>', 'narrow');
      $('#ov').dataset.url = url; $('#ov').dataset.title = title;
    });
  }

  /* ---------- camera scanner (html5-qrcode, loaded on demand) ---------- */
  function loadScanner(cb) {
    if (window.Html5Qrcode) return cb();
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js';
    s.onload = cb; s.onerror = function () { toast('Kamera tidak bisa dimuat. Ketik kodenya manual.', true); };
    document.head.appendChild(s);
  }
  function scan(btn) {
    var target = $(btn.dataset.target), append = btn.dataset.mode === 'append', form = btn.dataset.submit ? $(btn.dataset.submit) : null;
    openModal(head('Pindai QR', 'Arahkan kamera ke label atau tiket') + '<div id="reader" style="width:100%"></div><div class="hint" style="margin-top:8px">Kalau kamera tidak tersedia, ketik kodenya di kolom isian.</div>', 'narrow');
    loadScanner(function () {
      var sc = new Html5Qrcode('reader'); window.__scanner = sc;
      sc.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, function (text) {
        var v = String(text).replace(/^SILABAR:/, '');
        if (append) { target.value = (target.value ? target.value.replace(/\s+$/, '') + '\n' : '') + v + '\n'; } else { target.value = v; }
        closeModal(); toast('Terbaca: ' + v);
        if (form) form.submit();
      }, function () {}).catch(function () { toast('Kamera ditolak atau tidak ada. Ketik kodenya manual.', true); });
    });
  }

  /* ---------- delegated events ---------- */
  document.addEventListener('click', function (e) {
    var t = e.target;
    if ((t = e.target.closest('[data-theme-toggle]'))) {
      var dark = matchMedia('(prefers-color-scheme: dark)').matches, cur = document.documentElement.dataset.theme || (dark ? 'dark' : 'light'), nx = cur === 'dark' ? 'light' : 'dark';
      document.documentElement.dataset.theme = nx; try { localStorage.setItem('silabar-theme', nx); } catch (er) {} return;
    }
    if ((t = e.target.closest('[data-menu]'))) { $('#side').classList.toggle('open'); return; }
    if ((t = e.target.closest('[data-pop]'))) {
      var p = document.getElementById(t.dataset.pop), was = p.classList.contains('hidden');
      $$('.pop').forEach(function (x) { x.classList.add('hidden'); }); if (was) p.classList.remove('hidden'); return;
    }
    if (!e.target.closest('.pop')) $$('.pop').forEach(function (x) { x.classList.add('hidden'); });
    if ((t = e.target.closest('[data-close]'))) { closeModal(); return; }
    if ((t = e.target.closest('[data-print]'))) { try { window.print(); } catch (er) { toast('Gunakan Ctrl+P untuk mencetak.', true); } return; }
    if ((t = e.target.closest('[data-step]'))) { var i = $(t.dataset.target); i.value = Math.max(+i.min || 0, (+i.value || 0) + (+t.dataset.step)); i.dispatchEvent(new Event('change', { bubbles: true })); return; }
    if ((t = e.target.closest('[data-calendar]'))) { calendar(t.dataset.calendar, t.dataset.title || 'Kalender', 0); return; }
    if ((t = e.target.closest('[data-cal]'))) { var ov = $('#ov'); calendar(ov.dataset.url, ov.dataset.title, +t.dataset.cal); return; }
    if ((t = e.target.closest('[data-qr-big]'))) {
      openModal(head('QR tiket ' + t.dataset.title, 'Tunjukkan ke Petugas saat mengambil atau mengembalikan') + '<div style="text-align:center"><div class="big-qr">' + qrSvg(t.dataset.qrBig, 240) + '</div><div class="mono-s" style="margin-top:8px;font-size:18px">' + t.dataset.qrBig + '</div></div>', 'narrow'); return;
    }
    if ((t = e.target.closest('[data-scan]'))) { scan(t); return; }
    if ((t = e.target.closest('[data-fill]'))) { var f = t.dataset.fill.split('|'); $('#email').value = f[0]; $('#password').value = f[1]; return; }
    if ((t = e.target.closest('[data-open-modal]'))) { var src = document.getElementById(t.dataset.openModal); openModal(src.innerHTML, src.dataset.cls); return; }
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeModal(); $$('.pop').forEach(function (x) { x.classList.add('hidden'); }); } });
  document.addEventListener('submit', function (e) { var c = e.target.dataset.confirm; if (c && !window.confirm(c)) e.preventDefault(); });
  document.addEventListener('input', function (e) {
    var f = e.target.dataset && e.target.dataset.filter; if (!f) return;
    var q = e.target.value.toLowerCase();
    $$(f).forEach(function (row) { row.classList.toggle('hidden', q && row.textContent.toLowerCase().indexOf(q) < 0); });
  });

  /* ---------- boot ---------- */
  document.addEventListener('DOMContentLoaded', function () {
    paint(document);
    $$('[data-flash="ok"]').forEach(function (a) { toast(a.textContent.trim()); a.remove(); });
  });
})();
