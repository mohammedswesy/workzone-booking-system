<?php

use Illuminate\Support\Facades\File;

it('sends a strict CSP without unsafe-eval on HTML responses', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertHeader('X-Frame-Options', 'DENY');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');

    $csp = $response->headers->get('Content-Security-Policy')
        ?? $response->headers->get('Content-Security-Policy-Report-Only');

    expect($csp)->not->toBeEmpty();
    expect($csp)->not->toContain('unsafe-eval');
    expect($csp)->toContain("script-src 'self' 'nonce-");
    expect($csp)->toContain("style-src 'self' 'unsafe-inline'");
    expect($csp)->toContain("font-src 'self' data:");
    expect($csp)->toContain("img-src 'self' data: blob:");
    expect($csp)->toContain("connect-src 'self'");
    expect($csp)->toContain("frame-ancestors 'none'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->not->toContain('fonts.bunny.net');
});

it('uses report-only header when CSP_REPORT_ONLY is enabled', function () {
    config(['csp.report_only' => true]);

    $response = $this->get('/');

    $response->assertOk();
    expect($response->headers->get('Content-Security-Policy'))->toBeNull();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->not->toBeEmpty();
});

it('allows the Vite origin in CSP only when public/hot exists', function () {
    $hot = public_path('hot');
    $hadHot = File::exists($hot);
    $previous = $hadHot ? File::get($hot) : null;

    try {
        File::put($hot, "http://127.0.0.1:5173\n");
        config(['csp.vite_dev_origin' => 'http://127.0.0.1:5173']);

        $csp = $this->get('/')->headers->get('Content-Security-Policy');

        expect($csp)->toContain('http://127.0.0.1:5173');
        expect($csp)->toContain('ws://127.0.0.1:5173');
    } finally {
        if ($hadHot) {
            File::put($hot, $previous);
        } elseif (File::exists($hot)) {
            File::delete($hot);
        }
    }
});
