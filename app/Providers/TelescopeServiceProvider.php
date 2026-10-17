<?php

namespace App\Providers;

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        Telescope::filter(function (IncomingEntry $entry): bool {
            if (! $this->app->environment('local')) {
                return false;
            }
            // Diagnóstico de duração e SQL, sem capturar credenciais ou dados pessoais.
            if ($entry->type === 'request') {
                $entry->content = array_intersect_key($entry->content, array_flip([
                    'method', 'controller_action', 'middleware', 'response_status', 'duration', 'memory',
                ])) + ['uri' => '/'.request()->path()];
                $entry->user = null;

                return true;
            }
            if ($entry->type === 'query') {
                $entry->content['sql'] = preg_replace("/'(?:''|[^'])*'/s", "'***'", $entry->content['sql']);
                $entry->content['bindings'] = [];
                $entry->user = null;

                return true;
            }

            return false;
        });
    }

    protected function authorization()
    {
        Telescope::auth(fn ($request) => app()->environment('local')
            && in_array($request->getHost(), ['localhost', '127.0.0.1', '::1'], true));
    }
}
