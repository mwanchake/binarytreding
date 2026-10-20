<?php

namespace App\Http\Controllers\Main;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\UserProvider;
use App\Providers\RouteServiceProvider;
use App\Services\MainHttp\SocialService;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class SocialController extends Controller
{
  public function redirect($provider)
  {
    $service = config('services.' . $provider);
    if (empty($service['client_id']) || empty($service['client_secret']) || empty($service['redirect'])) {
      return $this->signInError($provider);
    }

    try {
      return Socialite::driver($provider)->redirect();
    } catch (Throwable $exception) {
      Log::error('Social sign-in redirect failed.', [
        'provider' => $provider,
        'exception' => $exception,
      ]);

      return $this->signInError($provider);
    }
  }

  public function Callback($provider, SocialService $socialService)
  {
    try {
      return $socialService->callB($provider);
    } catch (Throwable $exception) {
      Log::error('Social sign-in callback failed.', [
        'provider' => $provider,
        'exception' => $exception,
      ]);

      return $this->signInError($provider);
    }
  }

  private function signInError($provider)
  {
    return redirect('/login')->withErrors([
      'social' => __('locale.social_error', ['provider' => ucfirst($provider)]),
    ]);
  }
}
