<?php

namespace App\Http\Controllers;

use App\Services\Documents\SampleGallery;
use Illuminate\View\View;

/** Public front page with sample (fictional) ID cards and certificates. */
class LandingController extends Controller
{
    public function __invoke(SampleGallery $gallery): View
    {
        return view('landing', ['samples' => $gallery->all()]);
    }
}
