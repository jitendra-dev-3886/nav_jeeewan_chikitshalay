<?php

namespace Database\Seeders;

use App\Models\AvailabilityRule;
use App\Models\ContentPage;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ClinicSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(['key' => 'clinic'], ['value' => [
            'name' => 'Nav Jeevan Chikitsalay', 'name_hi' => 'नव जीवन चिकित्सालय', 'doctor' => 'Dr. Parmesh Kumar', 'doctor_hi' => 'डॉ परमेश कुमार',
            'qualifications' => 'BAMS, DETCT', 'designation' => 'General Physician and Surgeon', 'address' => 'Singarjot Ghat, Mahuadhani', 'address_hi' => 'सिंगारजोत घाट, महुआधनी',
            'phone' => '', 'whatsapp' => '', 'email' => '', 'map_url' => '', 'registration' => '', 'hours_confirmed' => false, 'services_confirmed' => false,
            'consent_text' => 'I agree that the clinic may use these details to arrange my appointment. I have read the privacy notice.',
            'instructions' => 'Please arrive 10 minutes before your appointment and bring any previous consultation documents. Your request is subject to clinic confirmation.',
        ]]);
        Setting::firstOrCreate(['key' => 'booking'], ['value' => ['instant_confirmation' => false, 'horizon_days' => 30, 'lead_minutes' => 30, 'cutoff_hours' => 2, 'daily_capacity' => 40, 'reminder_hours' => [24, 2]]]);
        Setting::firstOrCreate(['key' => 'templates'], ['value' => [
            'booking' => 'Your appointment request {reference} for {time} is {status}. Please keep your booking-management link safe.',
            'confirmed' => 'Your appointment {reference} for {time} is confirmed.',
            'rescheduled' => 'Your appointment {reference} is now scheduled for {time}. Status: {status}.',
            'cancelled' => 'Your appointment {reference} has been cancelled.',
            'reminder' => 'Reminder: your appointment {reference} is at {time}. Please arrive 10 minutes early.',
        ]]);
        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            foreach ([['09:00', '13:00'], ['16:00', '19:00']] as [$start,$end]) {
                AvailabilityRule::firstOrCreate(['weekday' => $weekday, 'start_time' => $start, 'end_time' => $end], ['slot_minutes' => 20, 'buffer_minutes' => 0, 'capacity' => 1, 'active' => true]);
            }
        }
        foreach ([
            ['General consultation', 'general-consultation', 'A conversation about your health concerns and the next steps in your care.', 'stethoscope'],
            ['Follow-up visit', 'follow-up-visit', 'Continue a previous consultation and discuss your progress with the doctor.', 'heart'],
        ] as [$name,$slug,$summary,$icon]) {
            Service::firstOrCreate(['slug' => $slug], ['name' => $name, 'summary' => $summary, 'description' => $summary, 'preparation' => 'Bring relevant previous consultation documents and a list of current medicines.', 'duration' => 20, 'icon' => $icon, 'published' => true]);
        }
        foreach ([
            ['Do I need an account to book?', 'booking-account', 'No. Select a consultation and an available time, then enter your contact details. Save your secure management link after booking.'],
            ['When is my appointment confirmed?', 'confirmation', 'Requests are reviewed by reception unless instant confirmation is enabled. Your confirmation screen shows the current status.'],
            ['Can I change my appointment?', 'change-appointment', 'Use your secure booking link to cancel or choose another available time before the clinic’s change cut-off.'],
            ['Is this an emergency service?', 'emergency', 'No. Online appointments and enquiries are not monitored as an emergency service. Seek immediate in-person emergency care when needed.'],
        ] as [$title,$slug,$body]) {
            ContentPage::firstOrCreate(['slug' => $slug], ['type' => 'faq', 'title' => $title, 'body' => $body, 'language' => 'en', 'published' => true]);
        }
        foreach ([
            ['Privacy notice', 'privacy', 'We collect the contact and appointment details you submit to arrange a visit and manage clinic enquiries. Authorized clinic staff can access this information. Email updates are optional. This website does not collect diagnoses or prescriptions. Contact reception to request a correction or discuss deletion of your details. Keep your private booking link secure. The clinic must confirm its retention period and privacy contact before public launch.'],
            ['Appointment terms', 'terms', 'Online bookings are appointment requests unless the displayed status is Confirmed. Consultation times may change; reception can help with scheduling. Use your secure link to cancel or reschedule before the stated cut-off. No online payment is collected. Clinic policies must be reviewed before public launch.'],
            ['Medical disclaimer', 'disclaimer', 'This website provides clinic information and appointment scheduling. It does not provide a diagnosis, treatment recommendation, or emergency response. For personal medical advice, consult a qualified practitioner in person.'],
        ] as [$title,$slug,$body]) {
            ContentPage::firstOrCreate(['slug' => $slug], ['type' => 'page', 'title' => $title, 'body' => $body, 'language' => 'en', 'published' => true]);
        }
    }
}
