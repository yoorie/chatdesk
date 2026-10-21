<?php

namespace App\Services;

class TwoFactorTotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $secret;
    }

    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D+/', '', $code);
        if (strlen((string) $code) !== 6) {
            return false;
        }

        $timeSlice = (int) floor(time() / 30);
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->code($secret, $timeSlice + $offset), (string) $code)) {
                return true;
            }
        }

        return false;
    }

    public function otpauthUrl(string $issuer, string $email, string $secret): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$email)
            .'?secret='.$secret
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }

    private function code(string $secret, int $timeSlice): string
    {
        $key = $this->base32Decode($secret);
        $time = pack('N*', 0).pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $buffer = 0;
        $bitsLeft = 0;
        $result = '';

        foreach (str_split($secret) as $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $value;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
