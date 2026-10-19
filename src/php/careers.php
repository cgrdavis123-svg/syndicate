<?php
// Safety net: if the server maps /careers/job-name here (Apache MultiViews), show that job instead of the list.
if (preg_match('#^/([a-z0-9-]+)/?$#', (string)($_SERVER['PATH_INFO'] ?? ''), $m)) { $_GET['slug'] = $m[1]; require __DIR__ . '/job.php'; exit; }
require __DIR__ . '/app/bootstrap.php';

$jobs = published_jobs();
$companies = array_values(array_unique(array_column($jobs, 'company')));
$counts = array_count_values(array_column($jobs, 'company'));

site_header([
    'title' => 'Careers — Syndicate Holdings Group',
    'description' => 'Open roles across Syndicate Holdings Group and its brands. Remote-first, with roots in Spartanburg, SC.',
    'path' => 'careers.php',
]);
?>
<section class="page-hero">
  <div class="wrap page-hero-grid">
    <div>
      <div class="crumbs reveal"><a href="./">Home</a><i>/</i><span>Careers</span></div>
      <h1 class="reveal d1">Don&rsquo;t just join a company.<br>Help <span class="serif grad-text">build</span> a few.</h1>
      <p class="lede reveal d2">Syndicate is where operators, creators and marketers work across several companies, launch new ones and own real outcomes.</p>
      <div class="cta-row reveal d3" style="margin-top:34px">
        <a href="#roles" class="btn btn-primary">See open roles <?= ARROW ?></a>
        <a href="<?= e(job_path('open-application')) ?>" class="btn btn-ghost">Open application</a>
      </div>
    </div>
    <div class="stats reveal d2" style="grid-template-columns:1fr 1fr">
      <div class="stat"><span class="k">Open roles</span><div class="v" data-count="<?= count($jobs) ?>"><?= count($jobs) ?></div></div>
      <div class="stat"><span class="k">Companies hiring</span><div class="v" data-count="<?= count($companies) ?>"><?= count($companies) ?></div></div>
    </div>
  </div>
</section>

<section class="section" id="roles" style="scroll-margin-top:72px">
  <div class="wrap">
    <div class="section-head">
      <div class="reveal">
        <span class="eyebrow"><span class="dot"></span>Open roles</span>
        <h2>Find your <em>lane.</em></h2>
      </div>
      <p class="reveal d1">Every role is part of Syndicate Holdings Group, whichever brand it sits in. Filter by company or search for what you do.</p>
    </div>

    <div class="job-tools reveal" id="jobTools">
      <div class="chip-row" role="group" aria-label="Filter by company">
        <button type="button" class="chip on" data-company="">All <span><?= count($jobs) ?></span></button>
        <?php foreach (COMPANIES as $c): if (empty($counts[$c])) continue; ?>
          <button type="button" class="chip" data-company="<?= e($c) ?>"><?= e($c) ?> <span><?= (int)$counts[$c] ?></span></button>
        <?php endforeach; ?>
      </div>
      <div class="job-search">
        <select id="jobWorkplace" aria-label="Filter by workplace">
          <option value="">Any workplace</option>
          <?php foreach (WORKPLACES as $w): ?><option><?= e($w) ?></option><?php endforeach; ?>
        </select>
        <input id="jobQuery" type="search" placeholder="Search roles" aria-label="Search roles">
      </div>
    </div>

    <div class="jobs" id="jobList">
      <?php foreach ($jobs as $j): ?>
        <a class="job-row reveal" href="<?= e(job_path($j['slug'])) ?>"
           data-company="<?= e($j['company']) ?>" data-workplace="<?= e($j['workplace']) ?>"
           data-text="<?= e(strtolower($j['title'] . ' ' . $j['team'] . ' ' . $j['company'] . ' ' . $j['location'] . ' ' . $j['summary'])) ?>">
          <div class="job-main">
            <span class="tag <?= company_tone($j['company']) ?>"><?= e(strtoupper($j['company'])) ?></span>
            <h3><?= e($j['title']) ?></h3>
            <p><?= e($j['summary']) ?></p>
          </div>
          <div class="job-meta">
            <span><?= e($j['team']) ?></span>
            <span><?= e($j['location']) ?> · <?= e($j['workplace']) ?></span>
            <span><?= e($j['employment_type']) ?></span>
          </div>
          <span class="arr"><?= ARROW ?></span>
        </a>
      <?php endforeach; ?>
      <div class="jobs-empty" id="jobsEmpty" <?= $jobs ? 'hidden' : '' ?>>
        <h3>No roles match that search.</h3>
        <p>Try a different filter, or send an open application and tell us where you&rsquo;d fit.</p>
        <a href="<?= e(job_path('open-application')) ?>" class="btn btn-ghost btn-sm">Open application <?= ARROW ?></a>
      </div>
    </div>

    <a class="open-slot reveal" href="<?= e(job_path('open-application')) ?>" style="margin-top:16px">
      <div>
        <h4><span class="tag blue">ANY TEAM</span> Don&rsquo;t see your role?</h4>
        <p>Send an open application. We read every one, and keep great people in mind for future roles.</p>
      </div>
      <span class="btn btn-ghost">Open application <?= ARROW ?></span>
    </a>
  </div>
</section>

<section class="section alt">
  <div class="wrap">
    <div class="section-head">
      <div class="reveal">
        <span class="eyebrow"><span class="dot"></span>Why Syndicate</span>
        <h2>Built for <em>builders.</em></h2>
      </div>
      <p class="reveal d1">A small holding company is a different kind of workplace. Here&rsquo;s what that means day to day.</p>
    </div>
    <div class="grid-3">
      <article class="pillar spot reveal">
        <div class="icon"><svg viewBox="0 0 20 20" fill="none"><rect x="2.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="2.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="2.5" y="11.5" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11.5" y="11.5" width="6" height="6" rx="1.5" fill="currentColor"/></svg></div>
        <h3>Two brands, one team</h3>
        <p>Work across Syndicate Marketing, Creator Ops Media and the ideas we help bring to life.</p>
      </article>
      <article class="pillar spot reveal d1">
        <div class="icon"><svg viewBox="0 0 20 20" fill="none"><path d="M3 15l4.5-4.5 3 3L17 7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.5 7H17v4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
        <h3>Real outcomes</h3>
        <p>GMV, revenue and growth. You&rsquo;ll see exactly what your work moves.</p>
      </article>
      <article class="pillar spot reveal d2">
        <div class="icon"><svg viewBox="0 0 20 20" fill="none"><path d="M10 2.5v4M10 13.5v4M2.5 10h4M13.5 10h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="10" cy="10" r="2.5" fill="currentColor"/></svg></div>
        <h3>Bring ideas to life</h3>
        <p>Help founders and businesses launch new brands, and see them go live.</p>
      </article>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head center">
      <span class="eyebrow reveal"><span class="dot"></span>How we hire</span>
      <h2 class="reveal d1">Simple and fast.</h2>
    </div>
    <div class="model reveal">
      <div class="step"><span class="k">STEP 01</span><div class="bar"></div><h3>Apply</h3><p>Pick a role or send an open application. It takes about five minutes.</p></div>
      <div class="step"><span class="k">STEP 02</span><div class="bar"></div><h3>Intro call</h3><p>A real conversation about you, our brands and what&rsquo;s next.</p></div>
      <div class="step"><span class="k">STEP 03</span><div class="bar"></div><h3>Show us</h3><p>Walk us through your work, or take on a short, practical project.</p></div>
      <div class="step"><span class="k">STEP 04</span><div class="bar"></div><h3>Build</h3><p>Join the team and start shipping.</p></div>
    </div>
  </div>
</section>

<section class="section alt">
  <div class="wrap" style="max-width:960px">
    <div class="section-head center">
      <span class="eyebrow reveal"><span class="dot"></span>Applicant FAQ</span>
      <h2 class="reveal d1">Before you apply.</h2>
    </div>
    <div class="faq reveal">
      <details open><summary>Do I need to live in Spartanburg?<i></i></summary><p>No. Syndicate doesn&rsquo;t have a headquarters, and most work is remote. Each listing says whether a role is remote or hybrid. Hybrid roles include some in-person work around Spartanburg, SC, like client shoots and meetings.</p></details>
      <details><summary>Can I apply to more than one role?<i></i></summary><p>Yes. Apply to each role you&rsquo;re interested in. Your applications go to the same hiring team, so we&rsquo;ll see the full picture.</p></details>
      <details><summary>What should I include?<i></i></summary><p>A resume, plus links to your work: a portfolio, accounts you&rsquo;ve grown, content you&rsquo;ve made or shops you&rsquo;ve run. Show us what you&rsquo;ve built.</p></details>
      <details><summary>What happens to my information?<i></i></summary><p>Only our hiring team sees your application. We keep it for up to 2 years so we can consider you for future roles, then delete it automatically. You can ask us to delete it sooner. Read our <a href="applicant-privacy.html" style="color:var(--text);text-decoration:underline">Applicant Privacy Notice</a>.</p></details>
      <details><summary>Do you offer accommodations?<i></i></summary><p>Yes. Syndicate is an equal opportunity employer. If you need an accommodation to apply or interview, or the online form doesn&rsquo;t work for you, email <a href="mailto:<?= e(config('careers_to')) ?>" style="color:var(--text);text-decoration:underline"><?= e(config('careers_to')) ?></a> and we&rsquo;ll help.</p></details>
      <details><summary>Will I hear back?<i></i></summary><p>We read every application. You&rsquo;ll get an email confirming we received it, and we&rsquo;ll reach out directly if there&rsquo;s a fit.</p></details>
    </div>
  </div>
</section>

<section class="cta">
  <div class="wrap">
    <div class="cta-card reveal">
      <span class="eyebrow"><span class="dot"></span>Open application</span>
      <h2>Think you&rsquo;re a <span class="grad-text">fit?</span></h2>
      <p>Send us your work, your links and what you want to build.</p>
      <div class="cta-row"><a href="<?= e(job_path('open-application')) ?>" class="btn btn-primary">Apply now <?= ARROW ?></a></div>
    </div>
  </div>
</section>
<?php site_footer(); ?>
