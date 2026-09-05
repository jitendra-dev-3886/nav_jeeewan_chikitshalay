# SRS implementation and acceptance notes

Source: `Nav_Jeevan_Clinic_Technical_SRS.docx`, version 1.0, 5 September 2026.

| Requirement | Implementation / verification |
| --- | --- |
| FR-001 responsive pages | Public and staff layouts; desktop/mobile Chrome overflow checks. Cross-browser and full accessibility acceptance remain launch tasks. |
| FR-002 bookable slots | Availability rules, breaks via split windows, closures, duration, buffer, lead time, horizon, daily and slot capacity. API tests cover closures, invalid grids, horizon and capacity. |
| FR-003 random public references | Cryptographically random `NJC-` references with a database unique constraint. |
| FR-004 concurrent capacity | Shared schedule lock inside the transaction. Eight-process tests on both SQLite and MySQL verify one winner for capacity one. |
| FR-005 statuses | Pending, Confirmed, Checked in, Completed, Cancelled, No-show; transition validation and tests. |
| FR-006 staff operations and history | Reception/admin create, reschedule, cancel, confirm, check in, complete; appointment events and audit. |
| FR-007 role permissions | API middleware and resource checks for admin/receptionist/doctor. No unrestricted public patient list. Permission tests included. |
| FR-008 content publishing | Article/FAQ/page/service editing, preview, draft/published flag. Saving an edited record replaces its previous version; there is no version-history editor. |
| FR-009 form controls | Server and client validation, normalized Indian mobile numbers, honeypot, consent, separate rate limit buckets. CSRF on all writes. |
| FR-010 notification attempts | Database notification records, queue worker, mail status, attempts, reminders, retry. Provider credentials required for external delivery. |
| FR-011 patient management | Random 64-character token; only SHA-256 hash stored; expiration two days after visit; change cut-off; masked mobile; no internal notes in summaries. |
| FR-012 report filters | Date, status, service, source; status/source/service/day/hour counts; cancellation/no-show rates; enquiry pipeline. |
| FR-013 settings | Clinic profile, consent, instructions, booking policy, reminder hours, templates; schedule managed separately. |
| FR-014 SEO | Public title/description/canonical metadata, clean routes, XML sitemap and robots controls. Laravel serves metadata and escaped public content in the built HTML before React starts. Permanent redirects and banner editing are included. Verified structured data and search-console review remain deployment work. |
| FR-015 exports | Admin-only CSV with formula-injection escaping; exports recorded in audit log. |

## Details and boundaries

- Single clinic, one primary practitioner. English interface with supplied Hindi identity fields and per-content language metadata. Full translated interface is not included.
- Weekly hours and two consultation options are provisional defaults; front-end notices explicitly identify them. They can be edited without code changes.
- Staff can add closures; existing bookings are intentionally retained so reception can contact patients and reschedule them. Closures prevent new bookings; they do not silently cancel visits.
- Enquiry conversion is a staff-selected workflow status, not automatic identity matching or marketing attribution.
- Media re-encodes accepted image files; arbitrary documents, SVG uploads and medical records are not accepted.
- Role definitions are fixed in the API rather than a granular permission-builder UI. The optional system administrator and content editor roles can be added in a later access-model extension.
- Automated analytics, OTP, waitlist, and authorized overbooking are not implemented. Weekly rules support optional effective start/end dates. These secondary/optional modules should be scoped with the clinic if required for launch.
- No medical record, prescription, billing, online payment, diagnosis automation, or emergency triage module is included.

## Acceptance work remaining with the clinic / deployment environment

1. Confirm clinic content, opening hours, services, phone and map details, registration, privacy contact, retention period, and legal text.
2. Review production database permissions and test SMTP delivery, scheduler and queue supervision.
3. Complete staff UAT with real operational policies and approved communication templates.
4. Complete cross-browser, screen-reader, contrast, performance/load, security-header, HTTPS, backup/restore and monitoring checks in the hosting environment.
5. Set availability and recovery objectives with hosting, including RPO/RTO and backup retention. No uptime or Core Web Vitals SLA is implied by local tests.
