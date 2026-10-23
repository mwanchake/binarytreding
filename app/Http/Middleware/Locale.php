<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Locale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->cookie('currentLanguage', 'en');
        $locales = ['en' => 'en', 'es' => 'es', 'ru' => '_ru'];
        App::setLocale($locales[$locale] ?? 'en');

        return $next($request);
    }
}
