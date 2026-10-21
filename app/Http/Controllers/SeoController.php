<?php

namespace App\Http\Controllers;

use App\Services\SeoService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(SeoService $seo): Response
    {
        return response()
            ->view('seo.sitemap', ['urls' => $seo->sitemapUrls()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(SeoService $seo): Response
    {
        return response()
            ->view('seo.robots', ['sitemapUrl' => url('/sitemap.xml')])
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
