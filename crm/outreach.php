<?php
// Outreach: prospects the outreach agent found and emailed, their replies, the do-not-contact list and settings.
require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/outreach.php';
$user = require_user();
$id = (int)get('id');
$p = $id ? one('SELECT * FROM outreach_prospects WHERE id = ?', [$id]) : null;
if ($id && !$p) { flash('That prospect no longer exists.'); redirect('outreach.php'); }
start_session();

if (is_post()) {
    $act = post('action');
    if ($act === 'toggle') {
        if (setting('outreach_on') === '1') { setting('outreach_on', '0'); flash('Outreach is off. Nothing will send until you switch it back on.'); }
        elseif ($probs = out_problems()) flash('Not switched on yet: ' . implode(' ', $probs));
        else { setting('outreach_on', '1'); setting('outreach_paused', ''); flash('Outreach is on. Emails go out on weekdays between 9 AM and 5 PM.'); }
        redirect('outreach.php');
    }
    if ($act === 'settings') {
        $box = (int)post('mailbox');
        setting('outreach_mailbox', $box && mailbox($box) ? (string)$box : '');
        setting('outreach_from_name', mb_substr(post('from_name') ?: 'Grady Davis', 0, 80));
        setting('outreach_address', mb_substr(post('address'), 0, 300));
        setting('outreach_start', (string)max(1, min(50, (int)post('start'))));
        setting('outreach_cap', (string)max(1, min(100, (int)post('cap'))));
        flash('Outreach settings saved.'); redirect('outreach.php#settings');
    }
    if ($act === 'newkey') { $k = 'out_' . bin2hex(random_bytes(24)); setting('outreach_api_hash', hash('sha256', $k)); $_SESSION['out_key'] = $k; redirect('outreach.php#key'); }
    if ($act === 'revoke') { setting('outreach_api_hash', ''); flash('Key removed. The agent can’t add prospects or answer replies until you make a new one.'); redirect('outreach.php#key'); }
    if ($act === 'optout') {
        $v = strtolower(trim(post('value')));
        if (filter_var($v, FILTER_VALIDATE_EMAIL)) out_optout($v, 'added by hand');
        elseif (preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $v)) q('INSERT INTO outreach_optout (email, domain, reason, created_at) VALUES (?,?,?,?)', ['', $v, 'added by hand', now()]);
        else { flash('Enter an email address or a domain like example.com.'); redirect('outreach.php#optout'); }
        foreach (all("SELECT * FROM outreach_prospects WHERE status IN ('queued','active','paused') AND (email = ? OR domain = ?)", [$v, $v]) as $x) { out_set((int)$x['id'], ['status' => 'cancelled', 'next_at' => null]); out_log((int)$x['id'], 'note', 'Stopped: added to do-not-contact.'); }
        flash('Added to the do-not-contact list.'); redirect('outreach.php#optout');
    }
    if ($act === 'optout_rm') { q('DELETE FROM outreach_optout WHERE id = ?', [(int)post('oid')]); flash('Removed from the do-not-contact list.'); redirect('outreach.php#optout'); }
    if ($p) {
        $pid = (int)$p['id'];
        if ($act === 'pause' && in_array($p['status'], ['queued', 'active'], true)) { out_set($pid, ['status' => 'paused']); out_log($pid, 'note', 'Paused by ' . $user['name'] . '.'); }
        if ($act === 'bad' && $p['status'] === 'blocked') { out_set($pid, ['status' => 'bounced', 'next_at' => null]); out_optout($p['email'], 'bounced'); out_log($pid, 'note', 'Marked as a bad address by ' . $user['name'] . '.'); }
        if ($act === 'resume' && in_array($p['status'], ['paused', 'blocked'], true)) { out_set($pid, ['status' => (int)$p['step'] ? 'active' : 'queued', 'next_at' => (int)$p['step'] ? out_bdays(time(), 1) : null]); out_log($pid, 'note', 'Resumed.'); }
        if ($act === 'stop') { out_optout($p['email'], 'stopped by hand', true); out_set($pid, ['status' => 'cancelled', 'next_at' => null]); out_log($pid, 'note', 'Stopped and added to do-not-contact by ' . $user['name'] . '.'); }
        if ($act === 'mine' && in_array($p['status'], ['replied', 'needs_you'], true)) { out_set($pid, ['status' => 'talking']); out_log($pid, 'note', 'Taken over by ' . $user['name'] . '.'); }
        if ($act === 'back' && in_array($p['status'], ['needs_you', 'talking'], true)) { out_set($pid, ['status' => 'replied']); out_log($pid, 'note', 'Handed back to the agent.'); }
        if ($act === 'delete' && (int)$p['step'] === 0) { q('DELETE FROM outreach_prospects WHERE id = ?', [$pid]); flash('Prospect removed.'); redirect('outreach.php'); }
        if ($act === 'drafts') {
            $f = [];
            foreach (['subject' => 140, 'body' => 3000, 'fu1' => 2000, 'fu2' => 1500] as $k => $max) {
                $step = ['subject' => 0, 'body' => 0, 'fu1' => 1, 'fu2' => 2][$k];
                if ((int)$p['step'] <= $step && isset($_POST[$k])) $f[$k] = out_clean((string)$_POST[$k], $max);
            }
            foreach ($f as $k => $t) if ($k !== 'subject' && ($err = out_links_error($t, $p['website']))) { flash($err); redirect('outreach.php?id=' . $pid); }
            if ($f) { out_set($pid, $f); out_log($pid, 'note', 'Emails edited by ' . $user['name'] . '.'); }
            flash('Saved.');
        }
        redirect('outreach.php?id=' . $pid);
    }
    redirect('outreach.php');
}

layout_top($p ? $p['business'] : 'Outreach', 'outreach', $user);
$pill = function (string $s) { [$l, $c] = OUT_STATUS[$s] ?? [$s, '#8a929c']; return '<span class="pill" style="--c:' . $c . '">' . e($l) . '</span>'; };

if (!$p):
    $on = setting('outreach_on') === '1'; $probs = out_problems(); $status = get('status');
    $counts = []; foreach (all('SELECT status, COUNT(*) n FROM outreach_prospects GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
    $groups = ['' => ['All', null], 'attention' => ['Needs you', ['needs_you', 'replied', 'blocked']], 'interested' => ['Interested', ['interested', 'talking']], 'sending' => ['Sending', ['queued', 'active', 'paused']], 'done' => ['Done', ['finished', 'declined', 'unsubscribed', 'bounced', 'cancelled']]];
    $in = $groups[$status][1] ?? null;
    $rows = $in ? all('SELECT * FROM outreach_prospects WHERE status IN (' . implode(',', array_fill(0, count($in), '?')) . ') ORDER BY updated_at DESC LIMIT 300', $in)
        : all('SELECT * FROM outreach_prospects ORDER BY updated_at DESC LIMIT 300');
    $week = date('Y-m-d H:i:s', strtotime('-7 days'));
    $wk = ['sent' => 0, 'reply' => 0, 'declined' => 0, 'unsubscribe' => 0, 'bounce' => 0, 'blocked' => 0];
    foreach (all('SELECT kind, COUNT(*) n FROM outreach_log WHERE created_at >= ? GROUP BY kind', [$week]) as $r) $wk[$r['kind']] = (int)$r['n'];
    $interestedWk = (int)val("SELECT COUNT(*) FROM outreach_log WHERE kind = 'agent' AND body LIKE 'Interested%' AND created_at >= ?", [$week]);
    $boxes = mailboxes(); $key = $_SESSION['out_key'] ?? ''; unset($_SESSION['out_key']);
?>
<div class="page-head"><div><h1>Outreach</h1><p class="small"><a href="previews.php">Homepage previews →</a> · <a href="checkups.php">Checkups →</a></p><p class="muted small">Prospects the outreach agent researched and emailed from <?= e(out_mailbox()['email'] ?? 'your outreach mailbox') ?>. Follow-ups, opt-outs and bounces are handled automatically.</p></div>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><button class="btn <?= $on ? 'ghost' : 'primary' ?> sm" type="submit"><?= $on ? 'Switch off' : 'Switch on' ?></button></form></div>
<?php if (setting('outreach_paused') !== ''): ?><p class="notice bad"><?= e(setting('outreach_paused')) ?></p><?php endif; ?>
<?php if (setting('outreach_error') !== ''): ?><p class="notice bad">Last send failed. <?= e(setting('outreach_error')) ?></p><?php endif; ?>
<?php if ($probs): ?><p class="notice">Before outreach can send: <?= e(implode(' ', $probs)) ?> <a href="#settings">Settings</a></p><?php endif; ?>
<div class="kpis">
  <div class="kpi card"><span>Status</span><b style="color:<?= $on ? 'var(--lime)' : 'inherit' ?>"><?= $on ? 'On' : 'Off' ?></b><small><?= out_sent_today() ?> of <?= out_limit_today() ?> sent today</small></div>
  <div class="kpi card"><span>Sent, 7 days</span><b class="num"><?= $wk['sent'] ?></b><small><?= ($counts['queued'] ?? 0) ?> waiting to send</small></div>
  <div class="kpi card"><span>Replies, 7 days</span><b class="num"><?= $wk['reply'] + $wk['declined'] + $wk['unsubscribe'] ?></b><small><?= $interestedWk ?> interested · <?= $wk['declined'] + $wk['unsubscribe'] ?> no</small></div>
  <div class="kpi card"><span>Bounces, 7 days</span><b class="num"><?= $wk['bounce'] ?></b><small><?= $wk['sent'] ? round($wk['bounce'] / $wk['sent'] * 100) . '% of sends' : 'Pauses itself above 8%' ?><?= $wk['blocked'] ? ' · ' . $wk['blocked'] . ' blocked as spam' : '' ?></small></div>
</div>
<div class="seg-links" style="margin:6px 0 4px"><?php foreach ($groups as $k => [$l, $sts]): $n = $sts ? array_sum(array_map(function ($s) use ($counts) { return $counts[$s] ?? 0; }, $sts)) : 0; ?><a href="outreach.php<?= $k ? '?status=' . $k : '' ?>"<?= $status === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?><?= $n ? ' <span class="dim num">' . $n . '</span>' : '' ?></a><?php endforeach; ?></div>
<div class="card flush"><div class="table-wrap"><table class="tbl"><thead><tr><th>Business</th><th>Status</th><th>Emails</th><th>Next</th><th>Updated</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><a href="outreach.php?id=<?= (int)$r['id'] ?>"><b><?= e($r['business']) ?></b></a><br><span class="muted small"><?= e(implode(' · ', array_filter([$r['niche'], $r['city'], $r['email']]))) ?></span></td>
    <td><?= $pill($r['status']) ?> <span class="muted small"><?= e($r['priority']) ?></span></td><td class="num"><?= (int)$r['step'] ?> / 3</td>
    <td class="small"><?= in_array($r['status'], ['queued', 'active'], true) ? ($r['next_at'] ? e(date('D M j, g:i A', (int)$r['next_at'])) : 'Next send window') : '<span class="muted">—</span>' ?></td>
    <td class="muted small"><?= e(ago($r['updated_at'])) ?></td></tr>
<?php endforeach; if (!$rows): ?><tr><td colspan="5" class="empty">No prospects here yet. The outreach agent adds them after its research runs.</td></tr><?php endif; ?>
</tbody></table></div></div>

<div class="grid-2" style="margin-top:18px">
  <form method="post" class="card form-grid" id="settings"><?= csrf_field() ?><input type="hidden" name="action" value="settings">
    <div class="card-h span2"><h2>Settings</h2></div>
    <label class="f span2"><span>Send from <small class="muted">(connect it first in <a href="mailboxes.php">Inbox → Mailboxes</a>)</small></span><select name="mailbox"><option value="">Choose a mailbox</option>
      <?php foreach ($boxes as $b): ?><option value="<?= (int)$b['id'] ?>"<?= (string)$b['id'] === setting('outreach_mailbox') ? ' selected' : '' ?>><?= e($b['email']) ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Sender name</span><input name="from_name" value="<?= e(setting('outreach_from_name') ?: 'Grady Davis') ?>"></label>
    <label class="f"><span>Postal address <small class="muted">(email footer, required by law)</small></span><input name="address" value="<?= e(setting('outreach_address')) ?>" placeholder="Business name, street, city, state ZIP"></label>
    <label class="f"><span>Emails a day to start <small class="muted">(+4 each week)</small></span><input type="number" min="1" max="50" name="start" value="<?= (int)setting('outreach_start') ?: 8 ?>"></label>
    <label class="f"><span>Most emails a day</span><input type="number" min="1" max="100" name="cap" value="<?= (int)setting('outreach_cap') ?: 30 ?>"></label>
    <p class="muted small span2">Sends on weekdays, 9 AM–5 PM, spread through the day. Follow-ups go 4 and 10 business days after the first email and stop as soon as someone replies. Every email has an unsubscribe link and header.</p>
    <div class="save-bar span2"><span></span><button class="btn primary sm" type="submit">Save</button></div>
  </form>
  <div class="stack">
    <section class="card" id="key"><div class="card-h"><h2>Agent key</h2><span class="pill" style="--c:<?= setting('outreach_api_hash') ? '#c6ff3d' : '#8a929c' ?>"><?= setting('outreach_api_hash') ? 'Set' : 'Not set' ?></span></div>
      <?php if ($key): ?><p class="small">Copy this key now; it won’t be shown again. Add it as a network secret for <span class="mono">crm.stusyn.com</span> in the agent’s cloud environment (header <span class="mono">Authorization</span>, prefix <span class="mono">Bearer</span>).</p>
        <div class="code-box"><pre id="outKey"><?= e($key) ?></pre><button class="btn ghost xs" type="button" data-copy-text="#outKey">Copy</button></div>
      <?php else: ?><p class="muted small">The outreach agent uses this key to add prospects and answer replies through <span class="mono">outreach-api.php</span>. Making a new key replaces the old one.</p><?php endif; ?>
      <div class="row" style="margin-top:10px"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="newkey"><button class="btn ghost sm" type="submit"><?= setting('outreach_api_hash') ? 'Make a new key' : 'Make a key' ?></button></form>
      <?php if (setting('outreach_api_hash')): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="revoke"><button class="link-btn link-danger small" type="submit">Remove key</button></form><?php endif; ?></div>
    </section>
    <section class="card" id="optout"><div class="card-h"><h2>Do not contact</h2><span class="muted small num"><?= (int)val('SELECT COUNT(*) FROM outreach_optout') ?></span></div>
      <form method="post" class="row"><?= csrf_field() ?><input type="hidden" name="action" value="optout"><input name="value" placeholder="email@business.com or business.com" style="flex:1"><button class="btn ghost sm" type="submit">Add</button></form>
      <table class="tbl" style="margin-top:8px"><tbody><?php foreach (all('SELECT * FROM outreach_optout ORDER BY id DESC LIMIT 15') as $o): ?>
        <tr><td><span class="mono small"><?= e($o['email'] ?: '*@' . $o['domain']) ?></span><?= $o['email'] && $o['domain'] ? ' <span class="muted small">+ whole domain</span>' : '' ?><br><span class="muted small"><?= e($o['reason']) ?> · <?= e(ago($o['created_at'])) ?></span></td>
          <td class="r"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="optout_rm"><input type="hidden" name="oid" value="<?= (int)$o['id'] ?>"><button class="link-btn small" type="submit" onclick="return confirm('Remove from the do-not-contact list?')">Remove</button></form></td></tr>
      <?php endforeach; ?></tbody></table>
    </section>
  </div>
</div>
<?php else:
    $log = all('SELECT * FROM outreach_log WHERE prospect_id = ? ORDER BY id DESC', [$p['id']]);
    $thread = $p['reply_email_id'] ? (string)val('SELECT thread_id FROM emails WHERE id = ?', [$p['reply_email_id']]) : '';
    $step = (int)$p['step'];
    $btn = function (string $act, string $label, string $cls = 'btn ghost sm', string $confirm = '') { return '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="' . $act . '"><button class="' . $cls . '" type="submit"' . ($confirm ? ' onclick="return confirm(\'' . e($confirm) . '\')"' : '') . '>' . e($label) . '</button></form>'; };
?>
<div class="page-head"><div><a class="muted small" href="outreach.php">← Outreach</a><h1><?= e($p['business']) ?></h1><p class="muted small"><?= e(implode(' · ', array_filter([$p['contact_name'], $p['email'], $p['city'], $p['niche']]))) ?></p></div>
  <div class="row"><?= $pill($p['status']) ?>
    <?php if ($thread): ?><a class="btn ghost sm" href="email.php?t=<?= e($thread) ?>">Open conversation</a><?php endif; ?>
    <?php if (in_array($p['status'], ['queued', 'active'], true)) echo $btn('pause', 'Pause'); ?>
    <?php if ($p['status'] === 'paused') echo $btn('resume', 'Resume'); ?>
    <?php if ($p['status'] === 'blocked') { echo $btn('resume', 'Resume'); echo $btn('bad', 'It’s a bad address', 'btn ghost sm', 'Mark as bounced and add to do-not-contact?'); } ?>
    <?php if (in_array($p['status'], ['replied', 'needs_you'], true)) echo $btn('mine', 'I’ll answer this'); ?>
    <?php if (in_array($p['status'], ['needs_you', 'talking'], true)) echo $btn('back', 'Let the agent answer'); ?>
    <?php if ($p['deal_id']): ?><a class="btn ghost sm" href="deal.php?id=<?= (int)$p['deal_id'] ?>">Deal</a><?php endif; ?>
  </div></div>
<div class="grid-detail">
  <form method="post" class="card form-grid"><?= csrf_field() ?><input type="hidden" name="action" value="drafts">
    <div class="card-h span2"><h2>Emails</h2><span class="muted small"><?= $step ?> of 3 sent<?= $p['next_at'] && in_array($p['status'], ['queued', 'active'], true) ? ' · next ' . e(date('D M j, g:i A', (int)$p['next_at'])) : '' ?></span></div>
    <label class="f span2"><span>Subject</span><input name="subject" value="<?= e($p['subject']) ?>"<?= $step > 0 ? ' disabled' : '' ?>></label>
    <?php foreach (['body' => ['First email', 0], 'fu1' => ['Follow-up 1 · 4 business days later', 1], 'fu2' => ['Follow-up 2 · 10 business days after the first', 2]] as $k => [$l, $s]): ?>
    <label class="f span2"><span><?= e($l) ?><?= $step > $s ? ' <small class="muted">· sent</small>' : '' ?></span><textarea name="<?= $k ?>" rows="<?= $k === 'body' ? 9 : 5 ?>"<?= $step > $s ? ' disabled' : '' ?>><?= e($p[$k]) ?></textarea></label>
    <?php endforeach; ?>
    <p class="muted small span2">The signature, postal address and unsubscribe line are added to every email automatically.</p>
    <?php if ($step < 3 && in_array($p['status'], ['queued', 'active', 'paused'], true)): ?><div class="save-bar span2"><span></span><button class="btn primary sm" type="submit">Save changes</button></div><?php endif; ?>
  </form>
  <div class="stack">
    <section class="card"><div class="card-h"><h2>What we saw</h2><span class="muted small">Priority <?= e($p['priority']) ?></span></div>
      <dl class="facts">
        <?php if ($p['website']): ?><div><dt>Website</dt><dd><a href="<?= e(preg_match('#^https?://#', $p['website']) ? $p['website'] : 'https://' . $p['website']) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('#^https?://#', '', $p['website'])) ?></a></dd></div><?php endif; ?>
        <?php if ($p['best_fit']): ?><div><dt>Best fit</dt><dd><?= e($p['best_fit']) ?></dd></div><?php endif; ?>
        <?php if ($p['batch']): ?><div><dt>Batch</dt><dd><?= e($p['batch']) ?></dd></div><?php endif; ?>
      </dl>
      <?php if ($p['notes']): ?><p class="small" style="white-space:pre-line;margin-top:10px"><?= e($p['notes']) ?></p><?php endif; ?>
    </section>
    <section class="card"><div class="card-h"><h2>Activity</h2></div>
      <ul class="feed-list"><?php foreach ($log as $l): ?><li><b class="small"><?= e(ucfirst(str_replace('_', ' ', $l['kind']))) ?></b> <span class="muted small"><?= e(ago($l['created_at'])) ?></span><?php if ($l['body'] !== ''): ?><div class="small" style="white-space:pre-line"><?= e(mb_substr($l['body'], 0, 1500)) ?></div><?php endif; ?></li><?php endforeach; ?></ul>
    </section>
    <section class="card danger-row"><div class="row">
      <?= $btn('stop', 'Stop and never email again', 'link-btn link-danger small', 'Stop emailing ' . $p['business'] . ' and add them to the do-not-contact list?') ?>
      <?php if ($step === 0) echo $btn('delete', 'Remove prospect', 'link-btn small', 'Remove this prospect? Nothing has been sent to them.'); ?>
    </div></section>
  </div>
</div>
<?php endif; layout_bottom();
