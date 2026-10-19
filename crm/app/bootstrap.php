<?php
// Syndicate CRM — shared setup: config, database, auth, security helpers and website import.
declare(strict_types=1);

if (PHP_VERSION_ID < 70400) {
    http_response_code(500);
    exit('Syndicate CRM needs PHP 7.4 or newer. Change it in cPanel under "MultiPHP Manager".');
}

// config.php holds this server's settings and secrets. It isn't in git or the deploy zip, so it survives every deploy.
if (!is_file(__DIR__ . '/config.php')) { http_response_code(500); exit('Copy app/config.sample.php to app/config.php and fill it in.'); }
$CONFIG = require __DIR__ . '/config.php';
date_default_timezone_set($CONFIG['timezone']);
define('BASE', rtrim($CONFIG['base_path'], '/') . '/');
define('STORAGE', rtrim($CONFIG['storage_dir'], '/'));
define('DOCROOT', dirname(__DIR__));

ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (is_dir(STORAGE) || @mkdir(STORAGE, 0755, true)) ini_set('error_log', STORAGE . '/php-errors.log');

header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

function config(string $k) { global $CONFIG; return $CONFIG[$k] ?? null; }

/* ---------- Reference lists ---------- */
const STAGES = ['new' => 'New lead', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'proposal' => 'Proposal', 'won' => 'Won', 'lost' => 'Lost'];
const OPEN_STAGES = ['new', 'contacted', 'qualified', 'proposal'];
const BRANDS = ['Syndicate Marketing', 'Creator Ops Media', 'Syndicate Holdings Group'];
const ACTIVITY_KINDS = ['note' => 'Note', 'call' => 'Call', 'email' => 'Email', 'meeting' => 'Meeting'];
const CONSULT_STATUSES = ['scheduled' => 'Scheduled', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No-show'];
const BOOKING_STATUSES = ['confirmed' => 'Confirmed', 'pending' => 'Awaiting payment', 'cancelled' => 'Cancelled', 'expired' => 'Not paid'];
const QUOTE_STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'viewed' => 'Viewed', 'accepted' => 'Accepted · awaiting payment', 'won' => 'Won', 'declined' => 'Declined', 'expired' => 'Expired'];
const INVOICE_STATUSES = ['draft' => 'Draft', 'sent' => 'Sent', 'paid' => 'Paid', 'void' => 'Void'];
const REPEATS = ['week' => 'Every week', 'month' => 'Every month', 'quarter' => 'Every 3 months', 'year' => 'Every year'];
const REPEAT_ADJ = ['week' => 'weekly', 'month' => 'monthly', 'quarter' => 'quarterly', 'year' => 'yearly'];
const PROJECT_STATUSES = ['planning' => 'Planning', 'active' => 'In progress', 'review' => 'In review', 'paused' => 'On hold', 'done' => 'Complete'];
// The care plan's promise, kept in one place (also in catalog.json).
const CARE_BLURB = 'We watch your site around the clock, keep it updated, secure and backed up, and make your small changes, with a monthly report so you can see it all.';
const CARE_FEATURES = ['Uptime checks every 15 minutes, with alerts if it goes down', 'SSL certificate and domain renewal watch', 'Weekly security and broken-link scan', 'Updates and weekly backups, logged where you can see them', 'Up to 1 hour of edits a month, requested from your portal', 'Monthly care report: uptime, speed, security, visitors and work done', 'Cancel online any time'];
// Care plan tiers: edit minutes a month and how soon requests are started.
const CARE_TIERS = ['care-plan' => ['name' => 'Care plan', 'minutes' => 60, 'reply' => 'two business days'], 'care-plus' => ['name' => 'Care Plus', 'minutes' => 120, 'reply' => 'one business day']];
// Platforms we look after. On hosted ones the platform runs software updates and server backups; we back up content monthly.
const CARE_PLATFORMS = ['wordpress' => 'WordPress', 'shopify' => 'Shopify', 'squarespace' => 'Squarespace', 'wix' => 'Wix', 'webflow' => 'Webflow', 'custom' => 'Hand-built or other', 'unsure' => 'Not sure'];
const CARE_HOSTED = ['shopify', 'squarespace', 'wix', 'webflow'];
const AD_PLANS_SKUS = ['ads-one' => 1, 'ads-two' => 2, 'ads-all' => 3];
const SOURCES = ['Website', 'Website — consultation', 'Website — order', 'Marketing site', 'Marketing site — order', 'Marketing site — checkout','Referral', 'TikTok', 'Instagram', 'LinkedIn', 'Email', 'Event', 'Cold outreach', 'Existing client', 'Other'];

/* ---------- Output helpers ---------- */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $p = ''): string { return BASE . ltrim($p, '/'); }
function redirect(string $p): void { header('Location: ' . url($p), true, 303); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function post(string $k, string $d = ''): string { return trim((string)($_POST[$k] ?? $d)); }
function get(string $k, string $d = ''): string { return trim((string)($_GET[$k] ?? $d)); }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }
function money($n, bool $cents = true): string { return '$' . number_format((float)$n, $cents ? 2 : 0); }
function money_short($n): string { $n = (float)$n; return $n >= 1000000 ? '$' . round($n / 1000000, 1) . 'M' : ($n >= 10000 ? '$' . round($n / 1000) . 'K' : money($n, false)); }
function hours($minutes): string { $h = (float)$minutes / 60; return rtrim(rtrim(number_format($h, 2), '0'), '.') . ' h'; }
function fmt_date(?string $d, string $f = 'M j, Y'): string { return $d ? date($f, strtotime($d)) : ''; }
function valid_date(string $d): bool { $t = DateTime::createFromFormat('Y-m-d', $d); return $t && $t->format('Y-m-d') === $d; }
function ago(?string $ts): string {
    if (!$ts) return '';
    $d = time() - strtotime($ts);
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 86400 * 7) return floor($d / 86400) . 'd ago';
    return fmt_date($ts);
}
function due_label(?string $d): string {
    if (!$d) return '';
    $diff = (int)round((strtotime($d) - strtotime(today())) / 86400);
    if ($diff === 0) return 'Today';
    if ($diff === 1) return 'Tomorrow';
    if ($diff === -1) return 'Yesterday';
    if ($diff < 0) return abs($diff) . ' days overdue';
    if ($diff < 7) return date('l', strtotime($d));
    return fmt_date($d, 'M j');
}
function person_name(array $c): string { return trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: ($c['email'] ?? 'Unnamed'); }
function initials(string $s): string { $p = preg_split('/\s+/', trim($s)); return strtoupper(mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[1] ?? '', 0, 1)) ?: '?'; }
function pill(string $kind, string $value, string $label = ''): string { return '<span class="pill p-' . e($kind) . '-' . e($value) . '">' . e($label ?: $value) . '</span>'; }
function stage_pill(string $s): string { return pill('stage', $s, STAGES[$s] ?? $s); }
function brand_tag(?string $b): string {
    if (!$b) return '';
    $cls = $b === 'Creator Ops Media' ? 'pink' : ($b === 'Syndicate Marketing' ? 'lime' : 'blue');
    return '<span class="tag ' . $cls . '">' . e($b) . '</span>';
}
function selected($a, $b): string { return (string)$a === (string)$b ? ' selected' : ''; }

/* ---------- Database ---------- */
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    if (!extension_loaded('pdo_sqlite')) { http_response_code(500); exit('Turn on the "pdo_sqlite" PHP extension in cPanel under "Select PHP Version" → Extensions.'); }
    if (!is_dir(STORAGE . '/backups')) @mkdir(STORAGE . '/backups', 0755, true);
    $pdo = new PDO('sqlite:' . STORAGE . '/crm.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    return $pdo;
}
function q(string $sql, array $args = []): PDOStatement { $st = db()->prepare($sql); $st->execute($args); return $st; }
function one(string $sql, array $args = []) { $r = q($sql, $args)->fetch(); return $r ?: null; }
function all(string $sql, array $args = []): array { return q($sql, $args)->fetchAll(); }
function val(string $sql, array $args = []) { return q($sql, $args)->fetchColumn(); }

function migrate(PDO $pdo): void {
    $v = (int)$pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v < 1) migrate_v1($pdo);
    if ($v < 2) {
        // v2: client invoice links, payment links, view tracking
        $pdo->exec("ALTER TABLE invoices ADD COLUMN share_token TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN payment_url TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN viewed_at TEXT");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN reminded_at TEXT");
        foreach ($pdo->query('SELECT id FROM invoices')->fetchAll() as $r) {
            $pdo->prepare('UPDATE invoices SET share_token = ? WHERE id = ?')->execute([bin2hex(random_bytes(16)), $r['id']]);
        }
        $ins = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        $ins->execute(['payment_link_default', '']);
        $ins->execute(['mail_from', 'hello@stusyn.com']);
        $pdo->exec('PRAGMA user_version = 2');
    }
    if ($v < 3) {
        // v3: recurring invoices. The schedule lives on the invoice it was set up on.
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_every TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_start TEXT");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_count INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_next TEXT");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_send INTEGER NOT NULL DEFAULT 0");
        $pdo->exec("ALTER TABLE invoices ADD COLUMN repeat_parent INTEGER");
        $pdo->exec('PRAGMA user_version = 3');
    }
    if ($v < 4) {
        // v4: inbox. Mailboxes you connect, the folders synced from each, and a copy of every email.
        $pdo->exec("
            CREATE TABLE mailboxes (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT NOT NULL DEFAULT '', host TEXT NOT NULL,
                imap_port INTEGER NOT NULL DEFAULT 993, smtp_port INTEGER NOT NULL DEFAULT 465, verify_tls INTEGER NOT NULL DEFAULT 1, secret TEXT NOT NULL,
                signature TEXT NOT NULL DEFAULT '', sent_folder TEXT NOT NULL DEFAULT '', last_sync TEXT, last_error TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE TABLE mail_folders (mailbox_id INTEGER NOT NULL REFERENCES mailboxes(id) ON DELETE CASCADE, role TEXT NOT NULL, name TEXT NOT NULL,
                uidvalidity INTEGER NOT NULL DEFAULT 0, last_uid INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (mailbox_id, role));
            CREATE TABLE emails (id INTEGER PRIMARY KEY AUTOINCREMENT, mailbox_id INTEGER NOT NULL REFERENCES mailboxes(id) ON DELETE CASCADE, folder TEXT NOT NULL, uid INTEGER,
                message_id TEXT NOT NULL, refs TEXT NOT NULL DEFAULT '', thread_id TEXT NOT NULL, from_addr TEXT NOT NULL DEFAULT '', from_name TEXT NOT NULL DEFAULT '',
                reply_to TEXT NOT NULL DEFAULT '', to_list TEXT NOT NULL DEFAULT '[]', cc_list TEXT NOT NULL DEFAULT '[]', addrs TEXT NOT NULL DEFAULT '',
                subject TEXT NOT NULL DEFAULT '', snippet TEXT NOT NULL DEFAULT '', body_text TEXT NOT NULL DEFAULT '', body_html TEXT NOT NULL DEFAULT '',
                attachments TEXT NOT NULL DEFAULT '[]', sent_at TEXT NOT NULL, seen INTEGER NOT NULL DEFAULT 0, seen_dirty INTEGER NOT NULL DEFAULT 0,
                archived INTEGER NOT NULL DEFAULT 0, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
                size INTEGER NOT NULL DEFAULT 0, raw_path TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE INDEX idx_emails_thread ON emails(thread_id);
            CREATE INDEX idx_emails_msgid ON emails(mailbox_id, folder, message_id);
            CREATE INDEX idx_emails_contact ON emails(contact_id);
            CREATE INDEX idx_emails_company ON emails(company_id);
            CREATE INDEX idx_emails_list ON emails(folder, archived, sent_at);
        ");
        $pdo->exec('PRAGMA user_version = 4');
    }
    if ($v < 5) {
        // v5: client portal. Projects with steps, a shared feed (updates, approvals, messages) and files.
        $pdo->exec("
            ALTER TABLE contacts ADD COLUMN portal_access INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE contacts ADD COLUMN portal_token TEXT NOT NULL DEFAULT '';
            ALTER TABLE contacts ADD COLUMN portal_seen_at TEXT;
            CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
                contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL, deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL, brand TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'planning', progress INTEGER NOT NULL DEFAULT 0, start_date TEXT, due_date TEXT, summary TEXT NOT NULL DEFAULT '',
                shared INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE project_steps (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                title TEXT NOT NULL, due_date TEXT, done_at TEXT, position INTEGER NOT NULL DEFAULT 0);
            CREATE TABLE project_posts (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                author TEXT NOT NULL, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL, kind TEXT NOT NULL DEFAULT 'update', body TEXT NOT NULL DEFAULT '',
                approval TEXT NOT NULL DEFAULT '', decision_note TEXT NOT NULL DEFAULT '', decided_by INTEGER, decided_at TEXT, created_at TEXT NOT NULL);
            CREATE TABLE project_files (id INTEGER PRIMARY KEY AUTOINCREMENT, project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
                post_id INTEGER REFERENCES project_posts(id) ON DELETE SET NULL, author TEXT NOT NULL, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
                name TEXT NOT NULL, type TEXT NOT NULL DEFAULT '', size INTEGER NOT NULL DEFAULT 0, path TEXT NOT NULL, created_at TEXT NOT NULL);
            CREATE TABLE portal_links (token_hash TEXT PRIMARY KEY, contact_id INTEGER NOT NULL REFERENCES contacts(id) ON DELETE CASCADE, expires_at INTEGER NOT NULL);
            CREATE INDEX idx_projects_company ON projects(company_id);
            CREATE INDEX idx_posts_project ON project_posts(project_id);
            CREATE INDEX idx_files_project ON project_files(project_id);
        ");
        $pdo->exec('PRAGMA user_version = 5');
    }
    if ($v < 6) {
        // v6: site stats. Each client website, its install key and its conversion goals. Visits go in storage/analytics.sqlite.
        $pdo->exec("
            CREATE TABLE sites (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
                domains TEXT NOT NULL, public_id TEXT NOT NULL UNIQUE, shared INTEGER NOT NULL DEFAULT 1, monthly_report INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL);
            CREATE TABLE site_goals (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, name TEXT NOT NULL,
                kind TEXT NOT NULL DEFAULT 'event', match TEXT NOT NULL);
        ");
        $pdo->exec('PRAGMA user_version = 6');
    }
    if ($v < 7) {
        // v7: online store. Products sold on the Syndicate Marketing site, orders paid through Stripe Checkout, and Stripe events already handled.
        $pdo->exec("
            CREATE TABLE products (id INTEGER PRIMARY KEY AUTOINCREMENT, sku TEXT NOT NULL UNIQUE, category TEXT NOT NULL, name TEXT NOT NULL, blurb TEXT NOT NULL DEFAULT '',
                features TEXT NOT NULL DEFAULT '', price REAL NOT NULL DEFAULT 0, interval TEXT NOT NULL DEFAULT '', unit TEXT NOT NULL DEFAULT '', max_qty INTEGER NOT NULL DEFAULT 1,
                requires TEXT NOT NULL DEFAULT '', badge TEXT NOT NULL DEFAULT '', featured INTEGER NOT NULL DEFAULT 0, timeline_days INTEGER NOT NULL DEFAULT 0,
                steps TEXT NOT NULL DEFAULT '', site INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, ref TEXT NOT NULL UNIQUE, access_key TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending',
                name TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '', business TEXT NOT NULL DEFAULT '', website TEXT NOT NULL DEFAULT '',
                brief TEXT NOT NULL DEFAULT '{}', one_time REAL NOT NULL DEFAULT 0, monthly REAL NOT NULL DEFAULT 0, discount REAL NOT NULL DEFAULT 0, tax REAL NOT NULL DEFAULT 0,
                paid_total REAL NOT NULL DEFAULT 0, promo_code TEXT NOT NULL DEFAULT '', live INTEGER NOT NULL DEFAULT 0,
                stripe_session TEXT NOT NULL DEFAULT '', stripe_customer TEXT NOT NULL DEFAULT '', stripe_subscription TEXT NOT NULL DEFAULT '', stripe_payment TEXT NOT NULL DEFAULT '',
                sub_status TEXT NOT NULL DEFAULT '', contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
                deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL, invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL, site_id INTEGER,
                source TEXT NOT NULL DEFAULT '', ip_hash TEXT NOT NULL DEFAULT '', seen_at TEXT, followed_up INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL, paid_at TEXT, updated_at TEXT NOT NULL);
            CREATE TABLE order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
                product_id INTEGER REFERENCES products(id) ON DELETE SET NULL, sku TEXT NOT NULL, name TEXT NOT NULL, category TEXT NOT NULL DEFAULT '',
                price REAL NOT NULL, interval TEXT NOT NULL DEFAULT '', quantity INTEGER NOT NULL DEFAULT 1, project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL);
            CREATE TABLE stripe_events (id TEXT PRIMARY KEY, type TEXT NOT NULL, created_at TEXT NOT NULL);
            CREATE INDEX idx_orders_status ON orders(status, created_at);
            CREATE INDEX idx_orders_session ON orders(stripe_session);
            CREATE INDEX idx_orders_sub ON orders(stripe_subscription);
            CREATE INDEX idx_order_items ON order_items(order_id);
            ALTER TABLE invoices ADD COLUMN stripe_invoice TEXT NOT NULL DEFAULT '';
        ");
        $seed = json_decode((string)@file_get_contents(__DIR__ . '/catalog.json'), true);
        $ins = $pdo->prepare('INSERT OR IGNORE INTO products (sku, category, name, blurb, features, price, interval, unit, max_qty, requires, badge, featured, timeline_days, steps, site, position, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($seed['products'] ?? [] as $i => $p) {
            $ins->execute([$p['sku'], $p['category'], $p['name'], $p['blurb'] ?? '', implode("\n", $p['features'] ?? []), (float)$p['price'], $p['interval'] ?? '', $p['unit'] ?? '',
                (int)($p['max_qty'] ?? 1), implode(',', $p['requires'] ?? []), $p['badge'] ?? '', !empty($p['featured']) ? 1 : 0, (int)($p['timeline_days'] ?? 0),
                implode("\n", $p['steps'] ?? []), !empty($p['site']) ? 1 : 0, ($i + 1) * 10, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        }
        $set = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (['shop_site_url' => '', 'shop_promo' => '1', 'shop_tax' => '0', 'shop_notify' => 'hello@stusyn.com', 'stripe_secret' => '', 'stripe_webhook_secret' => ''] as $k => $val) $set->execute([$k, $val]);
        $pdo->exec('PRAGMA user_version = 7');
    }
    if ($v < 8) {
        // v8: freelance pricing. Only products still at their original price change; prices you've edited stay.
        $up = $pdo->prepare('UPDATE products SET price = ?, updated_at = ? WHERE sku = ? AND price = ?');
        foreach (['web-one-page' => [500, 350], 'web-starter' => [1500, 850], 'web-business' => [3000, 1600], 'web-store' => [4500, 2500], 'ads-one' => [500, 300], 'ads-two' => [900, 550], 'ads-all' => [1250, 800], 'care-plan' => [79, 49], 'add-ads-setup' => [300, 150], 'add-page' => [150, 75], 'add-copy' => [100, 50], 'add-logo' => [350, 200], 'add-gbp' => [150, 100], 'add-rush' => [300, 150], 'svc-ads-audit' => [250, 150], 'svc-site-audit' => [250, 150], 'svc-analytics' => [200, 125], 'svc-strategy' => [150, 75]] as $sku => [$was, $now]) $up->execute([$now, date('Y-m-d H:i:s'), $sku, $was]);
        $pdo->exec('PRAGMA user_version = 8');
    }
    if ($v < 9) {
        // v9: quotes. Proposals clients open from a link, accept with their name, and pay a deposit or in full through Stripe.
        $pdo->exec("
            CREATE TABLE quotes (id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE, token TEXT NOT NULL UNIQUE, status TEXT NOT NULL DEFAULT 'draft',
                brand TEXT NOT NULL DEFAULT '', title TEXT NOT NULL DEFAULT '', intro TEXT NOT NULL DEFAULT '', contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
                company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL, deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL, valid_until TEXT,
                deposit_pct INTEGER NOT NULL DEFAULT 100, terms TEXT NOT NULL DEFAULT '', steps TEXT NOT NULL DEFAULT '', timeline_days INTEGER NOT NULL DEFAULT 0,
                notes TEXT NOT NULL DEFAULT '', sent_at TEXT, viewed_at TEXT, reminded_at TEXT, accepted_at TEXT, accepted_name TEXT NOT NULL DEFAULT '', accepted_ip TEXT NOT NULL DEFAULT '',
                declined_at TEXT, decline_reason TEXT NOT NULL DEFAULT '', stripe_session TEXT NOT NULL DEFAULT '', stripe_customer TEXT NOT NULL DEFAULT '',
                stripe_subscription TEXT NOT NULL DEFAULT '', stripe_payment TEXT NOT NULL DEFAULT '', sub_status TEXT NOT NULL DEFAULT '', live INTEGER NOT NULL DEFAULT 0,
                paid_at TEXT, paid_total REAL NOT NULL DEFAULT 0, invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
                balance_invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL, project_id INTEGER REFERENCES projects(id) ON DELETE SET NULL,
                created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE quote_items (id INTEGER PRIMARY KEY AUTOINCREMENT, quote_id INTEGER NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,
                product_id INTEGER REFERENCES products(id) ON DELETE SET NULL, description TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', quantity REAL NOT NULL DEFAULT 1,
                unit_price REAL NOT NULL DEFAULT 0, interval TEXT NOT NULL DEFAULT '', optional INTEGER NOT NULL DEFAULT 0, selected INTEGER NOT NULL DEFAULT 1, position INTEGER NOT NULL DEFAULT 0);
            CREATE INDEX idx_quotes_status ON quotes(status);
            CREATE INDEX idx_quotes_session ON quotes(stripe_session);
            CREATE INDEX idx_quote_items ON quote_items(quote_id);
        ");
        $set = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (['quote_prefix' => 'Q-', 'quote_next' => '1001', 'quote_valid_days' => '30',
            'quote_terms' => "Prices are in US dollars. The deposit is due when you accept, and work starts once it’s paid. The remaining balance is invoiced when the work is delivered, due within 14 days.\nMonthly services renew each month until cancelled with 30 days’ notice. Ad spend is paid directly to the ad platforms.\nOnce paid in full, the work we create for you is yours. Your deposit is refundable in full before your kickoff call; after that, refunds are based on the work completed.\nThis quote is valid until the date shown above."] as $k => $val) $set->execute([$k, $val]);
        $pdo->exec('PRAGMA user_version = 9');
    }
    if ($v < 10) {
        // v10: online booking. Meeting types people can book, your weekly hours, days off, and each booking (which also becomes a consultation on your calendar).
        $pdo->exec("
            CREATE TABLE meeting_types (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL, brand TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '',
                minutes INTEGER NOT NULL DEFAULT 30, price REAL NOT NULL DEFAULT 0, location TEXT NOT NULL DEFAULT 'video', location_detail TEXT NOT NULL DEFAULT '', question TEXT NOT NULL DEFAULT '',
                public INTEGER NOT NULL DEFAULT 1, active INTEGER NOT NULL DEFAULT 1, max_per_day INTEGER NOT NULL DEFAULT 0, kind TEXT NOT NULL DEFAULT 'lead', position INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE bookings (id INTEGER PRIMARY KEY AUTOINCREMENT, token TEXT NOT NULL UNIQUE, type_id INTEGER REFERENCES meeting_types(id) ON DELETE SET NULL, type_name TEXT NOT NULL DEFAULT '',
                starts_at INTEGER NOT NULL, minutes INTEGER NOT NULL, status TEXT NOT NULL DEFAULT 'confirmed', name TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '',
                business TEXT NOT NULL DEFAULT '', answer TEXT NOT NULL DEFAULT '', guest_tz TEXT NOT NULL DEFAULT '', price REAL NOT NULL DEFAULT 0, paid_total REAL NOT NULL DEFAULT 0,
                stripe_session TEXT NOT NULL DEFAULT '', stripe_payment TEXT NOT NULL DEFAULT '', live INTEGER NOT NULL DEFAULT 0, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
                company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL, deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL, consultation_id INTEGER REFERENCES consultations(id) ON DELETE SET NULL,
                invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL, source TEXT NOT NULL DEFAULT '', ip_hash TEXT NOT NULL DEFAULT '', reminded_at TEXT, cancelled_at TEXT, cancel_reason TEXT NOT NULL DEFAULT '',
                seen_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE booking_blocks (id INTEGER PRIMARY KEY AUTOINCREMENT, day TEXT NOT NULL, start_time TEXT NOT NULL DEFAULT '', end_time TEXT NOT NULL DEFAULT '', reason TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE INDEX idx_bookings_start ON bookings(status, starts_at);
            CREATE INDEX idx_bookings_session ON bookings(stripe_session);
            CREATE INDEX idx_blocks_day ON booking_blocks(day);
        ");
        $ins = $pdo->prepare('INSERT OR IGNORE INTO meeting_types (slug, name, brand, description, minutes, price, location, question, public, kind, position, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $now = date('Y-m-d H:i:s');
        foreach ([
            ['discovery-call', 'Discovery call', 'Syndicate Marketing', 'A free 20-minute call about your website, ads or marketing. Tell us where you are and we’ll tell you honestly what we’d do, and what it would cost.', 20, 0, 'video', 'What would you like to talk about?', 1, 'lead', 10],
            ['kickoff', 'Project kickoff call', 'Syndicate Marketing', 'For new clients: we go through your goals, your customers and what we need from you to get started. About 30 minutes.', 30, 0, 'video', 'Anything we should know before the call?', 0, 'kickoff', 20],
            ['tiktok-shop-consultation', 'TikTok Shop consultation', 'Creator Ops Media', 'One hour, one-on-one, on your TikTok Shop: setup, product listings, creators, affiliates, ads or whatever is holding you back. Paid when you book.', 60, 150, 'video', 'What do you want to get out of the session? Include your shop link if you have one.', 1, 'consult', 30],
        ] as $t) $ins->execute(array_merge($t, [$now, $now]));
        $set = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (['booking_hours' => json_encode(['1' => ['09:00', '17:00'], '2' => ['09:00', '17:00'], '3' => ['09:00', '17:00'], '4' => ['09:00', '17:00'], '5' => ['09:00', '15:00']]),
            'booking_notice_hours' => '12', 'booking_window_days' => '30', 'booking_buffer' => '15', 'booking_step' => '30', 'booking_meeting_url' => ''] as $k => $val) $set->execute([$k, $val]);
        $pdo->exec('PRAGMA user_version = 10');
    }
    if ($v < 11) {
        // v11: auto-renewal rules. When a monthly plan is set to end (Unix time), the notice period for ads plans,
        // a record of what the buyer agreed to at checkout, and when the yearly renewal reminder last went out.
        $pdo->exec("ALTER TABLE orders ADD COLUMN cancel_at INTEGER; ALTER TABLE quotes ADD COLUMN cancel_at INTEGER;
            ALTER TABLE orders ADD COLUMN consent TEXT NOT NULL DEFAULT '';
            ALTER TABLE orders ADD COLUMN renewal_reminded_at TEXT; ALTER TABLE quotes ADD COLUMN renewal_reminded_at TEXT;");
        $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)')->execute(['plan_cancel_notice_days', '30']);
        $pdo->exec('PRAGMA user_version = 11');
    }
    if ($v < 12) {
        // v12: website care (uptime checks, incidents, SSL/domain/security watch, work log, edit requests)
        // and two-factor sign-in for CRM users.
        $pdo->exec("
            ALTER TABLE sites ADD COLUMN care INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE sites ADD COLUMN care_order_id INTEGER;
            ALTER TABLE sites ADD COLUMN care_minutes INTEGER NOT NULL DEFAULT 60;
            ALTER TABLE sites ADD COLUMN backup_days INTEGER NOT NULL DEFAULT 7;
            ALTER TABLE sites ADD COLUMN monitor INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE sites ADD COLUMN check_url TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN check_keyword TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN notify_client INTEGER NOT NULL DEFAULT 1;
            ALTER TABLE sites ADD COLUMN status TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN status_since INTEGER;
            ALTER TABLE sites ADD COLUMN fail_count INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE sites ADD COLUMN last_check INTEGER;
            ALTER TABLE sites ADD COLUMN last_ms INTEGER;
            ALTER TABLE sites ADD COLUMN last_error TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN ssl_expires INTEGER;
            ALTER TABLE sites ADD COLUMN ssl_issuer TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN ssl_checked INTEGER;
            ALTER TABLE sites ADD COLUMN domain_expires INTEGER;
            ALTER TABLE sites ADD COLUMN domain_registrar TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN domain_checked INTEGER;
            ALTER TABLE sites ADD COLUMN scan TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN scan_checked INTEGER;
            ALTER TABLE sites ADD COLUMN alerts TEXT NOT NULL DEFAULT '{}';
            CREATE TABLE site_checks (site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, ts INTEGER NOT NULL, ok INTEGER NOT NULL, code INTEGER NOT NULL DEFAULT 0, ms INTEGER NOT NULL DEFAULT 0, err TEXT NOT NULL DEFAULT '');
            CREATE INDEX idx_checks_site ON site_checks(site_id, ts);
            CREATE TABLE site_incidents (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, started INTEGER NOT NULL, ended INTEGER, cause TEXT NOT NULL DEFAULT '');
            CREATE INDEX idx_incidents_site ON site_incidents(site_id, started);
            CREATE TABLE care_log (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, kind TEXT NOT NULL DEFAULT 'note', title TEXT NOT NULL,
                details TEXT NOT NULL DEFAULT '', minutes INTEGER NOT NULL DEFAULT 0, request_id INTEGER, shared INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL);
            CREATE INDEX idx_carelog_site ON care_log(site_id, created_at);
            CREATE TABLE care_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
                title TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', page_url TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'open', reply TEXT NOT NULL DEFAULT '', minutes INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL, updated_at TEXT NOT NULL, done_at TEXT);
            CREATE INDEX idx_carereq_site ON care_requests(site_id, status);
            ALTER TABLE users ADD COLUMN totp_secret TEXT NOT NULL DEFAULT '';
            ALTER TABLE users ADD COLUMN totp_last INTEGER NOT NULL DEFAULT 0;
            ALTER TABLE users ADD COLUMN recovery_codes TEXT NOT NULL DEFAULT '[]';
        ");
        // Care plan description, only where it hasn't been edited by hand.
        $old = "Updates, security checks and weekly backups\nUptime monitoring\nUp to 1 hour of edits each month\nMonthly Site Stats report by email\nCancel any time";
        $new = implode("\n", CARE_FEATURES);
        $pdo->prepare("UPDATE products SET features = ?, blurb = ?, updated_at = ? WHERE sku = 'care-plan' AND features = ?")->execute([$new, CARE_BLURB, date('Y-m-d H:i:s'), $old]);
        $pdo->exec('PRAGMA user_version = 12');
    }
    if ($v < 13) {
        // v13: Care Plus tier, care for sites we didn't build, Google PageSpeed scores, and web care reports.
        $pdo->exec("
            ALTER TABLE sites ADD COLUMN care_sku TEXT NOT NULL DEFAULT 'care-plan';
            ALTER TABLE sites ADD COLUMN care_platform TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN care_built INTEGER NOT NULL DEFAULT 1;
            ALTER TABLE sites ADD COLUMN care_since TEXT;
            ALTER TABLE sites ADD COLUMN report_key TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN psi TEXT NOT NULL DEFAULT '';
            ALTER TABLE sites ADD COLUMN psi_checked INTEGER;
        ");
        $pdo->exec("INSERT OR IGNORE INTO settings (key, value) VALUES ('psi_key', '')");
        $pdo->exec("UPDATE sites SET care_since = (SELECT MIN(created_at) FROM care_log WHERE care_log.site_id = sites.id) WHERE care = 1");
        foreach (json_decode((string)@file_get_contents(__DIR__ . '/catalog.json'), true)['products'] ?? [] as $p) {
            if ($p['sku'] !== 'care-plus') continue;
            $pos = (int)$pdo->query("SELECT COALESCE(MAX(position), 0) FROM products WHERE sku = 'care-plan'")->fetchColumn() + 1;
            $pdo->prepare('INSERT OR IGNORE INTO products (sku, category, name, blurb, features, price, interval, site, position, created_at, updated_at) VALUES (?,?,?,?,?,?,?,1,?,?,?)')
                ->execute([$p['sku'], $p['category'], $p['name'], $p['blurb'], implode("\n", $p['features']), (float)$p['price'], $p['interval'], $pos, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        }
        $pdo->exec('PRAGMA user_version = 13');
    }
    if ($v < 14) {
        // v14: one reminder email for checkouts that weren't paid.
        $pdo->exec("ALTER TABLE orders ADD COLUMN reminded_at TEXT");
        $pdo->exec('PRAGMA user_version = 14');
    }
    if ($v < 15) {
        // v15: ads management (ads.stusyn.com).
        $pdo->exec("
            CREATE TABLE ad_clients (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL UNIQUE REFERENCES companies(id) ON DELETE CASCADE, status TEXT NOT NULL DEFAULT 'onboarding',
                sku TEXT NOT NULL DEFAULT '', platforms TEXT NOT NULL DEFAULT '', order_id INTEGER, site_id INTEGER, monthly_budget REAL NOT NULL DEFAULT 0, target_cpl REAL NOT NULL DEFAULT 0,
                goal TEXT NOT NULL DEFAULT '', area TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', onboarding TEXT NOT NULL DEFAULT '{}', report_key TEXT NOT NULL DEFAULT '',
                since TEXT, ended_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE TABLE ad_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, platform TEXT NOT NULL, name TEXT NOT NULL DEFAULT '',
                external_id TEXT NOT NULL DEFAULT '', access TEXT NOT NULL DEFAULT 'requested', pixel INTEGER NOT NULL DEFAULT 0, conversions INTEGER NOT NULL DEFAULT 0, notes TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE TABLE ad_campaigns (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, platform TEXT NOT NULL, name TEXT NOT NULL,
                objective TEXT NOT NULL DEFAULT 'leads', status TEXT NOT NULL DEFAULT 'draft', daily_budget REAL NOT NULL DEFAULT 0, start_date TEXT, end_date TEXT, landing_url TEXT NOT NULL DEFAULT '',
                utm_campaign TEXT NOT NULL DEFAULT '', audience TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE INDEX idx_adcamp_client ON ad_campaigns(client_id, status);
            CREATE TABLE ad_stats (campaign_id INTEGER NOT NULL REFERENCES ad_campaigns(id) ON DELETE CASCADE, day TEXT NOT NULL, impressions INTEGER NOT NULL DEFAULT 0, clicks INTEGER NOT NULL DEFAULT 0,
                spend REAL NOT NULL DEFAULT 0, conversions REAL NOT NULL DEFAULT 0, value REAL NOT NULL DEFAULT 0, source TEXT NOT NULL DEFAULT 'manual', import_id INTEGER, PRIMARY KEY (campaign_id, day));
            CREATE INDEX idx_adstats_day ON ad_stats(day);
            CREATE TABLE ad_imports (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, platform TEXT NOT NULL, filename TEXT NOT NULL DEFAULT '',
                rows INTEGER NOT NULL DEFAULT 0, from_day TEXT, to_day TEXT, created_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE TABLE ad_creatives (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, campaign_id INTEGER REFERENCES ad_campaigns(id) ON DELETE SET NULL,
                platform TEXT NOT NULL DEFAULT '', format TEXT NOT NULL DEFAULT 'image', title TEXT NOT NULL, headline TEXT NOT NULL DEFAULT '', primary_text TEXT NOT NULL DEFAULT '', cta TEXT NOT NULL DEFAULT '',
                url TEXT NOT NULL DEFAULT '', file TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'draft', client_note TEXT NOT NULL DEFAULT '', decided_by TEXT NOT NULL DEFAULT '', decided_at TEXT,
                nudged_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE INDEX idx_adcre_client ON ad_creatives(client_id, status);
            CREATE TABLE ad_log (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, campaign_id INTEGER REFERENCES ad_campaigns(id) ON DELETE SET NULL,
                kind TEXT NOT NULL DEFAULT 'note', title TEXT NOT NULL, details TEXT NOT NULL DEFAULT '', shared INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL);
            CREATE INDEX idx_adlog_client ON ad_log(client_id, created_at);
            CREATE TABLE ad_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, author TEXT NOT NULL, name TEXT NOT NULL DEFAULT '',
                body TEXT NOT NULL, seen_at TEXT, created_at TEXT NOT NULL);
            CREATE TABLE ad_months (client_id INTEGER NOT NULL REFERENCES ad_clients(id) ON DELETE CASCADE, month TEXT NOT NULL, summary TEXT NOT NULL DEFAULT '', next_steps TEXT NOT NULL DEFAULT '', sent_at TEXT, PRIMARY KEY (client_id, month));
        ");
        $pdo->exec('PRAGMA user_version = 15');
    }
    if ($v < 16) {
        // v16: the wiki / help center (wiki.stusyn.com). Articles are seeded from app/wiki-content/*.md and edited in the browser.
        $pdo->exec("
            CREATE TABLE wiki_articles (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, title TEXT NOT NULL, summary TEXT NOT NULL DEFAULT '', category TEXT NOT NULL DEFAULT 'general',
                audience TEXT NOT NULL DEFAULT 'public', body TEXT NOT NULL DEFAULT '', position INTEGER NOT NULL DEFAULT 100, status TEXT NOT NULL DEFAULT 'published', keywords TEXT NOT NULL DEFAULT '',
                seed_hash TEXT NOT NULL DEFAULT '', edited INTEGER NOT NULL DEFAULT 0, views INTEGER NOT NULL DEFAULT 0, helpful_yes INTEGER NOT NULL DEFAULT 0, helpful_no INTEGER NOT NULL DEFAULT 0,
                updated_by TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE INDEX idx_wiki_cat ON wiki_articles(audience, category, position);
            CREATE TABLE wiki_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, article_id INTEGER NOT NULL REFERENCES wiki_articles(id) ON DELETE CASCADE, title TEXT NOT NULL, summary TEXT NOT NULL DEFAULT '',
                body TEXT NOT NULL, by_name TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE TABLE wiki_searches (q TEXT PRIMARY KEY, results INTEGER NOT NULL DEFAULT 0, count INTEGER NOT NULL DEFAULT 1, last_at TEXT NOT NULL);
            CREATE TABLE wiki_feedback (id INTEGER PRIMARY KEY AUTOINCREMENT, article_id INTEGER REFERENCES wiki_articles(id) ON DELETE CASCADE, helpful INTEGER NOT NULL, comment TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE TABLE wiki_questions (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL, question TEXT NOT NULL, page TEXT NOT NULL DEFAULT '', contact_id INTEGER, answered_at TEXT, created_at TEXT NOT NULL);
        ");
        $pdo->exec('PRAGMA user_version = 16');
    }
    if ($v < 17) {
        // v17: special offers (a set price and/or free months on named products, with an end date and a limit),
        // and free trials on monthly plans bought with one.
        $pdo->exec("
            CREATE TABLE offers (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, name TEXT NOT NULL, items TEXT NOT NULL DEFAULT '[]',
                ends_on TEXT, max_uses INTEGER NOT NULL DEFAULT 0, active INTEGER NOT NULL DEFAULT 1, note TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            ALTER TABLE orders ADD COLUMN offer TEXT NOT NULL DEFAULT '';
            ALTER TABLE orders ADD COLUMN trial_end INTEGER;
            ALTER TABLE orders ADD COLUMN trial_reminded_at TEXT;
            ALTER TABLE order_items ADD COLUMN list_price REAL;
            ALTER TABLE order_items ADD COLUMN trial_months INTEGER NOT NULL DEFAULT 0;
        ");
        $ins = $pdo->prepare('INSERT OR IGNORE INTO offers (slug, name, items, ends_on, max_uses, active, note, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?)');
        foreach (json_decode((string)@file_get_contents(__DIR__ . '/catalog.json'), true)['offers'] ?? [] as $of) {
            $ins->execute([$of['slug'], $of['name'], json_encode($of['items'], JSON_UNESCAPED_SLASHES), $of['ends_on'] ?? null, (int)($of['max_uses'] ?? 0), (int)($of['active'] ?? 1), (string)($of['note'] ?? ''), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        }
        $pdo->exec('PRAGMA user_version = 17');
    }
    if ($v < 18) {
        // v18: Community products (Discord setup, community audit, community management) and the press kit add-on.
        $pos = (int)$pdo->query("SELECT COALESCE(MAX(position), 0) FROM products WHERE category = 'care'")->fetchColumn();
        $last = (int)$pdo->query('SELECT COALESCE(MAX(position), 0) FROM products')->fetchColumn();
        $ins = $pdo->prepare('INSERT OR IGNORE INTO products (sku, category, name, blurb, features, price, interval, unit, max_qty, requires, badge, featured, timeline_days, steps, site, position, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach (json_decode((string)@file_get_contents(__DIR__ . '/catalog.json'), true)['products'] ?? [] as $p) {
            if (!in_array($p['sku'], ['com-discord-setup', 'com-audit', 'com-manage', 'com-manage-plus', 'add-presskit'], true)) continue;
            $at = $p['category'] === 'community' ? ++$pos : ($last += 10);
            $ins->execute([$p['sku'], $p['category'], $p['name'], $p['blurb'] ?? '', implode("\n", $p['features'] ?? []), (float)$p['price'], $p['interval'] ?? '', $p['unit'] ?? '',
                (int)($p['max_qty'] ?? 1), implode(',', $p['requires'] ?? []), $p['badge'] ?? '', !empty($p['featured']) ? 1 : 0, (int)($p['timeline_days'] ?? 0),
                implode("\n", $p['steps'] ?? []), !empty($p['site']) ? 1 : 0, $at, date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
        }
        $pdo->exec('PRAGMA user_version = 18');
    }
    if ($v < 19) {
        // v19: outreach. Prospects researched by the outreach agent, emailed from a dedicated mailbox with two follow-ups,
        // replies and opt-outs tracked; a permanent do-not-contact list.
        $pdo->exec("
            CREATE TABLE outreach_prospects (id INTEGER PRIMARY KEY AUTOINCREMENT, business TEXT NOT NULL, contact_name TEXT NOT NULL DEFAULT '',
                email TEXT NOT NULL COLLATE NOCASE, domain TEXT NOT NULL DEFAULT '', website TEXT NOT NULL DEFAULT '', city TEXT NOT NULL DEFAULT '', niche TEXT NOT NULL DEFAULT '',
                notes TEXT NOT NULL DEFAULT '', best_fit TEXT NOT NULL DEFAULT '', sku TEXT NOT NULL DEFAULT '', priority TEXT NOT NULL DEFAULT 'B', batch TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'queued', step INTEGER NOT NULL DEFAULT 0, next_at INTEGER, subject TEXT NOT NULL, body TEXT NOT NULL,
                fu1 TEXT NOT NULL DEFAULT '', fu2 TEXT NOT NULL DEFAULT '', token TEXT NOT NULL UNIQUE, msg_ids TEXT NOT NULL DEFAULT ' ',
                first_sent_at TEXT, last_sent_at TEXT, replied_at TEXT, reply_email_id INTEGER, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
                deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
            CREATE INDEX idx_outreach_due ON outreach_prospects(status, next_at);
            CREATE INDEX idx_outreach_email ON outreach_prospects(email);
            CREATE INDEX idx_outreach_domain ON outreach_prospects(domain);
            CREATE TABLE outreach_log (id INTEGER PRIMARY KEY AUTOINCREMENT, prospect_id INTEGER NOT NULL REFERENCES outreach_prospects(id) ON DELETE CASCADE,
                kind TEXT NOT NULL, body TEXT NOT NULL DEFAULT '', email_id INTEGER, created_at TEXT NOT NULL);
            CREATE INDEX idx_outreach_log ON outreach_log(prospect_id, id);
            CREATE INDEX idx_outreach_log_kind ON outreach_log(kind, created_at);
            CREATE TABLE outreach_optout (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL DEFAULT '' COLLATE NOCASE, domain TEXT NOT NULL DEFAULT '' COLLATE NOCASE,
                reason TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
            CREATE INDEX idx_optout_email ON outreach_optout(email);
            CREATE INDEX idx_optout_domain ON outreach_optout(domain);
        ");
        $ins = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach (['outreach_on' => '0', 'outreach_mailbox' => '', 'outreach_from_name' => 'Grady Davis', 'outreach_address' => '', 'outreach_start' => '8',
            'outreach_cap' => '30', 'outreach_api_hash' => '', 'outreach_last_email' => '0', 'outreach_paused' => ''] as $k => $val) $ins->execute([$k, $val]);
        $pdo->exec('PRAGMA user_version = 19');
    }
}
function migrate_v1(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL, created_at TEXT NOT NULL, last_login_at TEXT);
        CREATE TABLE companies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, website TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '',
            industry TEXT NOT NULL DEFAULT '', city TEXT NOT NULL DEFAULT '', address TEXT NOT NULL DEFAULT '', brand TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE contacts (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
            first_name TEXT NOT NULL DEFAULT '', last_name TEXT NOT NULL DEFAULT '', email TEXT NOT NULL DEFAULT '', phone TEXT NOT NULL DEFAULT '',
            title TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE INDEX idx_contacts_email ON contacts(email);
        CREATE TABLE deals (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL,
            contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL, brand TEXT NOT NULL DEFAULT '', stage TEXT NOT NULL DEFAULT 'new',
            value REAL NOT NULL DEFAULT 0, expected_close TEXT, source TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', lost_reason TEXT NOT NULL DEFAULT '',
            position INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL, closed_at TEXT);
        CREATE INDEX idx_deals_stage ON deals(stage);
        CREATE TABLE activities (id INTEGER PRIMARY KEY AUTOINCREMENT, entity_type TEXT NOT NULL, entity_id INTEGER NOT NULL, kind TEXT NOT NULL,
            body TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
        CREATE INDEX idx_activities_entity ON activities(entity_type, entity_id);
        CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, due_date TEXT, priority INTEGER NOT NULL DEFAULT 0,
            entity_type TEXT NOT NULL DEFAULT '', entity_id INTEGER, done_at TEXT, created_at TEXT NOT NULL);
        CREATE INDEX idx_tasks_due ON tasks(done_at, due_date);
        CREATE TABLE consultations (id INTEGER PRIMARY KEY AUTOINCREMENT, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
            company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL, deal_id INTEGER REFERENCES deals(id) ON DELETE SET NULL,
            date TEXT NOT NULL, start_time TEXT NOT NULL DEFAULT '', minutes INTEGER NOT NULL DEFAULT 60, rate REAL NOT NULL DEFAULT 150,
            topic TEXT NOT NULL DEFAULT '', notes TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'scheduled', billable INTEGER NOT NULL DEFAULT 1,
            invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, number TEXT NOT NULL UNIQUE, brand TEXT NOT NULL DEFAULT '',
            company_id INTEGER REFERENCES companies(id) ON DELETE SET NULL, contact_id INTEGER REFERENCES contacts(id) ON DELETE SET NULL,
            issue_date TEXT NOT NULL, due_date TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'draft', tax_rate REAL NOT NULL DEFAULT 0,
            notes TEXT NOT NULL DEFAULT '', paid_at TEXT, paid_method TEXT NOT NULL DEFAULT '', sent_at TEXT, created_at TEXT NOT NULL, updated_at TEXT NOT NULL);
        CREATE TABLE invoice_items (id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
            description TEXT NOT NULL, quantity REAL NOT NULL DEFAULT 1, unit_price REAL NOT NULL DEFAULT 0, consultation_id INTEGER, position INTEGER NOT NULL DEFAULT 0);
        CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL);
        CREATE TABLE attempts (kind TEXT NOT NULL, ip_hash TEXT NOT NULL, created_at INTEGER NOT NULL);
    ");
    $defaults = [
        'business_name' => 'Syndicate Holdings Group LLC', 'business_email' => 'hello@stusyn.com', 'business_location' => 'Spartanburg, South Carolina',
        'business_website' => 'stusyn.com', 'default_rate' => '150', 'invoice_prefix' => 'SYN-', 'invoice_next' => '1001', 'invoice_terms_days' => '14',
        'invoice_footer' => 'Thank you for your business.', 'payment_instructions' => 'Payment details will be sent with this invoice.',
        'site_last_message_id' => '0', 'site_last_sync' => '',
    ];
    $ins = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
    foreach ($defaults as $k => $val) $ins->execute([$k, $val]);
    $pdo->exec('PRAGMA user_version = 1');
}

/* ---------- Email ---------- */
function send_mail(string $to, string $subject, string $body, string $replyTo = '', string $fromName = ''): bool {
    $from = setting('mail_from') ?: 'hello@stusyn.com';
    $name = '=?UTF-8?B?' . base64_encode($fromName !== '' ? $fromName : setting('business_name')) . '?=';
    $headers = ['From: ' . $name . ' <' . $from . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];
    $reply = $replyTo ?: $from;
    if (filter_var($reply, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $reply;
    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) error_log('CRM mail failed: to=' . $to . ' subject=' . $subject);
    return $ok;
}
// ads.stusyn.com (the ads app in the ads/ folder).
function ads_url(string $p = ''): string { return rtrim((string)(config('ads_url') ?: 'https://ads.stusyn.com'), '/') . '/' . ltrim($p, '/'); }
function wiki_url(string $p = ''): string { return rtrim((string)(config('wiki_url') ?: 'https://wiki.stusyn.com'), '/') . '/' . ltrim($p, '/'); }
function abs_url(string $p = ''): string { return rtrim((string)config('app_url'), '/') . url($p); }
function invoice_link(array $inv): string { return abs_url('i.php?t=' . $inv['share_token']); }
function setting(string $k, ?string $set = null): string {
    if ($set !== null) { q('INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value', [$k, $set]); return $set; }
    return (string)(val('SELECT value FROM settings WHERE key = ?', [$k]) ?: '');
}

/* ---------- Activity log ---------- */
function log_activity(string $type, int $id, string $kind, string $body): void {
    q('INSERT INTO activities (entity_type, entity_id, kind, body, created_at) VALUES (?,?,?,?,?)', [$type, $id, $kind, $body, now()]);
}
function timeline(string $type, int $id): array {
    return all('SELECT * FROM activities WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 200', [$type, $id]);
}

/* ---------- Sessions, CSRF, auth ---------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('syn_crm');
    session_set_cookie_params(['lifetime' => 0, 'path' => BASE, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    $dir = STORAGE . '/sessions';
    if (is_dir($dir) || @mkdir($dir, 0700, true)) session_save_path($dir);
    ini_set('session.gc_maxlifetime', '43200');
    session_start();
}
function csrf_token(): string { start_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_ok(): bool { start_session(); $t = (string)($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? ''); return !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t); }
function ip_hash(): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . config('setup_key')); }
function too_many(string $kind, int $max, int $minutes): bool {
    q('DELETE FROM attempts WHERE created_at < ?', [time() - 86400]);
    return (int)val('SELECT COUNT(*) FROM attempts WHERE kind = ? AND ip_hash = ? AND created_at > ?', [$kind, ip_hash(), time() - $minutes * 60]) >= $max;
}
function record_attempt(string $kind): void { q('INSERT INTO attempts (kind, ip_hash, created_at) VALUES (?,?,?)', [$kind, ip_hash(), time()]); }
// A short fingerprint of the password hash, kept in the session. Changing or resetting the password
// changes it, which signs out every other session for that account.
function pw_mark(string $hash): string { return substr(hash('sha256', $hash), 0, 16); }
function sign_in_user(array $u): void {
    start_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    $_SESSION['pwv'] = pw_mark((string)$u['password_hash']);
}
function current_user(): ?array {
    start_session();
    if (empty($_SESSION['uid'])) return null;
    $u = one('SELECT id, name, email, password_hash FROM users WHERE id = ?', [$_SESSION['uid']]);
    if (!$u || !hash_equals(pw_mark($u['password_hash']), (string)($_SESSION['pwv'] ?? ''))) { unset($_SESSION['uid'], $_SESSION['pwv']); return null; }
    unset($u['password_hash']);
    return $u;
}
// Password reset links: emailed to the account, work once, expire after 30 minutes.
function reset_link_create(int $uid): string {
    $tok = bin2hex(random_bytes(24));
    setting('reset_token', hash('sha256', $tok) . '|' . $uid . '|' . (time() + 1800));
    return $tok;
}
function reset_link_user(string $tok): ?array {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return null;
    [$h, $uid, $exp] = array_pad(explode('|', setting('reset_token')), 3, '');
    if ($h === '' || (int)$exp < time() || !hash_equals($h, hash('sha256', $tok))) return null;
    return one('SELECT * FROM users WHERE id = ?', [(int)$uid]);
}
function require_user(): array {
    header('Cache-Control: no-store');
    if ((int)val('SELECT COUNT(*) FROM users') === 0) redirect('setup.php');
    $u = current_user();
    if (!$u) redirect('login.php');
    if (is_post() && !csrf_ok()) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
    return $u;
}
function flash(?string $m = null): ?string { start_session(); if ($m !== null) { $_SESSION['flash'] = $m; return null; } $x = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $x; }

/* ---------- Lookups ---------- */
function company_options(): array { return all('SELECT id, name FROM companies ORDER BY name COLLATE NOCASE'); }
function contact_options(): array { return all("SELECT c.id, c.first_name, c.last_name, c.email, co.name company FROM contacts c LEFT JOIN companies co ON co.id = c.company_id ORDER BY c.first_name COLLATE NOCASE, c.last_name COLLATE NOCASE"); }
function deal_options(): array { return all("SELECT id, title FROM deals ORDER BY stage IN ('won','lost'), updated_at DESC"); }
function entity_link(string $type, ?int $id): string {
    if (!$id) return '';
    if ($type === 'deal' && ($r = one('SELECT title FROM deals WHERE id = ?', [$id]))) return '<a href="' . e(url('deal.php?id=' . $id)) . '">' . e($r['title']) . '</a>';
    if ($type === 'company' && ($r = one('SELECT name FROM companies WHERE id = ?', [$id]))) return '<a href="' . e(url('company.php?id=' . $id)) . '">' . e($r['name']) . '</a>';
    if ($type === 'contact' && ($r = one('SELECT * FROM contacts WHERE id = ?', [$id]))) return '<a href="' . e(url('contact.php?id=' . $id)) . '">' . e(person_name($r)) . '</a>';
    return '';
}
function invoice_total(int $id): array {
    $inv = one('SELECT tax_rate FROM invoices WHERE id = ?', [$id]);
    $sub = (float)val('SELECT COALESCE(SUM(quantity * unit_price), 0) FROM invoice_items WHERE invoice_id = ?', [$id]);
    $tax = round($sub * (float)($inv['tax_rate'] ?? 0) / 100, 2);
    return ['subtotal' => $sub, 'tax' => $tax, 'total' => round($sub + $tax, 2)];
}
function next_invoice_number(): string {
    $n = (int)setting('invoice_next');
    do { $num = setting('invoice_prefix') . $n; $n++; } while (val('SELECT 1 FROM invoices WHERE number = ?', [$num]));
    setting('invoice_next', (string)$n);
    return $num;
}

// Date $n periods after $start. Monthly schedules keep the start day, moved back in short months (Jan 31 → Feb 28).
function repeat_date(string $start, string $every, int $n): string {
    $d = new DateTime($start);
    if ($every === 'week') return $d->modify('+' . (7 * $n) . ' days')->format('Y-m-d');
    $m = (int)$d->format('n') - 1 + $n * (['month' => 1, 'quarter' => 3, 'year' => 12][$every] ?? 1);
    $y = (int)$d->format('Y') + intdiv($m, 12); $m = $m % 12 + 1;
    $last = (int)(new DateTime(sprintf('%04d-%02d-01', $y, $m)))->format('t');
    return sprintf('%04d-%02d-%02d', $y, $m, min((int)$d->format('j'), $last));
}
// What one repeat of an amount is worth per month, for the recurring revenue total.
function per_month(float $amount, string $every): float { return $amount * (['week' => 52 / 12, 'month' => 1, 'quarter' => 1 / 3, 'year' => 1 / 12][$every] ?? 0); }

function fmt_bytes(int $b): string { return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB'; }
// The largest upload PHP accepts here, in bytes (cPanel → MultiPHP INI Editor changes it).
function upload_limit(): int {
    $b = function ($v) { $v = trim((string)$v); $n = (float)$v; $u = strtolower(substr($v, -1)); return (int)($u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n))); };
    $lim = array_filter([$b(ini_get('upload_max_filesize')), $b(ini_get('post_max_size'))]);
    return $lim ? min($lim) : 8388608;
}

/* ---------- Website import (stusyn.com contact form → leads) ---------- */
function site_db_path(): string {
    $cfg = (string)config('site_db');
    if ($cfg !== '') return $cfg;
    foreach ([dirname(DOCROOT) . '/public_html/storage/syndicate.sqlite', dirname(DOCROOT) . '/storage/syndicate.sqlite', DOCROOT . '/../storage/syndicate.sqlite'] as $p) {
        if (@is_file($p)) return $p;
    }
    return '';
}
function find_or_create_contact(string $name, string $email, string $company): array {
    $contact = $email !== '' ? one('SELECT * FROM contacts WHERE email = ? COLLATE NOCASE', [$email]) : null;
    $companyId = $contact['company_id'] ?? null;
    if (!$companyId && $company !== '') {
        $co = one('SELECT id FROM companies WHERE name = ? COLLATE NOCASE', [$company]);
        if ($co) $companyId = (int)$co['id'];
        else { q('INSERT INTO companies (name, created_at, updated_at) VALUES (?,?,?)', [$company, now(), now()]); $companyId = (int)db()->lastInsertId(); }
    }
    if (!$contact) {
        $parts = preg_split('/\s+/', trim($name), 2);
        q('INSERT INTO contacts (company_id, first_name, last_name, email, created_at, updated_at) VALUES (?,?,?,?,?,?)', [$companyId, $parts[0] ?? '', $parts[1] ?? '', $email, now(), now()]);
        $contact = one('SELECT * FROM contacts WHERE id = ?', [(int)db()->lastInsertId()]);
    } elseif (!$contact['company_id'] && $companyId) {
        q('UPDATE contacts SET company_id = ?, updated_at = ? WHERE id = ?', [$companyId, now(), $contact['id']]);
        $contact['company_id'] = $companyId;
    }
    return $contact;
}
// Returns ['ok' => bool, 'imported' => int, 'message' => string]
function sync_website(bool $force = false): array {
    $path = site_db_path();
    if ($path === '' || !@is_readable($path)) return ['ok' => false, 'imported' => 0, 'message' => 'Website database not found. Set its path in app/config.php ("site_db").'];
    if (!$force && setting('site_last_sync') !== '' && strtotime(setting('site_last_sync')) > time() - 300) return ['ok' => true, 'imported' => 0, 'message' => 'Synced recently.'];
    try {
        $site = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $site->exec('PRAGMA busy_timeout = 3000');
        $st = $site->prepare('SELECT * FROM messages WHERE id > ? ORDER BY id ASC LIMIT 500');
        $st->execute([(int)setting('site_last_message_id')]);
        $rows = $st->fetchAll();
    } catch (Throwable $ex) {
        error_log('Website sync failed: ' . $ex->getMessage());
        return ['ok' => false, 'imported' => 0, 'message' => "Couldn't read the website database."];
    }
    $n = 0;
    foreach ($rows as $m) {
        $isConsult = stripos((string)$m['topic'], 'consult') !== false;
        $brand = $isConsult || stripos((string)$m['topic'], 'tiktok') !== false ? 'Creator Ops Media' : (stripos((string)$m['topic'], 'marketing') !== false ? 'Syndicate Marketing' : 'Syndicate Holdings Group');
        if (stripos((string)$m['topic'], 'career') !== false) { setting('site_last_message_id', (string)$m['id']); continue; } // careers belong in the hiring dashboard
        $contact = find_or_create_contact((string)$m['name'], (string)$m['email'], (string)$m['company']);
        $isOrder = stripos((string)$m['topic'], 'one-page website order') !== false; // checkout on stusyn.com/one-page-website.html
        $ref = $isOrder && preg_match('/Order (SM-[A-Z0-9-]+)/', (string)$m['message'], $rm) ? $rm[1] : '';
        $title = ($isConsult ? 'Consultation — ' : ($isOrder ? 'One-page website — ' : (string)$m['topic'] . ' — ')) . ($m['company'] ?: person_name($contact));
        q('INSERT INTO deals (title, company_id, contact_id, brand, stage, value, source, notes, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$title, $contact['company_id'], $contact['id'], $brand, $isOrder ? 'proposal' : 'new', $isConsult ? (float)setting('default_rate') : ($isOrder && preg_match('/· \$([\d,]+)/', (string)$m['message'], $pm) ? (float)str_replace(',', '', $pm[1]) : 0), $isConsult ? 'Website — consultation' : ($isOrder ? 'Website — order' : 'Website'), '', $m['created_at'], now()]);
        $dealId = (int)db()->lastInsertId();
        log_activity('deal', $dealId, 'email', "Website message (" . $m['topic'] . "):\n\n" . $m['message']);
        log_activity('contact', (int)$contact['id'], 'email', "Website message (" . $m['topic'] . "):\n\n" . $m['message']);
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,?,?,?,?)', [$isOrder ? 'Check Stripe for payment' . ($ref ? " ($ref)" : '') . ', then schedule kickoff with ' . person_name($contact) : 'Reply to ' . person_name($contact), today(), 1, 'deal', $dealId, now()]);
        setting('site_last_message_id', (string)$m['id']);
        $n++;
    }
    setting('site_last_sync', now());
    return ['ok' => true, 'imported' => $n, 'message' => $n ? "Imported $n new website " . ($n === 1 ? 'lead' : 'leads') . '.' : 'No new website messages.'];
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
function layout_top(string $title, string $active, ?array $user): void { require __DIR__ . '/views/top.php'; }
function layout_bottom(): void { require __DIR__ . '/views/bottom.php'; }
