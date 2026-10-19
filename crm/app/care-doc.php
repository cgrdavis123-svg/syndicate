<?php
// The monthly care report as a printable web page (crm.stusyn.com/care-report.php), with simple SVG charts.
require_once __DIR__ . '/care.php';
require_once __DIR__ . '/analytics.php';

// Axis labels live in HTML so they don't stretch with the chart on narrow screens.
function care_chart_wrap(string $svg, string $top, string $bottom, string $first, string $last): string {
    return '<div class="cr-cw">' . ($top !== '' ? '<span class="cr-y top">' . e($top) . '</span>' : '') . ($bottom !== '' ? '<span class="cr-y bot">' . e($bottom) . '</span>' : '') . $svg
        . '<div class="cr-x"><span>' . e($first) . '</span><span>' . e($last) . '</span></div></div>';
}
// Vertical bars. $vals: numbers (null = no data), $labels: one per bar, used for tooltips. $cls: optional class per bar.
function care_svg_bars(array $vals, array $labels, string $unit = '', array $cls = [], string $aria = '', array $tips = []): string {
    $n = max(1, count($vals)); $w = 600; $h = 150; $pad = 22; $max = max(1, max(array_map('floatval', array_filter($vals, function ($v) { return $v !== null; })) ?: [1]));
    $bw = ($w - 2) / $n; $o = '<svg class="cr-chart" viewBox="0 0 ' . $w . ' ' . ($h + 1) . '" role="img" aria-label="' . e($aria) . '" preserveAspectRatio="none">';
    $o .= '<line x1="0" y1="' . $h . '" x2="' . $w . '" y2="' . $h . '" class="axis"/>';
    $top = $tips ? '' : an_num((int)round($max)) . $unit;
    foreach (array_values($vals) as $i => $v) {
        $x = 1 + $i * $bw; $bh = $v === null ? 0 : max($v > 0 ? 2 : 0, $h * $v / $max * .88);
        $o .= '<rect x="' . round($x + $bw * .15, 1) . '" y="' . round($h - $bh, 1) . '" width="' . round($bw * .7, 1) . '" height="' . round($bh, 1) . '" rx="2" class="' . e($cls[$i] ?? 'bar') . '"><title>' . e($tips[$i] ?? (($labels[$i] ?? '') . ': ' . ($v === null ? 'no data' : an_num((int)round($v)) . $unit))) . '</title></rect>';
    }
    return care_chart_wrap($o . '</svg>', $top, '', $labels[0] ?? '', end($labels) ?: '');
}
// A line with dots, for response times.
function care_svg_line(array $vals, array $labels, string $unit = '', string $aria = ''): string {
    $pts = array_filter($vals, function ($v) { return $v !== null; });
    if (!$pts) return '<p class="cr-empty">No checks yet this month.</p>';
    $w = 600; $h = 120; $pad = 22; $n = max(2, count($vals)); $max = max($pts) * 1.1; $min = min($pts) * .6;
    $xy = []; foreach (array_values($vals) as $i => $v) if ($v !== null) $xy[] = [round($i * $w / ($n - 1), 1), round($h - ($v - $min) / max(1, $max - $min) * $h, 1), $labels[$i] ?? '', $v];
    $o = '<svg class="cr-chart" viewBox="0 0 ' . $w . ' ' . ($h + 1) . '" role="img" aria-label="' . e($aria) . '" preserveAspectRatio="none">';
    $o .= '<line x1="0" y1="' . $h . '" x2="' . $w . '" y2="' . $h . '" class="axis"/>';
    $o .= '<polyline class="ln" points="' . implode(' ', array_map(function ($p) { return $p[0] . ',' . $p[1]; }, $xy)) . '"/>';
    foreach ($xy as [$x, $y, $l, $v]) $o .= '<circle cx="' . $x . '" cy="' . $y . '" r="3" class="pt"><title>' . e($l . ': ' . an_num((int)$v) . $unit) . '</title></circle>';
    return care_chart_wrap($o . '</svg>', an_num((int)round($max)) . $unit, an_num((int)round($min)) . $unit, $labels[0] ?? '', end($labels) ?: '');
}

function render_care_report(array $s, string $month): void {
    $from = strtotime($month . '-01'); $to = min(strtotime('+1 month', $from), strtotime('tomorrow'));
    $current = $month === date('Y-m'); $label = date('F Y', $from);
    $d = care_report_data($s, $from, $to); $tier = care_tier($s);
    $company = (string)val('SELECT name FROM companies WHERE id = ?', [(int)$s['company_id']]);
    $days = $d['days']; $dl = array_map(function ($x) { return date('M j', strtotime($x['day'])); }, $days);
    $T = $d['totals'] ?: ['visitors' => 0, 'visits' => 0, 'pageviews' => 0, 'conversions' => 0]; $P = $d['prev'] ?: null;
    $chg = function (string $k) use ($T, $P) { if (!$P || empty($P[$k])) return ''; $c = ($T[$k] - $P[$k]) / $P[$k] * 100; return '<small class="' . ($c >= 0 ? 'up' : 'down') . '">' . ($c >= 0 ? '▲ ' : '▼ ') . abs(round($c)) . '% vs last month</small>'; };
    $V = array_filter($d['vitals']); $psi = $d['psi'] && empty($d['psi']['error']) ? $d['psi'] : null;
    $find = care_findings($s); $bad = count(array_filter($find, function ($f) { return $f[0] !== 'good'; }));
    $allow = max(1, (int)$s['care_minutes']);
    ?>
<article class="care-doc">
  <header class="cr-top">
    <div><div class="inv-brand"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg><b><?= e(care_brand($s)) ?></b></div>
      <p><?= e($tier['name']) ?> report<?= $company ? ' for ' . e($company) : '' ?></p></div>
    <div class="cr-title"><h1><?= e($label) ?></h1><p><?= e($s['name']) ?> · <?= e(preg_replace('#^https?://#', '', care_url($s))) ?><?= $current ? '<br><span class="cr-so-far">Month so far, updated live</span>' : '' ?></p></div>
  </header>

  <section class="cr-kpis">
    <div><span>Uptime</span><b class="num"><?= e(care_fmt_pct($d['uptime'])) ?></b><small><?= $d['incidents'] ? count($d['incidents']) . ' outage' . (count($d['incidents']) === 1 ? '' : 's') . ' · ' . e(care_dur($d['down'])) : 'No outages' ?></small></div>
    <div><span>Response time</span><b class="num"><?= $d['ms'] ? $d['ms'] . '<small> ms</small>' : '—' ?></b><small>Average from our checks</small></div>
    <div><span>Visitors</span><b class="num"><?= an_num((int)$T['visitors']) ?></b><?= $chg('visitors') ?: '<small>Counted without cookies</small>' ?></div>
    <div><span>Leads &amp; goals</span><b class="num"><?= an_num((int)$T['conversions']) ?></b><?= $chg('conversions') ?: '<small>Calls, forms and more</small>' ?></div>
  </section>

  <section class="cr-sec">
    <h2>Uptime</h2>
    <p class="cr-lede">We checked your site every 15 minutes<?= $d['uptime'] !== null && $d['uptime'] >= 99.9 ? ' and it was there for your visitors' : '' ?>. Each bar is a day.</p>
    <?= care_svg_bars(array_map(function ($x) { return $x['pct'] === null ? null : 1; }, $days), $dl, '', array_map(function ($x) { return $x['pct'] === null ? 'none' : ($x['pct'] >= 99.9 ? 'ok' : ($x['pct'] >= 97 ? 'warn' : 'bad')); }, $days), 'Uptime by day',
      array_map(function ($x) { return date('M j', strtotime($x['day'])) . ': ' . ($x['pct'] === null ? 'not checked' : care_fmt_pct($x['pct']) . ' up'); }, $days)) ?>
    <div class="cr-legend"><span class="ok">Up all day</span><span class="warn">Short blip</span><span class="bad">Outage</span></div>
    <?php if ($d['incidents']): ?><ul class="cr-list"><?php foreach ($d['incidents'] as $i): ?><li><b><?= e(date('M j, g:i a', (int)$i['started'])) ?></b> · down for <?= e(care_dur((int)($i['ended'] ?: time()) - (int)$i['started'])) ?><?= $i['ended'] ? '' : ' (ongoing)' ?><?= $i['cause'] !== '' ? ' · ' . e($i['cause']) : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
    <h3>Response time</h3>
    <?= care_svg_line(array_map(function ($x) { return $x['ms']; }, $days), $dl, ' ms', 'Average response time by day') ?>
  </section>

  <section class="cr-sec">
    <h2>Speed</h2>
    <?php if ($psi): ?>
      <p class="cr-lede">Google’s PageSpeed test of your home page on a phone, run <?= e(date('M j', (int)$psi['at'])) ?>. 90 and up is good.</p>
      <?= care_psi_rings($psi) ?>
      <?php if ($psi['fixes']): ?><h3>Biggest speed wins left</h3><ul class="cr-list"><?php foreach (array_slice($psi['fixes'], 0, 3) as $f): ?><li><?= e($f['title']) ?><?= $f['ms'] >= 100 ? ' <span class="cr-muted">· could save about ' . e(number_format($f['ms'] / 1000, 1)) . ' s</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
    <?php else: ?><p class="cr-lede">Your first PageSpeed test runs within a week of starting.</p><?php endif; ?>
    <?php if ($V): ?><h3>What real visitors saw</h3><div class="cr-vitals">
      <?php foreach (['lcp' => ['Main content shows', 'Good under 2.5 s'], 'inp' => ['Reacts to taps', 'Good under 200 ms'], 'cls' => ['Layout stays put', 'Good under 0.10']] as $m => [$n, $g]): if (empty($V[$m])) continue; ?><div class="<?= vital_rating($m, $V[$m]['p75']) ?>"><span><?= e($n) ?></span><b class="num"><?= e(vital_fmt($m, $V[$m]['p75'])) ?></b><small><?= e($g) ?></small></div><?php endforeach; ?>
    </div><?php endif; ?>
  </section>

  <section class="cr-sec">
    <h2>Security &amp; health</h2>
    <p class="cr-lede"><?= !$find ? 'Your first weekly scan is on its way.' : ($bad ? "Our weekly scan found $bad thing" . ($bad === 1 ? '' : 's') . ' to look at. We’re on it.' : 'Our weekly scan found nothing to worry about.') ?></p>
    <?php if ($find): ?><ul class="cr-find"><?php foreach ($find as [$k, $t]): ?><li class="<?= e($k) ?>"><?= e($t) ?></li><?php endforeach; ?>
      <?php if ($s['ssl_expires']): ?><li class="<?= care_days_left((int)$s['ssl_expires']) <= 14 ? 'warn' : 'good' ?>">Security certificate valid until <?= e(date('M j, Y', (int)$s['ssl_expires'])) ?></li><?php endif; ?>
      <?php if ($s['domain_expires']): ?><li class="<?= care_days_left((int)$s['domain_expires']) <= 30 ? 'warn' : 'good' ?>">Domain registered until <?= e(date('M j, Y', (int)$s['domain_expires'])) ?></li><?php endif; ?></ul><?php endif; ?>
  </section>

  <section class="cr-sec">
    <h2>Visitors</h2>
    <?php if ((int)$T['visits'] === 0): ?><p class="cr-lede"><?= $s['care_built'] ? 'No visits recorded yet.' : 'Visitor numbers appear once the Site Stats snippet is on your site. We add it during setup.' ?></p>
    <?php else: ?>
      <?= care_svg_bars(array_map(function ($r) { return $r[1]; }, $d['series']), array_map(function ($r) { return $r[0]; }, $d['series']), '', [], 'Visitors by day') ?>
      <?php $goals = array_filter($d['goals'], function ($g) { return $g['conversions'] > 0; }); if ($goals): ?><div class="cr-goals"><?php foreach ($goals as $g): ?><div><b class="num"><?= an_num((int)$g['conversions']) ?></b><span><?= e($g['goal']['name']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <?php endif; ?>
  </section>

  <section class="cr-sec">
    <h2>Work we did</h2>
    <div class="cr-meter"><span>Edit time used</span><b class="num"><?= (int)$d['minutes'] ?> of <?= (int)$s['care_minutes'] ?> min</b><i><em style="width:<?= min(100, round($d['minutes'] / $allow * 100)) ?>%"></em></i><small><?= (int)$d['requests_done'] ?> request<?= $d['requests_done'] === 1 ? '' : 's' ?> done · <?= (int)$d['backups'] ?> backup<?= $d['backups'] === 1 ? '' : 's' ?></small></div>
    <?php if ($d['log']): ?><ul class="cr-log"><?php foreach ($d['log'] as $l): ?><li><time><?= e(date('M j', strtotime($l['created_at']))) ?></time><span class="cr-kind"><?= e(CARE_KINDS[$l['kind']] ?? 'Note') ?></span><span><?= e($l['title']) ?><?php if ($l['details'] !== ''): ?><small><?= e($l['details']) ?></small><?php endif; ?></span></li><?php endforeach; ?></ul>
    <?php else: ?><p class="cr-lede">Routine checks only; nothing needed fixing.</p><?php endif; ?>
  </section>

  <footer class="cr-foot">
    <p><b>Need a change?</b> Request it from your client portal and track it until it’s done. <?= e($tier['name']) ?> includes <?= (int)$s['care_minutes'] ?> minutes of edits a month, started within <?= e($tier['reply']) ?>.</p>
    <p class="no-print"><a class="btn primary" href="<?= e(abs_url('portal-care.php?id=' . (int)$s['id'])) ?>">Request a change <?= ARROW ?></a></p>
    <p class="cr-muted"><?= e(care_brand($s)) ?> · <?= e(setting('business_email')) ?></p>
  </footer>
</article>
<?php }
