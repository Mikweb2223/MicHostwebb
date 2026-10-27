<?php
declare(strict_types=1);

function base32Encode(string $bytes): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bytes) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($bits, 5) as $chunk) {
        $result .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    }
    return $result;
}

function base32Decode(string $value): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
    $bits = '';
    foreach (str_split($value) as $character) {
        $position = strpos($alphabet, $character);
        if ($position === false) {
            return '';
        }
        $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
    }

    $result = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $result .= chr(bindec($chunk));
        }
    }
    return $result;
}

function generateTotpSecret(): string
{
    return base32Encode(random_bytes(20));
}

function verifyTotpCode(string $secret, string $code, int $time = 0): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }

    $key = base32Decode($secret);
    if ($key === '') {
        return false;
    }

    $counter = (int)floor(($time ?: time()) / 30);
    for ($offset = -1; $offset <= 1; $offset++) {
        $binaryCounter = pack('N*', 0) . pack('N*', $counter + $offset);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $index = ord($hash[19]) & 0x0f;
        $number = ((ord($hash[$index]) & 0x7f) << 24)
            | ((ord($hash[$index + 1]) & 0xff) << 16)
            | ((ord($hash[$index + 2]) & 0xff) << 8)
            | (ord($hash[$index + 3]) & 0xff);
        if (hash_equals(str_pad((string)($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) {
            return true;
        }
    }
    return false;
}
