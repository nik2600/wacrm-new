<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use App\Support\Audit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks admin-panel access from IPs not in security.ip_allowlist_cidrs.
 *
 * Only enforces:
 *   - when security.ip_allowlist_enabled is true
 *   - on /admin/* routes (this middleware is wired to the admin group)
 *
 * On block: writes audit row 'admin.access.blocked_by_ip' (failure) and
 * returns 403 with a clear message + the violating IP so admin can
 * troubleshoot without log-diving.
 */
class IPAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) SystemSetting::get('security.ip_allowlist_enabled', false)) {
            return $next($request);
        }

        $cidrs = SystemSetting::get('security.ip_allowlist_cidrs', []);
        $cidrs = is_array($cidrs) ? array_filter($cidrs) : [];
        if (empty($cidrs)) {
            // Allowlist on but empty → fail open so admin doesn't lock
            // themselves out from an empty list.
            return $next($request);
        }

        $ip = $request->ip();
        if ($this->ipInAnyCidr($ip, $cidrs)) {
            return $next($request);
        }

        Audit::log('admin.access.blocked_by_ip', [
            'layer'  => 'platform',
            'result' => 'failure',
            'meta'   => ['ip' => $ip, 'cidrs_count' => count($cidrs), 'path' => $request->path()],
        ]);

        return response()->view('errors.ip-blocked', [
            'ip' => $ip,
        ], 403);
    }

    private function ipInAnyCidr(string $ip, array $cidrs): bool
    {
        foreach ($cidrs as $cidr) {
            if ($this->ipInCidr($ip, trim($cidr))) return true;
        }
        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        if ($cidr === '') return false;

        // Bare IP without /mask = exact match.
        if (! str_contains($cidr, '/')) {
            return $ip === $cidr;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $mask = (int) $mask;

        $ipPacked     = @inet_pton($ip);
        $subnetPacked = @inet_pton($subnet);
        if ($ipPacked === false || $subnetPacked === false) return false;
        if (strlen($ipPacked) !== strlen($subnetPacked)) return false; // v4 vs v6

        $bytes = $mask >> 3;
        $bits  = $mask & 7;

        // Compare full bytes.
        if ($bytes > 0 && substr($ipPacked, 0, $bytes) !== substr($subnetPacked, 0, $bytes)) {
            return false;
        }
        // Compare the remaining bits on the boundary byte.
        if ($bits > 0 && $bytes < strlen($ipPacked)) {
            $maskByte = ~((1 << (8 - $bits)) - 1) & 0xFF;
            if ((ord($ipPacked[$bytes]) & $maskByte) !== (ord($subnetPacked[$bytes]) & $maskByte)) {
                return false;
            }
        }
        return true;
    }
}
