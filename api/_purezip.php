<?php
/**
 * _purezip.php — pure-PHP ZIP writer + reader (ไม่ต้องการ ext-zip / ZipArchive)
 * รองรับ method 0 (STORE) และ method 8 (DEFLATE ผ่าน gzdeflate/gzinflate)
 */
class PureZip {
    private array $entries = [];

    public function addFromString(string $name, string $data): void {
        $this->entries[] = ['name' => $name, 'data' => $data];
    }

    public function addFile(string $path, string $name): bool {
        $data = @file_get_contents($path);
        if ($data === false) return false;
        $this->entries[] = ['name' => $name, 'data' => $data];
        return true;
    }

    public function bytes(): string {
        $local = '';
        $cd    = '';

        foreach ($this->entries as $e) {
            $loff    = strlen($local);
            $raw     = $e['data'];
            $crc     = crc32($raw);
            $rawLen  = strlen($raw);
            $dt      = self::dosTime(time());

            $comp = function_exists('gzdeflate') ? gzdeflate($raw, 6) : false;
            if ($comp !== false && strlen($comp) < $rawLen) {
                $method = 8; $blob = $comp;
            } else {
                $method = 0; $blob = $raw;
            }
            $compLen = strlen($blob);
            $nLen    = strlen($e['name']);

            $lh = pack('VvvvVVVVvv',
                0x04034b50, 20, 0, $method, $dt, $crc, $compLen, $rawLen, $nLen, 0);
            $local .= $lh . $e['name'] . $blob;

            $cd .= pack('VvvvvVVVVvvvvvVV',
                0x02014b50, 20, 20, 0, $method, $dt, $crc, $compLen, $rawLen,
                $nLen, 0, 0, 0, 0, 0, $loff) . $e['name'];
        }

        $cdOff = strlen($local);
        $cdSz  = strlen($cd);
        $n     = count($this->entries);
        $eocd  = pack('VvvvvVVv', 0x06054b50, 0, 0, $n, $n, $cdSz, $cdOff, 0);

        return $local . $cd . $eocd;
    }

    /** อ่าน entry จาก ZIP binary string ตามชื่อไฟล์ */
    public static function getFromName(string $zip, string $entryName): string|false {
        $len = strlen($zip);
        $eocdOff = false;
        for ($i = $len - 22; $i >= max(0, $len - 65580); $i--) {
            if (substr($zip, $i, 4) === "\x50\x4b\x05\x06") {
                $eocdOff = $i; break;
            }
        }
        if ($eocdOff === false) return false;

        $e = unpack('vtotal/VcdSz/VcdOff', substr($zip, $eocdOff + 10, 10));
        $pos = $e['cdOff'];

        for ($i = 0; $i < $e['total']; $i++) {
            if (substr($zip, $pos, 4) !== "\x50\x4b\x01\x02") break;
            $h = unpack('vmethod/Vdt/Vcrc/VcompLen/VrawLen/vnLen/vexLen/vcomLen/vdisk/vint/Vext/Vloff',
                substr($zip, $pos + 10, 36));
            $name = substr($zip, $pos + 46, $h['nLen']);
            $pos += 46 + $h['nLen'] + $h['exLen'] + $h['comLen'];

            if ($name !== $entryName) continue;

            $lh   = unpack('vnLen/vexLen', substr($zip, $h['loff'] + 26, 4));
            $dOff = $h['loff'] + 30 + $lh['nLen'] + $lh['exLen'];
            $blob = substr($zip, $dOff, $h['compLen']);

            if ($h['method'] === 0) return $blob;
            if ($h['method'] === 8) return function_exists('gzinflate') ? @gzinflate($blob) : false;
            return false;
        }
        return false;
    }

    private static function dosTime(int $ts): int {
        $d = getdate($ts);
        return (($d['year'] - 1980) << 25) | ($d['mon'] << 21) | ($d['mday'] << 16)
             | ($d['hours'] << 11) | ($d['minutes'] << 5) | ($d['seconds'] >> 1);
    }
}
