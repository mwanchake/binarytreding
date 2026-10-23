<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingsService;
use Illuminate\Http\Request;
use Jackiedo\DotenvEditor\Facades\DotenvEditor;

class SettingsController extends Controller
{
  public function getAllSettings(Request $request, SettingsService $settingsService)
  {
      return response()->json($settingsService->getAllSettingsServ($request));
  }

  public function save(Request $request, SettingsService $settingsService)
  {
      $request->validate([
          'data' => ['required', 'string', 'max:20000'],
          'reason' => ['required', 'string', 'min:10', 'max:1000'],
      ]);
      return response()->json($settingsService->saveServ($request));
  }
}
