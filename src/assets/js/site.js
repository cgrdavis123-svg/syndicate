(function () {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const dpr = Math.min(window.devicePixelRatio || 1, 2);

  $$('[data-year]').forEach(el => { el.textContent = new Date().getFullYear(); });

  /* ---------- Nav, progress bar ---------- */
  const nav = $('#nav'), progress = $('#progress');
  const onScroll = () => {
    nav.classList.toggle('scrolled', window.scrollY > 12);
    const max = document.documentElement.scrollHeight - innerHeight;
    progress.style.transform = 'scaleX(' + (max > 0 ? window.scrollY / max : 0) + ')';
  };
  onScroll(); window.addEventListener('scroll', onScroll, { passive: true });

  const menuBtn = $('#menuBtn'), menu = $('#mobileMenu');
  menuBtn.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    menuBtn.setAttribute('aria-expanded', open);
    nav.classList.toggle('solid', open);
  });

  /* ---------- Reveal on scroll ---------- */
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
  }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
  $$('.reveal, .model').forEach(el => io.observe(el));

  /* ---------- Spotlight cards ---------- */
  $$('.spot').forEach(card => card.addEventListener('pointermove', e => {
    const r = card.getBoundingClientRect();
    card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
    card.style.setProperty('--my', (e.clientY - r.top) + 'px');
  }));

  /* ---------- Count-up numbers: data-count="10" data-prefix="$" data-suffix="M+" ---------- */
  const fmt = el => (el.dataset.prefix || '') + '{n}' + (el.dataset.suffix || '');
  const cio = new IntersectionObserver(entries => entries.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target, to = parseFloat(el.dataset.count), tpl = fmt(el), dur = 1600, t0 = performance.now();
    cio.unobserve(el);
    if (reduced) { el.textContent = tpl.replace('{n}', to); return; }
    const step = now => {
      const p = Math.min(1, (now - t0) / dur), v = to * (1 - Math.pow(1 - p, 4));
      el.textContent = tpl.replace('{n}', Number.isInteger(to) ? Math.round(v) : v.toFixed(1));
      if (p < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  }), { threshold: 0.5 });
  $$('[data-count]').forEach(el => cio.observe(el));

  /* ---------- Cycling helpers ---------- */
  function cycle(items, cls, ms) {
    if (!items.length || reduced) return;
    let i = 0, hover = false;
    items.forEach(x => {
      x.addEventListener('mouseenter', () => { hover = true; items.forEach(y => y.classList.remove(cls)); });
      x.addEventListener('mouseleave', () => { hover = false; });
    });
    setInterval(() => {
      if (hover) return;
      items.forEach(y => y.classList.remove(cls));
      items[i].classList.add(cls); i = (i + 1) % items.length;
    }, ms);
  }
  cycle($$('.stack .layer'), 'active', 1800);
  cycle($$('.fw-steps li'), 'active', 2200);

  const rot = $$('.rotator span');
  if (rot.length && !reduced) {
    let wi = 0;
    setInterval(() => { rot[wi].classList.remove('on'); wi = (wi + 1) % rot.length; rot[wi].classList.add('on'); }, 2600);
  }

  const log = $('#log');
  if (log && !reduced) {
    const lines = JSON.parse(log.dataset.lines || '[]');
    let li = 0;
    setInterval(() => {
      log.innerHTML = '<div class="log-line"><span class="ok">✓</span> ' + lines[li] + '</div>';
      li = (li + 1) % lines.length;
    }, 2400);
  }

  /* ---------- Phone mock (Creator Ops Media) ---------- */
  const feed = $('#phFeed'), toast = $('#phToast');
  if (feed && !reduced) {
    const msgs = [['maya', 'just ordered 🔥'], ['jordan', 'is this still on sale?'], ['k.lee', 'added to cart'], ['devon', 'need this'], ['ari', 'ordered 2!'], ['sam', 'restock when??']];
    let mi = 0;
    setInterval(() => {
      const d = document.createElement('div');
      d.innerHTML = '<b>' + msgs[mi][0] + '</b> ' + msgs[mi][1];
      feed.appendChild(d);
      while (feed.children.length > 4) feed.removeChild(feed.firstChild);
      mi = (mi + 1) % msgs.length;
    }, 1500);
    setInterval(() => { toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 1800); }, 4200);
  }

  /* ---------- HQ clock ---------- */
  const tEl = $('#hqTime');
  if (tEl) {
    const dEl = $('#hqDate'), sEl = $('#hqStatus');
    const tick = () => {
      const now = new Date(), tz = 'America/New_York';
      tEl.textContent = now.toLocaleTimeString('en-US', { timeZone: tz, hour12: false });
      dEl.textContent = now.toLocaleDateString('en-US', { timeZone: tz, weekday: 'long', month: 'long', day: 'numeric' }) + ' · Eastern Time';
      const parts = new Intl.DateTimeFormat('en-US', { timeZone: tz, hour: 'numeric', hour12: false, weekday: 'short' }).formatToParts(now);
      const h = +parts.find(p => p.type === 'hour').value % 24, wd = parts.find(p => p.type === 'weekday').value;
      const open = !['Sat', 'Sun'].includes(wd) && h >= 9 && h < 18;
      sEl.classList.toggle('open', open);
      sEl.lastElementChild.textContent = open ? 'OPEN' : 'AFTER HOURS';
    };
    tick(); setInterval(tick, 1000);
  }

  /* ---------- Story scrollspy ---------- */
  const spyLinks = $$('.story-aside nav a');
  if (spyLinks.length) {
    const sio = new IntersectionObserver(entries => entries.forEach(e => {
      if (e.isIntersecting) spyLinks.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + e.target.id));
    }), { rootMargin: '-40% 0px -55% 0px' });
    spyLinks.forEach(a => { const t = $(a.getAttribute('href')); if (t) sio.observe(t); });
  }

  /* ---------- Canvas: node field ---------- */
  const canvases = [];
  const field = $('#field');
  if (field) {
    const ctx = field.getContext('2d'), host = field.parentElement;
    let W, H, pts = [], mouse = { x: -9999, y: -9999 };
    const size = () => {
      const r = field.getBoundingClientRect(); W = r.width; H = r.height;
      field.width = W * dpr; field.height = H * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
      const n = Math.round(Math.min(90, W * H / 16000));
      pts = Array.from({ length: n }, () => ({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .25, vy: (Math.random() - .5) * .25, r: Math.random() * 1.4 + .4, a: Math.random() < .12 }));
    };
    const draw = () => {
      ctx.clearRect(0, 0, W, H);
      ctx.strokeStyle = 'rgba(255,255,255,0.035)'; ctx.lineWidth = 1;
      for (let x = 0; x < W; x += 64) { ctx.beginPath(); ctx.moveTo(x + .5, 0); ctx.lineTo(x + .5, H); ctx.stroke(); }
      for (let y = 0; y < H; y += 64) { ctx.beginPath(); ctx.moveTo(0, y + .5); ctx.lineTo(W, y + .5); ctx.stroke(); }
      for (let i = 0; i < pts.length; i++) {
        const p = pts[i];
        if (!reduced) { p.x += p.vx; p.y += p.vy; if (p.x < 0 || p.x > W) p.vx *= -1; if (p.y < 0 || p.y > H) p.vy *= -1; }
        for (let j = i + 1; j < pts.length; j++) {
          const q = pts[j], dx = p.x - q.x, dy = p.y - q.y, d = dx * dx + dy * dy;
          if (d < 15000) { ctx.strokeStyle = 'rgba(198,255,61,' + (0.12 * (1 - d / 15000)) + ')'; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(q.x, q.y); ctx.stroke(); }
        }
        const md = Math.hypot(p.x - mouse.x, p.y - mouse.y);
        if (md < 160) { ctx.strokeStyle = 'rgba(198,255,61,' + (0.35 * (1 - md / 160)) + ')'; ctx.beginPath(); ctx.moveTo(p.x, p.y); ctx.lineTo(mouse.x, mouse.y); ctx.stroke(); }
        ctx.fillStyle = p.a ? 'rgba(198,255,61,.9)' : 'rgba(255,255,255,.35)';
        ctx.beginPath(); ctx.arc(p.x, p.y, p.a ? p.r + .8 : p.r, 0, Math.PI * 2); ctx.fill();
      }
    };
    host.addEventListener('pointermove', e => { const r = field.getBoundingClientRect(); mouse.x = e.clientX - r.left; mouse.y = e.clientY - r.top; });
    host.addEventListener('pointerleave', () => { mouse.x = mouse.y = -9999; });
    canvases.push({ el: field, size, draw });
  }

  /* ---------- Canvas: HQ dot map ---------- */
  const map = $('#map');
  if (map) {
    const mx = map.getContext('2d'), t0 = performance.now();
    let MW, MH;
    const size = () => { const r = map.getBoundingClientRect(); MW = r.width; MH = r.height; map.width = MW * dpr; map.height = MH * dpr; mx.setTransform(dpr, 0, 0, dpr, 0, 0); };
    const draw = now => {
      mx.clearRect(0, 0, MW, MH);
      const cx = MW * .68, cy = MH * .42, gap = 14;
      for (let x = gap / 2; x < MW; x += gap) for (let y = gap / 2; y < MH; y += gap) {
        const n = Math.sin(x * .021) * Math.cos(y * .027) + Math.sin((x + y) * .011);
        if (n < -.15) continue;
        mx.fillStyle = 'rgba(255,255,255,' + Math.max(.04, .32 - Math.hypot(x - cx, y - cy) / (MW * 1.1)) + ')';
        mx.fillRect(x, y, 1.6, 1.6);
      }
      const ends = [[cx + MW * .2, cy - MH * .18, 'CLT'], [cx - MW * .42, cy + MH * .3, 'ATL'], [cx - MW * .1, cy - MH * .3, 'AVL']];
      mx.setLineDash([3, 5]); mx.lineWidth = 1.2;
      mx.font = '500 10px "JetBrains Mono", monospace';
      ends.forEach(([ex, ey, lb]) => {
        mx.strokeStyle = 'rgba(198,255,61,.35)';
        mx.beginPath(); mx.moveTo(cx, cy); mx.quadraticCurveTo((cx + ex) / 2, Math.min(cy, ey) - 40, ex, ey); mx.stroke();
        mx.fillStyle = 'rgba(255,255,255,.6)'; mx.beginPath(); mx.arc(ex, ey, 2.5, 0, Math.PI * 2); mx.fill();
        mx.fillStyle = 'rgba(255,255,255,.4)'; mx.fillText(lb, ex + 8, ey + 3);
      });
      mx.setLineDash([]);
      const t = (Math.max(0, now - t0) / 2000) % 1;
      for (let k = 0; k < 2; k++) {
        const tt = (t + k * .5) % 1;
        mx.strokeStyle = 'rgba(198,255,61,' + (0.6 * (1 - tt)) + ')';
        mx.beginPath(); mx.arc(cx, cy, 6 + tt * 50, 0, Math.PI * 2); mx.stroke();
      }
      mx.fillStyle = '#c6ff3d'; mx.shadowColor = '#c6ff3d'; mx.shadowBlur = 18;
      mx.beginPath(); mx.arc(cx, cy, 5, 0, Math.PI * 2); mx.fill(); mx.shadowBlur = 0;
      mx.font = '500 11px "JetBrains Mono", monospace'; mx.fillStyle = 'rgba(255,255,255,.85)';
      mx.fillText('SPARTANBURG · HQ', cx + 14, cy + 4);
    };
    canvases.push({ el: map, size, draw });
  }

  if (canvases.length) {
    const visible = new Set();
    const vio = new IntersectionObserver(es => es.forEach(e => e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)));
    canvases.forEach(c => { c.size(); vio.observe(c.el); c.draw(performance.now()); });
    let rT; window.addEventListener('resize', () => { clearTimeout(rT); rT = setTimeout(() => canvases.forEach(c => { c.size(); c.draw(performance.now()); }), 150); });
    if (!reduced) {
      const loop = now => { canvases.forEach(c => visible.has(c.el) && c.draw(now)); requestAnimationFrame(loop); };
      requestAnimationFrame(loop);
    }
  }

  /* ---------- Cookie consent ----------
     Only strictly necessary cookies are used. Analytics is our own cookie-free site stats, so it's
     on unless the visitor turns it off; marketing stays off unless they turn it on. Global Privacy
     Control forces both off. Scripts check window.SyndicateConsent.get('analytics' | 'marketing'). */
  const KEY = 'syn_consent', MAX_AGE = 365 * 864e5;
  const gpc = navigator.globalPrivacyControl === true;
  const readConsent = () => {
    try {
      const c = JSON.parse(localStorage.getItem(KEY));
      if (!c || Date.now() - c.ts >= MAX_AGE) return null;
      if (c.v === 1) c.analytics = true; // choices saved before analytics existed didn't cover it
      return c;
    } catch (e) { return null; }
  };
  let consent = readConsent();
  const listeners = [];
  const saveConsent = c => {
    consent = { v: 2, ts: Date.now(), analytics: !gpc && c.analytics !== false, marketing: !gpc && !!c.marketing };
    try { localStorage.setItem(KEY, JSON.stringify(consent)); } catch (e) {}
    listeners.forEach(fn => fn(consent));
  };
  window.SyndicateConsent = {
    get: cat => cat === 'necessary' || (!gpc && (cat === 'analytics' ? !consent || consent.analytics !== false : !!consent && !!consent[cat])),
    onChange: fn => listeners.push(fn),
  };
  const banner = $('#cookieBanner'), dlg = $('#cookieDialog');
  if (banner && dlg) {
    const boxes = $$('[data-consent]', dlg);
    if (!consent) banner.hidden = false;
    const closeDlg = () => dlg.classList.remove('open');
    const openDlg = () => {
      boxes.forEach(b => { b.checked = window.SyndicateConsent.get(b.dataset.consent); b.disabled = gpc; });
      $('#gpcNote').hidden = !gpc;
      dlg.classList.add('open'); (boxes[0] || $('#cookieSave')).focus();
    };
    const done = c => { saveConsent(c); banner.hidden = true; closeDlg(); };
    $('#cookieOk').addEventListener('click', () => done({ analytics: true }));
    $('#cookieReject').addEventListener('click', () => done({ analytics: false, marketing: false }));
    $('#cookieSave').addEventListener('click', () => done(Object.fromEntries(boxes.map(b => [b.dataset.consent, b.checked]))));
    $$('[data-cookie-settings]').forEach(b => b.addEventListener('click', openDlg));
    dlg.addEventListener('click', e => { if (e.target === dlg) closeDlg(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && dlg.classList.contains('open')) closeDlg(); });
  }

  /* ---------- Site stats (cookie-free, from crm.stusyn.com) ---------- */
  const stats = $('meta[name="syn-stats"]');
  const loadStats = () => {
    if (!stats || !stats.dataset.site || window.__synLoaded || $('script[data-site]')) return;
    const s = document.createElement('script');
    s.src = stats.content; s.defer = true; s.dataset.site = stats.dataset.site;
    document.head.appendChild(s);
  };
  if (window.SyndicateConsent.get('analytics')) loadStats();
  window.SyndicateConsent.onChange(() => { window.synOff = !window.SyndicateConsent.get('analytics'); if (!window.synOff) loadStats(); });

  /* ---------- Command palette ---------- */
  const pages = [
    ['Home', 'index.html', 'holding company'], ['Our story', 'about.html', 'founding'], ['Portfolio', 'portfolio.html', 'companies'],
    ['Creator Ops Media', 'creator-ops-media.html', 'tiktok shop consulting'], ['Syndicate Marketing', 'syndicate-marketing.html', 'web design paid ads social analytics'],
    ['Ideas', 'ideas.html', 'bring an idea to life'], ['Careers', 'careers.php', 'jobs'], ['Contact', 'contact.html', 'email'],
    ['Bring us an idea', 'contact.html?topic=idea', 'pitch'], ['Book a TikTok Shop consultation', 'contact.html?topic=consult', 'creator ops grady $150'],
    ['Order a one-page website', 'one-page-website.html', 'buy landing page site 350 checkout stripe syndicatemarketing.co'], ['Get a website quote', 'contact.html?topic=web', 'web design build site syndicate marketing'], ['Plan an ad campaign', 'contact.html?topic=ads', 'google meta facebook instagram tiktok ads ppc'], ['Work with Syndicate Marketing', 'contact.html?topic=marketing', 'social seo analytics'], ['Founder story', 'about.html#founder', 'grady davis'], ['Grady Davis resume', 'grady.html', 'founder cv marketing'],
    ['Privacy policy', 'privacy.html', 'legal data'], ['Cookie policy', 'cookies.html', 'legal'], ['Terms of use', 'terms.html', 'legal'],
    ['Applicant privacy', 'applicant-privacy.html', 'legal careers'], ['Accessibility', 'accessibility.html', 'ada wcag']
  ];
  const ck = $('#cmdk'), ckIn = $('#cmdkInput'), ckList = $('#cmdkList');
  let sel = 0, shown = [];
  const render = () => {
    const q = ckIn.value.trim().toLowerCase();
    shown = pages.filter(p => !q || (p[0] + ' ' + p[2]).toLowerCase().includes(q));
    sel = Math.min(sel, Math.max(0, shown.length - 1));
    ckList.innerHTML = shown.length ? shown.map((p, i) => '<li role="option" class="' + (i === sel ? 'sel' : '') + '"><a href="' + p[1] + '"><span>' + p[0] + '</span><span>' + p[2] + '</span></a></li>').join('') : '<li class="cmdk-empty">No matches</li>';
  };
  const openCk = () => { ck.classList.add('open'); ckIn.value = ''; sel = 0; render(); ckIn.focus(); };
  const closeCk = () => ck.classList.remove('open');
  $('#cmdkBtn').addEventListener('click', openCk);
  ck.addEventListener('click', e => { if (e.target === ck) closeCk(); });
  ckIn.addEventListener('input', () => { sel = 0; render(); });
  document.addEventListener('keydown', e => {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); ck.classList.contains('open') ? closeCk() : openCk(); return; }
    if (!ck.classList.contains('open')) return;
    if (e.key === 'Escape') closeCk();
    else if (e.key === 'ArrowDown') { e.preventDefault(); sel = (sel + 1) % Math.max(1, shown.length); render(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); sel = (sel - 1 + shown.length) % Math.max(1, shown.length); render(); }
    else if (e.key === 'Enter' && shown[sel]) { location.href = shown[sel][1]; closeCk(); }
  });

  /* ---------- Copy email ---------- */
  $$('[data-copy]').forEach(el => el.addEventListener('click', e => {
    if (!navigator.clipboard) return;
    e.preventDefault();
    navigator.clipboard.writeText(el.dataset.copy).then(() => {
      const lab = $('.copy', el); if (!lab) return;
      const old = lab.textContent; lab.textContent = 'COPIED ✓'; setTimeout(() => { lab.textContent = old; }, 1600);
    });
  }));

  /* ---------- Contact form: sends to contact.php, falls back to a prefilled email ---------- */
  const form = $('#contactForm');
  if (form) {
    const topic = new URLSearchParams(location.search).get('topic');
    if (topic) { const r = $('input[name="topic"][value="' + topic + '"]', form); if (r) r.checked = true; }
    const note = $('#formNote'), btn = $('button[type="submit"]', form);
    const mailtoFallback = d => {
      const t = $('input[name="topic"]:checked', form);
      const label = t ? $('label[for="' + t.id + '"]', form).textContent.trim() : 'General';
      const subject = '[' + label + '] ' + (d.get('company') || d.get('name') || 'Hello');
      const body = ['Name: ' + d.get('name'), 'Email: ' + d.get('email'), d.get('company') ? 'Company: ' + d.get('company') : null, '', d.get('message')].filter(l => l !== null).join('\n');
      location.href = 'mailto:' + form.dataset.to + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
      note.textContent = 'Opening your email app… If nothing happens, email us at ' + form.dataset.to + '.';
    };
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (!form.reportValidity()) return;
      const d = new FormData(form);
      btn.disabled = true; note.classList.remove('bad'); note.textContent = 'Sending…';
      try {
        const res = await fetch('contact.php', { method: 'POST', body: d, headers: { Accept: 'application/json' } });
        const json = await res.json();
        if (json.ok) {
          form.innerHTML = '<div class="success-panel" role="status" style="margin:0"><div class="success-ico"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12.5 10 17l9-10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h2>Message sent.</h2><p>Thanks for reaching out. A real person will read it and reply to you by email.</p></div>';
          return;
        }
        note.textContent = json.error || 'Something went wrong. Please try again.'; note.classList.add('bad');
        const ts = $('.cf-turnstile', form); if (ts && window.turnstile) window.turnstile.reset(ts); // tokens work once
      } catch (err) {
        mailtoFallback(d); // no server (static preview) — use the email app instead
      }
      btn.disabled = false;
    });
  }

  /* ---------- Careers: filter job list ---------- */
  const jobList = $('#jobList');
  if (jobList) {
    const rows = $$('.job-row', jobList), chips = $$('#jobTools .chip'), wp = $('#jobWorkplace'), q = $('#jobQuery'), empty = $('#jobsEmpty');
    let company = '';
    const apply = () => {
      const term = q.value.trim().toLowerCase(); let shown = 0;
      rows.forEach(r => {
        const ok = (!company || r.dataset.company === company) && (!wp.value || r.dataset.workplace === wp.value) && (!term || r.dataset.text.includes(term));
        r.hidden = !ok; if (ok) { shown++; r.classList.add('in'); }
      });
      empty.hidden = shown > 0;
    };
    chips.forEach(c => c.addEventListener('click', () => { chips.forEach(x => x.classList.remove('on')); c.classList.add('on'); company = c.dataset.company; apply(); }));
    wp.addEventListener('change', apply); q.addEventListener('input', apply);
  }

  /* ---------- Job application form ---------- */
  const file = $('#a-resume');
  if (file) {
    const zone = $('#dropzone'), txt = $('#dropText');
    const show = () => { const f = file.files[0]; if (f) { txt.innerHTML = '<b></b> · ' + (f.size / 1048576).toFixed(1) + ' MB'; txt.firstChild.textContent = f.name; zone.classList.add('has'); zone.classList.remove('bad'); } };
    file.addEventListener('change', show);
    ['dragenter', 'dragover'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(ev => zone.addEventListener(ev, e => { e.preventDefault(); zone.classList.remove('over'); }));
    zone.addEventListener('drop', e => { if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; show(); } });
    const applyForm = $('#apply');
    const btn = $('#applyBtn'), btnLabel = btn ? btn.firstChild.textContent : '';
    const max = +(($('input[name="MAX_FILE_SIZE"]', applyForm || document) || {}).value || 0);
    applyForm && applyForm.addEventListener('submit', e => {
      const f = file.files[0];
      if (f && max && f.size > max) {
        e.preventDefault(); zone.classList.add('bad');
        txt.textContent = 'This file is ' + (f.size / 1048576).toFixed(1) + ' MB. Please attach one under ' + Math.floor(max / 1048576) + ' MB.';
        return;
      }
      btn.disabled = true; btn.firstChild.textContent = 'Submitting… ';
    });
    // Coming back with the Back button shouldn't leave the button stuck on "Submitting…".
    window.addEventListener('pageshow', () => { if (btn) { btn.disabled = false; btn.firstChild.textContent = btnLabel; } });
  }
  $$('[data-copy-link]').forEach(b => b.addEventListener('click', () => {
    const done = () => { const t = b.textContent; b.textContent = 'Link copied ✓'; setTimeout(() => { b.textContent = t; }, 1600); };
    navigator.clipboard ? navigator.clipboard.writeText(b.dataset.copyLink).then(done, () => prompt('Copy this link:', b.dataset.copyLink)) : prompt('Copy this link:', b.dataset.copyLink);
  }));
})();
