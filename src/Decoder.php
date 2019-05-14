<?php
declare(strict_types=1);

namespace Firehed\CBOR;

use Exception;
use OutOfBoundsException;

class Decoder
{
    const MT_UINT = 0;
    const MT_NINT = 1;
    const MT_BYTESTRING = 2;
    const MT_TEXT = 3;
    const MT_ARRAY = 4;
    const MT_MAP = 5;
    const MT_TAG = 6;
    const MT_SIMPLE_AND_FLOAT = 7;

    private $cbor = '';
    private $i = 0;

    public function decode(string $cbor)
    {
        $this->cbor = $cbor;
        $this->i = 0;
        return $this->decodeItem();
    }

    public function decodeFromByteArray(array $bytes)
    {
        return $this->decode(implode('', array_map('chr', $bytes)));
    }

    public function getNumberOfBytesRead(): int
    {
        return $this->i;
    }

    /**
     * Reads out of the CBOR string and returns the next decoded value
     * @return mixed
     */
    private function decodeItem()
    {
        $item = ord($this->read(1));
        if ($item === 0xff) {
            throw new Stop();
        }
        $majorType = ($item & 0b11100000) >> 5;
        $addtlInfo = ($item & 0b00011111);
        switch ($majorType) {
            case self::MT_UINT:
                return $this->decodeUnsignedInteger($addtlInfo);
            case self::MT_NINT:
                return $this->decodeNegativeInteger($addtlInfo);
            case self::MT_BYTESTRING:
                return $this->decodeBinaryString($addtlInfo);
            case self::MT_TEXT:
                return $this->decodeText($addtlInfo);
            case self::MT_ARRAY:
                return $this->decodeArray($addtlInfo);
            case self::MT_MAP:
                return $this->decodeMap($addtlInfo);
            case self::MT_TAG:
                return $this->decodeTag($addtlInfo);
            case self::MT_SIMPLE_AND_FLOAT:
                return $this->decodeSimple($addtlInfo);
            default:
                throw new Exception('Invalid major type');
        }
    }

    private function decodeUnsignedInteger(int $info): int
    {
        if ($info <= 23) {
            return $info;
        }
        if ($info === 24) { // 8-bit int
            $data = ord($this->read(1));
            return $data;
        } elseif ($info === 25) { // 16-bit int
            $data = unpack('n', $this->read(2))[1];
            return $data;
        } elseif ($info === 26) { // 32-bit int
            $data = unpack('N', $this->read(4))[1];
            return $data;
        } elseif ($info === 27) { // 64-bit int
            // return $this->decodeBigint($this->read(8));
            $bytes = $this->read(8);
            if (ord($bytes[0]) & 0xF0) {
                throw new \OverflowException();
            }
            // $data = unpack('J', $this->read(8))[1];
            $data = unpack('J', $bytes)[1];

            return $data;
        } else {
            throw new OutOfBoundsException((string)$info);
        }
    }

    private function decodeNegativeInteger(int $addtlInfo): int
    {
        try {
            $uint = $this->decodeUnsignedInteger($addtlInfo);
            $negative = -1 - $uint;
            return $negative;
        } catch (\OverflowException $e) {
            throw new \UnderflowException();
        }
    }

    private function decodeBinaryString(int $addtlInfo): string
    {
        if ($addtlInfo === 31) {
            $ret = '';
            while (true) {
                try {
                    $ret .= $this->decodeItem();
                } catch (Stop $e) {
                    return $ret;
                }
            }
        }
        $length = $this->decodeUnsignedInteger($addtlInfo);
        $str = $this->read($length);
        return $str;
    }

    private function decodeText(int $addtlInfo): string
    {
        if ($addtlInfo === 31) {
            $ret = '';
            while (true) {
                try {
                    $ret .= $this->decodeItem();
                } catch (Stop $e) {
                    return $ret;
                }
            }
        }
        $length = $this->decodeUnsignedInteger($addtlInfo);
        $str = $this->read($length);
        return $str;
    }

    private function decodeArray(int $addtlInfo): array
    {
        $ret = [];
        if ($addtlInfo === 31) {
            while (true) {
                try {
                    $ret[] = $this->decodeItem();
                } catch (Stop $e) {
                    return $ret;
                }
            }
        }
        $numItems = $this->decodeUnsignedInteger($addtlInfo);
        for ($i = 0; $i < $numItems; $i++) {
            $ret[] = $this->decodeItem();
        }
        return $ret;
    }

    private function decodeMap(int $addtlInfo): array
    {
        $ret = [];
        if ($addtlInfo === 31) {
            while (true) {
                try {
                    $key = $this->decodeItem();
                    $ret[$key] = $this->decodeItem();
                } catch (Stop $e) {
                    return $ret;
                }
            }
        }
        $numItems = $this->decodeUnsignedInteger($addtlInfo);
        for ($i = 0; $i < $numItems; $i++) {
            $key = $this->decodeItem();
            $ret[$key] = $this->decodeItem();
        }
        return $ret;
    }

    /**
     * @see 2.4
     */
    private function decodeTag(int $addtlInfo)
    {
        $tagId = $this->decodeUnsignedInteger($addtlInfo);
        $tag = $this->decodeItem();
        switch ($tagId) {
            case 2: // postive bignum
                return $this->decodeBigint($tag);
            case 3: // negative bignum
                $positive = $this->decodeBigint($tag);
                return bcsub('-1', $positive);
        }
        // var_dump("tag #$tagId");
        // var_dump($tag);
        throw new OutOfBoundsException(bin2hex($tag));
    }

    /**
     * @return string The bigint value as a string (for bcmath)
     */
    private function decodeBigint(string $bytes): string
    {
        $out = '0';
        while (strlen($bytes) > 0) {
            $out = bcmul($out, '256');
            $leadingByte = ord(substr($bytes, 0, 1));
            $out = bcadd($out, (string)$leadingByte);
            $bytes = substr($bytes, 1);
        }
        return $out;
    }

    private function read(int $numBytes)
    {
        $data = substr($this->cbor, $this->i, $numBytes);
        $this->i += $numBytes;
        return $data;
    }

    /**
     * @see 2.3
     */
    private function decodeSimple(int $info)
    {
        switch ($info) {
            case 20:
                return false;
            case 21:
                return true;
            case 22:
                return null;
            case 23:
                // undefined
                return null; // PHP does not have separate null and undefined
            case 24:
                $next = ord($this->read(1));
                throw new UnassignedValueException($next);
            case 25:
                return $this->readHalfPrecisionFloat();
            case 26:
                return $this->readSinglePrecisionFloat();
            case 27:
                return $this->readDoublePrecisionFloat();
            case 31:
                // Note: this should be unreachable
                throw new Stop();
            default:
                throw new UnassignedValueException($info);

        }
    }

    // Adapted from RFC7049 Appendix D
    private function readHalfPrecisionFloat(): float
    {
        $bytes = $this->read(2);
        $half = (ord($bytes[0]) << 8) + ord($bytes[1]);
        $exp = ($half >> 10) & 0x1f;
        $mant = $half & 0x3ff;

        $val = 0;
        if ($exp === 0) {
            $val = self::ldexp($mant, -24);
        } elseif ($exp !== 31) {
            $val = self::ldexp($mant + 1024, $exp - 25);
        } elseif ($mant === 0) {
            $val = \INF;
        } else {
            $val = \NAN;
        }

        return ($half & 0x8000) ? -$val : $val;
    }

    // Adapted from C
    private static function ldexp(float $x, int $exponent): float
    {
        return $x * pow(2, $exponent);
    }

    private function readSinglePrecisionFloat()
    {
        $bytes = $this->read(4);
        $data = unpack('G', $bytes);
        return $data[1];
    }



    private function readDoublePrecisionFloat()
    {
        $bytes = $this->read(8);
        $data = unpack('E', $bytes);
        return $data[1];
    }
}
