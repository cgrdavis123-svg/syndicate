(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const csrf = ($('meta[name="csrf"]') || {}).content || ($('input[name="csrf"]') || {}).value || '';
  const money = n => '$' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const api = (data) => {
    const body = new FormData();
    Object.entries(data).forEach(([k, v]) => body.append(k, v));
    body.append('csrf', csrf);
    return fetch('api.php', { method: 'POST', body, credentials: 'same-origin' }).then(r => r.json());
  };
  const toast = msg => {
    const el = $('.toast') || document.body.appendChild(Object.assign(document.createElement('div'), { className: 'toast', role: 'status' }));
    el.classList.remove('gone'); el.textContent = msg;
    clearTimeout(toast.t); toast.t = setTimeout(() => el.classList.add('gone'), 3000);
  };

  /* Navigation & chrome */
  const side = $('#side'), menuBtn = $('#menuBtn');
  menuBtn && menuBtn.addEventListener('click', e => { e.stopPropagation(); side.classList.toggle('open'); });
  const quickBtn = $('#quickBtn'), quickMenu = $('#quickMenu');
  quickBtn && quickBtn.addEventListener('click', e => { e.stopPropagation(); const open = quickMenu.hidden; quickMenu.hidden = !open; quickBtn.setAttribute('aria-expanded', open); });
  document.addEventListener('click', e => {
    if (quickMenu && !quickMenu.hidden && !e.target.closest('.quick')) { quickMenu.hidden = true; quickBtn.setAttribute('aria-expanded', 'false'); }
    if (side && side.classList.contains('open') && !e.target.closest('#side')) side.classList.remove('open');
  });
  document.addEventListener('keydown', e => {
    const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName);
    if (e.key === '/' && !typing) { const s = $('#searchInput'); if (s) { e.preventDefault(); s.focus(); s.select(); } }
    if (e.key === 'Escape' && quickMenu) quickMenu.hidden = true;
  });
  const t = $('.toast'); if (t) setTimeout(() => t.classList.add('gone'), 3500);

  /* Generic behaviors */
  document.addEventListener('submit', e => {
    const btn = e.submitter, msg = (btn && btn.dataset.confirm) || e.target.dataset.confirm;
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  $$('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
  $$('tr[data-href]').forEach(tr => tr.addEventListener('click', e => { if (!e.target.closest('a, button, input, select, label')) location.href = tr.dataset.href; }));
  $$('[data-fill]').forEach(b => b.addEventListener('click', () => { const t = $(b.dataset.fill); if (t) { t.value = b.dataset.text; t.focus(); } }));
  $$('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
  $$('select[data-go]').forEach(sel => sel.addEventListener('change', () => { const o = sel.options[sel.selectedIndex]; if (o && o.dataset.href) location.href = o.dataset.href; }));
  $$('[data-copy]').forEach(b => b.addEventListener('click', () => {
    const src = $(b.dataset.copy); if (!src) return;
    const done = () => { const t0 = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => { b.textContent = t0; }, 1500); };
    if (navigator.clipboard) navigator.clipboard.writeText(src.value).then(done, () => { src.select(); });
    else { src.select(); document.execCommand('copy'); done(); }
  }));

  /* Task checkboxes */
  $$('.tasks li[data-task] .check').forEach(btn => btn.addEventListener('click', () => {
    const li = btn.closest('li');
    api({ action: 'toggle_task', id: li.dataset.task }).then(r => {
      if (!r.ok) return toast(r.error || 'Could not update the task.');
      btn.classList.toggle('on', r.done); li.classList.toggle('done', r.done);
      btn.setAttribute('aria-label', r.done ? 'Mark not done' : 'Mark done');
      if (r.done) toast('Task done.');
    }).catch(() => toast('Could not reach the server.'));
  }));

  /* Pipeline board drag & drop */
  const board = $('#board');
  if (board) {
    let dragged = null;
    $$('.deal', board).forEach(card => {
      card.addEventListener('dragstart', e => { dragged = card; card.classList.add('dragging'); e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', card.dataset.id); });
      card.addEventListener('dragend', () => { card.classList.remove('dragging'); $$('.col-body', board).forEach(c => c.classList.remove('over')); });
    });
    const after = (col, y) => $$('.deal:not(.dragging)', col).find(c => { const r = c.getBoundingClientRect(); return y < r.top + r.height / 2; });
    $$('.col-body', board).forEach(col => {
      col.addEventListener('dragover', e => {
        if (!dragged) return;
        e.preventDefault(); col.classList.add('over');
        const ref = after(col, e.clientY), add = $('.add-card', col);
        col.insertBefore(dragged, ref || add || null);
      });
      col.addEventListener('dragleave', e => { if (!col.contains(e.relatedTarget)) col.classList.remove('over'); });
      col.addEventListener('drop', e => {
        e.preventDefault(); col.classList.remove('over');
        if (!dragged) return;
        const stage = col.dataset.drop, order = $$('.deal', col).map(c => c.dataset.id).join(',');
        api({ action: 'move_deal', id: dragged.dataset.id, stage, order }).then(r => {
          if (!r.ok) { toast(r.error || 'Could not move the deal.'); location.reload(); return; }
          $$('.col', board).forEach(c => {
            const cards = $$('.deal', c), countEl = $('header .num', c);
            if (countEl) countEl.textContent = cards.length;
          });
          toast('Moved to ' + $('header .pill', col.parentElement).textContent.trim() + '.');
        }).catch(() => { toast('Could not reach the server.'); location.reload(); });
        dragged = null;
      });
    });
  }

  /* Invoice line items */
  const items = $('#items');
  if (items) {
    const tpl = $('#itemTpl'), tax = $('#taxIn');
    const recalc = () => {
      let sub = 0;
      $$('.item:not(.head)', items).forEach(row => {
        const q = parseFloat($('[name="qty[]"]', row).value) || 0, p = parseFloat($('[name="price[]"]', row).value) || 0;
        $('.amt', row).textContent = money(q * p); sub += q * p;
      });
      const tx = Math.round(sub * (parseFloat(tax.value) || 0)) / 100;
      $('#subOut').textContent = money(sub); $('#taxOut').textContent = money(tx); $('#totOut').textContent = money(sub + tx);
    };
    items.addEventListener('input', recalc); tax.addEventListener('input', recalc);
    items.addEventListener('click', e => {
      const x = e.target.closest('.x'); if (!x) return;
      const rows = $$('.item:not(.head)', items);
      if (rows.length > 1) x.closest('.item').remove(); else $$('input', x.closest('.item')).forEach(i => { if (i.type !== 'hidden') i.value = i.name === 'qty[]' ? 1 : ''; else i.value = 0; });
      recalc();
    });
    $('#addItem').addEventListener('click', () => { items.appendChild(tpl.content.cloneNode(true)); $$('.item', items).pop().querySelector('input').focus(); recalc(); });
    recalc();
  }

  /* Recurring invoice fields */
  const repeatIn = $('#repeatIn');
  if (repeatIn) {
    const next = $('#repeatNext'), issue = $('[name="issue_date"]');
    const step = { week: [0, 7], month: [1, 0], quarter: [3, 0], year: [12, 0] };
    const upd = () => {
      const on = !!repeatIn.value;
      $$('[data-repeat-only]').forEach(el => { el.hidden = !on; });
      if (on && !next.value && issue.value) {
        const [m, d] = step[repeatIn.value], [y, mo, da] = issue.value.split('-').map(Number);
        const last = new Date(Date.UTC(y, mo - 1 + m + 1, 0)).getUTCDate();
        const dt = new Date(Date.UTC(y, mo - 1 + m, Math.min(da, last) + d));
        if (dt.toISOString().slice(0, 10) >= next.dataset.min) next.value = dt.toISOString().slice(0, 10);
      }
    };
    repeatIn.addEventListener('change', upd); upd();
  }

  /* Inbox: check for new mail in the background, every couple of minutes */
  const badge = n => {
    const link = $('.nav a[href="inbox.php"]'); if (!link) return;
    let b = $('.badge', link);
    if (!n) { if (b) b.remove(); return; }
    if (!b) b = link.appendChild(Object.assign(document.createElement('span'), { className: 'badge' }));
    b.textContent = n;
  };
  const checkMail = () => api({ action: 'mail_sync' }).then(r => {
    if (!r.ok || r.skipped) return;
    badge(r.unread);
    const box = $('#newMail');
    if (box && r.new) {
      box.hidden = false; box.textContent = '';
      box.append(r.new + ' new email' + (r.new === 1 ? '' : 's') + '. ');
      const a = Object.assign(document.createElement('a'), { href: location.href, textContent: 'Show them' }); box.append(a);
    } else if (r.new) toast(r.new + ' new email' + (r.new === 1 ? '' : 's') + '.');
  }).catch(() => {});
  if ($('meta[name="mail-due"]')) checkMail();
  if ($('meta[name="csrf"]') && $('.nav a[href="inbox.php"]')) setInterval(() => { if (!document.hidden) checkMail(); }, 150000);

  /* Inbox: select conversations to archive */
  const list = $('#mailList');
  if (list) {
    const boxes = () => $$('input[name="t[]"]', list), all = $('#selAll'), btn = $('#archiveBtn');
    const upd = () => { const n = boxes().filter(b => b.checked).length; if (btn) { btn.disabled = !n; btn.textContent = n ? 'Archive ' + n : 'Archive'; } };
    list.addEventListener('change', e => { if (e.target === all) boxes().forEach(b => { b.checked = all.checked; }); upd(); });
  }

  /* Email frames grow to fit the message (they can't run scripts of their own) */
  $$('iframe.mail-frame').forEach(f => {
    const fit = () => { try { const d = f.contentDocument; if (d && d.documentElement) f.style.height = Math.max(80, d.documentElement.scrollHeight) + 'px'; } catch (e) {} };
    f.addEventListener('load', () => {
      fit();
      try { $$('img', f.contentDocument).forEach(i => i.addEventListener('load', fit)); if (window.ResizeObserver) new ResizeObserver(fit).observe(f.contentDocument.body); } catch (e) {}
    });
    const d = f.closest('details'); if (d) d.addEventListener('toggle', fit);
  });

  /* Attachments: show what's picked, and stop files the server won't accept */
  $$('.file-pick input[type="file"]').forEach(inp => inp.addEventListener('change', () => {
    const label = inp.nextElementSibling, max = +inp.dataset.max || 0;
    const total = [...inp.files].reduce((n, f) => n + f.size, 0);
    if (max && total > max) {
      inp.value = ''; label.textContent = 'Attach files';
      return toast('Those files add up to ' + (total / 1048576).toFixed(1) + ' MB. The limit here is ' + (max / 1048576).toFixed(0) + ' MB.');
    }
    label.textContent = inp.files.length ? inp.files.length === 1 ? inp.files[0].name : inp.files.length + ' files' : 'Attach files';
  }));
  $$('form.reply-box, form.compose').forEach(f => f.addEventListener('submit', e => {
    if (e.defaultPrevented) return;
    const b = $('button[type="submit"]', f); if (b) { b.disabled = true; b.textContent = 'Sending…'; }
  }));
  window.addEventListener('pageshow', () => $$('form.reply-box button[type="submit"], form.compose button[type="submit"]').forEach(b => { b.disabled = false; b.textContent = 'Send'; }));

  /* Site stats: chart hover, live visitors, install check */
  $$('.an-chart').forEach(svg => {
    const pts = JSON.parse(svg.dataset.points || '[]'), zone = $('.hover-zone', svg), line = $('.hover-line', svg), tip = svg.parentElement.querySelector('.an-tip');
    if (!zone || !pts.length) return;
    const L = +zone.dataset.l, R = +zone.dataset.r, W = +zone.dataset.w, n = pts.length;
    const fmt = v => v.toLocaleString('en-US');
    const move = e => {
      const box = svg.getBoundingClientRect(), x = (e.clientX - box.left) / box.width * W;
      const i = Math.max(0, Math.min(n - 1, Math.round(n === 1 ? 0 : (x - L) / ((W - L - R) / (n - 1)))));
      const px = n === 1 ? L + (W - L - R) / 2 : L + i * (W - L - R) / (n - 1);
      line.setAttribute('x1', px); line.setAttribute('x2', px); line.setAttribute('visibility', 'visible');
      const p = pts[i]; tip.hidden = false;
      tip.innerHTML = '<b></b><span><i class="lg-vis"></i>Visitors <em></em></span><span><i class="lg-pv"></i>Pageviews <em></em></span><span>Visits <em></em></span>';
      tip.querySelector('b').textContent = p[0]; const em = tip.querySelectorAll('em'); em[0].textContent = fmt(p[1]); em[1].textContent = fmt(p[2]); em[2].textContent = fmt(p[3]);
      const left = px / W * box.width; tip.style.left = Math.min(box.width - tip.offsetWidth - 4, Math.max(4, left + 12)) + 'px';
    };
    zone.addEventListener('mousemove', move);
    zone.addEventListener('touchstart', e => move(e.touches[0]), { passive: true });
    zone.addEventListener('mouseleave', () => { tip.hidden = true; line.setAttribute('visibility', 'hidden'); });
  });
  const livePill = $('.live-pill[data-live]'), liveStatus = $('.live-status[data-site]');
  const getLive = (el) => el.dataset.live && el.dataset.live !== 'api.php'
    ? fetch(el.dataset.live, { credentials: 'same-origin' }).then(r => r.json())
    : api({ action: 'an_live', site: el.dataset.site });
  if (livePill) {
    const tick = () => getLive(livePill).then(r => { if (!r.ok) return; $('b', livePill).textContent = r.now; livePill.classList.toggle('on', r.now > 0); livePill.title = r.pages && r.pages.length ? 'Now on: ' + r.pages.map(p => p.k + ' (' + p.n + ')').join(', ') : ''; }).catch(() => {});
    tick(); setInterval(() => { if (!document.hidden) tick(); }, 20000);
  }
  if (liveStatus) {
    const tick = () => api({ action: 'an_live', site: liveStatus.dataset.site }).then(r => {
      if (!r.ok || !r.last || +r.last === +liveStatus.dataset.last) return;
      liveStatus.dataset.last = r.last; liveStatus.innerHTML = '<span class="dot ok"></span>Receiving data · last visit just now';
      if (!liveStatus.dataset.toasted) { liveStatus.dataset.toasted = 1; toast('It works! A visit just came in.'); }
    }).catch(() => {});
    setInterval(() => { if (!document.hidden) tick(); }, 8000);
  }
  $$('[data-copy-text]').forEach(b => b.addEventListener('click', () => {
    const src = $(b.dataset.copyText); if (!src) return;
    const done = () => { const t0 = b.textContent; b.textContent = 'Copied ✓'; setTimeout(() => { b.textContent = t0; }, 1500); };
    const pick = () => { const r = document.createRange(); r.selectNodeContents(src); const s = getSelection(); s.removeAllRanges(); s.addRange(r); };
    if (navigator.clipboard) navigator.clipboard.writeText(src.textContent).then(done, pick); else { pick(); document.execCommand('copy'); done(); }
  }));
  const goalForm = $('.goal-form');
  if (goalForm) {
    const upd = () => { const k = $('input[name="kind"]:checked', goalForm).value; $$('[data-goal]', goalForm).forEach(i => { i.hidden = i.dataset.goal !== k; }); };
    $$('input[name="kind"]', goalForm).forEach(r => r.addEventListener('change', upd)); upd();
  }

  /* Consultation amount preview */
  const hoursIn = $('#hoursIn'), rateIn = $('#rateIn'), amountOut = $('#amountOut');
  if (hoursIn && rateIn && amountOut) {
    const upd = () => { amountOut.textContent = money((parseFloat(hoursIn.value) || 0) * (parseFloat(rateIn.value) || 0)); };
    hoursIn.addEventListener('input', upd); rateIn.addEventListener('input', upd);
  }

  /* Quote editor: line items, product picker, live totals */
  const qitems = $('#qitems');
  if (qitems) {
    const tpl = $('#qitemTpl'), dep = $('#depositPct'), picker = $('#addProduct'), steps = $('#qSteps'), days = $('#timelineDays');
    const products = picker ? JSON.parse(picker.dataset.products || '[]') : [];
    const recalc = () => {
      let one = 0, mo = 0, opt = 0;
      $$('.qitem:not(.head)', qitems).forEach(row => {
        const amt = (parseFloat($('[name="qty[]"]', row).value) || 0) * (parseFloat($('[name="price[]"]', row).value) || 0);
        const monthly = $('[name="interval[]"]', row).value === 'month', optional = $('[name="optional[]"]', row).value === '1';
        $('.amt', row).textContent = money(amt) + (monthly ? '/mo' : '');
        row.classList.toggle('is-opt', optional);
        if (optional) opt += amt; else if (monthly) mo += amt; else one += amt;
      });
      const pct = parseInt(dep.value, 10) || 0;
      $('#qOne').textContent = money(one); $('#qMo').textContent = money(mo) + '/mo'; $('#qOpt').textContent = money(opt);
      $('#qDue').textContent = money(Math.round(one * pct) / 100 + mo);
    };
    const addRow = () => { qitems.appendChild(tpl.content.cloneNode(true)); return $$('.qitem', qitems).pop(); };
    qitems.addEventListener('input', recalc); qitems.addEventListener('change', recalc); dep.addEventListener('change', recalc);
    qitems.addEventListener('click', e => {
      const x = e.target.closest('.x'); if (!x) return;
      if ($$('.qitem:not(.head)', qitems).length > 1) x.closest('.qitem').remove();
      else $$('input', x.closest('.qitem')).forEach(i => { i.value = i.name === 'qty[]' ? 1 : (i.type === 'hidden' ? 0 : ''); });
      recalc();
    });
    $('#addQItem').addEventListener('click', () => { addRow().querySelector('[name="desc[]"]').focus(); recalc(); });
    if (picker) picker.addEventListener('change', () => {
      const p = products.find(x => String(x.id) === picker.value); picker.value = ''; if (!p) return;
      let row = $$('.qitem:not(.head)', qitems).find(r => !$('[name="desc[]"]', r).value && !$('[name="price[]"]', r).value) || addRow();
      $('[name="product[]"]', row).value = p.id; $('[name="desc[]"]', row).value = p.name; $('[name="details[]"]', row).value = p.details;
      $('[name="price[]"]', row).value = p.price; $('[name="interval[]"]', row).value = p.interval === 'month' ? 'month' : '';
      const have = steps.value.split('\n').map(s => s.trim()).filter(Boolean);
      p.steps.forEach(s => { if (!have.includes(s)) have.push(s); });
      steps.value = have.join('\n');
      if (p.days > (parseInt(days.value, 10) || 0)) days.value = p.days;
      recalc();
    });
    recalc();
  }

  /* Client proposal page: pick extras, accept, pay */
  const qf = $('#acceptForm');
  if (qf && !qf.hasAttribute('data-closed')) {
    const pct = parseInt(qf.dataset.deposit, 10) || 0;
    const fmt = n => '$' + (Math.round(n * 100) / 100).toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    const totals = () => {
      let one = 0, mo = 0;
      $$('.q-amt[data-amt], .q-check input:checked', qf).forEach(el => { const a = parseFloat(el.dataset.amt) || 0; if (el.dataset.int === 'month') mo += a; else one += a; });
      const deposit = Math.round(one * pct) / 100, due = deposit + mo;
      const set = (id, v) => { const el = $('#' + id); if (el) el.textContent = v; };
      set('qsOne', fmt(one)); set('qsMo', fmt(mo) + '/mo'); set('qsDep', fmt(deposit)); set('qsDue', fmt(due)); set('qsLater', fmt(one - deposit)); set('qPayAmt', fmt(due));
      const moRow = $('#qsMoRow'); if (moRow) moRow.hidden = mo <= 0;
      const later = $('#qsLaterRow'); if (later) later.hidden = one - deposit <= 0;
    };
    $$('.q-check input', qf).forEach(cb => cb.addEventListener('change', () => { cb.closest('.q-item').classList.toggle('on', cb.checked); totals(); }));
    $$('.q-check input:checked', qf).forEach(cb => cb.closest('.q-item').classList.add('on'));
    qf.addEventListener('submit', e => {
      e.preventDefault();
      const err = $('#qErr'), btn = $('#qPay'), label = btn.innerHTML; err.hidden = true;
      if (!qf.reportValidity()) return;
      btn.disabled = true; btn.textContent = 'One moment…';
      fetch(qf.getAttribute('action'), { method: 'POST', body: new FormData(qf), headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json().catch(() => ({ ok: false })))
        .then(r => {
          if (!r.ok) throw new Error(r.error || 'Something went wrong. Please try again.');
          if (r.url) { btn.textContent = 'Opening secure payment…'; location.href = r.url; }
          else location.href = qf.getAttribute('action') + '&accepted=1';
        })
        .catch(x => { err.textContent = x.message; err.hidden = false; btn.disabled = false; btn.innerHTML = label; });
    });
    totals();
  }

  /* Public booking: month calendar and open times in the visitor's own time zone */
  $$('[data-local-time]').forEach(el => {
    try { const d = new Date(parseInt(el.dataset.localTime, 10) * 1000); el.textContent = d.toLocaleString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' }); } catch (e) {}
  });
  $$('.book-app').forEach(app => {
    const tz = (Intl.DateTimeFormat().resolvedOptions().timeZone) || 'America/New_York';
    const cal = $('.book-cal', app), times = $('.book-times', app), form = $('.book-form', app);
    const keyOf = ts => new Intl.DateTimeFormat('en-CA', { timeZone: tz, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(ts * 1000));
    const timeOf = ts => new Date(ts * 1000).toLocaleTimeString('en-US', { timeZone: tz, hour: 'numeric', minute: '2-digit' });
    const longOf = ts => new Date(ts * 1000).toLocaleString('en-US', { timeZone: tz, weekday: 'long', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', timeZoneName: 'short' });
    const tzName = tz.replace(/_/g, ' ');
    let byDay = {}, view = null, picked = null;
    // Bot check (Turnstile): drawn once the form is visible; its script may still be loading, so wait for it.
    const box = form && $('.cf-turnstile', form);
    let widget = null;
    const captcha = () => {
      if (!box || widget !== null) return;
      if (!window.turnstile) { setTimeout(captcha, 250); return; }
      widget = window.turnstile.render(box, { sitekey: box.dataset.sitekey, action: box.dataset.action, theme: box.dataset.theme, size: box.dataset.size, appearance: box.dataset.appearance });
    };
    const load = () => fetch(app.dataset.slots, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }).then(r => r.json()).then(d => {
      byDay = {}; (d.slots || []).forEach(ts => { const k = keyOf(ts); (byDay[k] = byDay[k] || []).push(ts); });
      const days = Object.keys(byDay).sort();
      if (!days.length) { cal.innerHTML = '<p class="book-muted">No open times in the next few weeks. Email us and we’ll find one.</p>'; times.innerHTML = ''; return; }
      if (!picked || !byDay[picked]) picked = days[0];
      view = view || picked.slice(0, 7);
      draw();
    }).catch(() => { cal.innerHTML = '<p class="notice bad">We couldn’t load open times. Refresh the page to try again.</p>'; });
    const draw = () => {
      const [y, m] = view.split('-').map(Number), first = new Date(Date.UTC(y, m - 1, 1)), daysIn = new Date(Date.UTC(y, m, 0)).getUTCDate();
      const months = Object.keys(byDay).map(k => k.slice(0, 7)), hasPrev = months.some(x => x < view), hasNext = months.some(x => x > view);
      let h = '<div class="bc-head"><button type="button" class="bc-nav" data-m="-1"' + (hasPrev ? '' : ' disabled') + ' aria-label="Previous month">‹</button><b>' + first.toLocaleDateString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' }) + '</b><button type="button" class="bc-nav" data-m="1"' + (hasNext ? '' : ' disabled') + ' aria-label="Next month">›</button></div><div class="bc-grid">';
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(d => { h += '<span class="bc-dow">' + d + '</span>'; });
      for (let i = 0; i < first.getUTCDay(); i++) h += '<span></span>';
      for (let d = 1; d <= daysIn; d++) {
        const k = view + '-' + String(d).padStart(2, '0'), open = !!byDay[k];
        h += '<button type="button" class="bc-day' + (open ? ' open' : '') + (k === picked ? ' on' : '') + '" data-k="' + k + '"' + (open ? '' : ' disabled') + '>' + d + '</button>';
      }
      cal.innerHTML = h + '</div><p class="bc-tz">Times are in ' + tzName + '</p>';
      const list = byDay[picked] || [];
      times.innerHTML = list.length ? '<b class="bt-day">' + new Date(list[0] * 1000).toLocaleDateString('en-US', { timeZone: tz, weekday: 'long', month: 'long', day: 'numeric' }) + '</b><div class="bt-grid">' + list.map(ts => '<button type="button" class="bt-slot' + (String(ts) === form.elements.start.value ? ' on' : '') + '" data-ts="' + ts + '">' + timeOf(ts) + '</button>').join('') + '</div>' : '';
    };
    app.addEventListener('click', e => {
      const nav = e.target.closest('.bc-nav'), day = e.target.closest('.bc-day.open'), slot = e.target.closest('.bt-slot');
      if (nav) { const [y, m] = view.split('-').map(Number), d = new Date(Date.UTC(y, m - 1 + Number(nav.dataset.m), 1)); view = d.toISOString().slice(0, 7); draw(); }
      if (day) { picked = day.dataset.k; draw(); }
      if (slot) {
        form.elements.start.value = slot.dataset.ts; if (form.elements.tz) form.elements.tz.value = tz;
        $('.book-chosen', form).innerHTML = '<span>Your time</span><b>' + longOf(parseInt(slot.dataset.ts, 10)) + '</b>';
        form.hidden = false; draw(); captcha();
        const first = $('input[name="name"]', form); if (first) first.focus({ preventScroll: true });
        form.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    });
    form.addEventListener('submit', e => {
      e.preventDefault();
      const err = $('.book-err', form), btn = $('button[type="submit"]', form), label = btn.innerHTML; err.hidden = true;
      if (!form.reportValidity()) return;
      btn.disabled = true; btn.textContent = app.dataset.paid ? 'Opening secure payment…' : 'Booking…';
      fetch(app.dataset.post, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then(r => r.json().catch(() => ({ ok: false })))
        .then(r => {
          if (!r.ok) { const x = new Error(r.error || 'Something went wrong. Please try again.'); x.taken = r.taken; throw x; }
          location.href = r.url;
        })
        .catch(x => { err.textContent = x.message; err.hidden = false; btn.disabled = false; btn.innerHTML = label; if (widget !== null) window.turnstile.reset(widget); if (x.taken) { form.elements.start.value = ''; form.hidden = true; load(); } });
    });
    load();
  });
})();

/* Two-step sign-in: draw the authenticator QR code (assets/js/qrcode.js) */
document.querySelectorAll('[data-qr]').forEach(function (el) {
  if (!window.qrcode) return;
  var q = window.qrcode(0, 'M'); q.addData(el.getAttribute('data-qr')); q.make();
  el.innerHTML = q.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
});
