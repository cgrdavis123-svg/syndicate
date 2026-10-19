<?php
require __DIR__ . '/app/bootstrap.php';

$admin = current_admin();
$job = find_job(get('slug'), (bool)$admin);
if (!$job) not_found();

$isOpenApp = $job['slug'] === 'open-application';
$closed = $job['status'] === 'closed';
$maxMb = (int)config('max_upload_mb');
$errors = [];
$old = [];

function clean_url(string $u): string {
    if ($u === '') return '';
    if (!preg_match('#^https?://#i', $u)) $u = 'https://' . $u;
    return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

// A request bigger than PHP's post_max_size arrives with no fields or files at all, which would
// otherwise look like an expired session. Tell the applicant the real problem.
if (is_post() && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $errors['form'] = "Your resume is too large to upload. Please attach a file under {$maxMb} MB, or email it to " . config('careers_to') . '.';
} elseif (is_post() && !$closed) {
    foreach (['name', 'email', 'phone', 'location', 'linkedin', 'portfolio', 'work_authorized', 'source', 'why'] as $k) $old[$k] = post($k);
    $old['consent'] = post('consent');

    // Bots fill the hidden field; quietly pretend it worked.
    if (post('website') !== '') redirect(with_query(job_path($job['slug']), ['applied' => 1]));

    if (!csrf_ok()) $errors['form'] = 'Your session expired. Please submit the form again.';
    elseif (!form_stamp_ok(post('stamp'))) $errors['form'] = 'That was quick! Please review your details and submit again.';
    elseif (too_many('apply', 6, 60)) $errors['form'] = "You've sent several applications in the last hour. Please try again later.";
    elseif (!captcha_ok()) $errors['form'] = CAPTCHA_FAIL;

    if (mb_strlen($old['name']) < 2) $errors['name'] = 'Enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email address.';
    if ($old['linkedin'] !== '' && clean_url($old['linkedin']) === '') $errors['linkedin'] = 'Enter a valid link, or leave this blank.';
    if ($old['portfolio'] !== '' && clean_url($old['portfolio']) === '') $errors['portfolio'] = 'Enter a valid link, or leave this blank.';
    if (!in_array($old['work_authorized'], ['Yes', 'No'], true)) $errors['work_authorized'] = 'Choose an answer.';
    if (mb_strlen($old['why']) < 20) $errors['why'] = 'Tell us a little more (at least a couple of sentences).';
    if (mb_strlen($old['why']) > 5000) $errors['why'] = 'Keep this under 5,000 characters.';
    if ($old['consent'] !== '1') $errors['consent'] = 'Please confirm so we can store your application.';

    // Resume upload
    $f = $_FILES['resume'] ?? null;
    $ext = '';
    if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
        $errors['resume'] = 'Attach your resume.';
    } elseif ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE || $f['size'] > $maxMb * 1048576) {
        $errors['resume'] = "Your file is too large. The limit is {$maxMb} MB.";
    } elseif ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
        $errors['resume'] = 'The upload failed. Please try again.';
    } else {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $allowed = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream', 'application/CDFV2', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
        ];
        $mime = class_exists('finfo') ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
        if (!isset($allowed[$ext]) || ($mime && !in_array($mime, $allowed[$ext], true))) {
            $errors['resume'] = 'Upload a PDF or Word document (.pdf, .doc or .docx).';
        }
    }

    if (!$errors) {
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($f['tmp_name'], STORAGE . '/resumes/' . $stored)) {
            $errors['form'] = "We couldn't save your resume. Please try again, or email us at " . config('careers_to') . '.';
        } else {
            @chmod(STORAGE . '/resumes/' . $stored, 0640);
            $origName = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename($f['name'])), 0, 120);
            $st = db()->prepare('INSERT INTO applications (job_id, job_title, name, email, phone, location, linkedin, portfolio, work_authorized, source, why, resume_file, resume_name, created_at, updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute([$job['id'], $job['title'], $old['name'], $old['email'], mb_substr($old['phone'], 0, 40), mb_substr($old['location'], 0, 120),
                clean_url($old['linkedin']), clean_url($old['portfolio']), $old['work_authorized'], in_array($old['source'], SOURCES, true) ? $old['source'] : '',
                $old['why'], $stored, $origName, now(), now()]);
            $appId = (int)db()->lastInsertId();
            record_attempt('apply');

            send_mail(config('careers_to'), "New application: {$job['title']} — {$old['name']}",
                "A new application just came in.\n\n" .
                "Role: {$job['title']} ({$job['company']})\nName: {$old['name']}\nEmail: {$old['email']}\n" .
                ($old['phone'] ? "Phone: {$old['phone']}\n" : '') . ($old['location'] ? "Location: {$old['location']}\n" : '') .
                ($old['linkedin'] ? 'LinkedIn: ' . clean_url($old['linkedin']) . "\n" : '') . ($old['portfolio'] ? 'Portfolio: ' . clean_url($old['portfolio']) . "\n" : '') .
                "Authorized to work in the US: {$old['work_authorized']}\n\nWhy Syndicate:\n{$old['why']}\n\n" .
                'Review it in the dashboard: ' . abs_url('admin/application.php?id=' . $appId) . "\n",
                $old['email'], config('careers_mail_from'), config('careers_mail_from_name'));

            $first = explode(' ', $old['name'])[0];
            send_mail($old['email'], "We got your application — {$job['title']}",
                "Hi {$first},\n\nThanks for applying for {$job['title']}" . ($isOpenApp ? '' : " at {$job['company']}") . ". We've received your application and our team will review it.\n\n" .
                "If there's a fit, we'll reach out to you directly at this email address.\n\n" .
                "In the meantime, you can see all our open roles at " . abs_url('careers.php') . "\n\n— The Syndicate team\nSyndicate Holdings Group · Spartanburg, SC",
                config('careers_to'), config('careers_mail_from'), config('careers_mail_from_name'));

            redirect(with_query(job_path($job['slug']), ['applied' => 1]));
        }
    }
}

$applied = get('applied') === '1';
$pageTitle = $isOpenApp ? 'Open application — Syndicate Careers' : "{$job['title']} — {$job['company']} | Syndicate Careers";
$fieldErr = function (string $k) use ($errors) { return isset($errors[$k]) ? '<p class="err" id="err-' . $k . '">' . e($errors[$k]) . '</p>' : ''; };
$val = function (string $k) use ($old) { return e($old[$k] ?? ''); };
$inv = function (string $k) use ($errors) { return isset($errors[$k]) ? ' aria-invalid="true" aria-describedby="err-' . $k . '"' : ''; };

site_header([
    'title' => $pageTitle,
    'description' => $job['summary'],
    'path' => job_path($job['slug']),
    'robots' => $job['status'] === 'draft' ? 'noindex' : '',
]);

if (!$isOpenApp && $job['status'] === 'published'):
    $desc = '<p>' . implode('</p><p>', array_map('e', paragraphs($job['about']))) . '</p>';
    foreach (['responsibilities' => "What you'll do", 'requirements' => "What you'll bring", 'nice_to_have' => 'Nice to have'] as $k => $h) {
        if (lines($job[$k])) $desc .= '<h3>' . e($h) . '</h3><ul><li>' . implode('</li><li>', array_map('e', lines($job[$k]))) . '</li></ul>';
    }
    $ld = [
        '@context' => 'https://schema.org', '@type' => 'JobPosting',
        'title' => $job['title'], 'description' => $desc,
        'datePosted' => date('Y-m-d', strtotime($job['published_at'] ?: $job['created_at'])),
        'validThrough' => date('Y-m-d\TH:i:sP', strtotime(($job['published_at'] ?: $job['created_at']) . ' +120 days')),
        'employmentType' => strtoupper(str_replace('-', '_', $job['employment_type'])),
        'hiringOrganization' => ['@type' => 'Organization', 'name' => $job['company'] === 'Syndicate Holdings Group' ? 'Syndicate Holdings Group LLC' : $job['company'], 'sameAs' => config('site_url')],
        'directApply' => true,
    ];
    if ($job['workplace'] === 'Remote') {
        $ld['jobLocationType'] = 'TELECOMMUTE';
        $ld['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => 'USA'];
    } else {
        $ld['jobLocation'] = ['@type' => 'Place', 'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Spartanburg', 'addressRegion' => 'SC', 'addressCountry' => 'US']];
    }
?>
<script type="application/ld+json"><?= json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>

<section class="page-hero job-hero" style="--glow: <?= $job['company'] === 'Creator Ops Media' ? 'rgba(255,92,138,.18)' : ($job['company'] === 'Syndicate Marketing' ? 'rgba(198,255,61,.14)' : 'rgba(110,168,255,.16)') ?>">
  <div class="wrap">
    <div class="crumbs reveal"><a href="./">Home</a><i>/</i><a href="careers.php">Careers</a><i>/</i><span><?= e($job['title']) ?></span></div>
    <?php if ($job['status'] === 'draft'): ?><p class="notice warn reveal" style="margin-top:20px">Draft preview. Only signed-in admins can see this page.</p><?php endif; ?>
    <div class="reveal d1" style="margin-top:22px"><span class="tag <?= company_tone($job['company']) ?>"><?= e(strtoupper($job['company'])) ?><?= $job['team'] ? ' · ' . e(strtoupper($job['team'])) : '' ?></span></div>
    <h1 class="reveal d1" style="margin-top:18px"><?= e($job['title']) ?></h1>
    <p class="lede reveal d2"><?= e($job['summary']) ?></p>
    <div class="job-facts reveal d3">
      <span><b>Location</b><?= e($job['location']) ?></span>
      <span><b>Workplace</b><?= e($job['workplace']) ?></span>
      <span><b>Type</b><?= e($job['employment_type']) ?></span>
      <?php if ($job['compensation']): ?><span><b>Pay</b><?= e($job['compensation']) ?></span><?php endif; ?>
      <?php if (!$isOpenApp && $job['published_at']): ?><span><b>Posted</b><?= e(fmt_date($job['published_at'])) ?></span><?php endif; ?>
    </div>
  </div>
</section>

<section class="section" style="padding-top:80px">
  <div class="wrap job-layout">
    <article class="job-body">
      <?php if ($applied): ?>
        <div class="success-panel" id="apply" role="status" style="scroll-margin-top:100px">
          <div class="success-ico"><svg viewBox="0 0 24 24" fill="none"><path d="M5 12.5 10 17l9-10" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <h2>Application received.</h2>
          <p>Thanks for applying<?= $isOpenApp ? '' : ' for ' . e($job['title']) ?>. We&rsquo;ve sent a confirmation to your email, and our team will reach out directly if there&rsquo;s a fit.</p>
          <div class="cta-row"><a href="careers.php#roles" class="btn btn-primary">See other roles <?= ARROW ?></a><a href="about.html" class="btn btn-ghost">Read our story</a></div>
        </div>
      <?php endif; ?>

      <div class="job-copy">
        <h2 class="job-h">About the role</h2>
        <?php foreach (paragraphs($job['about']) as $p): ?><p><?= e($p) ?></p><?php endforeach; ?>
        <?php foreach (['responsibilities' => 'What you&rsquo;ll do', 'requirements' => 'What you&rsquo;ll bring', 'nice_to_have' => 'Nice to have'] as $k => $h): if (!lines($job[$k])) continue; ?>
          <h2 class="job-h"><?= $h ?></h2>
          <ul class="job-list"><?php foreach (lines($job[$k]) as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul>
        <?php endforeach; ?>
        <h2 class="job-h">About Syndicate</h2>
        <p>Founded in Spartanburg, SC in 2019, Syndicate Holdings Group is the holding company for our brands. They include Syndicate Marketing, our social media and business marketing group, and Creator Ops Media, a TikTok Shop consultancy built on more than $10M in GMV.</p>
        <p class="eeo"><strong style="color:var(--text)">Equal opportunity.</strong> Syndicate Holdings Group and its companies are equal opportunity employers. We don&rsquo;t discriminate on the basis of race, color, religion, sex, pregnancy, sexual orientation, gender identity, national origin, age, disability, genetic information, veteran status or any other status protected by law. If you need an accommodation to apply or interview, email <a href="mailto:<?= e(config('careers_to')) ?>?subject=Accommodation%20request"><?= e(config('careers_to')) ?></a>.</p>
      </div>

      <?php if ($closed): ?>
        <div class="notice" id="apply">This role is no longer accepting applications. <a href="careers.php#roles">See open roles</a> or <a href="<?= e(job_path('open-application')) ?>">send an open application</a>.</div>
      <?php elseif (!$applied): ?>
      <form class="form spot apply-form" id="apply" method="post" action="<?= e(job_path($job['slug'])) ?>#apply" enctype="multipart/form-data" novalidate>
        <div>
          <span class="eyebrow"><span class="dot"></span>Apply</span>
          <h2 style="font-size:clamp(28px,3vw,40px);margin-top:12px"><?= $isOpenApp ? 'Tell us about you.' : 'Apply for this role.' ?></h2>
        </div>
        <?php if (isset($errors['form'])): ?><p class="notice warn" role="alert"><?= e($errors['form']) ?></p>
        <?php elseif ($errors): ?><p class="notice warn" role="alert">Please fix the highlighted fields below.</p><?php endif; ?>
        <?= csrf_field() ?>
        <input type="hidden" name="stamp" value="<?= e(form_stamp()) ?>">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxMb * 1048576 ?>">
        <div class="hp" aria-hidden="true"><label for="a-website">Website</label><input id="a-website" name="website" tabindex="-1" autocomplete="off"></div>

        <div class="field-row">
          <div class="f"><label for="a-name">Full name *</label><input id="a-name" name="name" required autocomplete="name" value="<?= $val('name') ?>"<?= $inv('name') ?>><?= $fieldErr('name') ?></div>
          <div class="f"><label for="a-email">Email *</label><input id="a-email" name="email" type="email" required autocomplete="email" value="<?= $val('email') ?>"<?= $inv('email') ?>><?= $fieldErr('email') ?></div>
        </div>
        <div class="field-row">
          <div class="f"><label for="a-phone">Phone</label><input id="a-phone" name="phone" type="tel" autocomplete="tel" value="<?= $val('phone') ?>"></div>
          <div class="f"><label for="a-location">City, state</label><input id="a-location" name="location" autocomplete="address-level2" placeholder="Spartanburg, SC" value="<?= $val('location') ?>"></div>
        </div>
        <div class="field-row">
          <div class="f"><label for="a-linkedin">LinkedIn</label><input id="a-linkedin" name="linkedin" inputmode="url" placeholder="linkedin.com/in/you" value="<?= $val('linkedin') ?>"<?= $inv('linkedin') ?>><?= $fieldErr('linkedin') ?></div>
          <div class="f"><label for="a-portfolio">Portfolio, TikTok or website</label><input id="a-portfolio" name="portfolio" inputmode="url" placeholder="tiktok.com/@you" value="<?= $val('portfolio') ?>"<?= $inv('portfolio') ?>><?= $fieldErr('portfolio') ?></div>
        </div>

        <div class="f">
          <label for="a-resume">Resume * <span style="color:var(--dim)">(PDF or Word, up to <?= $maxMb ?> MB)</span></label>
          <label class="drop<?= isset($errors['resume']) ? ' bad' : '' ?>" for="a-resume" id="dropzone">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V4m0 0-4.5 4.5M12 4l4.5 4.5M4 16v2.5A1.5 1.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span class="drop-t" id="dropText"><b>Choose a file</b> or drag it here</span>
          </label>
          <input class="file-input" id="a-resume" name="resume" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required<?= $inv('resume') ?>>
          <?= $fieldErr('resume') ?>
        </div>

        <div class="f">
          <label for="a-why"><?= $isOpenApp ? 'What would you bring to Syndicate? *' : 'Why this role, and what have you built? *' ?></label>
          <textarea id="a-why" name="why" required maxlength="5000" placeholder="Share the work you're proudest of, and why Syndicate."<?= $inv('why') ?>><?= $val('why') ?></textarea>
          <?= $fieldErr('why') ?>
        </div>

        <div class="field-row">
          <div class="f"><label for="a-auth">Are you authorized to work in the US? *</label>
            <select id="a-auth" name="work_authorized" required<?= $inv('work_authorized') ?>>
              <option value="">Choose…</option>
              <?php foreach (['Yes', 'No'] as $o): ?><option<?= ($old['work_authorized'] ?? '') === $o ? ' selected' : '' ?>><?= $o ?></option><?php endforeach; ?>
            </select><?= $fieldErr('work_authorized') ?></div>
          <div class="f"><label for="a-source">How did you hear about us?</label>
            <select id="a-source" name="source">
              <option value="">Choose…</option>
              <?php foreach (SOURCES as $o): ?><option<?= ($old['source'] ?? '') === $o ? ' selected' : '' ?>><?= e($o) ?></option><?php endforeach; ?>
            </select></div>
        </div>

        <label class="consent<?= isset($errors['consent']) ? ' bad' : '' ?>"><input type="checkbox" name="consent" value="1"<?= ($old['consent'] ?? '') === '1' ? ' checked' : '' ?>> <span>I agree that Syndicate Holdings Group can store my application for up to 2 years and contact me about this and future roles, as described in the <a href="applicant-privacy.html" target="_blank" rel="noopener" style="color:var(--text);text-decoration:underline">Applicant Privacy Notice</a>.</span></label>
        <?= $fieldErr('consent') ?>
        <?= captcha_widget('apply', 'dark') ?><?= captcha_script() ?>

        <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
          <p class="form-note">Questions or need an accommodation? Email <a href="mailto:<?= e(config('careers_to')) ?>" style="color:var(--text)"><?= e(config('careers_to')) ?></a>. See our <a href="applicant-privacy.html" style="color:var(--text)">Applicant Privacy Notice</a>.</p>
          <button class="btn btn-primary" type="submit" id="applyBtn">Submit application <?= ARROW ?></button>
        </div>
      </form>
      <?php endif; ?>
    </article>

    <aside class="job-side">
      <div class="side-card spot">
        <span class="eyebrow">The role</span>
        <h3><?= e($job['title']) ?></h3>
        <dl>
          <div><dt>Company</dt><dd><?= e($job['company']) ?></dd></div>
          <div><dt>Team</dt><dd><?= e($job['team']) ?></dd></div>
          <div><dt>Location</dt><dd><?= e($job['location']) ?></dd></div>
          <div><dt>Workplace</dt><dd><?= e($job['workplace']) ?></dd></div>
          <div><dt>Type</dt><dd><?= e($job['employment_type']) ?></dd></div>
          <?php if ($job['compensation']): ?><div><dt>Pay</dt><dd><?= e($job['compensation']) ?></dd></div><?php endif; ?>
        </dl>
        <?php if (!$closed && !$applied): ?><a href="#apply" class="btn btn-primary" style="width:100%;justify-content:center">Apply now <?= ARROW ?></a><?php endif; ?>
        <button type="button" class="btn btn-ghost btn-sm" style="width:100%;justify-content:center" data-copy-link="<?= e(abs_url(job_path($job['slug']))) ?>">Copy link to this role</button>
      </div>
      <a class="side-link" href="careers.php#roles">← All open roles</a>
    </aside>
  </div>
</section>
<?php site_footer(); ?>
