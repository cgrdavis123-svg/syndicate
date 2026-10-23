<?php
// Free homepage previews: every concept page made for a prospect, who opened it, and making one by hand.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/previews.php';
$user = require_user();
if (is_post()) {
    $a = post('action'); $r = (int)post('id') ? one('SELECT * FROM previews WHERE id = ?', [(int)post('id')]) : null;
    if ($a === 'make') {
        $res = pv_create(['business' => post('business'), 'niche' => post('niche'), 'city' => post('city'), 'phone' => post('phone'), 'email' => post('email'), 'website' => post('website'),
            'tagline' => post('tagline'), 'about' => post('about'), 'services' => array_filter(array_map('trim', explode("\n", post('services')))), 'areas' => array_filter(array_map('trim', explode(',', post('areas')))),
            'hours' => array_filter(array_map('trim', explode("\n", post('hours')))), 'contact_name' => post('contact_name'), 'theme' => post('theme'),
            'links' => ['steam' => post('steam'), 'discord' => post('discord')]], 'staff');
        flash($res['ok'] ? 'Preview made. It’s live for ' . PV_DAYS . ' days.' : 'Not made: ' . $res['error'] . '.');
        if ($res['ok']) redirect('previews.php?made=' . $res['token']);
    } elseif ($r && $a === 'remove') {
        q("UPDATE previews SET removed_at = ?, removed_by = 'staff' WHERE id = ?", [time(), $r['id']]); flash('Taken down.');
    } elseif ($r && $a === 'extend' && pv_live($r)) {
        q('UPDATE previews SET expires_at = ? WHERE id = ?', [time() + PV_DAYS * 86400, $r['id']]); flash('Extended for another ' . PV_DAYS . ' days.');
    }
    redirect('previews.php');
}
$rows = all('SELECT * FROM previews ORDER BY id DESC LIMIT 200');
$k = one('SELECT COUNT(*) n, SUM(views > 0) opened FROM previews WHERE created_at > ?', [time() - 30 * 86400]);
$made = pv_row(get('made'));
layout_top('Homepage previews', 'outreach', $user);
?>
<div class="page-head"><div><a class="muted small" href="outreach.php">← Outreach</a><h1>Homepage previews</h1><p class="muted small">Free concept home pages made from a prospect’s public details, for outreach emails. Each one shows a banner saying it’s a concept, isn’t in Google, can be removed by the owner, and expires after <?= PV_DAYS ?> days. It counts as opened only when a real browser shows it, so link scanners don’t count, and your own visits while signed in don’t either.</p></div></div>
<?php if ($made): ?><div class="notice">Preview ready for <b><?= e($made['business']) ?></b>: <a href="<?= e(pv_url($made)) ?>" target="_blank" rel="noopener"><?= e(pv_url($made)) ?></a></div><?php endif; ?>
<div class="kpis">
  <div class="card kpi"><span>Made in the last 30 days</span><b class="num"><?= (int)$k['n'] ?></b><small>Agent and by hand</small></div>
  <div class="card kpi"><span>Opened</span><b class="num"><?= (int)$k['opened'] ?></b><small>By a real browser</small></div>
</div>
<section class="card"><div class="table-wrap"><table class="tbl cards"><thead><tr><th>Business</th><th>Style</th><th>From</th><th class="r">Views</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): $live = pv_live($r); ?>
  <tr><td><a href="<?= e(pv_url($r)) ?>" target="_blank" rel="noopener"><b><?= e($r['business']) ?></b></a><?= $r['email'] !== '' ? '<br><span class="muted small">' . e($r['email']) . '</span>' : '' ?></td>
    <td data-label="Style"><?= e(PV_THEMES[$r['theme']]['label'] ?? $r['theme']) ?></td>
    <td data-label="From"><?= $r['via'] === 'agent' ? 'Outreach agent' : 'Staff' ?><br><span class="muted small"><?= e(date('M j', (int)$r['created_at'])) ?></span></td>
    <td data-label="Views" class="r num"><?= (int)$r['views'] ?><?= $r['viewed_at'] ? '<br><span class="muted small">' . e(date('M j, g:i a', (int)$r['viewed_at'])) . '</span>' : '' ?></td>
    <td data-label="Status"><?= $r['removed_at'] ? '<span class="tag">' . ($r['removed_by'] === 'owner' ? 'Removed by the owner' : 'Taken down') . '</span>' : ($live ? '<span class="tag lime">Live until ' . e(date('M j', (int)$r['expires_at'])) . '</span>' : '<span class="tag">Expired</span>') ?></td>
    <td class="r"><?php if ($live): ?><form method="post" class="row" style="justify-content:flex-end;gap:8px"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn ghost sm" name="action" value="extend">Extend</button><button class="link-danger" name="action" value="remove">Take down</button></form><?php endif; ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">No previews yet.</td></tr><?php endif; ?>
</tbody></table></div></section>
<details class="card"><summary><b>Make one by hand</b> <span class="muted small">(use only details the business publishes itself)</span></summary>
<form method="post" class="form-grid" style="margin-top:14px"><?= csrf_field() ?><input type="hidden" name="action" value="make">
  <label class="f"><span>Business name</span><input name="business" required></label>
  <label class="f"><span>Kind of business</span><input name="niche" placeholder="HVAC contractor, bakery, dental practice…"></label>
  <label class="f"><span>Town</span><input name="city" placeholder="Spartanburg, SC"></label>
  <label class="f"><span>Public phone <small>(optional)</small></span><input name="phone"></label>
  <label class="f"><span>Their email <small>(optional, ties it to outreach)</small></span><input name="email" type="email"></label>
  <label class="f"><span>First name <small>(optional)</small></span><input name="contact_name"></label>
  <label class="f"><span>Their current website <small>(optional)</small></span><input name="website"></label>
  <label class="f"><span>Style</span><select name="theme"><option value="">Pick from the kind of business</option><?php foreach (PV_THEMES as $k2 => $t): ?><option value="<?= $k2 ?>"><?= e($t['label']) ?></option><?php endforeach; ?></select></label>
  <label class="f span2"><span>Headline <small>(optional, their own tagline)</small></span><input name="tagline" maxlength="90"></label>
  <label class="f span2"><span>Services <small>(one per line, from their site or listing; for a game studio, their games)</small></span><textarea name="services" rows="4"></textarea></label>
  <label class="f"><span>Steam page <small>(game studios, optional)</small></span><input name="steam" type="url" placeholder="https://store.steampowered.com/app/…"></label>
  <label class="f"><span>Discord invite <small>(game studios, optional)</small></span><input name="discord" type="url" placeholder="https://discord.gg/…"></label>
  <label class="f"><span>Areas they serve <small>(comma separated)</small></span><input name="areas"></label>
  <label class="f"><span>Hours <small>(one per line, e.g. “Mon–Fri: 8am–5pm”)</small></span><textarea name="hours" rows="3"></textarea></label>
  <label class="f span2"><span>About <small>(optional, in their own words, shortened)</small></span><textarea name="about" rows="3" maxlength="600"></textarea></label>
  <div class="save-bar span2"><span></span><button class="btn primary sm">Make preview</button></div>
</form></details>
<?php layout_bottom();
