<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);
        Vite::useCspNonce($nonce);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        $header = config('csp.report_only')
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($header, $this->policy($nonce));

        if ($request->secure() || config('app.env') === 'production') {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $directives = config('csp.directives', []);
        $script = "script-src 'self' 'nonce-{$nonce}'";

        $viteHot = public_path('hot');
        if (File::exists($viteHot)) {
            $origin = rtrim((string) config('csp.vite_dev_origin', 'http://127.0.0.1:5173'), '/');
            $wsOrigin = Str::startsWith($origin, 'https:')
                ? 'wss://'.Str::after($origin, 'https://')
                : 'ws://'.Str::after($origin, 'http://');

            $script .= " {$origin}";
            $directives = array_map(function (string $line) use ($origin, $wsOrigin) {
                if (str_starts_with($line, 'style-src ')) {
                    return $line." {$origin}";
                }
                if (str_starts_with($line, 'font-src ')) {
                    return $line." {$origin}";
                }
                if (str_starts_with($line, 'img-src ')) {
                    return $line." {$origin}";
                }
                if (str_starts_with($line, 'connect-src ')) {
                    return $line." {$origin} {$wsOrigin}";
                }

                return $line;
            }, $directives);
        }

        $out = [];
        foreach ($directives as $line) {
            if (str_starts_with($line, 'script-src ')) {
                $out[] = $script;

                continue;
            }
            $out[] = $line;
        }

        if (! collect($out)->contains(fn ($l) => str_starts_with($l, 'script-src '))) {
            array_unshift($out, $script);
        }

        return implode('; ', $out);
    }
}
