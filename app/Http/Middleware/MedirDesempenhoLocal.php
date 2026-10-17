<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MedirDesempenhoLocal
{
    public function handle(Request $request, Closure $next)
    {
        if (! app()->environment('local')) {
            return $next($request);
        }
        $inicio = microtime(true);
        $sql = 0;
        DB::listen(function (QueryExecuted $evento) use (&$sql): void {
            $sql += $evento->time;
        });
        $resposta = $next($request);
        $boot = defined('LARAVEL_START') ? ($inicio - LARAVEL_START) * 1000 : 0;
        $resposta->headers->set('Server-Timing', sprintf('bootstrap;dur=%.2f, application;dur=%.2f, sql;dur=%.2f', $boot, (microtime(true) - $inicio) * 1000, $sql));

        return $resposta;
    }
}
