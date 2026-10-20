<?php
// Reads MaxMind-format (.mmdb) IP databases, such as DB-IP's free "IP to City Lite" or MaxMind GeoLite2,
// to turn a visitor's IP address into a country, state and city. The IP itself is never stored.
// It reads only the parts of the file it needs, so a 100 MB+ city database is fine on shared hosting.
class Mmdb {
    private $f;
    private int $nodes; private int $rec; private int $ipv; private int $tree; private int $data;
    private int $v4start = 0;
    public array $meta = [];

    public function __construct(string $path) {
        $this->f = @fopen($path, 'rb');
        if (!$this->f) throw new RuntimeException('Cannot open ' . basename($path));
        $size = fstat($this->f)['size'];
        $tail = min($size, 131072);
        fseek($this->f, $size - $tail);
        $buf = fread($this->f, $tail);
        $pos = strrpos($buf, "\xAB\xCD\xEFMaxMind.com");
        if ($pos === false) throw new RuntimeException(basename($path) . ' is not an .mmdb database');
        $metaStart = $size - $tail + $pos + 14;
        [$this->meta] = $this->decode($metaStart, $metaStart);
        $this->nodes = (int)$this->meta['node_count'];
        $this->rec = (int)$this->meta['record_size'];
        $this->ipv = (int)$this->meta['ip_version'];
        $this->tree = (int)(($this->rec * 2 / 8) * $this->nodes);
        $this->data = $this->tree + 16;
        if (!in_array($this->rec, [24, 28, 32], true)) throw new RuntimeException('Unsupported record size');
        if ($this->ipv === 6) { // IPv4 addresses live under ::/96 in IPv6 databases
            $n = 0;
            for ($i = 0; $i < 96 && $n < $this->nodes; $i++) $n = $this->record($n, 0);
            $this->v4start = $n;
        }
    }

    public function get(string $ip): ?array {
        $bin = @inet_pton($ip);
        if ($bin === false) return null;
        $bits = strlen($bin) * 8;
        if ($bits === 128 && $this->ipv === 4) return null;
        $node = $bits === 32 && $this->ipv === 6 ? $this->v4start : 0;
        for ($i = 0; $i < $bits && $node < $this->nodes; $i++) {
            $bit = (ord($bin[$i >> 3]) >> (7 - ($i & 7))) & 1;
            $node = $this->record($node, $bit);
        }
        if ($node <= $this->nodes) return null; // no data for this address
        $off = $this->tree + ($node - $this->nodes);
        [$val] = $this->decode($off, $this->data);
        return is_array($val) ? $val : null;
    }

    private function bytes(int $off, int $n): string { fseek($this->f, $off); return $n > 0 ? (string)fread($this->f, $n) : ''; }
    private function record(int $node, int $bit): int {
        $sz = $this->rec * 2 / 8;
        $b = $this->bytes($node * $sz, (int)$sz);
        if ($this->rec === 24) return unpack('N', "\0" . substr($b, $bit * 3, 3))[1];
        if ($this->rec === 32) return unpack('N', substr($b, $bit * 4, 4))[1];
        // 28-bit: the middle byte holds the top 4 bits of both records
        $mid = ord($b[3]);
        return $bit === 0 ? ((($mid & 0xF0) << 20) | unpack('N', "\0" . substr($b, 0, 3))[1]) : ((($mid & 0x0F) << 24) | unpack('N', "\0" . substr($b, 4, 3))[1]);
    }
    private function uint(string $b): int { $v = 0; for ($i = 0, $n = strlen($b); $i < $n; $i++) $v = ($v << 8) | ord($b[$i]); return $v; }

    // Returns [value, next offset]. $base is where pointers count from.
    private function decode(int $off, int $base, int $depth = 0): array {
        if ($depth > 64) throw new RuntimeException('Database is corrupt');
        $ctrl = ord($this->bytes($off, 1)); $off++;
        $type = $ctrl >> 5;
        if ($type === 1) { // pointer
            $ss = ($ctrl >> 3) & 3; $v = $ctrl & 7;
            $b = $this->bytes($off, $ss + 1); $off += $ss + 1;
            $p = [($v << 8) | ord($b[0]), (($v << 16) | $this->uint($b)) + 2048, (($v << 24) | $this->uint($b)) + 526336, $this->uint($b)][$ss];
            [$val] = $this->decode($base + $p, $base, $depth + 1);
            return [$val, $off];
        }
        if ($type === 0) { $type = 7 + ord($this->bytes($off, 1)); $off++; }
        $size = $ctrl & 0x1f;
        if ($size >= 29) {
            $n = $size - 28; $b = $this->bytes($off, $n); $off += $n;
            $size = [29 => 29, 30 => 285, 31 => 65821][$size] + $this->uint($b);
        }
        switch ($type) {
            case 2: return [$this->bytes($off, $size), $off + $size];
            case 3: return [unpack('E', $this->bytes($off, 8))[1], $off + 8];
            case 4: return [$this->bytes($off, $size), $off + $size];
            case 5: case 6: case 9: case 10: return [$this->uint($this->bytes($off, $size)), $off + $size];
            case 8: $v = $this->uint($this->bytes($off, $size)); if ($size === 4 && $v >= 0x80000000) $v -= 0x100000000; return [$v, $off + $size];
            case 14: return [$size !== 0, $off];
            case 15: return [unpack('G', $this->bytes($off, 4))[1], $off + 4];
            case 7: $m = []; for ($i = 0; $i < $size; $i++) { [$k, $off] = $this->decode($off, $base, $depth + 1); [$v, $off] = $this->decode($off, $base, $depth + 1); $m[$k] = $v; } return [$m, $off];
            case 11: $a = []; for ($i = 0; $i < $size; $i++) { [$a[], $off] = $this->decode($off, $base, $depth + 1); } return [$a, $off];
        }
        return [null, $off];
    }
}
