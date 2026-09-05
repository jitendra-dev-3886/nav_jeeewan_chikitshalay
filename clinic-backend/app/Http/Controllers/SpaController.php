<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\Request;

class SpaController extends Controller
{
    public function asset(string $filename)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+\.(js|css)$/', $filename), 404);
        $file = base_path('../clinic-frontend/dist/assets/'.$filename);
        abort_unless(is_file($file), 404);

        return response()->file($file, ['Content-Type' => str_ends_with($filename, '.css') ? 'text/css' : 'application/javascript', 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    public function show(Request $request, string $path = '')
    {
        $url = '/'.ltrim($path, '/');
        $redirect = Redirect::where('from_path', $url)->where('active', true)->first();
        if ($redirect) {
            return redirect($redirect->to_path, 301);
        }
        $file = base_path('../clinic-frontend/dist/index.html');
        if (! is_file($file)) {
            return response()->json(['application' => 'Nav Jeevan Chikitsalay', 'message' => 'Build clinic-frontend to serve the website from Laravel.']);
        }
        $profile = Setting::getValue('clinic', []);
        $title = $profile['name'] ?? 'Nav Jeevan Chikitsalay';
        $description = 'Clinic information and appointment requests in Singarjot Ghat, Mahuadhani.';
        $body = '';
        $status = 200;
        $escape = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $private = preg_match('#^/(admin|login|manage)(/|$)#', $url);
        if (! $private) {
            $page = in_array($url, ['/privacy', '/terms', '/disclaimer']) ? ContentPage::where('slug', trim($url, '/'))->where('published', true)->first() : null;
            if (preg_match('#^/(articles|pages)/([a-zA-Z0-9_-]+)$#', $url, $match)) {
                $page = ContentPage::where('slug', $match[2])->where('published', true)->first();
            }
            $service = null;
            if (preg_match('#^/services/([a-zA-Z0-9_-]+)$#', $url, $match)) {
                $service = Service::where('slug', $match[1])->where('published', true)->first();
            }
            if ($page) {
                $title = $page->seo_title ?: $page->title;
                $description = $page->seo_description ?: ($page->excerpt ?: $page->title);
                $body = '<h1>'.$escape($page->title).'</h1><p>'.nl2br($escape($page->body)).'</p>';
            } elseif ($service) {
                $title = $service->seo_title ?: $service->name;
                $description = $service->seo_description ?: $service->summary;
                $body = '<h1>'.$escape($service->name).'</h1><p>'.$escape($service->description).'</p><h2>Before your visit</h2><p>'.$escape($service->preparation).'</p>';
            } elseif (in_array($url, ['/', '/about', '/doctor', '/contact', '/services', '/gallery', '/articles', '/faqs', '/appointment'])) {
                $titles = ['/' => 'Thoughtful care, close to home', '/about' => 'About the clinic & doctor', '/doctor' => 'Meet your doctor', '/contact' => 'Contact & directions', '/gallery' => 'Clinic gallery', '/services' => 'Our services', '/articles' => 'Health articles', '/faqs' => 'Frequently asked questions', '/appointment' => 'Book an appointment'];
                $title = $titles[$url];
                $body = '<h1>'.$escape($profile['name'] ?? 'Nav Jeevan Chikitsalay').'</h1><p>'.$escape($profile['doctor'] ?? '').' · '.$escape($profile['qualifications'] ?? '').'</p><p>'.$escape($profile['designation'] ?? '').'</p><p>'.$escape($profile['address'] ?? '').'</p>';
                if (in_array($url, ['/', '/services'])) {
                    foreach (Service::where('published', true)->get() as $s) {
                        $body .= '<h2><a href="/services/'.$escape($s->slug).'">'.$escape($s->name).'</a></h2><p>'.$escape($s->summary).'</p>';
                    }
                }
                if ($url === '/articles') {
                    foreach (ContentPage::where('published', true)->where('type', 'article')->get() as $p) {
                        $body .= '<h2><a href="/articles/'.$escape($p->slug).'">'.$escape($p->title).'</a></h2><p>'.$escape($p->excerpt).'</p>';
                    }
                }
                if ($url === '/faqs') {
                    foreach (ContentPage::where('published', true)->where('type', 'faq')->get() as $p) {
                        $body .= '<h2>'.$escape($p->title).'</h2><p>'.$escape($p->body).'</p>';
                    }
                }
            } else {
                $title = 'Page not found';
                $body = '<h1>Page not found</h1>';
                $status = 404;
            }
        }
        $html = file_get_contents($file);
        $html = preg_replace('#<title>.*?</title>#s', '<title>'.$escape($title).' | Nav Jeevan Chikitsalay</title>', $html);
        $html = preg_replace('#<meta name="description"[^>]*>#', '<meta name="description" content="'.$escape($description).'" />', $html);
        $extra = '<link rel="canonical" href="'.$escape(rtrim(config('app.frontend_url'), '/').$url).'" />';
        if ($private || $status === 404) {
            $extra .= '<meta name="robots" content="noindex, nofollow" />';
        }
        $html = str_replace('</head>', $extra.'</head>', $html);
        if (! $private) {
            $html = str_replace('<div id="root"></div>','<div id="root"><main class="container section">'.$body.'<p><a href="/appointment">Book an appointment</a> · <a href="/contact">Contact the clinic</a></p><p>This website is not an emergency service.</p></main></div>',$html);
        }

        return response($html,$status,['Content-Type' => 'text/html; charset=UTF-8', 'Cache-Control' => 'no-cache']);
    }
}
