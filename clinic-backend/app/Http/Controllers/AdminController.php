<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\AvailabilityRule;
use App\Models\Banner;
use App\Models\ContentPage;
use App\Models\Enquiry;
use App\Models\Media;
use App\Models\NotificationAttempt;
use App\Models\Patient;
use App\Models\Redirect;
use App\Models\ScheduleBlock;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const RESOURCES = ['services' => Service::class, 'availability' => AvailabilityRule::class, 'closures' => ScheduleBlock::class, 'content' => ContentPage::class, 'enquiries' => Enquiry::class, 'testimonials' => Testimonial::class, 'users' => User::class, 'banners' => Banner::class, 'redirects' => Redirect::class];

    private function appointmentsQuery(Request $r)
    {
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'status' => ['nullable', Rule::in(BookingService::STATUSES)], 'service_id' => 'nullable|integer', 'source' => 'nullable|in:website,phone,walk_in', 'search' => 'nullable|string|max:100']);

        return Appointment::with('patient', 'service')->when($r->query('from'), fn ($q, $v) => $q->where('starts_at', '>=', CarbonImmutable::parse($v, 'Asia/Kolkata')->utc()))->when($r->query('to'), fn ($q, $v) => $q->where('starts_at', '<', CarbonImmutable::parse($v, 'Asia/Kolkata')->addDay()->utc()))->when($r->query('status'), fn ($q, $v) => $q->where('status', $v))->when($r->query('service_id'), fn ($q, $v) => $q->where('service_id', $v))->when($r->query('source'), fn ($q, $v) => $q->where('source', $v))->when($r->query('search'), fn ($q, $v) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$v.'%')->orWhereHas('patient', fn ($p) => $p->where('name', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%'))));
    }

    public function appointments(Request $r)
    {
        return $this->appointmentsQuery($r)->orderBy('starts_at')->paginate(25);
    }

    public function showAppointment(Appointment $appointment)
    {
        return $appointment->load('patient', 'service', 'events');
    }

    public function book(BookingRequest $r, BookingService $b)
    {
        return response()->json($b->create($r->validated(), true), 201);
    }

    public function change(Request $r, Appointment $appointment, BookingService $b)
    {
        $data = $r->validate(['status' => ['sometimes', Rule::in(BookingService::STATUSES)], 'starts_at' => 'sometimes|date', 'internal_notes' => 'nullable|string|max:2000', 'change_reason' => 'nullable|string|max:300']);
        abort_if(isset($data['status'],$data['starts_at']), 422, 'Reschedule separately from changing status.');

        return $b->change($appointment, $data);
    }

    public function dashboard()
    {
        $day = CarbonImmutable::now('Asia/Kolkata')->startOfDay();
        $a = Appointment::where('starts_at', '>=', $day->utc())->where('starts_at', '<', $day->addDay()->utc())->with('patient', 'service')->orderBy('starts_at')->get();

        return ['today' => $day->toDateString(), 'counts' => $a->countBy('status'), 'total' => $a->count(), 'appointments' => $a, 'new_enquiries' => Enquiry::where('status', 'new')->count()];
    }

    public function report(Request $r)
    {
        $a = $this->appointmentsQuery($r)->get();

        return ['total' => $a->count(), 'statuses' => $a->countBy('status'), 'sources' => $a->countBy('source'), 'services' => $a->countBy(fn ($a) => $a->service->name), 'days' => $a->countBy(fn ($a) => $a->starts_at->setTimezone('Asia/Kolkata')->toDateString()), 'hours' => $a->countBy(fn ($a) => $a->starts_at->setTimezone('Asia/Kolkata')->format('H:00')), 'enquiries' => Enquiry::selectRaw('status, count(*) as total')->groupBy('status')->get()];
    }

    public function export(Request $r)
    {
        $q = $this->appointmentsQuery($r)->orderBy('starts_at');
        AuditLog::record('appointments.exported', 'appointment', null, collect($r->query())->only(['from', 'to', 'status', 'service_id', 'source'])->all());

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Patient', 'Phone', 'Service', 'Time (IST)', 'Status', 'Source']);
            foreach ($q->cursor() as $a) {
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@\-\t\r\n]/', (string) $v) ? "'".$v : $v, [$a->reference, $a->patient->name, $a->patient->phone, $a->service->name, $a->starts_at->setTimezone('Asia/Kolkata')->format('Y-m-d H:i'), $a->status, $a->source]));
            }
            fclose($out);
        }, 'appointments.csv', ['Content-Type' => 'text/csv', 'Cache-Control' => 'no-store']);
    }

    private function resource(Request $r, string $resource): string
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);
        abort_unless(in_array($r->user()->role, $resource === 'enquiries' ? ['admin', 'receptionist'] : ['admin']), 403);

        return self::RESOURCES[$resource];
    }

    public function index(Request $r, string $resource)
    {
        $model = $this->resource($r, $resource);

        return $model::orderByDesc('id')->get();
    }

    private function rules(string $resource, ?int $id): array
    {
        return match ($resource) {
            'banners' => ['title' => 'required|string|max:150', 'body' => 'required|string|max:500', 'link_label' => 'nullable|string|max:50', 'link_path' => ['nullable', 'regex:#^/(?!/|api(?:/|$)|admin(?:/|$))[a-zA-Z0-9/_-]*$#'], 'published' => 'required|boolean'],
            'redirects' => ['from_path' => ['required', 'regex:#^/(?!/|api(?:/|$)|admin(?:/|$)|assets(?:/|$)|storage(?:/|$)|login$|manage(?:/|$))[a-zA-Z0-9/_-]+$#', Rule::unique('redirects')->ignore($id)], 'to_path' => ['required', 'different:from_path', 'regex:#^/(?!/|api(?:/|$)|admin(?:/|$))[a-zA-Z0-9/_-]*$#'], 'active' => 'required|boolean'],
            'services' => ['name' => 'required|string|max:100', 'slug' => ['required', 'alpha_dash', 'max:120', Rule::unique('services')->ignore($id)], 'summary' => 'required|string|max:500', 'description' => 'nullable|string|max:8000', 'preparation' => 'nullable|string|max:1000', 'duration' => 'required|integer|min:5|max:120', 'icon' => 'nullable|string|max:30', 'published' => 'required|boolean', 'seo_title' => 'nullable|string|max:150', 'seo_description' => 'nullable|string|max:300'],
            'availability' => ['effective_from' => 'nullable|required_with:effective_to|date_format:Y-m-d', 'effective_to' => 'nullable|date_format:Y-m-d|after_or_equal:effective_from', 'weekday' => 'required|integer|between:0,6', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i|after:start_time', 'slot_minutes' => 'required|integer|between:5,120', 'buffer_minutes' => 'required|integer|between:0,60', 'capacity' => 'required|integer|between:1,20', 'active' => 'required|boolean'],
            'closures' => ['starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'reason' => 'required|string|max:200'],
            'content' => ['type' => 'required|in:article,faq,page', 'title' => 'required|string|max:150', 'slug' => ['required', 'alpha_dash', 'max:150', Rule::unique('content_pages')->ignore($id)], 'language' => 'required|in:en,hi', 'excerpt' => 'nullable|string|max:500', 'body' => 'required|string|max:30000', 'published' => 'required|boolean', 'seo_title' => 'nullable|string|max:150', 'seo_description' => 'nullable|string|max:300'],
            'enquiries' => ['status' => 'required|in:new,in_progress,converted,closed', 'assigned_to' => 'nullable|exists:users,id'],
            'testimonials' => ['name' => 'required|string|max:100', 'quote' => 'required|string|max:1000', 'consent' => 'required|boolean', 'published' => 'required|boolean', 'sort_order' => 'required|integer|between:0,1000'],
            'users' => ['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'password' => [$id ? 'nullable' : 'required', 'string', 'min:12', 'max:128'], 'role' => 'required|in:admin,receptionist,doctor', 'active' => 'required|boolean'],
        };
    }

    public function save(Request $r, string $resource, ?int $id = null)
    {
        $model = $this->resource($r, $resource);
        abort_if($resource === 'enquiries' && ! $id, 405);
        $data = $r->validate($this->rules($resource, $id));
        if ($resource === 'testimonials' && $data['published'] && ! $data['consent']) {
            abort(422, 'Publication requires recorded consent.');
        }
        if ($resource === 'users') {
            if (empty($data['password'])) {
                unset($data['password']);
            }
            if ($id === $r->user()->id && ($data['role'] !== 'admin' || ! $data['active'])) {
                abort(422, 'You cannot remove your own administrator access.');
            }
        }
        if ($resource === 'closures') {
            foreach (['starts_at', 'ends_at'] as $key) {
                $data[$key] = CarbonImmutable::parse($data[$key])->utc();
            }
        }

        return DB::transaction(function () use ($resource, $model, $id, $data) {
            if ($resource === 'redirects' && $data['active']) {
                app(BookingService::class)->lock();
                $seen = [$data['from_path']];
                $path = $data['to_path'];
                for ($i = 0; $i < 100; $i++) {
                    abort_if(in_array($path, $seen), 422, 'Redirects cannot create a loop.');
                    $seen[] = $path;
                    $next = Redirect::where('from_path', $path)->where('active', true)->when($id, fn ($q) => $q->where('id', '!=', $id))->first();
                    if (! $next) {
                        break;
                    }$path = $next->to_path;
                }
                abort_if($i === 100, 422, 'Redirect chain is too long.');
            }
            if (in_array($resource, ['availability', 'closures', 'services'])) {
                app(BookingService::class)->lock();
            }
            if ($resource === 'availability' && $data['active']) {
                abort_if(AvailabilityRule::where('weekday', $data['weekday'])->where('active', true)->when($id, fn ($q) => $q->where('id', '!=', $id))->where('start_time', '<', $data['end_time'])->where('end_time', '>', $data['start_time'])->exists(), 422, 'Active availability windows cannot overlap.');
            }
            $record = $id ? $model::findOrFail($id) : new $model;
            $record->fill($data)->save();
            AuditLog::record($id ? 'record.updated' : 'record.created', $resource, $record->id);

            return $record;
        });
    }

    public function destroy(Request $r, string $resource, int $id)
    {
        $model = $this->resource($r, $resource);
        abort_if(in_array($resource, ['users', 'enquiries']), 422, 'Deactivate users or close enquiries to preserve history.');

        return DB::transaction(function () use ($resource, $model, $id) {
            app(BookingService::class)->lock();
            if ($resource === 'services') {
                abort_if(Appointment::where('service_id', $id)->exists(), 422, 'This service has bookings. Unpublish it instead.');
            }
            $model::findOrFail($id)->delete();
            AuditLog::record('record.deleted', $resource, $id);

            return response()->noContent();
        });
    }

    public function settings()
    {
        return Setting::all()->pluck('value', 'key');
    }

    public function saveSettings(Request $r)
    {
        $data = $r->validate([
            'clinic' => 'required|array:name,name_hi,doctor,doctor_hi,qualifications,designation,address,address_hi,phone,whatsapp,email,map_url,registration,hours_confirmed,services_confirmed,consent_text,instructions',
            'clinic.name' => 'required|string|max:120', 'clinic.name_hi' => 'nullable|string|max:120', 'clinic.doctor' => 'required|string|max:120', 'clinic.doctor_hi' => 'nullable|string|max:120', 'clinic.qualifications' => 'nullable|string|max:200', 'clinic.designation' => 'nullable|string|max:200', 'clinic.address' => 'required|string|max:500', 'clinic.address_hi' => 'nullable|string|max:500',
            'clinic.phone' => 'nullable|regex:/^[+0-9 ()-]{10,20}$/', 'clinic.whatsapp' => 'nullable|regex:/^[0-9]{10,15}$/', 'clinic.email' => 'nullable|email|max:150', 'clinic.map_url' => 'nullable|url:https|max:500', 'clinic.registration' => 'nullable|string|max:100', 'clinic.hours_confirmed' => 'required|boolean', 'clinic.services_confirmed' => 'required|boolean', 'clinic.consent_text' => 'required|string|max:1000', 'clinic.instructions' => 'required|string|max:1000',
            'booking' => 'required|array:instant_confirmation,horizon_days,lead_minutes,cutoff_hours,daily_capacity,reminder_hours', 'booking.instant_confirmation' => 'required|boolean', 'booking.horizon_days' => 'required|integer|between:1,90', 'booking.lead_minutes' => 'required|integer|between:0,1440', 'booking.cutoff_hours' => 'required|integer|between:0,72', 'booking.daily_capacity' => 'required|integer|between:1,300', 'booking.reminder_hours' => 'required|array|max:3', 'booking.reminder_hours.*' => 'integer|between:1,72',
            'templates' => 'sometimes|array', 'templates.*' => 'required|string|max:1000',
        ]);
        DB::transaction(function () use ($data) {
            app(BookingService::class)->lock();
            foreach ($data as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value]);
            } AuditLog::record('settings.updated', 'settings');
        });

        return $this->settings();
    }

    public function patients(Request $r)
    {
        $r->validate(['search' => 'nullable|string|max:100']);

        return Patient::when($r->query('search'), fn ($q, $v) => $q->where('name', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%'))->orderByDesc('id')->paginate(30);
    }

    public function audit()
    {
        return AuditLog::orderByDesc('id')->paginate(50);
    }

    public function notifications()
    {
        return NotificationAttempt::orderByDesc('id')->paginate(50);
    }

    public function retry(NotificationAttempt $notification)
    {
        abort_unless(in_array($notification->status, ['failed', 'unconfigured']), 422);
        $notification->update(['status' => 'pending', 'attempts' => 0, 'scheduled_at' => now()]);
        AuditLog::record('notification.retried', 'notification', $notification->id);

        return $notification;
    }

    public function media()
    {
        return Media::orderByDesc('id')->get();
    }

    public function upload(Request $r)
    {
        $data = $r->validate(['image' => 'required|file|mimes:jpg,jpeg,png,webp|max:4096|dimensions:max_width=4000,max_height=4000', 'alt' => 'required|string|max:200', 'caption' => 'nullable|string|max:200', 'published' => 'required|boolean']);
        $image = imagecreatefromstring(file_get_contents($r->file('image')->getRealPath()));
        abort_unless($image, 422);
        $w = imagesx($image);
        $h = imagesy($image);
        if ($w > 1600 || $h > 1600) {
            $ratio = min(1600 / $w, 1600 / $h);
            $small = imagescale($image, (int) ($w * $ratio), (int) ($h * $ratio));
            imagedestroy($image);
            $image = $small;
        }
        ob_start();
        imagewebp($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = 'gallery/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $bytes);
        $record = Media::create(['path' => '/storage/'.$path, 'alt' => $data['alt'], 'caption' => $data['caption'] ?? null, 'published' => $data['published']]);
        AuditLog::record('media.uploaded','media',$record->id);

        return $record;
    }

    public function updateMedia(Request $r,Media $media)
    {
        $media->update($r->validate(['alt' => 'required|string|max:200', 'caption' => 'nullable|string|max:200', 'published' => 'required|boolean']));
        AuditLog::record('media.updated','media',$media->id);

        return $media;
    }
}
