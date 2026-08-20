<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // すべての web レスポンスにセキュリティヘッダーを付与する。
        // Attach security headers to every web response.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // Railway 等の PaaS は TLS をロードバランサーで終端し、アプリへは HTTP で転送する。
        // PaaS platforms such as Railway terminate TLS at the load balancer and forward plain HTTP.
        // X-Forwarded-* を信頼しないと、HTTPS ページ内で http:// のアセット URL が生成され
        // ブラウザに混在コンテンツとしてブロックされてしまうため、プロキシを信頼する。
        // Without trusting X-Forwarded-*, Laravel would emit http:// asset URLs on an https://
        // page and browsers would block them as mixed content, so we trust the proxy.
        // ロードバランサーの IP は固定されないためワイルドカードで指定する。
        // The load balancer IP is not fixed, hence the wildcard.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
