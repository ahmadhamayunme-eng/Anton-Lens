<?php
namespace App\Security;

class ProxyGuard {
    public static function isPrivateIp(string $ip): bool {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    public static function validateUrl(string $url, array $allowedHosts): bool {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) return false;
        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost'], true) || filter_var($host, FILTER_VALIDATE_IP)) return false;
        if (!in_array($host, $allowedHosts, true)) return false;
        $ips = gethostbynamel($host);
        if (!$ips) return false;
        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) return false;
        }
        return true;
    }
}
