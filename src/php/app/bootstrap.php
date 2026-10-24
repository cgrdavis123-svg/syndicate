<?php
// Shared setup for every PHP page: config, database, sessions, security helpers and email.
declare(strict_types=1);

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('This site needs PHP 7.4 or newer. Change the PHP version in cPanel under "Select PHP Version" or "MultiPHP Manager".');
}

// config.php holds this server's settings and secrets. It isn't in git or the deploy zip, so it survives every deploy.
if (!is_file(__DIR__ . '/config.php')) { http_response_code(500); exit('Copy app/config.sample.php to app/config.php and fill it in.'); }
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['timezone']);

define('BASE', rtrim($CONFIG['base_path'], '/') . '/');
define('STORAGE', rtrim($CONFIG['storage_dir'], '/'));

ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(STORAGE) || @mkdir(STORAGE, 0755, true)) {
    ini_set('error_log', STORAGE . '/php-errors.log');
}

function config(string $key) { global $CONFIG; return $CONFIG[$key] ?? null; }

/* ---------- Output helpers ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE . ltrim($path, '/'); }
function abs_url(string $path = ''): string { return rtrim(config('site_url'), '/') . url($path); }
function job_path(string $slug): string { return config('pretty_urls') ? 'careers/' . rawurlencode($slug) : 'job.php?slug=' . rawurlencode($slug); }
// Adds query parameters to a path that may already have a query string (job.php?slug=…).
function with_query(string $path, array $q): string { return $path . (strpos($path, '?') === false ? '?' : '&') . http_build_query($q); }
function redirect(string $path): void { header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)), true, 303); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function lines(?string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$s)), 'strlen')); }
function paragraphs(?string $s): array { return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/', (string)$s)), 'strlen')); }
function slugify(string $s): string { $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-')); return $s !== '' ? $s : 'role'; }
function fmt_date(?string $ts, string $f = 'M j, Y'): string { return $ts ? date($f, strtotime($ts)) : ''; }
function now(): string { return date('Y-m-d H:i:s'); }
function ago(?string $ts): string {
    if (!$ts) return '';
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 86400 * 7) return floor($d / 86400) . 'd ago';
    return fmt_date($ts);
}
function not_found(): void {
    http_response_code(404);
    $f = __DIR__ . '/../404.html';
    if (is_file($f)) readfile($f); else echo 'Not found';
    exit;
}

/* ---------- Reference lists ---------- */
const COMPANIES = ['Syndicate Holdings Group', 'Syndicate Marketing', 'Creator Ops Media'];
const WORKPLACES = ['On-site', 'Hybrid', 'Remote'];
const EMPLOYMENT = ['Full-time', 'Part-time', 'Contract', 'Internship'];
const JOB_STATUSES = ['published' => 'Published', 'draft' => 'Draft', 'closed' => 'Closed'];
const APP_STATUSES = ['new' => 'New', 'reviewing' => 'Reviewing', 'interview' => 'Interview', 'offer' => 'Offer', 'hired' => 'Hired', 'rejected' => 'Not a fit'];
const SOURCES = ['TikTok', 'Instagram', 'LinkedIn', 'Indeed', 'Referral', 'Syndicate website', 'Other'];
function company_tone(string $c): string { return $c === 'Creator Ops Media' ? 'pink' : ($c === 'Syndicate Marketing' ? 'acc' : 'blue'); }

/* ---------- Database ---------- */
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) {
        http_response_code(500);
        exit('The PDO SQLite PHP extension is off. Turn on "pdo_sqlite" in cPanel under "Select PHP Version" → Extensions.');
    }
    if (!is_dir(STORAGE . '/resumes')) @mkdir(STORAGE . '/resumes', 0755, true);
    $pdo = new PDO('sqlite:' . STORAGE . '/syndicate.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    purge_expired($pdo);
    return $pdo;
}

// Deletes applications, resumes and messages older than the retention period. Runs at most once a day.
function purge_expired(PDO $pdo): void {
    $marker = STORAGE . '/.last-purge';
    if (is_file($marker) && filemtime($marker) > time() - 86400) return;
    @touch($marker);
    $days = max(30, (int)(config('retention_days') ?: 730));
    $cutoff = date('Y-m-d H:i:s', strtotime("-$days days"));
    $st = $pdo->prepare('SELECT id, resume_file FROM applications WHERE created_at < ?');
    $st->execute([$cutoff]);
    foreach ($st->fetchAll() as $r) {
        if ($r['resume_file']) @unlink(STORAGE . '/resumes/' . basename($r['resume_file']));
    }
    $pdo->prepare('DELETE FROM applications WHERE created_at < ?')->execute([$cutoff]);
    $pdo->prepare('DELETE FROM messages WHERE created_at < ?')->execute([$cutoff]);
}

function migrate(PDO $pdo): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                created_at TEXT NOT NULL,
                last_login_at TEXT
            );
            CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                company TEXT NOT NULL,
                team TEXT NOT NULL DEFAULT '',
                location TEXT NOT NULL DEFAULT '',
                workplace TEXT NOT NULL DEFAULT 'On-site',
                employment_type TEXT NOT NULL DEFAULT 'Full-time',
                compensation TEXT NOT NULL DEFAULT '',
                summary TEXT NOT NULL DEFAULT '',
                about TEXT NOT NULL DEFAULT '',
                responsibilities TEXT NOT NULL DEFAULT '',
                requirements TEXT NOT NULL DEFAULT '',
                nice_to_have TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'draft',
                published_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS applications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_id INTEGER REFERENCES jobs(id) ON DELETE SET NULL,
                job_title TEXT NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                phone TEXT NOT NULL DEFAULT '',
                location TEXT NOT NULL DEFAULT '',
                linkedin TEXT NOT NULL DEFAULT '',
                portfolio TEXT NOT NULL DEFAULT '',
                work_authorized TEXT NOT NULL DEFAULT '',
                source TEXT NOT NULL DEFAULT '',
                why TEXT NOT NULL DEFAULT '',
                resume_file TEXT NOT NULL DEFAULT '',
                resume_name TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'new',
                rating INTEGER NOT NULL DEFAULT 0,
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_app_job ON applications(job_id);
            CREATE INDEX IF NOT EXISTS idx_app_status ON applications(status);
            CREATE TABLE IF NOT EXISTS messages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                topic TEXT NOT NULL DEFAULT '',
                name TEXT NOT NULL,
                email TEXT NOT NULL,
                company TEXT NOT NULL DEFAULT '',
                message TEXT NOT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS attempts (
                kind TEXT NOT NULL,
                ip_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );
            CREATE INDEX IF NOT EXISTS idx_attempts ON attempts(kind, ip_hash, created_at);
        ");
        $count = (int)$pdo->query('SELECT COUNT(*) FROM jobs')->fetchColumn();
        if ($count === 0) {
            $ins = $pdo->prepare('INSERT INTO jobs (slug,title,company,team,location,workplace,employment_type,compensation,summary,about,responsibilities,requirements,nice_to_have,status,published_at,created_at,updated_at)
                VALUES (:slug,:title,:company,:team,:location,:workplace,:employment_type,:compensation,:summary,:about,:responsibilities,:requirements,:nice_to_have,:status,:published_at,:created_at,:updated_at)');
            foreach (require __DIR__ . '/seed-jobs.php' as $job) {
                $ins->execute($job + ['compensation' => '', 'nice_to_have' => '', 'status' => 'published', 'published_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $pdo->exec('PRAGMA user_version = 1');
    }
    if ($v < 2) {
        // v2: two-step sign-in for Hiring HQ admins.
        $pdo->exec("ALTER TABLE admins ADD COLUMN totp_secret TEXT NOT NULL DEFAULT '';
            ALTER TABLE admins ADD COLUMN totp_last INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE admins ADD COLUMN recovery_codes TEXT NOT NULL DEFAULT '[]';");
        $pdo->exec('PRAGMA user_version = 2');
    }
    if ($v < 3) {
        // v3: Commissioned Sales Rep for Syndicate Marketing (1099, 10% commission), unless a job with that slug exists.
        $job = [
            'slug' => 'commissioned-sales-rep', 'title' => 'Commissioned Sales Rep', 'company' => 'Syndicate Marketing', 'team' => 'Sales',
            'location' => 'United States', 'workplace' => 'Remote', 'employment_type' => 'Contract',
            'compensation' => '10% commission (independent contractor, 1099)',
            'summary' => 'Find small businesses in the niches we serve, introduce them to Syndicate Marketing, and earn 10% of what they buy.',
            'about' => "Syndicate Marketing builds websites, runs paid ads, and sets up and manages Discord communities for small businesses, with fixed prices published on our site. We're looking for people who like finding businesses that need help and starting honest conversations with them.\n\nYou'll find small businesses in the niches we serve (local trades and service businesses, and gaming studios, creators, esports teams and gaming businesses), reach out, and send interested owners to our packages, a free discovery call or a quote. We handle the sale from there and do all the work.\n\nHow you're paid: 10% of what each client you bring in pays us. One-off packages and add-ons count in full. Monthly plans (ads management, website care, community management) count for the client's first 12 months. Ad spend is paid by clients straight to the ad platforms, so it isn't included, and payments that are refunded don't earn commission.\n\nThis is a commission-only, independent contractor (1099) role. You set your own hours, use your own phone and computer, and handle your own taxes. There's no base pay, no quota and no fee to join.",
            'responsibilities' => "Research small businesses in our niches that could use a better website, ads or a community\nReach out by email, phone or in person with an honest, personal pitch\nSend interested owners to the right package, a free discovery call or a quote request\nLog who you've contacted so the same business isn't contacted twice\nFollow our outreach rules: no made-up claims or results, real prices only, opt-outs honored right away, and commercial emails that follow CAN-SPAM",
            'requirements' => "18 or older and based in the US\nComfortable starting conversations with business owners\nClear, friendly writing and follow-through\nAble to work independently as a contractor",
            'nice_to_have' => "Sales experience, especially with local businesses or agencies\nKnowing the gaming or creator world, or local trades in your area\nYour own network of small business owners",
            'status' => 'published',
        ];
        $exists = $pdo->prepare('SELECT 1 FROM jobs WHERE slug = ?'); $exists->execute([$job['slug']]);
        if (!$exists->fetchColumn()) {
            $t = date('Y-m-d H:i:s');
            $pdo->prepare('INSERT INTO jobs (slug,title,company,team,location,workplace,employment_type,compensation,summary,about,responsibilities,requirements,nice_to_have,status,published_at,created_at,updated_at)
                VALUES (:slug,:title,:company,:team,:location,:workplace,:employment_type,:compensation,:summary,:about,:responsibilities,:requirements,:nice_to_have,:status,:p,:c,:u)')
                ->execute($job + ['p' => $t, 'c' => $t, 'u' => $t]);
        }
        $pdo->exec('PRAGMA user_version = 3');
    }
    if ($v < 4) {
        // v4: team onboarding portal (stusyn.com/team/): members, one-time sign-in links, checklist steps, doc links.
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS team_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                role TEXT NOT NULL DEFAULT 'sales_rep',
                status TEXT NOT NULL DEFAULT 'onboarding',
                start_date TEXT NOT NULL DEFAULT '',
                end_date TEXT NOT NULL DEFAULT '',
                ref_link TEXT NOT NULL DEFAULT '',
                agreement_url TEXT NOT NULL DEFAULT '',
                pay_method TEXT NOT NULL DEFAULT '',
                notes TEXT NOT NULL DEFAULT '',
                invited_at TEXT,
                last_seen_at TEXT,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS team_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                member_id INTEGER NOT NULL REFERENCES team_members(id) ON DELETE CASCADE,
                token_hash TEXT NOT NULL UNIQUE,
                expires_at INTEGER NOT NULL,
                used_at INTEGER
            );
            CREATE TABLE IF NOT EXISTS team_steps (
                member_id INTEGER NOT NULL REFERENCES team_members(id) ON DELETE CASCADE,
                step TEXT NOT NULL,
                done_at TEXT,
                done_by TEXT NOT NULL DEFAULT '',
                nudged_at TEXT,
                data TEXT NOT NULL DEFAULT '{}',
                PRIMARY KEY (member_id, step)
            );
            CREATE TABLE IF NOT EXISTS team_settings (
                k TEXT PRIMARY KEY,
                v TEXT NOT NULL DEFAULT ''
            );
        ");
        $pdo->exec('PRAGMA user_version = 4');
    }
}

/* ---------- Jobs ---------- */
function open_application(): array {
    return [
        'id' => null, 'slug' => 'open-application', 'title' => 'Open application', 'company' => 'Syndicate Holdings Group',
        'team' => 'Any team', 'location' => 'Remote or Upstate SC', 'workplace' => 'Hybrid', 'employment_type' => 'Full-time',
        'compensation' => '', 'status' => 'published', 'published_at' => null,
        'summary' => "Don't see your role? Tell us what you'd build with us.",
        'about' => "We're always meeting great operators, creators and marketers, even when we don't have the perfect role posted. If you've built something and you want to build more, tell us about it.\n\nWe read every open application and reach out when there's a fit.",
        'responsibilities' => '', 'requirements' => '', 'nice_to_have' => '',
    ];
}
function find_job(string $slug, bool $includeHidden = false): ?array {
    if ($slug === 'open-application') return open_application();
    $st = db()->prepare('SELECT * FROM jobs WHERE slug = ?');
    $st->execute([$slug]);
    $job = $st->fetch();
    if (!$job) return null;
    if ($job['status'] === 'draft' && !$includeHidden) return null;
    return $job;
}
function published_jobs(): array {
    return db()->query("SELECT * FROM jobs WHERE status = 'published' ORDER BY published_at DESC, id DESC")->fetchAll();
}

/* ---------- Sessions, CSRF, rate limits ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('syn_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $dir = STORAGE . '/sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
    session_start();
}
function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool {
    start_session();
    return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''));
}
function ip_hash(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . config('setup_key')); }
function too_many(string $kind, int $max, int $minutes): bool {
    $db = db();
    $db->prepare('DELETE FROM attempts WHERE created_at < ?')->execute([time() - 86400]);
    $st = $db->prepare('SELECT COUNT(*) FROM attempts WHERE kind = ? AND ip_hash = ? AND created_at > ?');
    $st->execute([$kind, ip_hash(), time() - $minutes * 60]);
    return (int)$st->fetchColumn() >= $max;
}
function record_attempt(string $kind): void {
    db()->prepare('INSERT INTO attempts (kind, ip_hash, created_at) VALUES (?, ?, ?)')->execute([$kind, ip_hash(), time()]);
}
// Signed timestamp so forms submitted faster than a human could fill them are rejected.
function form_stamp(): string { $t = (string)time(); return $t . '.' . hash_hmac('sha256', $t, config('setup_key')); }
function form_stamp_ok(string $stamp, int $minSeconds = 3): bool {
    $p = explode('.', $stamp, 2);
    if (count($p) !== 2 || !hash_equals(hash_hmac('sha256', $p[0], config('setup_key')), $p[1])) return false;
    $age = time() - (int)$p[0];
    return $age >= $minSeconds && $age < 86400;
}

/* ---------- Admin auth ---------- */
// A short fingerprint of the password hash, kept in the session. Changing or resetting a password
// changes it, which signs out every other session for that admin.
function pw_mark(string $hash): string { return substr(hash('sha256', $hash), 0, 16); }
function sign_in_admin(array $a): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int)$a['id'];
    $_SESSION['pwv'] = pw_mark((string)$a['password_hash']);
}
function current_admin(): ?array {
    start_session();
    if (empty($_SESSION['admin_id'])) return null;
    $st = db()->prepare('SELECT id, email, password_hash FROM admins WHERE id = ?');
    $st->execute([$_SESSION['admin_id']]);
    $a = $st->fetch();
    if (!$a || !hash_equals(pw_mark($a['password_hash']), (string)($_SESSION['pwv'] ?? ''))) { unset($_SESSION['admin_id'], $_SESSION['pwv']); return null; }
    unset($a['password_hash']);
    return $a;
}
// Password reset links: emailed to the admin, work once, expire after 30 minutes. Kept in storage/ (blocked from the web).
function reset_link_create(int $id): string {
    $tok = bin2hex(random_bytes(24));
    file_put_contents(STORAGE . '/reset-link.json', json_encode(['h' => hash('sha256', $tok), 'id' => $id, 'exp' => time() + 1800]), LOCK_EX);
    return $tok;
}
function reset_link_admin(string $tok): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    $r = json_decode((string)@file_get_contents(STORAGE . '/reset-link.json'), true);
    if (!is_array($r) || (int)($r['exp'] ?? 0) < time() || !hash_equals((string)($r['h'] ?? ''), hash('sha256', $tok))) return null;
    $st = db()->prepare('SELECT * FROM admins WHERE id = ?');
    $st->execute([(int)$r['id']]);
    return $st->fetch() ?: null;
}
function reset_link_clear(): void { @unlink(STORAGE . '/reset-link.json'); }
function require_admin(): array {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    if ((int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) redirect('admin/setup.php');
    $a = current_admin();
    if (!$a) redirect('admin/login.php');
    if (is_post() && !csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    return $a;
}
function flash(?string $msg = null): ?string {
    start_session();
    if ($msg !== null) { $_SESSION['flash'] = $msg; return null; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

/* ---------- Email ---------- */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', string $from = '', string $fromName = ''): bool {
    $from = filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : config('mail_from');
    $name = '=?UTF-8?B?' . base64_encode($fromName !== '' ? $fromName : (string)config('mail_from_name')) . '?=';
    $headers = [
        'From: ' . $name . ' <' . $from . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: Syndicate',
    ];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
    $subj = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $subj, $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) error_log('Mail failed: to=' . $to . ' subject=' . $subject);
    return $ok;
}

// The setup key in config.php. Refused while it's still the sample value or too short to be safe.
function setup_key_ok(string $given): bool { $k = (string)config('setup_key'); return strlen($k) >= 20 && stripos($k, 'change-me') === false && hash_equals($k, $given); }

/* ---------- Bot check (Cloudflare Turnstile) ---------- */
// Public forms show a Turnstile widget and send its token as "cf-turnstile-response". Off until both keys are in config.php
// (turnstile_site, turnstile_secret). A bad or missing token is refused; if Cloudflare can't be reached, the form goes
// through (and it's logged), so a Cloudflare outage never costs you a lead. Rate limits still apply either way.
function captcha_site(): string { return (string)config('turnstile_secret') !== '' ? (string)config('turnstile_site') : ''; }
function captcha_widget(string $action, string $theme = 'auto'): string {
    $k = captcha_site();
    return $k === '' ? '' : '<div class="cf-turnstile" data-sitekey="' . e($k) . '" data-action="' . e($action) . '" data-theme="' . e($theme) . '" data-size="flexible" data-appearance="interaction-only"></div>';
}
function captcha_script(bool $explicit = false): string {
    return captcha_site() === '' ? '' : '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js' . ($explicit ? '?render=explicit' : '') . '" async defer></script>';
}
function captcha_ok(?string $token = null): bool {
    $secret = (string)config('turnstile_secret');
    if ($secret === '' || (string)config('turnstile_site') === '') return true;
    $token = (string)($token ?? ($_POST['cf-turnstile-response'] ?? ''));
    if ($token === '' || strlen($token) > 2048) return false;
    $body = http_build_query(['secret' => $secret, 'response' => $token, 'remoteip' => (string)($_SERVER['REMOTE_ADDR'] ?? '')]);
    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $raw = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4]);
        $raw = curl_exec($ch);
        if (curl_getinfo($ch, CURLINFO_RESPONSE_CODE) !== 200) $raw = false;
        curl_close($ch);
    } else {
        $raw = @file_get_contents($url, false, stream_context_create(['http' => ['method' => 'POST', 'header' => 'Content-Type: application/x-www-form-urlencoded', 'content' => $body, 'timeout' => 8]]));
    }
    $r = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($r)) { error_log('Turnstile check unreachable; form let through.'); return true; }
    if (empty($r['success'])) error_log('Turnstile refused a form: ' . implode(',', (array)($r['error-codes'] ?? [])));
    return !empty($r['success']);
}
const CAPTCHA_FAIL = 'Please wait a moment for the security check under the form, then try again. If it keeps happening, email us.';

/* ---------- Layout ---------- */
const ARROW = '<svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
function site_header(array $page): void { require __DIR__ . '/views/site-top.php'; }
function site_footer(): void { require __DIR__ . '/views/site-bottom.php'; }
function admin_header(string $title, string $active, ?array $admin = null): void { require __DIR__ . '/views/admin-top.php'; }
function admin_footer(): void { require __DIR__ . '/views/admin-bottom.php'; }
function status_pill(string $s): string {
    $label = APP_STATUSES[$s] ?? JOB_STATUSES[$s] ?? ucfirst($s);
    return '<span class="pill-s s-' . e($s) . '">' . e($label) . '</span>';
}
