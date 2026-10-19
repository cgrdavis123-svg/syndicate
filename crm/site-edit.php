<?php
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/analytics.php';
$user = require_user();
$id = (int)get('id');
$s = ['name' => '', 'company_id' => (int)get('company') ?: null, 'domains' => '', 'shared' => 1, 'monthly_report' => 1];
if ($id) { $s = one('SELECT * FROM sites WHERE id = ?', [$id]); if (!$s) { flash('That website isn’t set up.'); redirect('analytics.php'); } }
elseif ($s['company_id'] && ($co = one('SELECT name, website FROM companies WHERE id = ?', [$s['company_id']]))) { $s['name'] = $co['name']; $s['domains'] = preg_replace('#^(https?://)?(www\.)?#i', '', rtrim($co['website'], '/')); }
$back = 'site-edit.php?id=' . $id;
$error = '';
if (is_post()) {
    $a = post('action');
    if ($a === 'delete' && $id) {
        q('DELETE FROM sites WHERE id = ?', [$id]);
        aq('DELETE FROM events WHERE site_id = ?', [$id]); an_roll_schema(); aq('DELETE FROM rollups WHERE site_id = ?', [$id]); aq('DELETE FROM rollup_days WHERE site_id = ?', [$id]);
        flash('Website and its stats deleted.'); redirect('analytics.php');
    }
    if ($a === 'goal_add' && $id) {
        $kind = post('kind') === 'page' ? 'page' : 'event';
        $match = mb_substr(post($kind === 'page' ? 'page' : 'event'), 0, 200);
        if ($kind === 'page' && $match !== '' && $match[0] !== '/') $match = '/' . $match;
        if ($match === '') $error = $kind === 'page' ? 'Enter the page address, like /thank-you.' : 'Choose or type the action name.';
        else { q('INSERT INTO site_goals (site_id, name, kind, match) VALUES (?,?,?,?)', [$id, mb_substr(post('name') ?: ($kind === 'page' ? 'Visited ' . $match : $match), 0, 80), $kind, $match]); flash('Goal added.'); redirect($back . '#goals'); }
    }
    if ($a === 'goal_delete') { q('DELETE FROM site_goals WHERE id = ? AND site_id = ?', [(int)post('goal'), $id]); flash('Goal removed.'); redirect($back . '#goals'); }
    if ($a === 'save') {
        $s = array_merge($s, ['name' => mb_substr(post('name'), 0, 120), 'company_id' => (int)post('company_id') ?: null, 'shared' => post('shared') === '1' ? 1 : 0, 'monthly_report' => post('monthly_report') === '1' ? 1 : 0]);
        $doms = array_values(array_unique(array_filter(array_map(function ($d) { return strtolower(trim(preg_replace('#^(https?://)?(www\.)?#i', '', trim($d)), '/ ')); }, preg_split('/[\s,]+/', post('domains'))))));
        $s['domains'] = implode(', ', $doms);
        if (mb_strlen($s['name']) < 2) $error = 'Give the website a name.';
        elseif (!$doms) $error = 'Enter the website’s domain, like upstatecoffee.com.';
        elseif ($bad = array_filter($doms, function ($d) { return !preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $d) && $d !== 'localhost'; })) $error = 'These don’t look like domains: ' . implode(', ', $bad) . '. Use just the domain, like upstatecoffee.com.';
        else {
            if ($id) { q('UPDATE sites SET name = ?, company_id = ?, domains = ?, shared = ?, monthly_report = ? WHERE id = ?', [$s['name'], $s['company_id'], $s['domains'], $s['shared'], $s['monthly_report'], $id]); flash('Saved.'); }
            else {
                q('INSERT INTO sites (name, company_id, domains, public_id, shared, monthly_report, created_at) VALUES (?,?,?,?,?,?,?)', [$s['name'], $s['company_id'], $s['domains'], site_key(), $s['shared'], $s['monthly_report'], now()]);
                $id = (int)db()->lastInsertId();
                foreach ([['Form sent', 'Form submit'], ['Phone call', 'Phone call'], ['Email click', 'Email click']] as [$n, $m]) q("INSERT INTO site_goals (site_id, name, kind, match) VALUES (?, ?, 'event', ?)", [$id, $n, $m]);
                flash('Website added. Now add the install code to the site.');
            }
            redirect('site-edit.php?id=' . $id . '#install');
        }
    }
}
$goals = $id ? site_goals($id) : [];
$last = $id ? (int)aval('SELECT MAX(ts) FROM events WHERE site_id = ?', [$id]) : 0;
$seenEvents = $id ? array_column(aall("SELECT DISTINCT name FROM events WHERE site_id = ? AND type = 'event' ORDER BY name LIMIT 50", [$id]), 'name') : [];
layout_top($id ? $s['name'] . ' settings' : 'Add a website', 'analytics', $user);
?>
<div class="page-head"><div><a class="muted small" href="<?= $id ? 'site.php?id=' . $id : 'analytics.php' ?>">← <?= $id ? 'Back to stats' : 'Site stats' ?></a><h1><?= $id ? e($s['name']) : 'Add a website' ?></h1></div></div>
<?php if ($error): ?><p class="notice bad"><?= e($error) ?></p><?php endif; ?>
<div class="grid-detail">
  <div class="stack">
    <form method="post" class="card form-grid">
      <?= csrf_field() ?><input type="hidden" name="action" value="save">
      <div class="card-h span2"><h2>Website</h2></div>
      <label class="f"><span>Name</span><input name="name" required value="<?= e($s['name']) ?>" placeholder="Upstate Coffee website"></label>
      <label class="f"><span>Client</span><select name="company_id"><option value="">— None —</option><?php foreach (company_options() as $o): ?><option value="<?= (int)$o['id'] ?>"<?= selected($o['id'], $s['company_id']) ?>><?= e($o['name']) ?></option><?php endforeach; ?></select></label>
      <label class="f span2"><span>Domains <small class="muted">(separate with commas; subdomains are included automatically)</small></span><input name="domains" required value="<?= e($s['domains']) ?>" placeholder="upstatecoffee.com, shop.upstatecoffee.com"></label>
      <label class="chk span2"><input type="checkbox" name="shared" value="1"<?= $s['shared'] ? ' checked' : '' ?>> <span>Show these stats in the client’s portal</span></label>
      <label class="chk span2"><input type="checkbox" name="monthly_report" value="1"<?= $s['monthly_report'] ? ' checked' : '' ?>> <span>Email a monthly report to the client’s portal users on the 1st</span></label>
      <div class="save-bar span2">
        <?php if ($id): ?><button class="link-danger" type="submit" name="action" value="delete" formnovalidate data-confirm="Delete this website and all of its stats? This can’t be undone.">Delete website</button><?php else: ?><span></span><?php endif; ?>
        <button class="btn primary sm" type="submit" name="action" value="save"><?= $id ? 'Save' : 'Add website' ?></button>
      </div>
    </form>

    <?php if ($id): ?>
    <section class="card" id="install"><div class="card-h"><h2>Install code</h2><span class="live-status" data-site="<?= (int)$id ?>" data-last="<?= $last ?>"><?= $last ? '<span class="dot ok"></span>Receiving data · last visit ' . e(ago(date('Y-m-d H:i:s', $last))) : '<span class="dot off"></span>Waiting for the first visit…' ?></span></div>
      <p class="muted small">Paste this into the <code>&lt;head&gt;</code> of every page. It’s about 3 KB compressed, loads after the page, and sets no cookies.</p>
      <div class="code-box"><pre id="snippet"><?= e(site_snippet($s)) ?></pre><button class="btn ghost xs" type="button" data-copy-text="#snippet">Copy</button></div>
      <details class="guide"><summary>WordPress</summary><p>Install the free <b>WPCode</b> plugin (or use your theme’s “Header scripts” box). Go to <b>Code Snippets → Header &amp; Footer</b>, paste the code into <b>Header</b> and save.</p></details>
      <details class="guide"><summary>Shopify</summary><p><b>Online Store → Themes → ⋯ → Edit code</b>, open <code>theme.liquid</code>, and paste the code just before <code>&lt;/head&gt;</code>. Then save.</p></details>
      <details class="guide"><summary>Squarespace</summary><p><b>Settings → Advanced → Code Injection</b>, paste into <b>Header</b>, and save. (Needs a Business plan or higher.)</p></details>
      <details class="guide"><summary>Wix</summary><p><b>Settings → Custom Code → + Add Custom Code</b>, paste it, choose <b>All pages</b>, load once, place in <b>Head</b>.</p></details>
      <details class="guide"><summary>Webflow</summary><p><b>Site settings → Custom code → Head code</b>, paste and publish.</p></details>
      <details class="guide"><summary>Google Tag Manager</summary><p>Add a <b>Custom HTML</b> tag with the code, trigger it on <b>All Pages</b>, and publish the container.</p></details>
      <details class="guide"><summary>Custom site, React or Next.js</summary><p>Add the tag to the shared layout’s <code>&lt;head&gt;</code>. Route changes in single-page apps are counted automatically. If the site routes with <code>#/</code> hashes, add <code>data-hash="true"</code>.</p>
        <p>To beat ad blockers, serve the script and the collector from the client’s own domain with a rewrite (e.g. Netlify, Vercel or Cloudflare): send <code>/st/a.js</code> to <code><?= e(abs_url('a.js')) ?></code> and <code>/st/e</code> to <code><?= e(abs_url('t.php')) ?></code>, then use <code>src="/st/a.js" data-api="/st/e"</code>.</p></details>
      <details class="guide"><summary>Track your own buttons and goals</summary>
        <p>Calls (<code>tel:</code> links), email links, form sends, file downloads and outbound links are tracked automatically. To track anything else, add an attribute:</p>
        <pre class="code-sm">&lt;a href="/book" data-syn-event="Book now" data-syn-prop-service="Teeth cleaning"&gt;Book&lt;/a&gt;</pre>
        <p>Or from JavaScript, e.g. after an online order: <code>syn('Order placed', {total: '42.50'})</code>. Never send names, emails or phone numbers of visitors.</p>
        <p>To leave your own visits out, open the site once with <code>?syn_ignore=1</code> at the end of the address on each of your browsers. <code>?syn_ignore=0</code> turns counting back on.</p></details>
      <p class="muted small">No-JavaScript fallback (optional, counts basic pageviews): <code><?= e('<noscript><img src="' . abs_url('t.php?s=' . $s['public_id']) . '" alt="" width="1" height="1"></noscript>') ?></code></p>
    </section>
    <?php endif; ?>
  </div>
  <?php if ($id): ?>
  <div class="stack">
    <section class="card" id="goals"><div class="card-h"><h2>Goals</h2><span class="muted small">What counts as a conversion</span></div>
      <?php if (!$goals): ?><p class="empty">No goals yet.</p><?php else: ?><ul class="list"><?php foreach ($goals as $g): ?>
        <li class="li-row"><span class="grow"><b><?= e($g['name']) ?></b><small><?= $g['kind'] === 'page' ? 'Visits ' . e($g['match']) : 'Action: ' . e($g['match']) ?></small></span><form method="post" class="inline" data-confirm="Remove this goal? Past data stays."><?= csrf_field() ?><input type="hidden" name="action" value="goal_delete"><input type="hidden" name="goal" value="<?= (int)$g['id'] ?>"><button class="x" type="submit" aria-label="Remove goal">×</button></form></li>
      <?php endforeach; ?></ul><?php endif; ?>
      <form method="post" class="stack-sm goal-form"><?= csrf_field() ?><input type="hidden" name="action" value="goal_add">
        <div class="seg" role="radiogroup" aria-label="Goal type"><input type="radio" name="kind" id="gk-e" value="event" checked><label for="gk-e">An action</label><input type="radio" name="kind" id="gk-p" value="page"><label for="gk-p">A page visit</label></div>
        <input name="name" placeholder="Goal name, e.g. Booked appointment" aria-label="Goal name">
        <input name="event" list="seenEvents" placeholder="Action, e.g. Book now" aria-label="Action name" data-goal="event">
        <input name="page" placeholder="Page, e.g. /thank-you or /order/*/done" aria-label="Page address" data-goal="page" hidden>
        <datalist id="seenEvents"><?php foreach (array_unique(array_merge(['Form submit', 'Phone call', 'Email click', 'File download', 'Outbound link', 'Text message'], $seenEvents)) as $n): ?><option value="<?= e($n) ?>"><?php endforeach; ?></datalist>
        <button class="btn ghost sm" type="submit">Add goal</button>
        <p class="muted small">Use <code>*</code> as a wildcard in page goals.</p>
      </form>
    </section>
    <section class="card"><div class="card-h"><h2>Privacy</h2></div>
      <ul class="plain-list muted small">
        <li>No cookies, local storage or fingerprinting, so no cookie banner is needed under US law. Add a line to the client’s privacy policy, e.g. “We use privacy-friendly analytics that do not use cookies or collect personal information.”</li>
        <li>IP addresses are never saved. Visitors are counted with a one-way code that changes every day.</li>
        <li>Form contents are never recorded, only that a form was sent.</li>
        <li>Data is kept <?= (int)(setting('analytics_retention_days') ?: 760) ?> days, then deleted automatically.</li>
      </ul>
    </section>
  </div>
  <?php endif; ?>
</div>
<?php layout_bottom(); ?>
