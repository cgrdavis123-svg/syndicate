<?php
// A small IMAP client with only what the CRM inbox needs: sign in, find folders, search, fetch, flag and save to Sent.
// It talks to the mail server directly, so it doesn't need PHP's imap extension (removed from PHP 8.4).
class ImapError extends RuntimeException {}

class Imap {
    private $s;
    private int $n = 0;
    private string $caps = '';

    public function __construct(string $host, int $port, bool $verify, int $timeout = 20) {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $plain = $port !== 993; // 993 is secure from the start; other ports (143) switch to secure with STARTTLS
        $s = @stream_socket_client(($plain ? 'tcp://' : 'ssl://') . $host . ':' . $port, $errno, $err, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new ImapError(mail_conn_error($host, $port, $err ?: (error_get_last()['message'] ?? '')));
        stream_set_timeout($s, $timeout);
        $this->s = $s;
        $greet = $this->line();
        if (strpos($greet, '* OK') !== 0 && strpos($greet, '* PREAUTH') !== 0) throw new ImapError('The mail server refused the connection: ' . trim($greet));
        if ($plain) {
            $this->cmd('STARTTLS');
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new ImapError("Couldn't start a secure connection to $host.");
        }
        if (preg_match('/\[CAPABILITY ([^\]]*)\]/i', $greet, $m)) $this->caps = strtoupper($m[1]);
    }

    public function login(string $user, string $pass): void {
        if ($this->caps === '' || stripos($this->caps, 'AUTH=') === false) {
            foreach ($this->cmd('CAPABILITY') as $l) if (preg_match('/^\* CAPABILITY (.*)$/i', rtrim($l), $m)) $this->caps = strtoupper($m[1]);
        }
        try {
            if (strpos($this->caps, 'AUTH=PLAIN') !== false) $this->cont('AUTHENTICATE PLAIN', base64_encode("\0" . $user . "\0" . $pass));
            else $this->cmd('LOGIN ' . self::q($user) . ' ' . self::q($pass));
        } catch (ImapError $ex) {
            throw new ImapError('The mail server rejected the email address or password.');
        }
    }

    // [[name, flags], ...]
    public function folders(): array {
        $out = [];
        foreach ($this->cmd('LIST "" "*"') as $l) {
            if (!preg_match('/^\* LIST /i', $l)) continue;
            $i = 7; $t = self::parse($l, $i);
            if (count($t) >= 3) $out[] = [(string)$t[2], array_map('strtolower', (array)$t[0])];
        }
        return $out;
    }

    public function select(string $folder, bool $readOnly = false): array {
        $info = ['uidvalidity' => 0, 'exists' => 0];
        foreach ($this->cmd(($readOnly ? 'EXAMINE ' : 'SELECT ') . self::q($folder)) as $l) {
            if (preg_match('/\[UIDVALIDITY (\d+)\]/i', $l, $m)) $info['uidvalidity'] = (int)$m[1];
            if (preg_match('/^\* (\d+) EXISTS/i', $l, $m)) $info['exists'] = (int)$m[1];
        }
        return $info;
    }

    public function search(string $criteria): array {
        $uids = [];
        foreach ($this->cmd('UID SEARCH ' . $criteria) as $l) {
            if (preg_match('/^\* SEARCH(.*)$/i', rtrim($l), $m)) foreach (preg_split('/\s+/', trim($m[1])) as $u) if (ctype_digit($u)) $uids[] = (int)$u;
        }
        sort($uids);
        return $uids;
    }

    // UID => [ITEM => value]
    public function fetch(string $set, string $items): array {
        $out = [];
        foreach ($this->cmd('UID FETCH ' . $set . ' (' . $items . ')') as $l) {
            if (!preg_match('/^\* \d+ FETCH /i', $l)) continue;
            $i = strpos($l, '('); $list = self::parse($l, $i)[0] ?? [];
            $row = [];
            for ($k = 0; $k + 1 < count($list); $k += 2) $row[strtoupper((string)$list[$k])] = $list[$k + 1];
            if (isset($row['UID'])) $out[(int)$row['UID']] = $row;
        }
        return $out;
    }

    public function store(array $uids, string $change): void {
        if ($uids) $this->cmd('UID STORE ' . implode(',', $uids) . ' ' . $change . ' (\Seen)');
    }

    public function append(string $folder, string $message): void {
        $this->cont('APPEND ' . self::q($folder) . ' (\Seen) {' . strlen($message) . '}', $message);
    }

    public function logout(): void { try { $this->cmd('LOGOUT'); } catch (Throwable $ex) {} @fclose($this->s); }

    /* ---------- Protocol ---------- */
    public static function q(string $s): string { return '"' . addcslashes($s, "\"\\") . '"'; }

    private function line(): string {
        $l = fgets($this->s);
        if ($l === false) throw new ImapError(stream_get_meta_data($this->s)['timed_out'] ? 'The mail server took too long to answer.' : 'The mail server closed the connection.');
        return $l;
    }
    private function bytes(int $n): string {
        $buf = '';
        while (strlen($buf) < $n) {
            $chunk = fread($this->s, min(65536, $n - strlen($buf)));
            if ($chunk === false || ($chunk === '' && feof($this->s))) throw new ImapError('The mail server closed the connection.');
            if ($chunk === '' && stream_get_meta_data($this->s)['timed_out']) throw new ImapError('The mail server took too long to answer.');
            $buf .= $chunk;
        }
        return $buf;
    }
    // One full server response, with any {n} literals read in place.
    private function response(): string {
        $l = $this->line();
        while (preg_match('/\{(\d+)\}\r?\n$/', $l, $m)) $l .= $this->bytes((int)$m[1]) . $this->line();
        return $l;
    }
    private function finish(string $tag): array {
        $untagged = [];
        while (true) {
            $l = $this->response();
            if (strpos($l, $tag . ' ') === 0) {
                if (stripos($l, $tag . ' OK') !== 0) throw new ImapError(trim(substr($l, strlen($tag) + 1)));
                return $untagged;
            }
            if ($l[0] === '*') $untagged[] = $l;
        }
    }
    private function cmd(string $c): array {
        $tag = 'C' . (++$this->n);
        fwrite($this->s, $tag . ' ' . $c . "\r\n");
        return $this->finish($tag);
    }
    // A command whose second part goes after the server says "+ go ahead" (sign-in and APPEND).
    private function cont(string $c, string $data): array {
        $tag = 'C' . (++$this->n);
        fwrite($this->s, $tag . ' ' . $c . "\r\n");
        while (true) {
            $l = $this->response();
            if ($l[0] === '+') break;
            if (strpos($l, $tag . ' ') === 0) throw new ImapError(trim(substr($l, strlen($tag) + 1)));
        }
        for ($o = 0, $len = strlen($data); $o < $len; $o += 65536) fwrite($this->s, substr($data, $o, 65536));
        fwrite($this->s, "\r\n");
        return $this->finish($tag);
    }

    // Parses IMAP data (atoms, "strings", {n} literals and (lists)) starting at $i.
    public static function parse(string $s, int &$i, bool $inList = false): array {
        $out = []; $len = strlen($s);
        while ($i < $len) {
            $c = $s[$i];
            if ($c === ' ' || $c === "\r" || $c === "\n") { $i++; continue; }
            if ($c === ')') { $i++; if ($inList) return $out; continue; }
            if ($c === '(') { $i++; $out[] = self::parse($s, $i, true); continue; }
            if ($c === '"') {
                $v = ''; $i++;
                while ($i < $len && $s[$i] !== '"') { if ($s[$i] === '\\') $i++; $v .= $s[$i] ?? ''; $i++; }
                $i++; $out[] = $v; continue;
            }
            if ($c === '{' && preg_match('/\G\{(\d+)\}\r?\n/', $s, $m, 0, $i)) {
                $i += strlen($m[0]); $out[] = substr($s, $i, (int)$m[1]); $i += (int)$m[1]; continue;
            }
            $start = $i; $depth = 0;
            while ($i < $len) {
                $ch = $s[$i];
                if ($ch === '[') $depth++;
                elseif ($ch === ']') $depth--;
                elseif ($depth <= 0 && ($ch === ' ' || $ch === '(' || $ch === ')' || $ch === "\r" || $ch === "\n")) break;
                $i++;
            }
            $atom = substr($s, $start, $i - $start);
            $out[] = strtoupper($atom) === 'NIL' ? null : $atom;
        }
        return $out;
    }
}

function mail_conn_error(string $host, int $port, $err): string {
    $err = (string)$err;
    if (stripos($err, 'certificate') !== false || stripos($err, 'SSL') !== false) return "The secure connection to $host failed (certificate problem). Check the server name, or untick “Check the server certificate” under Advanced.";
    return "Couldn't reach $host on port $port. Check the mail server name and port." . ($err !== '' ? " ($err)" : '');
}
