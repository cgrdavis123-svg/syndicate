<?php
// The monthly ads report as a printable page (ads.stusyn.com/report.php). Same paper style as the care report.
require_once __DIR__ . '/ads.php';
require_once __DIR__ . '/care-doc.php';

function render_ads_report(array $a, string $month): void {
    $d = ads_report_data($a, $month); $S = $d['S']; $P = $d['P'];
    $label = date('F Y', strtotime($month . '-01')); $current = $month === date('Y-m');
    $num = function ($v, $dec = 1) { return rtrim(rtrim(number_format((float)$v, $dec), '0'), '.'); };
    $chg = function ($now, $was, $lowerIsBetter = false) {
        if (!$was) return '<small>No data last month</small>';
        $c = ($now - $was) / $was * 100; $good = $lowerIsBetter ? $c <= 0 : $c >= 0;
        return '<small class="' . ($good ? 'up' : 'down') . '">' . ($c >= 0 ? '▲ ' : '▼ ') . abs(round($c)) . '% vs last month</small>';
    };
    $dl = array_map(function ($x) { return date('M j', strtotime($x['day'])); }, $d['daily']);
    ?>
<article class="care-doc">
  <header class="cr-top">
    <div><div class="inv-brand"><svg viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#0b0d10"/><path d="M22 9h-9a4 4 0 0 0 0 8h6a4 4 0 0 1 0 8h-9" fill="none" stroke="#c6ff3d" stroke-width="2.8" stroke-linecap="round"/></svg><b><?= e(ads_brand($a)) ?></b></div>
      <p>Ads report for <?= e($a['company']) ?><?= ads_platforms($a) ? '<br>' . e(implode(', ', array_map('ads_platform', ads_platforms($a)))) : '' ?></p></div>
    <div class="cr-title"><h1><?= e($label) ?></h1><p><?= $current ? '<span class="cr-so-far">Month so far, updated as results come in</span>' : e(fmt_date($d['from'], 'M j') . ' – ' . fmt_date($d['to'], 'M j, Y')) ?></p></div>
  </header>
  <section class="cr-kpis">
    <div><span>Ad spend</span><b class="num"><?= e(ads_money($S['spend'])) ?></b><?= $chg($S['spend'], $P['spend']) ?></div>
    <div><span>Leads</span><b class="num"><?= e($num($S['leads'])) ?></b><?= $chg($S['leads'], $P['leads']) ?></div>
    <div><span>Cost per lead</span><b class="num"><?= $S['cpl'] !== null ? e(ads_money($S['cpl'], 2)) : '—' ?></b><?= $S['cpl'] !== null && $P['cpl'] !== null ? $chg($S['cpl'], $P['cpl'], true) : '<small>' . ((float)$a['target_cpl'] > 0 ? 'Target ' . e(ads_money($a['target_cpl'], 2)) : '&nbsp;') . '</small>' ?></div>
    <div><span>Clicks</span><b class="num"><?= number_format((int)$S['clicks']) ?></b><small><?= $S['cpc'] !== null ? e(ads_money($S['cpc'], 2)) . ' per click' : '&nbsp;' ?></small></div>
  </section>
  <p class="cr-muted" style="margin:-14px 0 0;font-size:12.5px"><?= $S['leads_src'] === 'site' ? 'Leads are visits from your ads that called, emailed, filled in a form or bought on your website, counted by Site Stats.' : 'Leads are the conversions the ad platforms reported.' ?> Ad spend is paid to the platforms directly and isn’t part of our fee.</p>
  <?php if ($d['note']['summary'] !== '' || $d['note']['next_steps'] !== ''): ?>
  <section class="cr-sec cr-notes">
    <?php if ($d['note']['summary'] !== ''): ?><h2>How the month went</h2><p class="cr-lede"><?= nl2br(e($d['note']['summary'])) ?></p><?php endif; ?>
    <?php if ($d['note']['next_steps'] !== ''): ?><h3>Next month</h3><p class="cr-lede"><?= nl2br(e($d['note']['next_steps'])) ?></p><?php endif; ?>
  </section><?php endif; ?>
  <section class="cr-sec">
    <h2>Spend and results by day</h2>
    <?= care_svg_bars(array_column($d['daily'], 'spend'), $dl, '', [], 'Ad spend by day', array_map(function ($x) { return date('M j', strtotime($x['day'])) . ': ' . ads_money($x['spend'], 2) . ' spent'; }, $d['daily'])) ?>
    <h3>Conversions reported by the platforms</h3>
    <?= care_svg_bars(array_column($d['daily'], 'conv'), $dl, '', [], 'Conversions by day') ?>
  </section>
  <?php if ($d['platforms']): ?><section class="cr-sec"><h2>By platform</h2>
    <table class="cr-table"><thead><tr><th>Platform</th><th class="r">Spend</th><th class="r">Clicks</th><th class="r">Click rate</th><th class="r">Conversions</th><th class="r">Cost/conv.</th></tr></thead><tbody>
    <?php foreach ($d['platforms'] as $p => $t): ?><tr><td><?= e(ads_platform($p)) ?></td><td class="r"><?= e(ads_money($t['spend'], 2)) ?></td><td class="r"><?= number_format((int)$t['clicks']) ?></td><td class="r"><?= e(ads_pct($t['ctr'], 2)) ?></td><td class="r"><?= e($num($t['conversions'])) ?></td><td class="r"><?= $t['conversions'] > 0 ? e(ads_money($t['spend'] / $t['conversions'], 2)) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table></section><?php endif; ?>
  <?php if ($d['campaigns']): ?><section class="cr-sec"><h2>Campaigns</h2>
    <table class="cr-table"><thead><tr><th>Campaign</th><th class="r">Spend</th><th class="r">Clicks</th><th class="r">Conversions</th><th class="r">Cost/conv.</th></tr></thead><tbody>
    <?php foreach ($d['campaigns'] as $c): $t = $c['t']; ?><tr><td><?= e($c['name']) ?><small class="cr-muted" style="display:block"><?= e(ads_platform($c['platform']) . ' · ' . (AD_OBJECTIVES[$c['objective']] ?? '')) ?></small></td><td class="r"><?= e(ads_money($t['spend'], 2)) ?></td><td class="r"><?= number_format((int)$t['clicks']) ?></td><td class="r"><?= e($num($t['conversions'])) ?><?= $c['site_leads'] !== null ? '<small class="cr-muted" style="display:block">' . (int)$c['site_leads'] . ' on your site</small>' : '' ?></td><td class="r"><?= $t['conversions'] > 0 ? e(ads_money($t['spend'] / $t['conversions'], 2)) : '—' ?></td></tr><?php endforeach; ?>
    </tbody></table></section><?php endif; ?>
  <section class="cr-sec"><h2>What we did</h2>
    <?php if ($d['log']): ?><ul class="cr-log"><?php foreach ($d['log'] as $l): ?><li><time><?= e(date('M j', strtotime($l['created_at']))) ?></time><span class="cr-kind"><?= e(AD_LOG_KINDS[$l['kind']] ?? 'Note') ?></span><span><?= e($l['title']) ?><?php if ($l['details'] !== ''): ?><small><?= e($l['details']) ?></small><?php endif; ?></span></li><?php endforeach; ?></ul>
    <?php else: ?><p class="cr-lede">Day-to-day monitoring and bid adjustments only this month.</p><?php endif; ?>
  </section>
  <footer class="cr-foot">
    <p><b>Questions or ideas?</b> Message us from your ads portal and we’ll get back to you within one business day.</p>
    <p class="no-print"><a class="btn primary" href="<?= e(ads_url('portal.php#messages')) ?>">Open your ads portal <?= ARROW ?></a></p>
    <p class="cr-muted"><?= e(ads_brand($a)) ?> · <?= e(setting('business_email')) ?></p>
  </footer>
</article>
<?php }
