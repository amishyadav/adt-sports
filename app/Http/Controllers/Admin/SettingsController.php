<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::allAsArray();
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // Social URLs are validated as http/https URLs — the 'url' rule rejects
        // javascript:/data: payloads that would otherwise land in a rendered href.
        $data = $request->validate([
            'site_name'         => 'nullable|string|max:120',
            'site_tagline'      => 'nullable|string|max:160',
            'site_email'        => 'nullable|email|max:255',
            'site_phone'        => 'nullable|string|max:40',
            'site_whatsapp'     => 'nullable|string|max:40',
            'site_address'      => 'nullable|string|max:300',
            'site_description'  => 'nullable|string|max:300',
            'breaking_ticker'   => 'nullable|string|max:2000',
            'footer_tagline'    => 'nullable|string|max:300',
            'articles_per_page' => 'nullable|integer|min:5|max:50',
            'facebook_url'      => 'nullable|url:http,https|max:255',
            'instagram_url'     => 'nullable|url:http,https|max:255',
            'youtube_url'       => 'nullable|url:http,https|max:255',
            'twitter_url'       => 'nullable|url:http,https|max:255',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, (string) ($value ?? ''));
        }

        ActivityLog::record('settings.updated', null, 'Updated site settings');

        return back()->with('success', 'Settings saved successfully.');
    }
}
