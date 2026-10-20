<?php
// Reads raw emails (MIME): headers, addresses, the text and HTML bodies, and attachments.

// Splits a raw message or part into ['h' => [name => [values]], 'body' => string].
function mime_split(string $raw): array {
    $p = preg_match('/\r?\n\r?\n/', $raw, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : strlen($raw);
    $head = substr($raw, 0, $p); $body = (string)substr($raw, $p + (isset($m[0]) ? strlen($m[0][0]) : 0));
    $h = []; $last = null;
    foreach (preg_split('/\r?\n/', $head) as $line) {
        if ($line === '') continue;
        if (($line[0] === ' ' || $line[0] === "\t") && $last !== null) { $h[$last][count($h[$last]) - 1] .= ' ' . trim($line); continue; }
        if (($c = strpos($line, ':')) === false) continue;
        $last = strtolower(trim(substr($line, 0, $c)));
        $h[$last][] = trim(substr($line, $c + 1));
    }
    return ['h' => $h, 'body' => $body];
}
function mime_h(array $h, string $k): string { return (string)($h[$k][0] ?? ''); }

// "text/plain; charset=utf-8; name*=UTF-8''r%C3%A9sum%C3%A9.pdf" → ['text/plain', ['charset' => 'utf-8', 'name' => 'résumé.pdf']]
function mime_params(string $v): array {
    $parts = []; $cur = ''; $q = false;
    for ($i = 0, $n = strlen($v); $i < $n; $i++) {
        $c = $v[$i];
        if ($c === '"') $q = !$q;
        if ($c === ';' && !$q) { $parts[] = $cur; $cur = ''; continue; }
        $cur .= $c;
    }
    $parts[] = $cur;
    $value = strtolower(trim(array_shift($parts)));
    $params = []; $ext = [];
    foreach ($parts as $p) {
        if (($e = strpos($p, '=')) === false) continue;
        $k = strtolower(trim(substr($p, 0, $e))); $val = trim(substr($p, $e + 1));
        if (strlen($val) > 1 && $val[0] === '"' && substr($val, -1) === '"') $val = stripcslashes(substr($val, 1, -1));
        if (preg_match('/^([^*]+)\*(\d+)?(\*)?$/', $k, $m)) { $ext[$m[1]][(int)($m[2] ?? 0)] = [$val, !empty($m[3]) || !isset($m[2])]; continue; }
        $params[$k] = $val;
    }
    foreach ($ext as $k => $chunks) { // RFC 2231: name*0*=utf-8''..., name*1*=...
        ksort($chunks); $charset = 'utf-8'; $out = '';
        foreach ($chunks as $i => [$val, $enc]) {
            if ($i === 0 && $enc && preg_match("/^([^']*)'[^']*'(.*)$/", $val, $m)) { $charset = $m[1] ?: 'utf-8'; $val = $m[2]; }
            $out .= $enc ? rawurldecode($val) : $val;
        }
        $params[$k] = to_utf8($out, $charset);
    }
    return [$value, $params];
}

function to_utf8(string $s, string $charset = ''): string {
    $cs = strtolower(trim($charset, " \"'"));
    if ($cs === '' || $cs === 'us-ascii' || $cs === 'utf-8' || $cs === 'utf8') {
        if (mb_check_encoding($s, 'UTF-8')) return $s;
        $cs = 'windows-1252';
    }
    if (in_array($cs, ['iso-8859-1', 'latin1', 'ascii'], true)) $cs = 'windows-1252';
    if (in_array($cs, ['gb2312', 'gbk'], true)) $cs = 'gb18030';
    try {
        $out = @mb_convert_encoding($s, 'UTF-8', $cs);
        if (is_string($out) && $out !== '') return $out;
    } catch (Throwable $ex) {}
    $out = function_exists('iconv') ? @iconv($cs, 'UTF-8//IGNORE', $s) : false;
    return is_string($out) && $out !== '' ? $out : mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
}

// Decodes "=?utf-8?B?...?=" encoded words in a header.
function mime_decode(string $s): string {
    if (strpos($s, '=?') === false) return to_utf8($s);
    $s = preg_replace('/\?=\s+=\?/', '?==?', $s); // whitespace between encoded words isn't shown
    return preg_replace_callback('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', function ($m) {
        $cs = preg_replace('/\*.*$/', '', $m[1]);
        $raw = strtoupper($m[2]) === 'B' ? (string)base64_decode($m[3]) : quoted_printable_decode(str_replace('_', ' ', $m[3]));
        return to_utf8($raw, $cs);
    }, to_utf8($s));
}

// "Ann <a@x.com>, "Smith, Bo" <b@y.com>" → [['Ann', 'a@x.com'], ['Smith, Bo', 'b@y.com']]
function mime_addresses(string $v): array {
    $chunks = []; $cur = ''; $q = false; $angle = 0; $paren = 0;
    for ($i = 0, $n = strlen($v); $i < $n; $i++) {
        $c = $v[$i];
        if ($c === '\\' && $q) { $cur .= $c . ($v[++$i] ?? ''); continue; }
        if ($c === '"' && !$paren) $q = !$q;
        elseif (!$q && $c === '<') $angle++;
        elseif (!$q && $c === '>') $angle = max(0, $angle - 1);
        elseif (!$q && $c === '(') $paren++;
        elseif (!$q && $c === ')') $paren = max(0, $paren - 1);
        if (!$q && !$angle && !$paren && ($c === ',' || $c === ';')) { $chunks[] = $cur; $cur = ''; continue; }
        $cur .= $c;
    }
    $chunks[] = $cur;
    $out = [];
    foreach ($chunks as $c) {
        $c = trim(preg_replace('/^[^"<]*:/', '', trim($c))); // group syntax "Team: a@x, b@y;"
        if ($c === '') continue;
        if (preg_match('/^(.*)<([^>]*)>/s', $c, $m)) { $name = trim($m[1]); $addr = trim($m[2]); }
        else { $name = ''; $addr = trim(preg_replace('/\([^)]*\)/', '', $c)); if (preg_match('/\(([^)]*)\)/', $c, $mm)) $name = $mm[1]; }
        if (strlen($name) > 1 && $name[0] === '"' && substr($name, -1) === '"') $name = stripcslashes(substr($name, 1, -1));
        $addr = strtolower(trim($addr, " \t'\""));
        if (strpos($addr, '@') === false) continue;
        $out[] = [trim(mime_decode($name), " \t'\""), $addr];
    }
    return $out;
}

function mime_body(string $body, string $cte): string {
    $cte = strtolower(trim($cte));
    if ($cte === 'base64') return (string)base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $body));
    if ($cte === 'quoted-printable') return quoted_printable_decode(preg_replace('/\r?\n/', "\r\n", $body));
    return $body;
}

// Walks the MIME tree. Returns ['h', 'text', 'html', 'attachments' => [[name, type, size, cid, inline, data]]].
function mime_parse(string $raw): array {
    $top = mime_split($raw);
    $res = ['h' => $top['h'], 'text' => '', 'html' => '', 'attachments' => []];
    mime_walk($top, $res, 0);
    return $res;
}
function mime_walk(array $part, array &$res, int $depth): void {
    [$type, $p] = mime_params(mime_h($part['h'], 'content-type') ?: 'text/plain');
    [$disp, $dp] = mime_params(mime_h($part['h'], 'content-disposition'));
    if (strpos($type, 'multipart/') === 0 && !empty($p['boundary']) && $depth < 20) {
        foreach (mime_multipart($part['body'], $p['boundary']) as $sub) mime_walk(mime_split($sub), $res, $depth + 1);
        return;
    }
    $data = mime_body($part['body'], mime_h($part['h'], 'content-transfer-encoding'));
    $name = $dp['filename'] ?? $p['name'] ?? '';
    $name = $name !== '' ? mime_decode($name) : '';
    $isText = $type === 'text/plain' || $type === 'text/html';
    if ($isText && $disp !== 'attachment' && $name === '') {
        $key = $type === 'text/html' ? 'html' : 'text';
        $txt = to_utf8($data, $p['charset'] ?? '');
        $res[$key] .= ($res[$key] !== '' ? "\n" : '') . $txt;
        return;
    }
    if ($type === 'message/rfc822' && $name === '') $name = (mime_decode(mime_h(mime_split($data)['h'], 'subject')) ?: 'Attached message') . '.eml';
    $cid = trim(mime_h($part['h'], 'content-id'), '<> ');
    $res['attachments'][] = [
        'name' => $name !== '' ? $name : 'attachment-' . (count($res['attachments']) + 1) . mime_ext($type),
        'type' => $type, 'size' => strlen($data), 'cid' => $cid,
        'inline' => $disp === 'inline' || ($disp === '' && $cid !== ''), 'data' => $data,
    ];
}
function mime_multipart(string $body, string $boundary): array {
    $parts = []; $cur = null; $open = '--' . $boundary;
    foreach (preg_split('/\r?\n/', $body) as $line) {
        $t = rtrim($line);
        if ($t === $open . '--') { if ($cur !== null) $parts[] = implode("\r\n", $cur); $cur = null; break; }
        if ($t === $open) { if ($cur !== null) $parts[] = implode("\r\n", $cur); $cur = []; continue; }
        if ($cur !== null) $cur[] = $line;
    }
    if ($cur) $parts[] = implode("\r\n", $cur); // message cut off before its closing boundary
    return $parts;
}
function mime_ext(string $type): string {
    $map = ['image/png' => '.png', 'image/jpeg' => '.jpg', 'image/gif' => '.gif', 'application/pdf' => '.pdf', 'text/calendar' => '.ics', 'message/rfc822' => '.eml', 'text/plain' => '.txt', 'text/html' => '.html'];
    return $map[$type] ?? '';
}

// Readable text from an HTML email, for previews and search.
function html_to_text(string $html): string {
    $html = preg_replace('#<(script|style|head|title)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h[1-6]|table|blockquote)>#i', "\n", $html);
    $html = preg_replace('#<li\b[^>]*>#i', '• ', $html);
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = str_replace("\xC2\xA0", ' ', $t);
    $t = preg_replace("/[ \t]+/", ' ', $t);
    $t = preg_replace("/ *\n */", "\n", $t);
    return trim(preg_replace("/\n{3,}/", "\n\n", $t));
}
