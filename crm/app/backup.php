<?php
// Nightly backups, run by cron.php. Once a day (after 2 AM) it copies everything that can't be rebuilt from git into
// storage/backups/YYYY-MM-DD/: the CRM and Site Stats databases, the stusyn.com database (applicants, contact
// messages) and the keys that unlock saved passwords and two-step sign-in. Each database copy is a consistent
// snapshot (VACUUM INTO) and is checked before it counts. The last BACKUP_KEEP days are kept.
// If a backup fails, hello@ gets one email and a task a day until it works again; the dashboard warns after 2 days.
// These copies sit on the same server: they protect against a bad deploy, a damaged database or a mistake, not
// against losing the hosting account. Download one now and then (Settings → Your data → Download latest backup).

const BACKUP_KEEP = 14;     // days of nightly backups to keep
const BACKUP_HOUR = 2;      // earliest hour (CRM time zone) the nightly backup runs

function backup_dir(): string { return STORAGE . '/backups'; }

// The nightly folders, newest first.
function backup_list(): array {
    $dirs = array_filter(glob(backup_dir() . '/20[0-9][0-9]-[0-9][0-9]-[0-9][0-9]', GLOB_ONLYDIR) ?: [], 'is_dir');
    rsort($dirs);
    return array_values($dirs);
}

// What a nightly backup holds: name in the backup => [source path, kind].
function backup_sources(): array {
    $src = ['crm.sqlite' => [STORAGE . '/crm.sqlite', 'db'], 'analytics.sqlite' => [STORAGE . '/analytics.sqlite', 'db'],
        'mail.key' => [STORAGE . '/mail.key', 'file'], 'totp.key' => [STORAGE . '/totp.key', 'file']];
    $site = site_db_path();
    if ($site !== '') {
        $src['stusyn/syndicate.sqlite'] = [$site, 'db'];
        $src['stusyn/totp.key'] = [dirname($site) . '/totp.key', 'file'];
    }
    return $src;
}

// A consistent copy of a SQLite database, checked afterwards. Throws on failure.
function backup_db(string $from, string $to): void {
    $pdo = new PDO('sqlite:' . $from, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA busy_timeout = 10000');
    try {
        $pdo->exec('VACUUM INTO ' . $pdo->quote($to));
    } catch (Throwable $e) {
        // SQLite older than 3.27: copy the file plus any write-ahead log, then let SQLite fold the log in.
        $pdo->exec('PRAGMA wal_checkpoint(FULL)');
        if (!@copy($from, $to)) throw new RuntimeException('could not copy ' . basename($from));
        if (is_file($from . '-wal')) @copy($from . '-wal', $to . '-wal');
    }
    $pdo = null;
    $chk = new PDO('sqlite:' . $to, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $ok = (string)$chk->query('PRAGMA integrity_check')->fetchColumn();
    if ($ok === 'ok' && is_file($to . '-wal')) $chk->exec('PRAGMA journal_mode = DELETE');
    $chk = null;
    if ($ok !== 'ok') throw new RuntimeException(basename($from) . ' copy failed its check: ' . $ok);
}

function backup_rmdir(string $dir): void {
    foreach (array_diff(scandir($dir) ?: [], ['.', '..']) as $f) { $p = $dir . '/' . $f; is_dir($p) ? backup_rmdir($p) : @unlink($p); }
    @rmdir($dir);
}

// Makes today's backup. Returns a one-line result for the cron output. $force runs it again even if today's exists.
function backup_run(bool $force = false): string {
    if (DEMO) return '';
    $day = today();
    if (!$force && (setting('backup_last') === $day || (int)date('G') < BACKUP_HOUR)) return '';
    $root = backup_dir();
    if (!is_dir($root) && !@mkdir($root, 0700, true)) return backup_failed('could not create storage/backups');
    $need = 0;
    foreach (backup_sources() as [$p]) $need += @is_file($p) ? (int)filesize($p) : 0;
    $free = @disk_free_space($root);
    if ($free !== false && $free < $need * 2 + 50 * 1048576) return backup_failed('not enough disk space (' . fmt_bytes((int)$free) . ' free, needs about ' . fmt_bytes($need * 2) . ')');

    $tmp = $root . '/.tmp-' . $day . '-' . bin2hex(random_bytes(3));
    $files = []; $missing = [];
    try {
        if (!@mkdir($tmp . '/stusyn', 0700, true)) throw new RuntimeException('could not create a backup folder');
        foreach (backup_sources() as $name => [$from, $kind]) {
            if (!@is_file($from)) {
                if (in_array($name, ['crm.sqlite', 'mail.key'], true)) throw new RuntimeException("$name is missing");
                $missing[] = $name; continue;
            }
            $to = $tmp . '/' . $name;
            if ($kind === 'db') backup_db($from, $to);
            elseif (!@copy($from, $to)) throw new RuntimeException("could not copy $name");
            @chmod($to, 0600);
            $files[$name] = ['bytes' => (int)filesize($to), 'sha256' => hash_file('sha256', $to)];
        }
        file_put_contents($tmp . '/manifest.json', json_encode(['made' => date('c'), 'files' => $files, 'missing' => $missing], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($tmp . '/manifest.json', 0600);
        $final = $root . '/' . $day;
        if (is_dir($final)) backup_rmdir($final);
        if (!@rename($tmp, $final)) throw new RuntimeException('could not save the backup folder');
    } catch (Throwable $e) {
        if (is_dir($tmp)) backup_rmdir($tmp);
        return backup_failed($e->getMessage());
    }
    foreach (array_slice(backup_list(), BACKUP_KEEP) as $old) backup_rmdir($old);
    foreach (glob($root . '/.tmp-*', GLOB_ONLYDIR) ?: [] as $stale) if (filemtime($stale) < time() - 86400) backup_rmdir($stale);

    $size = array_sum(array_column($files, 'bytes'));
    $wasDown = setting('backup_status') !== '' && setting('backup_status') !== 'ok';
    setting('backup_last', $day); setting('backup_ok_at', now()); setting('backup_status', 'ok'); setting('backup_size', (string)$size);
    if ($wasDown) send_mail(backup_notify(), 'CRM backups are working again', "Today's backup worked (" . fmt_bytes($size) . ").\n\n" . abs_url('settings.php#backups') . "\n");
    return 'Backup saved (' . fmt_bytes($size) . ($missing ? ', not found: ' . implode(', ', $missing) : '') . ')';
}

function backup_notify(): string { return setting('shop_notify') ?: (setting('business_email') ?: 'hello@stusyn.com'); }

// Records a failure; emails and adds a task at most once a day.
function backup_failed(string $why): string {
    error_log('CRM backup failed: ' . $why);
    setting('backup_status', $why);
    if (setting('backup_alerted') !== today()) {
        setting('backup_alerted', today());
        $last = setting('backup_ok_at');
        send_mail(backup_notify(), 'CRM backup failed', "Last night's CRM backup didn't work: $why.\n\n"
            . 'Last good backup: ' . ($last !== '' ? date('M j, Y g:i A', strtotime($last)) : 'none yet') . ".\n\n"
            . "Fix the problem, then run it again from Settings → Your data (or on the server: php app/cli.php backup).\n\n"
            . abs_url('settings.php#backups') . "\n");
        q('INSERT INTO tasks (title, due_date, priority, entity_type, entity_id, created_at) VALUES (?,?,1,?,?,?)', ['Fix the CRM backup: ' . mb_substr($why, 0, 120), today(), '', null, now()]);
    }
    return 'Backup failed: ' . $why;
}

// For the dashboard: a warning when the last good backup is over 2 days old (or there's never been one after day 1).
function backup_warning(): string {
    $ok = setting('backup_ok_at');
    if ($ok !== '' && strtotime($ok) > time() - 2 * 86400) return '';
    if ($ok === '' && setting('backup_status') === '') return ''; // brand new: the first one runs tonight
    return $ok === '' ? 'No CRM backup has worked yet.' : 'The last CRM backup was ' . date('M j', strtotime($ok)) . '.';
}

// Streams the newest nightly backup as a zip (or one database if this server has no zip support).
function backup_download(): void {
    $dirs = backup_list();
    if (!$dirs) { flash('No nightly backup yet. Run one first.'); redirect('settings.php#backups'); }
    $dir = $dirs[0]; $name = 'crm-backup-' . basename($dir);
    if (class_exists('ZipArchive')) {
        $zipFile = backup_dir() . '/.dl-' . bin2hex(random_bytes(6)) . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) if ($f->isFile()) $zip->addFile($f->getPathname(), substr($f->getPathname(), strlen($dir) + 1));
            $zip->close();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $name . '.zip"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile); @unlink($zipFile); exit;
        }
    }
    $file = $dir . '/crm.sqlite';
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $name . '.sqlite"');
    header('Content-Length: ' . filesize($file));
    readfile($file); exit;
}
