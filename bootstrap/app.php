<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Railway (y la mayoría de PaaS en contenedores) terminan el HTTPS en
        // su propio proxy de borde y reenvían la petición al contenedor por
        // HTTP simple. Sin confiar en ese proxy, asset()/url() no detectan
        // el esquema real y generan enlaces http:// que el navegador bloquea
        // como contenido mixto en una página https://. Confiar en '*' es
        // seguro aquí porque el contenedor no es alcanzable directamente
        // desde fuera de la red del proveedor, solo a través de su proxy.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
