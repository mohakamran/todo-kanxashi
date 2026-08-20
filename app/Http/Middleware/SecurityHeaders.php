<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// レスポンスに基本的なセキュリティヘッダーを付与するミドルウェア。
// Middleware that attaches basic security headers to every response.
// クリックジャッキングや MIME スニッフィング等の一般的な攻撃面を軽減する。
// Mitigates common attack surfaces such as clickjacking and MIME sniffing.
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // フレーム埋め込みを同一オリジンに限定（クリックジャッキング対策）。
        // Restrict framing to the same origin (clickjacking protection).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // ブラウザによる MIME タイプ推測を無効化。
        // Disable browser MIME-type sniffing.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // リファラの送出を最小限にする。
        // Minimize referrer leakage.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 不要なブラウザ機能を明示的に無効化。
        // Explicitly disable browser features the app does not use.
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        return $response;
    }
}
