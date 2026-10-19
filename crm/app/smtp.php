<?php
// Sends mail through the mailbox's own SMTP login, and builds the outgoing message.
// Mail sent this way is signed in as the real mailbox, so it gets the same SPF/DKIM treatment as your phone or Outlook.

class SmtpError extends RuntimeException {}

class Smtp {
    private $s;
    private string $ext = '';

    public function __construct(string $host, int $port, bool $verify, int $timeout = 30) {
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'peer_name' => $host, 'SNI_enabled' => true]]);
        $ssl = $port === 465;
        $s = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $err, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) throw new SmtpError(mail_conn_error($host, $port, $err ?: (error_get_last()['message'] ?? '')));
        stream_set_timeout($s, $timeout);
        $this->s = $s;
        $this->expect(220);
        $this->hello();
        if (!$ssl) {
            if (stripos($this->ext, 'STARTTLS') === false) throw new SmtpError("The mail server on port $port doesn't offer a secure connection. Use port 465.");
            $this->send('STARTTLS', 220);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new SmtpError("Couldn't start a secure connection to $host.");
            $this->hello();
        }
    }
    private function hello(): void {
        $me = parse_url((string)config('app_url'), PHP_URL_HOST) ?: 'localhost';
        $this->ext = $this->send('EHLO ' . $me, 250);
    }
    public function login(string $user, string $pass): void {
        try {
            if (preg_match('/AUTH[ =][^\r\n]*PLAIN/i', $this->ext)) { $this->send('AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), 235); return; }
            $this->send('AUTH LOGIN', 334); $this->send(base64_encode($user), 334); $this->send(base64_encode($pass), 235);
        } catch (SmtpError $ex) { throw new SmtpError('The mail server rejected the email address or password for sending.'); }
    }
    public function mail(string $from, array $to, string $data): void {
        $this->send('MAIL FROM:<' . $from . '>', 250);
        foreach ($to as $r) {
            try { $this->send('RCPT TO:<' . $r . '>', [250, 251]); }
            catch (SmtpError $ex) { throw new SmtpError("The mail server won't send to $r. " . $ex->getMessage()); }
        }
        $this->send('DATA', 354);
        $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], ["\n", "\n"], $data));
        $data = str_replace("\n", "\r\n", rtrim($data, "\n"));
        for ($o = 0, $len = strlen($data); $o < $len; $o += 65536) fwrite($this->s, substr($data, $o, 65536));
        $this->send("\r\n.", 250);
    }
    public function quit(): void { try { $this->send('QUIT', 221); } catch (Throwable $ex) {} @fclose($this->s); }

    private function send(string $line, $ok): string { fwrite($this->s, $line . "\r\n"); return $this->expect($ok); }
    private function expect($ok): string {
        $all = '';
        do {
            $l = fgets($this->s);
            if ($l === false) throw new SmtpError('The mail server closed the connection.');
            $all .= $l;
        } while (isset($l[3]) && $l[3] === '-');
        if (!in_array((int)substr($l, 0, 3), (array)$ok, true)) throw new SmtpError(trim(substr($l, 4)) ?: 'The mail server refused the message.');
        return $all;
    }
}

/* ---------- Building the message ---------- */
function mime_word(string $s): string {
    if (!preg_match('/[^\x20-\x7e]/', $s)) return $s;
    $words = []; $chunk = '';
    foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
        if (strlen($chunk . $ch) > 42) { $words[] = $chunk; $chunk = ''; }
        $chunk .= $ch;
    }
    $words[] = $chunk;
    return implode("\r\n ", array_map(function ($w) { return '=?UTF-8?B?' . base64_encode($w) . '?='; }, $words));
}
function mime_addr(string $name, string $addr): string {
    $name = trim(str_replace(["\r", "\n"], '', $name));
    if ($name === '') return $addr;
    if (preg_match('/[^\x20-\x7e]/', $name)) return mime_word($name) . ' <' . $addr . '>';
    return (preg_match('/[()<>\[\]:;@\\\\,."]/', $name) ? '"' . addcslashes($name, '"\\') . '"' : $name) . ' <' . $addr . '>';
}
function mime_filename(string $name): string {
    $ascii = preg_replace('/[^\x20-\x7e]|["\\\\]/u', '_', $name);
    return 'filename="' . $ascii . '"' . ($ascii !== $name ? "; filename*=UTF-8''" . rawurlencode($name) : '');
}

// $m: from [name, addr], to/cc [[name, addr]], subject, text, in_reply_to, references, attachments [[name, type, data]]
// Returns [raw message, message-id]
function build_message(array $m): array {
    $domain = substr(strrchr($m['from'][1], '@'), 1) ?: 'localhost';
    $mid = bin2hex(random_bytes(12)) . '@' . $domain;
    $join = function (array $list) { return implode(",\r\n ", array_map(function ($a) { return mime_addr($a[0], $a[1]); }, $list)); };
    $h = ['Date: ' . date('r'), 'From: ' . mime_addr($m['from'][0], $m['from'][1]), 'To: ' . $join($m['to'])];
    if (!empty($m['cc'])) $h[] = 'Cc: ' . $join($m['cc']);
    $h[] = 'Subject: ' . mime_word(str_replace(["\r", "\n"], ' ', $m['subject']));
    $h[] = 'Message-ID: <' . $mid . '>';
    if (!empty($m['in_reply_to'])) $h[] = 'In-Reply-To: <' . $m['in_reply_to'] . '>';
    if (!empty($m['references'])) $h[] = 'References: ' . implode("\r\n ", array_map(function ($r) { return '<' . $r . '>'; }, $m['references']));
    $h[] = 'MIME-Version: 1.0';
    $text = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $m['text']));
    if (empty($m['attachments'])) return [implode("\r\n", $h) . "\r\n" . $text . "\r\n", $mid];
    $b = '=_syn' . bin2hex(random_bytes(10));
    $h[] = 'Content-Type: multipart/mixed; boundary="' . $b . '"';
    $body = "This is a multi-part message in MIME format.\r\n\r\n--$b\r\n" . $text . "\r\n";
    foreach ($m['attachments'] as [$name, $type, $data]) {
        $type = preg_match('#^[\w.+-]+/[\w.+-]+$#', $type) ? $type : 'application/octet-stream';
        $body .= "--$b\r\nContent-Type: $type\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; " . mime_filename($name) . "\r\n\r\n" . chunk_split(base64_encode($data), 76, "\r\n");
    }
    return [implode("\r\n", $h) . "\r\n" . $body . "--$b--\r\n", $mid];
}
