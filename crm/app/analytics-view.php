<?php
// The stats report, shared by the CRM (site.php) and the client portal (portal-stats.php).
// $base is the page's own URL with ?id=…; every number links to a filtered view of itself.

function an_params(): array {
    $f = [];
    foreach ((array)($_GET['f'] ?? []) as $k => $v) if (isset(AN_DIMENSIONS[$k]) && is_string($v) && $v !== '') $f[$k] = mb_substr($v, 0, 300);
    return ['range' => isset(AN_RANGES[get('range')]) || get('range') === 'custom' ? get('range') : '30d', 'from' => get('from'), 'to' => get('to'), 'f' => $f];
}
function an_url(string $base, array $p, array $change = []): string {
    $f = isset($change['f']) ? array_filter(array_replace($p['f'] ?? [], $change['f']), 'strlen') : ($p['f'] ?? []);
    $p = array_replace($p, $change); $p['f'] = $f;
    $q = ['range' => $p['range'] !== '30d' ? $p['range'] : null];
    if ($p['range'] === 'custom') { $q['from'] = $p['from']; $q['to'] = $p['to']; }
    foreach ($p['f'] ?? [] as $k => $v) $q["f[$k]"] = $v;
    $qs = http_build_query(array_filter($q, function ($v) { return $v !== null && $v !== ''; }));
    return $base . ($qs !== '' ? '&' . $qs : '');
}

function render_an_table(string $title, array $rows, string $dim, string $base, array $p, array $o = []): void {
    $max = 0; foreach ($rows as $r) $max = max($max, (int)($r['visitors'] ?? $r['visits'] ?? $r['n'] ?? 0));
    $label = $o['label'] ?? function ($k) { return $k === '' ? '(none)' : $k; };
    $cols = $o['cols'] ?? ['visitors' => 'Visitors']; ?>
  <section class="card an-card"><div class="card-h"><h2><?= e($title) ?></h2><?php if (!empty($o['note'])): ?><span class="muted small"><?= e($o['note']) ?></span><?php endif; ?></div>
    <?php if (!$rows): ?><p class="empty">No data for this period.</p><?php else: ?>
    <table class="an-tbl"><thead><tr><th><?= e($o['head'] ?? AN_DIMENSIONS[$dim] ?? $title) ?></th><?php foreach ($cols as $c): ?><th class="r"><?= e($c) ?></th><?php endforeach; ?></tr></thead><tbody>
    <?php foreach ($rows as $r): $k = (string)$r['k']; $v = (int)($r['visitors'] ?? $r['visits'] ?? $r['n'] ?? 0); ?>
      <tr><td class="an-k"><i style="width:<?= $max ? round(100 * $v / $max, 1) : 0 ?>%"></i><?php if ($dim !== ''): ?><a href="<?= e(an_url($base, $p, ['f' => [$dim => $k === '' ? '(none)' : $k]])) ?>" title="Filter by this"><?= e($label($k, $r)) ?></a><?php else: ?><span><?= e($label($k, $r)) ?></span><?php endif; ?></td>
        <?php foreach ($cols as $ck => $c): ?><td class="r num"><?= $ck === 'secs' ? ($r['secs'] ? e(an_dur($r['secs'])) : '—') : ($ck === 'scroll' ? ($r['scroll'] ? round((float)$r['scroll']) . '%' : '—') : ($ck === 'bounce' ? ($r['visits'] ? round(100 * $r['bounces'] / $r['visits']) . '%' : '—') : ($ck === 'cr' ? ($r['visits'] ? round(100 * ($r['conv'] ?? 0) / $r['visits'], 1) . '%' : '—') : e(an_num($r[$ck] ?? 0))))) ?></td><?php endforeach; ?></tr>
    <?php endforeach; ?>
    </tbody></table><?php endif; ?>
  </section>
<?php }

function render_site_report(array $site, array $R, string $base, array $p, bool $staff): void {
    $T = $R['totals']; $P = $R['prev'];
    $rangeTxt = $R['range']['label']; ?>
  <div class="an-bar">
    <nav class="seg-links an-ranges" aria-label="Date range">
      <?php foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', 'month' => 'This month', 'lastmonth' => 'Last month', '90d' => '90 days', '12m' => '12 months'] as $k => $l): ?><a href="<?= e(an_url($base, $p, ['range' => $k])) ?>"<?= $R['range']['key'] === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a><?php endforeach; ?>
    </nav>
    <form method="get" class="an-custom row"><input type="hidden" name="id" value="<?= (int)$site['id'] ?>"><input type="hidden" name="range" value="custom"><?php foreach ($p['f'] as $k => $v): ?><input type="hidden" name="f[<?= e($k) ?>]" value="<?= e($v) ?>"><?php endforeach; ?>
      <input type="date" name="from" value="<?= e(date('Y-m-d', $R['range']['from'])) ?>" aria-label="From"><span class="muted">–</span><input type="date" name="to" value="<?= e(date('Y-m-d', $R['range']['to'] - 1)) ?>" aria-label="To"><button class="btn ghost xs" type="submit">Apply</button></form>
    <span class="live-pill" data-live="<?= e($staff ? 'api.php' : 'portal-stats.php?live=1&id=' . (int)$site['id']) ?>" data-site="<?= (int)$site['id'] ?>"><i></i><b class="num">–</b> online now</span>
  </div>
  <?php if ($p['f']): ?><div class="an-filters row"><span class="muted small">Filtered to</span><?php foreach ($p['f'] as $k => $v): $shown = $k === 'goal' ? (array_values(array_filter($R['goals'], function ($g) use ($v) { return (string)$g['id'] === $v; }))[0]['name'] ?? $v) : ($k === 'country' ? country_name($v === '(none)' ? '' : $v) : $v); ?><a class="chip" href="<?= e(an_url($base, $p, ['f' => [$k => '']])) ?>" title="Remove filter"><?= e(AN_DIMENSIONS[$k]) ?>: <b><?= e($shown) ?></b> ×</a><?php endforeach; ?><a class="muted small" href="<?= e(an_url($base, $p, ['f' => array_fill_keys(array_keys($p['f']), '')])) ?>">Clear all</a></div><?php endif; ?>

  <div class="an-kpis">
    <?php foreach ([['Visitors', an_num($T['visitors']), an_delta($T['visitors'], $P['visitors'])], ['Visits', an_num($T['visits']), an_delta($T['visits'], $P['visits'])], ['Pageviews', an_num($T['pageviews']), an_delta($T['pageviews'], $P['pageviews'])],
        ['Pages / visit', number_format($T['ppv'], 1), an_delta($T['ppv'], $P['ppv'])], ['Bounce rate', round($T['bounce']) . '%', an_delta($T['bounce'], $P['bounce'], true)], ['Engaged time', an_dur($T['duration']), an_delta($T['duration'], $P['duration'])],
        ['Conversions', an_num($T['conversions']) . ($T['visits'] ? ' <small>' . number_format($T['cr'], 1) . '%</small>' : ''), an_delta($T['conversions'], $P['conversions'])]] as [$l, $v, $d]): ?>
      <div class="an-kpi"><span><?= e($l) ?></span><b class="num"><?= $v ?></b><?= $d ?></div>
    <?php endforeach; ?>
  </div>
  <section class="card an-chart-card">
    <div class="card-h"><h2><?= e($rangeTxt) ?></h2><span class="legend"><i class="lg-vis"></i>Visitors <i class="lg-pv"></i>Pageviews</span></div>
    <div class="an-chart-wrap"><?= an_chart($R['series']) ?><div class="an-tip" hidden></div></div>
    <p class="muted small an-vs">Compared with the previous <?= $R['range']['key'] === 'today' ? 'day' : 'period of the same length' ?> (<?= e(date('M j', $R['range']['pfrom'])) ?> – <?= e(date('M j', $R['range']['pto'] - 1)) ?>). Bounce rate is better when it goes down.</p>
  </section>

  <div class="an-grid">
    <?php render_an_table('Top pages', $R['pages'], 'page', $base, $p, ['cols' => ['visitors' => 'Visitors', 'views' => 'Views', 'secs' => 'Time', 'scroll' => 'Scroll'], 'head' => 'Page']);
    render_an_table('Landing pages', $R['entries'], 'entry', $base, $p, ['cols' => ['visits' => 'Visits', 'bounce' => 'Bounce'], 'head' => 'First page of the visit']);
    render_an_table('Channels', $R['channel'], 'channel', $base, $p, ['cols' => ['visitors' => 'Visitors', 'cr' => 'Conv.']]);
    render_an_table('Sources', $R['source'], 'source', $base, $p, ['cols' => ['visitors' => 'Visitors', 'visits' => 'Visits', 'cr' => 'Conv.']]);
    render_an_table('Campaigns', $R['campaign'], 'campaign', $base, $p, ['cols' => ['visitors' => 'Visitors', 'visits' => 'Visits', 'cr' => 'Conv.'], 'head' => 'Campaign (utm_campaign)', 'label' => function ($k, $r) { return $k . ($r['src'] ? ' · ' . $r['src'] . ($r['med'] ? ' / ' . $r['med'] : '') : ''); }, 'note' => 'From ad links tagged with UTM']);
    ?>
    <section class="card an-card"><div class="card-h"><h2>Goals</h2><?php if ($staff): ?><a class="muted small" href="site-edit.php?id=<?= (int)$site['id'] ?>#goals">Edit goals</a><?php endif; ?></div>
      <?php if (!$R['goal_rows']): ?><p class="empty">No goals set up yet.</p><?php else: ?>
      <table class="an-tbl"><thead><tr><th>Goal</th><th class="r">Conversions</th><th class="r">Rate</th></tr></thead><tbody>
      <?php $gmax = max(1, max(array_column($R['goal_rows'], 'conversions'))); foreach ($R['goal_rows'] as $g): ?><tr><td class="an-k"><i style="width:<?= round(100 * $g['conversions'] / $gmax, 1) ?>%"></i><a href="<?= e(an_url($base, $p, ['f' => ['goal' => (string)$g['goal']['id']]])) ?>" title="Show only visits that converted"><?= e($g['goal']['name']) ?></a><small class="muted"> <?= $g['goal']['kind'] === 'page' ? 'visited ' . e($g['goal']['match']) : 'event' ?></small></td><td class="r num"><?= an_num($g['conversions']) ?></td><td class="r num"><?= number_format($g['cr'], 1) ?>%</td></tr><?php endforeach; ?>
      </tbody></table><?php endif; ?>
    </section>
    <?php
    render_an_table('Countries', $R['country'], 'country', $base, $p, ['label' => function ($k) { return country_name($k); }]);
    if ($R['region'] || $R['city']) { render_an_table('States & regions', $R['region'], 'region', $base, $p); render_an_table('Cities', $R['city'], 'city', $base, $p); }
    render_an_table('Devices', $R['device'], 'device', $base, $p, ['cols' => ['visitors' => 'Visitors', 'visits' => 'Visits']]);
    render_an_table('Browsers & apps', $R['browser'], 'browser', $base, $p, ['note' => 'In-app browsers show social traffic']);
    render_an_table('Operating systems', $R['os'], 'os', $base, $p);
    ?>
    <section class="card an-card an-wide"><div class="card-h"><h2>Actions</h2><span class="muted small">Calls, emails, form sends, downloads and outbound clicks</span></div>
      <?php if (!$R['events']): ?><p class="empty">No actions recorded in this period. Phone and email taps, form submits, downloads and outbound links are tracked automatically.</p><?php else: ?>
      <table class="an-tbl"><thead><tr><th>Action</th><th>Most common</th><th class="r">Visitors</th><th class="r">Total</th></tr></thead><tbody>
      <?php $emax = max(1, max(array_column($R['events'], 'n'))); foreach ($R['events'] as $ev): ?><tr><td class="an-k"><i style="width:<?= round(100 * $ev['n'] / $emax, 1) ?>%"></i><span><?= e($ev['k']) ?></span></td><td class="muted small an-top"><?= e(implode(' · ', array_map(function ($k, $n) { return $k . ' (' . $n . ')'; }, array_keys($ev['top']), $ev['top']))) ?: '—' ?></td><td class="r num"><?= an_num($ev['visitors']) ?></td><td class="r num"><?= an_num($ev['n']) ?></td></tr><?php endforeach; ?>
      </tbody></table><?php endif; ?>
    </section>
    <section class="card an-card an-wide"><div class="card-h"><h2>Site speed</h2><span class="muted small">Core Web Vitals from real visitors · 75th percentile, as Google measures it</span></div>
      <?php $V = $R['vitals']; $names = ['lcp' => ['Largest paint', 'How fast the main content shows'], 'inp' => ['Responsiveness', 'Delay after a tap or click'], 'cls' => ['Layout stability', 'How much things jump around'], 'fcp' => ['First paint', 'When something first appears'], 'ttfb' => ['Server response', 'Time to first byte']];
      if (!array_filter($V['all'])): ?><p class="empty">Not enough speed data yet. It comes in as people visit with modern browsers.</p><?php else: ?>
      <div class="vitals"><?php foreach ($names as $m => [$n, $d]): $a = $V['all'][$m]; $mob = $V['mobile'][$m]; ?>
        <div class="vital <?= $a ? vital_rating($m, $a['p75']) : '' ?>"><span><?= e($n) ?></span><b class="num"><?= $a ? e(vital_fmt($m, $a['p75'])) : '—' ?></b><small><?= e($d) ?></small><?php if ($mob): ?><small class="muted">Phones: <?= e(vital_fmt($m, $mob['p75'])) ?></small><?php endif; ?><small class="muted num"><?= $a ? an_num($a['n']) . ' views' : '' ?></small></div>
      <?php endforeach; ?></div>
      <p class="muted small"><span class="vk good"></span>Good <span class="vk ok"></span>Needs work <span class="vk poor"></span>Poor, using Google’s thresholds. Largest paint, responsiveness and layout stability are the three Google uses in search rankings.</p><?php endif; ?>
    </section>
    <section class="card an-card an-wide"><div class="card-h"><h2>When people visit</h2><span class="muted small">Visits by day and hour (<?= e(date('T')) ?>)</span></div>
      <?php $hmax = 0; foreach ($R['heat'] as $row) $hmax = max($hmax, max($row)); if (!$hmax): ?><p class="empty">No visits in this period.</p><?php else: ?>
      <div class="heat-wrap"><table class="heat"><thead><tr><th></th><?php for ($h = 0; $h < 24; $h++): ?><th><?= $h % 3 === 0 ? e(date('ga', strtotime("today $h:00"))) : '' ?></th><?php endfor; ?></tr></thead><tbody>
      <?php foreach ([1, 2, 3, 4, 5, 6, 0] as $wd): ?><tr><th><?= ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$wd] ?></th><?php for ($h = 0; $h < 24; $h++): $v = $R['heat'][$wd][$h] ?? 0; ?><td style="--a:<?= $v ? round(0.12 + 0.88 * $v / $hmax, 2) : 0 ?>" title="<?= ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][$wd] ?> <?= e(date('g a', strtotime("today $h:00"))) ?>: <?= $v ?> visit<?= $v === 1 ? '' : 's' ?>"></td><?php endfor; ?></tr><?php endforeach; ?>
      </tbody></table></div><?php endif; ?>
    </section>
  </div>
<?php }
