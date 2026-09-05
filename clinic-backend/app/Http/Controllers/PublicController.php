<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\AvailabilityRule;
use App\Models\Banner;
use App\Models\ContentPage;
use App\Models\Enquiry;
use App\Models\Media;
use App\Models\Redirect;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    public function clinic()
    {
        return response()->json(['branding' => Setting::getValue('branding', ['logo_url' => '/brand-logo.jpg', 'custom' => false]), 'profile' => Setting::getValue('clinic'), 'booking' => Setting::getValue('booking'), 'hours' => AvailabilityRule::where('active', true)->orderBy('weekday')->orderBy('start_time')->get(), 'testimonials' => Testimonial::where('published', true)->where('consent', true)->orderBy('sort_order')->get(), 'media' => Media::where('published', true)->get(), 'banners' => Banner::where('published', true)->orderBy('id')->get(), 'redirects' => Redirect::where('active', true)->get(['from_path', 'to_path'])]);
    }

    public function services()
    {
        return Service::where('published', true)->get();
    }

    public function content(Request $r)
    {
        return ContentPage::where('published', true)->when($r->query('type'), fn ($q, $type) => $q->where('type', $type))->get();
    }

    public function availability(Request $r, BookingService $booking)
    {
        $data = $r->validate(['date' => 'required|date_format:Y-m-d', 'service_id' => 'required|integer|exists:services,id']);

        return ['slots' => $booking->slots($data['date'], (int) $data['service_id']), 'timezone' => $booking->timezone()];
    }

    public function book(BookingRequest $r, BookingService $booking)
    {
        return response()->json($booking->create($r->validated()), 201);
    }

    public function show(string $token, BookingService $booking)
    {
        return $booking->summary($booking->byToken($token));
    }

    public function change(Request $r, string $token, BookingService $booking)
    {
        $data = $r->validate(['status' => 'required_without:starts_at|in:cancelled', 'starts_at' => 'required_without:status|date', 'change_reason' => 'nullable|string|max:300']);
        abort_if(isset($data['status'],$data['starts_at']), 422, 'Choose cancellation or rescheduling.');

        return $booking->summary($booking->change($booking->byToken($token), $data, true));
    }

    public function enquiry(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|min:2|max:100', 'phone' => ['required', 'regex:/^[6-9][0-9]{9}$/'], 'message' => 'required|string|min:10|max:1500', 'consent' => 'required|accepted', 'website' => 'nullable|max:0']);
        Enquiry::create(collect($data)->only(['name', 'phone', 'message'])->all());

        return response()->json(['message' => 'Thank you. Reception will review your enquiry.'],201);
    }
}
