<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Service;

class SeoController extends Controller
{
    public function sitemap()
    {
        $base = rtrim(config('app.frontend_url'), '/');
        $paths = ['/', '/about', '/doctor', '/services', '/articles', '/faqs', '/contact', '/appointment', '/privacy', '/terms', '/disclaimer'];
        foreach (Service::where('published', true)->pluck('slug') as $slug) {
            $paths[] = '/services/'.$slug;
        }
        foreach (ContentPage::where('published', true)->where('type', 'article')->pluck('slug') as $slug) {
            $paths[] = '/articles/'.$slug;
        }
        foreach (ContentPage::where('published', true)->where('type', 'page')->whereNotIn('slug', ['privacy', 'terms', 'disclaimer'])->pluck('slug') as $slug) {
            $paths[] = '/pages/'.$slug;
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($paths as $path) {
            $xml .= '<url><loc>'.htmlspecialchars($base.$path, ENT_XML1).'</loc></url>';
        }

        return response($xml.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }
}
