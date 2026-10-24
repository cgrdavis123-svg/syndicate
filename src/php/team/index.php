<?php
// Team portal home: the member's checklist, documents, links and help.
require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/team.php';
$me = require_member();
$id = (int)$me['id'];

if (is_post()) {
    $key = post('step');
    $def = team_step_def($me, $key);
    $rows = team_step_rows($id);
    $done = !empty($rows[$key]['done_at']);
    $act = post('action');
    if (!$def) { flash('That step isn’t on your checklist.'); }
    elseif ($act === 'tick' && $def['type'] === 'tick') { team_step_save($id, $key, true, 'member'); flash('Nice, “' . $def['title'] . '” is done.'); }
    elseif ($act === 'untick' && $def['type'] === 'tick' && $done) { team_step_save($id, $key, false, ''); flash('Marked as not done.'); }
    elseif ($act === 'nudge' && $def['type'] === 'admin' && !empty($def['nudge']) && !$done) {
        team_step_save($id, $key, false, '', [], true);
        team_notify($me, 'says “' . $def['nudge'] . '” on “' . $def['title'] . '”');
        flash('Thanks, Grady’s been told.');
    }
    elseif ($act === 'pay' && $def['type'] === 'pay' && isset(TEAM_PAY[post('method')])) {
        db()->prepare('UPDATE team_members SET pay_method = ?, updated_at = ? WHERE id = ?')->execute([post('method'), now(), $id]);
        team_step_save($id, $key, true, 'member', ['method' => post('method')]);
        flash('Saved: you’ll be paid by ' . TEAM_PAY[post('method')] . '.');
    }
    redirect('team/#s-' . preg_replace('/[^a-z_]/', '', $key));
}

$rows = team_step_rows($id);
$steps = team_visible_steps($me);
$prog = team_progress($me, $rows);
$rep = team_is_rep($me);
$leaving = in_array($me['status'], ['leaving', 'left'], true);
[$doneS, $allS] = $prog['start'];
$docs = $rep ? ['agreement_rep', 'playbook', 'handbook'] : ['agreement_contractor', 'roles', 'handbook'];
$pct = $allS ? (int)round($doneS / $allS * 100) : 0;
$check = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

team_header('Your checklist', $me);
?>
<div class="adm-head">
  <div>
    <span class="eyebrow"><span class="dot"></span><?= e(team_role($me['role'])) ?> · <?= e(TEAM_STATUSES[$me['status']] ?? $me['status']) ?></span>
    <h1><?= $doneS >= $allS ? 'You’re all set, ' : 'Welcome, ' ?><?= e(team_first($me)) ?></h1>
    <p class="muted" style="margin:6px 0 0"><?= $doneS ?> of <?= $allS ?> onboarding steps done<?= $me['start_date'] ? ' · Start date ' . e(fmt_date($me['start_date'])) : '' ?></p>
  </div>
  <div class="t-meter" role="img" aria-label="<?= $pct ?>% of onboarding done"><span style="width:<?= $pct ?>%"></span></div>
</div>

<?php if ($leaving): ?>
  <p class="notice">Thanks for your work with Syndicate. Your last steps are at the bottom of the checklist<?= $me['end_date'] ? ', with an end date of ' . e(fmt_date($me['end_date'])) : '' ?>.</p>
<?php endif; ?>

<section class="panel" id="checklist" aria-labelledby="h-check">
  <div class="panel-h"><h2 id="h-check">Checklist</h2><small class="muted">Most people finish in under an hour</small></div>
  <?php $phase = ''; ?>
  <ol class="t-steps">
  <?php foreach ($steps as $i => $s):
      $r = $rows[$s['key']] ?? null; $isDone = !empty($r['done_at']);
      $url = !empty($s['doc']) ? team_doc_url($me, $s['doc']) : '';
      if ($s['phase'] !== $phase): $phase = $s['phase']; if ($phase === 'end'): ?>
    </ol><h3 class="t-phase">Leaving Syndicate</h3><ol class="t-steps">
  <?php endif; endif; ?>
    <li class="t-step<?= $isDone ? ' done' : '' ?>" id="s-<?= e($s['key']) ?>">
      <span class="t-dot" aria-hidden="true"><?= $isDone ? $check : '' ?></span>
      <div class="t-body">
        <b><?= e($s['title']) ?></b><span class="sr-only"><?= $isDone ? ' (done)' : ' (to do)' ?></span>
        <p><?= e($s['help']) ?></p>
        <?php if ($url): ?><p><a class="t-doc" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer">Open <?= e(TEAM_DOCS[$s['doc']]) ?> ↗</a></p>
        <?php elseif (!empty($s['doc'])): ?><p class="muted t-small">Grady will share the <?= e(TEAM_DOCS[$s['doc']]) ?> with you.</p><?php endif; ?>
        <?php if ($isDone): ?>
          <p class="t-small muted mono">
            <?php if ($s['type'] === 'sign'): ?>Signed by <?= e($r['data']['name'] ?? '') ?> · <?= e(fmt_date($r['done_at'], 'M j, Y g:i a')) ?>
            <?php elseif ($s['type'] === 'pay'): ?><?= e(TEAM_PAY[$r['data']['method'] ?? ''] ?? '') ?> · <?= e(fmt_date($r['done_at'])) ?>
            <?php else: ?>Done <?= e(fmt_date($r['done_at'])) ?><?= $r['done_by'] === 'admin' ? ' by Grady' : '' ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </div>
      <div class="t-act">
        <?php if ($s['type'] === 'sign' && !$isDone): ?>
          <a class="btn btn-primary btn-sm" href="team/sign.php?s=<?= e($s['key']) ?>">Read &amp; sign</a>
        <?php elseif ($s['type'] === 'tick'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="step" value="<?= e($s['key']) ?>">
            <?php if ($isDone): ?><button class="btn btn-ghost btn-xs" name="action" value="untick">Undo</button>
            <?php else: ?><button class="btn btn-primary btn-sm" name="action" value="tick">Mark done</button><?php endif; ?>
          </form>
        <?php elseif ($s['type'] === 'pay'): ?>
          <form method="post" class="t-pay"><?= csrf_field() ?><input type="hidden" name="step" value="<?= e($s['key']) ?>"><input type="hidden" name="action" value="pay">
            <label class="sr-only" for="pay-m">Payment method</label>
            <select id="pay-m" name="method"><?php foreach (TEAM_PAY as $k => $l): ?><option value="<?= e($k) ?>"<?= $me['pay_method'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
            <button class="btn btn-<?= $isDone ? 'ghost' : 'primary' ?> btn-sm"><?= $isDone ? 'Change' : 'Save' ?></button>
          </form>
        <?php elseif ($s['type'] === 'admin' && !$isDone): ?>
          <?php if (!empty($r['nudged_at'])): ?><span class="tag">GRADY’S BEEN TOLD</span>
          <?php elseif (!empty($s['nudge'])): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="step" value="<?= e($s['key']) ?>"><button class="btn btn-ghost btn-sm" name="action" value="nudge"><?= e($s['nudge']) ?></button></form>
          <?php else: ?><span class="tag">WAITING ON GRADY</span><?php endif; ?>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
  </ol>
</section>

<div class="adm-grid">
  <section class="panel" id="docs" aria-labelledby="h-docs">
    <div class="panel-h"><h2 id="h-docs">Your documents</h2></div>
    <ul class="list">
      <?php foreach ($docs as $d): $u = team_doc_url($me, $d); ?>
        <li><?php if ($u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener noreferrer"><span class="grow"><b><?= e(TEAM_DOCS[$d]) ?></b></span><span class="muted">↗</span></a>
        <?php else: ?><span class="li-row"><span class="grow"><b><?= e(TEAM_DOCS[$d]) ?></b><small class="muted">Link coming from Grady</small></span></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
    <p class="form-note">You can export any document to PDF from its page. Your signatures are recorded here with the date and time.</p>
  </section>

  <section class="panel" id="tools" aria-labelledby="h-tools">
    <div class="panel-h"><h2 id="h-tools">Links &amp; tools</h2></div>
    <?php if ($rep): ?>
      <div class="f"><span class="lbl">Your referral link</span>
        <?php if ($me['ref_link']): ?><div class="t-copy"><input readonly value="<?= e($me['ref_link']) ?>" aria-label="Your referral link" onclick="this.select()"><button class="btn btn-ghost btn-sm" type="button" data-copy="<?= e($me['ref_link']) ?>">Copy</button></div>
        <p class="form-note">Add it to every email. Works on any of our pages: add <span class="mono">?ref=</span> plus your code.</p>
        <?php else: ?><p class="muted t-small" style="margin:0">Shows up here once Grady sets you up in the CRM.</p><?php endif; ?>
      </div>
      <ul class="list">
        <li><a href="https://crm.stusyn.com/refer.php" target="_blank" rel="noopener"><span class="grow"><b>Partner page</b><small>Referrals, earnings and payouts</small></span>↗</a></li>
        <li><a href="https://syndicatemarketing.co/checkup.html" target="_blank" rel="noopener"><span class="grow"><b>Free website checkup</b><small>Your best opener</small></span>↗</a></li>
        <li><a href="https://syndicatemarketing.co/pricing.html" target="_blank" rel="noopener"><span class="grow"><b>Prices</b><small>Always quote from here</small></span>↗</a></li>
        <li><a href="https://syndicatemarketing.co/starter-website-offer.html" target="_blank" rel="noopener"><span class="grow"><b>Current offer</b><small>Mention it only while it’s live</small></span>↗</a></li>
        <li><a href="https://syndicatemarketing.co/work.html" target="_blank" rel="noopener"><span class="grow"><b>Our work</b><small>“Concept” means fictional sample work</small></span>↗</a></li>
        <li><a href="https://crm.stusyn.com/portal-demo.php" target="_blank" rel="noopener"><span class="grow"><b>Demo client portal</b><small>A made-up business to show prospects</small></span>↗</a></li>
      </ul>
    <?php else: ?>
      <ul class="list">
        <li><a href="https://crm.stusyn.com/" target="_blank" rel="noopener"><span class="grow"><b>CRM</b><small>Projects, clients, tasks</small></span>↗</a></li>
        <?php if ($me['role'] === 'paid_social'): ?><li><a href="https://ads.stusyn.com/" target="_blank" rel="noopener"><span class="grow"><b>Ads app</b><small>Campaigns, creative approvals, results</small></span>↗</a></li><?php endif; ?>
        <li><a href="https://wiki.stusyn.com/team.php" target="_blank" rel="noopener"><span class="grow"><b>Team docs</b><small>How we run things</small></span>↗</a></li>
        <li><a href="https://wiki.stusyn.com/" target="_blank" rel="noopener"><span class="grow"><b>Help center</b><small>What clients read</small></span>↗</a></li>
        <li><a href="https://syndicatemarketing.co/pricing.html" target="_blank" rel="noopener"><span class="grow"><b>Prices</b><small>Every package we sell</small></span>↗</a></li>
      </ul>
    <?php endif; ?>
  </section>
</div>

<section class="panel" id="help" aria-labelledby="h-help">
  <div class="panel-h"><h2 id="h-help">Help</h2></div>
  <dl class="facts">
    <div><dt>Questions</dt><dd>Email <a href="mailto:hello@stusyn.com">hello@stusyn.com</a>. Grady replies within one business day.</dd></div>
    <?php if ($rep): ?>
    <div><dt>How you’re paid</dt><dd>10% of what each client you bring in pays us for 12 months. Each earning is held 30 days, then paid in the first five business days of the next month ($25 minimum, W-9 on file).</dd></div>
    <div><dt>Opt-outs</dt><dd>Someone asked you to stop? Stop right away and email us their business and email within 2 business days.</dd></div>
    <?php else: ?>
    <div><dt>Invoices</dt><dd>Send one to hello@stusyn.com on the 1st and 16th, itemized by client. Approved invoices are paid within 10 business days.</dd></div>
    <?php endif; ?>
    <div><dt>Something went wrong</dt><dd>Lost device, odd sign-in, data sent to the wrong person: email us the same day. Reporting fast is never held against you.</dd></div>
    <div><dt>The non-negotiables</dt><dd>No fake reviews, results or urgency. Published prices only. Never ask anyone for passwords or card numbers.</dd></div>
  </dl>
</section>
<script>
document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', () => {
  navigator.clipboard && navigator.clipboard.writeText(b.dataset.copy).then(() => { b.textContent = 'Copied'; setTimeout(() => { b.textContent = 'Copy'; }, 1800); });
}));
</script>
<?php team_footer(); ?>
