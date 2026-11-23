<?php

namespace Database\Seeders;

use App\Models\SelfServiceKbEntry;
use Illuminate\Database\Seeder;

class SelfServiceKnowledgeBaseSeeder extends Seeder
{
    /**
     * Seed all 46 structured Student Manual entries (39 standard A1–G4 + 7 sensitive/urgent S1–S7).
     * Safe to re-run: existing entries are never modified; only missing entry codes are inserted.
     *
     * STRICT INSTITUTIONAL RULE:
     * Because all 46 entries in STUDENT_SELF_SERVICE_KNOWLEDGE_BASE_DRAFT.md and
     * STUDENT_SELF_SERVICE_OFFICE_REVIEWS/ are currently awaiting UCLM office sign-off,
     * every entry is seeded with:
     *   - lifecycle_stage = 'Under Office Review'
     *   - approval_status = 'Pending UCLM Validation'
     *   - publication_status = 'Unpublished'
     *
     * No fabricated approval records are created.
     */
    public function run(): void
    {
        foreach (self::entries() as $item) {
            // NON-DESTRUCTIVE: firstOrCreate only inserts missing entry codes. Existing entries
            // (content, approval, publication, reviewer metadata, version) are never overwritten.
            SelfServiceKbEntry::firstOrCreate(
                ['entry_code' => $item['entry_code']],
                array_merge([
                    'source_reference' => 'University of Cebu Student Manual (June 2019 Edition)',
                    'is_sensitive' => false,
                    'sensitive_pathway_type' => null,
                    'lifecycle_stage' => 'Under Office Review',
                    'approval_status' => 'Pending UCLM Validation',
                    'publication_status' => 'Unpublished',
                    'validated_by' => null,
                    'validated_role' => null,
                    'validated_office' => null,
                    'validated_at' => null,
                    'published_by' => null,
                    'published_at' => null,
                    'version' => 1,
                ], $item)
            );
        }
    }

    /**
     * Return the 12 discovery categories with descriptions and subtopics.
     */
    public static function discoveryCategories(): array
    {
        return [
            [
                'id' => 'Academic',
                'name' => 'Academic',
                'description' => 'Grades, instructor and classroom policies, attendance rules, and class schedules.',
                'subtopics' => [
                    'Grades and grading concerns',
                    'Instructor and classroom issues',
                    'Attendance',
                    'Class schedules',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Enrollment',
                'name' => 'Enrollment',
                'description' => 'Adding and dropping subjects, study load revision, academic records, and withdrawal or transfer procedures.',
                'subtopics' => [
                    'Adding and dropping subjects',
                    'Study load',
                    'Enrollment records',
                    'Withdrawal and transfer procedures',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Financial',
                'name' => 'Financial',
                'description' => 'Tuition installments, full-payment discounts, refunds, promissory notes, and scholarships.',
                'subtopics' => [
                    'Tuition balances',
                    'Payment concerns',
                    'Refunds',
                    'Promissory notes',
                    'Scholarship and financial assistance',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Student ID',
                'name' => 'Student ID',
                'description' => 'Lost school ID replacement, damaged ID inquiries, and campus ID rules.',
                'subtopics' => [
                    'Lost ID',
                    'Damaged ID',
                    'Replacement procedures',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Library',
                'name' => 'Library',
                'description' => 'Book borrowing limits, overdue fines, lost library books, photocopying, and library internet services.',
                'subtopics' => [
                    'Borrowing',
                    'Overdue materials',
                    'Lost books',
                    'Library services',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Facilities',
                'name' => 'Facilities',
                'description' => 'Classroom and laboratory facilities, equipment issues, and campus venue or AV reservations.',
                'subtopics' => [
                    'Damaged chairs and equipment',
                    'Air-conditioning issues',
                    'Classroom and laboratory problems',
                    'Campus facilities',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Technology',
                'name' => 'Technology',
                'description' => 'Student portal, LMS access, campus Wi-Fi connectivity, and institutional email assistance.',
                'subtopics' => [
                    'Student portal',
                    'LMS access',
                    'Campus Wi-Fi',
                    'Institutional email',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Student Services',
                'name' => 'Student Services',
                'description' => 'Uniform exemptions, dress code and grooming rules, student organizations, activity permits, and bulletin boards.',
                'subtopics' => [
                    'Uniform exemption',
                    'Student organizations',
                    'Activity permits',
                    'Student services',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Medical',
                'name' => 'Medical',
                'description' => 'Medical and dental clinic consultations, validation of outside medical certificates, and PE exemptions.',
                'subtopics' => [
                    'Clinic assistance',
                    'Medical certificates',
                    'Medical service procedures',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Guidance',
                'name' => 'Guidance',
                'description' => 'Counseling consultations, psychological testing, student support, and confidential wellness guidance.',
                'subtopics' => [
                    'Counseling requests',
                    'Student support',
                    'Guidance services',
                ],
                'is_sensitive_category' => false,
            ],
            [
                'id' => 'Safety and Security',
                'name' => 'Safety and Security',
                'description' => 'Direct formal reporting and emergency pathways for harassment, bullying, threats, violence, hazing, and medical emergencies.',
                'subtopics' => [
                    'Bullying',
                    'Harassment',
                    'Threats',
                    'Violence',
                    'Other safety concerns',
                ],
                'is_sensitive_category' => true,
            ],
            [
                'id' => 'Other Concerns',
                'name' => 'Other Concerns',
                'description' => 'Religious retreat exemptions and other general student concerns that do not fit standard categories.',
                'subtopics' => [
                    'Issues that do not fit existing categories',
                ],
                'is_sensitive_category' => false,
            ],
        ];
    }

    /**
     * All 46 structured entries (39 standard + 7 sensitive) from STUDENT_SELF_SERVICE_KNOWLEDGE_BASE_DRAFT.md.
     */
    public static function entries(): array
    {
        return [
            // ── Category A: Student ID, Uniform, & Appearance ─────────────────────
            [
                'entry_code' => 'A1',
                'title' => 'Replacement of Lost School ID',
                'category' => 'Student ID',
                'manual_category' => 'Category A: Student Identification Card (ID), Uniform, & Appearance',
                'common_questions' => [
                    'I lost my school ID. How do I get a replacement ID and which offices do I need to visit?',
                    'Where do I apply for a replacement school ID card?',
                ],
                'approved_answer' => 'The school ID is non-transferable, must be worn at all times inside the campus, and is issued by the University Library. To replace a lost school ID, a student must secure a notarized Affidavit of Loss and process a School ID Issuance Form through the Student Affairs Office (SAO), their College Dean/Chairperson, the Civil Security Unit (CSU) Head, the Cashier, and the University Library.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure a Notarized Affidavit of Loss', 'instructions' => 'Secure an Affidavit of Loss from any licensed lawyer available.', 'requirements' => ['Notarized Affidavit of Loss from a licensed lawyer'], 'office' => 'Licensed Lawyer / Notary Public', 'source_reference' => 'Student Manual, pp. 64–65', 'explanation' => 'Required to formally attest to the loss of your official University identification card.'],
                    ['step_number' => 2, 'title' => 'Present Affidavit of Loss to SAO', 'instructions' => 'Go to the Student Affairs Office (SAO) and present the Affidavit of Loss.', 'requirements' => ['Notarized Affidavit of Loss'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'SAO verifies your Affidavit of Loss before issuing the replacement form.'],
                    ['step_number' => 3, 'title' => 'Receive the School ID Issuance Form', 'instructions' => 'Receive the School ID Issuance Form from the SAO.', 'requirements' => ['School ID Issuance Form (issued by SAO)'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Keep this form intact for routing across required signatories.'],
                    ['step_number' => 4, 'title' => 'Secure College Dean or Chairperson Signature', 'instructions' => 'Go to your College Dean or Department Chairperson for their signature on the School ID Issuance Form.', 'requirements' => ['School ID Issuance Form'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Confirms your enrollment status within your academic college.'],
                    ['step_number' => 5, 'title' => 'Secure Civil Security Unit (CSU) Head Signature', 'instructions' => 'Go to the Civil Security Unit (CSU) Head for the signing of the form.', 'requirements' => ['School ID Issuance Form signed by Dean/Chairperson'], 'office' => 'Civil Security Unit (CSU)', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Verifies that the lost ID is not currently confiscated for a campus security or disciplinary violation.'],
                    ['step_number' => 6, 'title' => 'Pay the ID Replacement Fee at the Cashier', 'instructions' => 'Go to the Cashier for payment of the ID replacement fee.', 'requirements' => ['Signed School ID Issuance Form'], 'office' => 'Cashier / Accounting Department', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Retain the Official Receipt as proof of payment.'],
                    ['step_number' => 7, 'title' => 'Claim Replacement School ID', 'instructions' => 'Go to the University Library (or confirmed UCLM ID release office) for the issuance of the replacement school ID.', 'requirements' => ['Completed School ID Issuance Form', 'Official Receipt from Cashier'], 'office' => 'University Library (Pending UCLM MIS/SAO confirmation)', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Present the signed form and official receipt to have your replacement ID issued.'],
                ],
                'requirements' => [
                    'Notarized Affidavit of Loss from a licensed lawyer',
                    'School ID Issuance Form (issued by SAO)',
                    'Signatures of the College Dean/Chairperson and CSU Head',
                    'Official receipt of payment from the Cashier',
                ],
                'responsible_office' => 'Student Affairs Office (SAO), College Dean/Chairperson, Civil Security Unit (CSU), Cashier / Accounting Department, and University Library',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Accounting and Cashier', 'UCLM Library', 'Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 64–65 (PDF pages 73–74)',
                'validation_needed' => [
                    'Confirm the current ID replacement fee charged at the UCLM Cashier.',
                    'Confirm whether UCLM currently issues replacement IDs at the Library or through MIS/SAO.',
                    'Confirm how a student obtains a temporary entry pass from CSU while processing a lost ID.',
                    'Confirm damaged/defective ID replacement rules (Issue 7).',
                ],
            ],
            [
                'entry_code' => 'A2',
                'title' => 'Non-Wearing of School ID and Prohibited ID Acts',
                'category' => 'Student ID',
                'manual_category' => 'Category A: Student Identification Card (ID), Uniform, & Appearance',
                'common_questions' => [
                    'What happens if I forget to bring or wear my school ID on campus, or if I borrow a classmate\'s ID?',
                ],
                'approved_answer' => 'Every student must wear their valid school ID at all times inside the campus ("No school ID, no entry" also applies to the library). Non-wearing of the school ID is subject to progressive disciplinary sanctions from the 1st to the 4th offense. Furthermore, using another student\'s school ID or study load, lending one\'s school ID, changing the picture on the ID, or tampering with the ID to make it appear as one\'s own are strictly prohibited and classified as serious offenses involving dishonesty.',
                'steps' => [
                    ['step_number' => 1, 'title' => '1st Offense Protocol', 'instructions' => 'If cited for a 1st offense of non-wearing of school ID: An authorized University official issues a verbal warning and logs/reports the infraction to the College Head/Dean or SAO.', 'requirements' => ['Validated school ID worn visibly at all times inside the campus'], 'office' => 'Civil Security Unit (CSU) / SAO / College Dean', 'source_reference' => 'Student Manual, pp. 64–65', 'explanation' => 'First-time infraction is recorded with a verbal warning.'],
                    ['step_number' => 2, 'title' => '2nd Offense Protocol (Written Explanation)', 'instructions' => 'If cited for a 2nd offense: Submit a written explanation bearing your parent\'s or guardian\'s signature, after which the Head/Dean or SAO issues a written reprimand.', 'requirements' => ['Written explanation signed by the student\'s parent or guardian'], 'office' => 'Student Affairs Office (SAO) / College Dean', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Requires parental acknowledgment of the repeated infraction.'],
                    ['step_number' => 3, 'title' => '3rd Offense Protocol', 'instructions' => 'If cited for a 3rd offense: The Head/Dean, SAO, or authorized official sends the repeated offender home for the day, which is recorded as an official absence.', 'requirements' => [], 'office' => 'Student Affairs Office (SAO) / College Dean / CSU', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Results in an official class absence for the day.'],
                    ['step_number' => 4, 'title' => '4th Offense Protocol', 'instructions' => 'If cited for a 4th offense: The Head/Dean, SAO, or authorized official recommends the student for investigation for approval of a one-week suspension from class (recorded as official absences).', 'requirements' => [], 'office' => 'Student Affairs Office (SAO) / College Dean', 'source_reference' => 'Student Manual, p. 65', 'explanation' => 'Escalates to formal disciplinary investigation for a one-week suspension.'],
                ],
                'requirements' => [
                    'Validated school ID worn visibly at all times inside the campus',
                    'For 2nd offense of non-wearing: Written explanation signed by the student\'s parent or guardian',
                ],
                'responsible_office' => 'Civil Security Unit (CSU), Student Affairs Office (SAO), and College Dean/Chairperson',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 64–65, 68 (PDF pages 73–74, 78)',
                'validation_needed' => [
                    'Confirm whether CSU at the UCLM gate issues a temporary visitor/student pass on the 1st offense or immediately denies entry when a student forgets their ID.',
                ],
            ],
            [
                'entry_code' => 'A3',
                'title' => 'Uniform Exemption Application',
                'category' => 'Student Services',
                'manual_category' => 'Category A: Student Identification Card (ID), Uniform, & Appearance',
                'common_questions' => [
                    'I am a working student / pregnant / cross-enrollee / married. How do I apply for a school uniform exemption?',
                ],
                'approved_answer' => 'Students who cannot wear the prescribed school uniform due to a valid reason qualified under University policy must apply for a Uniform Exemption Card from the Student Affairs Office (SAO) at the start of classes. The Uniform Exemption Card is non-transferable, must be presented to the campus security guard upon entry, and must be renewed every semester.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure Uniform Exemption Application Form', 'instructions' => 'Ask for a Uniform Exemption Application Form from the Student Affairs Office (SAO) at the start of classes.', 'requirements' => ['Uniform Exemption Application Form (available at SAO)'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'Applications must be filed at the start of every semester.'],
                    ['step_number' => 2, 'title' => 'Accomplish Form and Attach Supporting Documents', 'instructions' => 'Fill out the application form and attach the required supporting documents along with two (2) 2x2 colored pictures.', 'requirements' => ['Two (2) 2x2 colored pictures', 'Category-specific proof (e.g., Certificate of Employment, Marriage Certificate, Permit to Cross-Enroll, or Birth Certificate)'], 'office' => 'Student Applicant', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'Ensure all supporting documents match your qualification category.'],
                    ['step_number' => 3, 'title' => 'Submit Application to SAO for Evaluation', 'instructions' => 'Submit the accomplished application form and attachments to the SAO for evaluation and approval.', 'requirements' => ['Accomplished form and documentary attachments'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'SAO verifies eligibility under the 7 approved exemption categories.'],
                    ['step_number' => 4, 'title' => 'Pay Uniform Exemption Card Fee at Cashier', 'instructions' => 'Upon approval by the SAO, pay the required fee for the Exemption Card at the Cashier.', 'requirements' => ['SAO-approved Uniform Exemption Application Form'], 'office' => 'Cashier / Accounting Department', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'Obtain the official receipt from the Cashier.'],
                    ['step_number' => 5, 'title' => 'Present Official Receipt to SAO', 'instructions' => 'Submit the official receipt to the SAO as proof of payment.', 'requirements' => ['Official Receipt from Cashier'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'Required for card processing and release.'],
                    ['step_number' => 6, 'title' => 'Claim and Present Uniform Exemption Card', 'instructions' => 'Claim your Uniform Exemption Card from the SAO and present it to the security guard upon entering the University.', 'requirements' => ['Issued Uniform Exemption Card'], 'office' => 'Student Affairs Office (SAO) & Civil Security Unit (CSU)', 'source_reference' => 'Student Manual, p. 63', 'explanation' => 'The card is non-transferable and valid only for the current semester.'],
                ],
                'requirements' => [
                    'Qualification under one of the 7 manual categories: Pregnant women, Married students, Legitimately working students (non-family firm), Students over 35 years old, Cross-enrollees, Second coursers, or Special cases',
                    'Accomplished Uniform Exemption Application Form (available at SAO)',
                    'Two (2) 2x2 colored pictures',
                    'Authenticated marriage certificate (for married women)',
                    'Authenticated photocopy of Permit to Cross-Enroll (for cross-enrollees)',
                    'NSO (now PSA) Birth Certificate (for age verification)',
                    'Certificate of Employment (for working student / employee applicants)',
                ],
                'responsible_office' => 'Student Affairs Office (SAO), Cashier / Accounting Department, and Civil Security Unit (CSU)',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, page 63 (PDF page 72)',
                'validation_needed' => [
                    'Confirm the current fee for the Uniform Exemption Card at the UCLM Cashier.',
                    'Confirm supporting documents required for pregnant students, second coursers, or special cases.',
                    'Confirm the deadline after the start of classes for filing uniform exemption applications at UCLM.',
                ],
            ],
            [
                'entry_code' => 'A4',
                'title' => 'School Uniform Schedule, Dress Code, and Temporary Non-Uniform Entry',
                'category' => 'Student Services',
                'manual_category' => 'Category A: Student Identification Card (ID), Uniform, & Appearance',
                'common_questions' => [
                    'What days are we required to wear the school uniform, when is wash day, and what if I am a new student who doesn\'t have a uniform yet?',
                ],
                'approved_answer' => 'Students from any college or department with a duly approved prescribed uniform must wear their complete uniform from Mondays through Fridays, except on Wednesdays (and on days specifically designated by their respective colleges). Old students must wear their complete uniform one (1) week after the opening of classes, while new students must wear their complete uniform two (2) weeks after classes begin. Students not in proper uniform are refused entry unless endorsed by their Dean and approved by the SAO.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Follow the Prescribed Weekly Uniform Schedule', 'instructions' => 'Wear the complete prescribed college uniform on Mondays, Tuesdays, Thursdays, and Fridays (Wednesday is the designated non-uniform/civilian attire day, unless otherwise designated by your college; PE uniform is worn on PE class days).', 'requirements' => ['Complete prescribed college uniform'], 'office' => 'Civil Security Unit (CSU) & Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 62', 'explanation' => 'Old students have a 1-week grace period after classes open; new students have a 2-week grace period.'],
                    ['step_number' => 2, 'title' => 'Observe University Dress Code Standards', 'instructions' => 'Observe the University dress code at all times: skirt length must not be more than one (1) inch above the knee, and prohibited attire (slippers, sandals, bakya, sleeveless shirts/sando, miniskirts, thin-strap blouses, halter backs, or revealing clothing) is strictly disallowed.', 'requirements' => ['Compliance with University dress code'], 'office' => 'Civil Security Unit (CSU) & Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 62', 'explanation' => 'Applies on both uniform days and civilian/wash days.'],
                    ['step_number' => 3, 'title' => 'Request Temporary Non-Uniform Entry Approval (If Applicable)', 'instructions' => 'If you have a legitimate temporary reason for not wearing the prescribed uniform outside the initial grace period, secure a written endorsement from your College Dean and present it to the Student Affairs Office (SAO) for approval to enter the campus.', 'requirements' => ['Written endorsement from the College Dean', 'Approval from the Student Affairs Office (SAO)'], 'office' => 'College Dean\'s Office and Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 62', 'explanation' => 'Present the SAO-approved endorsement to CSU at the campus gate.'],
                ],
                'requirements' => [
                    'For temporary non-uniform entry exception: Endorsement from the College Dean and approval from the Student Affairs Office (SAO)',
                ],
                'responsible_office' => 'Student Affairs Office (SAO), College Dean\'s Office, and Civil Security Unit (CSU)',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, page 62 (PDF page 71)',
                'validation_needed' => [
                    'Confirm whether Wednesday remains the universal wash day across all UCLM colleges (including Maritime, Nursing, Criminology, and Hospitality Management).',
                ],
            ],
            [
                'entry_code' => 'A5',
                'title' => 'Prescribed Haircut, Hair Color, and Body Piercing Rules',
                'category' => 'Student Services',
                'manual_category' => 'Category A: Student Identification Card (ID), Uniform, & Appearance',
                'common_questions' => [
                    'What are the rules on haircut length, hair dyeing, and earrings for students entering the campus?',
                ],
                'approved_answer' => 'Students must maintain a neat, clean, and modest hairstyle. For male students in colleges other than Criminology and Maritime courses, hair length must not exceed the collar line and eyesight and must be trimmed periodically. Criminology and Maritime colleges enforce their own program-specific haircut standards. Outrageous hairstyles and loud hair colors (e.g., red, blue, violet) are prohibited for all students. Male students are also prohibited from wearing earrings, nose rings, or similar body piercings. Non-compliant students may be refused entry to the campus.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Comply with Prescribed Haircut and Grooming Rules Upon Entry', 'instructions' => 'Ensure your haircut, hair color, and personal grooming comply with University and college-specific standards before entering the campus gates.', 'requirements' => ['Male students (non-Maritime/non-Criminology): Hair trimmed above the collar line and eyesight; no earrings, nose rings, or body piercings', 'Maritime and Criminology students: Compliance with college-specific prescribed haircut and grooming rules', 'All students: Natural/modest hair color and hairstyle (no loud colors such as red, blue, or violet)'], 'office' => 'Civil Security Unit (CSU), Student Affairs Office (SAO), and respective College Deans', 'source_reference' => 'Student Manual, pp. 64–65', 'explanation' => 'Non-compliant students may be refused entry to the campus.'],
                ],
                'requirements' => [
                    'Male students (non-Maritime/non-Criminology): Hair trimmed above the collar line and eyesight; no earrings, nose rings, or body piercings',
                    'Maritime and Criminology students: Compliance with college-specific prescribed haircut and grooming rules',
                    'All students: Natural/modest hair color and hairstyle (no loud colors such as red, blue, or violet)',
                ],
                'responsible_office' => 'Civil Security Unit (CSU), Student Affairs Office (SAO), and respective College Deans',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 64–65 (PDF pages 73–74)',
                'validation_needed' => [
                    'Confirm current UCLM enforcement guidelines on subtle hair dyes/highlights and female ear piercing regulations.',
                ],
            ],

            // ── Category B: Library Services ───────────────────────────────────────
            [
                'entry_code' => 'B1',
                'title' => 'Borrowing Library Books and Materials',
                'category' => 'Library',
                'manual_category' => 'Category B: Library & Electronic Resource Services',
                'common_questions' => [
                    'How do I borrow books from the library, how many books can I borrow, and how long can I keep them?',
                ],
                'approved_answer' => 'All bona fide students can borrow library materials by presenting their validated school ID at the loan desk and legibly filling out the book card. Borrowing limits and loan durations depend on the type of book: Reference books (up to 2 books for 3 days, renewable for 3 days), Reserved books (1 hour inside the library, or overnight from 5:00 PM to 8:00 AM the next day), and Fiction books (1 week, renewable if not in demand). General references, Filipiniana books, periodicals, theses, and CDs/DVDs are for room/library reading only. One week before final examinations, books can no longer be borrowed for home use.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Wear Your Validated School ID Upon Library Entry', 'instructions' => 'Wear your valid school ID upon entering the library ("No school ID, no entry").', 'requirements' => ['Validated school ID'], 'office' => 'Library and Electronic Resource Services', 'source_reference' => 'Student Manual, pp. 53–55', 'explanation' => 'Using another student\'s ID results in confiscation.'],
                    ['step_number' => 2, 'title' => 'Locate Material and Proceed to the Loan Desk', 'instructions' => 'Locate the material and proceed to the library loan desk.', 'requirements' => [], 'office' => 'Library and Electronic Resource Services', 'source_reference' => 'Student Manual, pp. 54–55', 'explanation' => 'Check whether the book is Reference, Reserved, Fiction, or room-use only.'],
                    ['step_number' => 3, 'title' => 'Present Validated School ID', 'instructions' => 'Present your validated school ID to the loan desk staff.', 'requirements' => ['Validated school ID'], 'office' => 'Library Loan Desk', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Required to verify active student borrowing privileges.'],
                    ['step_number' => 4, 'title' => 'Sign the Book Card Legibly', 'instructions' => 'Fill out the book card legibly with your full name, course, and year level.', 'requirements' => ['Legibly signed book card with name, course, and year'], 'office' => 'Library Loan Desk', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Records the loan and due date of the borrowed material.'],
                    ['step_number' => 5, 'title' => 'Present Due Date Card and Bag for Inspection Upon Exit', 'instructions' => 'When leaving the library, open and show your bag, folders, and large envelopes for security inspection, and present the due date card of every library material being taken outside.', 'requirements' => ['Due date card for borrowed materials'], 'office' => 'Library Security / Exit Desk', 'source_reference' => 'Student Manual, p. 54', 'explanation' => 'Mandatory security check when exiting the library.'],
                ],
                'requirements' => [
                    'Validated school ID (non-transferable; using another student\'s ID results in confiscation)',
                    'Legibly signed book card with name, course, and year',
                ],
                'responsible_office' => 'Library and Electronic Resource Services',
                'primary_review_office' => 'UCLM Library',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 53–55 (PDF pages 62–64)',
                'validation_needed' => [
                    'Confirm current UCLM Library operating hours.',
                    'Confirm whether UCLM Library now uses an automated digital barcode/RFID borrowing system in place of manual book cards.',
                ],
            ],
            [
                'entry_code' => 'B2',
                'title' => 'Overdue Library Books — Fines and Penalties',
                'category' => 'Library',
                'manual_category' => 'Category B: Library & Electronic Resource Services',
                'common_questions' => [
                    'How much is the fine if I return a borrowed library book late?',
                ],
                'approved_answer' => 'The Library imposes overdue fines depending on the category of the borrowed material, and computation includes Sundays and Holidays. Habitual delinquency (frequently returning books late or disregarding library rules) results in the suspension of borrowing privileges. Note that the manual states fines may vary per campus.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Return the Overdue Book to the Loan Desk', 'instructions' => 'Return the overdue book to the library loan desk as soon as possible.', 'requirements' => ['Return of the overdue library material'], 'office' => 'Library Loan Desk', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Returning the book immediately stops additional daily or hourly fines from accruing.'],
                    ['step_number' => 2, 'title' => 'Have the Loan Desk Officer Compute the Fine', 'instructions' => 'Have the loan desk officer compute the accrued overdue fine based on the book type (2019 manual baseline rates: Reserved books: PhP 1.00 first hour + PhP 0.50 succeeding hour; Reference books: PhP 1.00/day; Fiction books: PhP 0.50/day; Unreturned reserved/periodicals for photocopying: PhP 10.00/day; including Sundays and Holidays).', 'requirements' => ['Overdue fine computation from Loan Desk Officer'], 'office' => 'Library Loan Desk', 'source_reference' => 'Student Manual, pp. 55–56', 'explanation' => 'Note: The manual specifies that fine rates may vary per campus.'],
                    ['step_number' => 3, 'title' => 'Settle the Overdue Fine', 'instructions' => 'Settle the fine according to the Library and Cashier payment procedure.', 'requirements' => ['Payment of computed overdue fines'], 'office' => 'Library and Electronic Resource Services & Cashier / Accounting Department', 'source_reference' => 'Student Manual, pp. 55–56', 'explanation' => 'Unsettled library obligations block semester/graduation clearance.'],
                ],
                'requirements' => [
                    'Return of the overdue library material',
                    'Payment of computed overdue fines',
                ],
                'responsible_office' => 'Library and Electronic Resource Services and Cashier / Accounting Department',
                'primary_review_office' => 'UCLM Library',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, pages 55–56 (PDF pages 64–65)',
                'validation_needed' => [
                    'Confirm current UCLM Library overdue fine rates.',
                    'Confirm whether overdue fines are paid directly at the Cashier\'s Office or logged to the student\'s tuition assessment account.',
                ],
            ],
            [
                'entry_code' => 'B3',
                'title' => 'Reporting and Replacing a Lost Library Book',
                'category' => 'Library',
                'manual_category' => 'Category B: Library & Electronic Resource Services',
                'common_questions' => [
                    'I lost a book I borrowed from the library. What should I do and how much will I be charged?',
                ],
                'approved_answer' => 'Lost library books must be reported immediately to the loan desk officer so that daily overdue fines stop accruing. The student must replace or pay for the lost book within fifteen (15) days, after which unsettled charges are reported to the Cashier\'s Office and billed to the student\'s account.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Report Lost Book Immediately to the Loan Desk Officer', 'instructions' => 'Report the lost book immediately to the library loan desk officer.', 'requirements' => ['Immediate report to the library loan desk officer'], 'office' => 'Library Loan Desk', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Reporting immediately stops daily overdue fines from continuing to accumulate.'],
                    ['step_number' => 2, 'title' => 'Replace or Pay for the Lost Book Within 15 Days', 'instructions' => 'Within fifteen (15) days of reporting, either replace the book or settle the payment covering: (a) current price of the book, (b) processing fee of PhP 25.00, and (c) accrued overdue fines up to the date of settlement/reporting.', 'requirements' => ['Replacement copy of the book (or payment of current book price) + PhP 25.00 processing fee + accrued fines within 15 days'], 'office' => 'Library and Electronic Resource Services & Cashier', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Must be completed within 15 days of reporting the loss.'],
                    ['step_number' => 3, 'title' => 'Clear Billed Account at Cashier (If Beyond 15 Days)', 'instructions' => 'If not settled within 15 days, the Library forwards the charges to the Cashier\'s Office to be charged to your student account (which must be cleared before graduation or transfer clearance).', 'requirements' => ['Settlement of billed library accountability at Cashier'], 'office' => 'Cashier / Accounting Department', 'source_reference' => 'Student Manual, p. 55', 'explanation' => 'Required for student clearance.'],
                ],
                'requirements' => [
                    'Immediate report to the library loan desk officer',
                    'Replacement copy of the book (or payment of the current book price) + PhP 25.00 processing fee + accrued fines within 15 days',
                ],
                'responsible_office' => 'Library and Electronic Resource Services and Cashier / Accounting Department',
                'primary_review_office' => 'UCLM Library',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, page 55 (PDF page 64)',
                'validation_needed' => [
                    'Confirm whether the PhP 25.00 processing fee for lost books is still the current rate at UCLM.',
                ],
            ],
            [
                'entry_code' => 'B4',
                'title' => 'Photocopying Library Materials and Using Library Internet Facilities',
                'category' => 'Library',
                'manual_category' => 'Category B: Library & Electronic Resource Services',
                'common_questions' => [
                    'How can I photocopy pages from a library book or use the computers and internet in the library?',
                ],
                'approved_answer' => 'Bona fide students may request permission to reproduce/photocopy library materials and may use the library\'s internet and computer facilities upon presentation of a valid school ID.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Request Permission for Photocopying Library Materials', 'instructions' => 'To reproduce/photocopy library materials: Ask permission from the Library Section-in-charge, sign the book card, and leave your valid school ID at the desk while taking the material for reproduction. Return the material on time (unreturned reserved books/periodicals borrowed for photocopying incur a fine of PhP 10.00/day).', 'requirements' => ['Validated school ID', 'Signed book card'], 'office' => 'Library Section-in-Charge', 'source_reference' => 'Student Manual, pp. 54–55', 'explanation' => 'Some materials (such as unpublished theses) are restricted from reproduction.'],
                    ['step_number' => 2, 'title' => 'Access Library Internet and Computer Facilities', 'instructions' => 'To use the Library Internet facilities: Present your valid school ID at the library internet section, observe room rules strictly (no talking, eating, drinking, smoking, companions/bystanders, or accessing sexually explicit websites), and pay the minimal charge for internet usage or printing of searched topics, if applicable.', 'requirements' => ['Validated school ID'], 'office' => 'Library Internet Section', 'source_reference' => 'Student Manual, p. 56', 'explanation' => 'Follow all posted computer and internet section regulations.'],
                ],
                'requirements' => [
                    'Validated school ID',
                ],
                'responsible_office' => 'Library and Electronic Resource Services',
                'primary_review_office' => 'UCLM Library',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 54–56 (PDF pages 63–65)',
                'validation_needed' => [
                    'Confirm current UCLM Library internet usage and printing fees.',
                ],
            ],

            // ── Category C: Registrar Services, Academic Records, & Enrollment ─────
            [
                'entry_code' => 'C1',
                'title' => 'Requesting Academic Records (Transcript of Records, Certifications)',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'How do I request my Transcript of Records (TOR) or academic certifications, and how many days does it take to release?',
                ],
                'approved_answer' => 'The Registrar\'s Office handles the archiving and issuance of academic records such as the Transcript of Records (TOR), diploma, and certificates of academic performance. Before academic records are released, the student must present an accomplished request form, an accomplished clearance, and proof of payment of the document fee. All financial and property obligations with the University must be settled first.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure Academic Record Request Form and Student Clearance', 'instructions' => 'Secure and accomplish an Academic Record Request Form and Student Clearance from the Registrar\'s Office.', 'requirements' => ['Accomplished Request Form', 'Student Clearance Form'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'Specify the exact academic record or certification and its purpose.'],
                    ['step_number' => 2, 'title' => 'Settle Obligations and Pay Document Fee at Cashier', 'instructions' => 'Settle any outstanding financial or property accountabilities and pay the required document fee at the Cashier / Accounting Department.', 'requirements' => ['Completed Student Clearance', 'Payment of document fee'], 'office' => 'Cashier / Accounting Department', 'source_reference' => 'Student Manual, pp. 21, 32', 'explanation' => 'No academic record will be released while financial or property liabilities remain unsettled.'],
                    ['step_number' => 3, 'title' => 'Submit Request Form, Completed Clearance, and Official Receipt', 'instructions' => 'Submit the accomplished request form, completed clearance, and official receipt of payment to the Registrar\'s Office.', 'requirements' => ['Accomplished Request Form', 'Accomplished Student Clearance', 'Official Receipt of document fee'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'Processing timeline begins upon receipt of the completed clearance and payment receipt.'],
                    ['step_number' => 4, 'title' => 'Claim Requested Academic Records', 'instructions' => 'Claim the requested academic records according to the official release schedule: Ordinary days/period: Five (5) working days upon receipt of the accomplished clearance and payment of the document fee; Enrollment period: Seven (7) working days upon receipt of the accomplished clearance and payment of the document fee.', 'requirements' => ['Claim stub / Official Receipt', 'Valid ID'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'If claiming through a representative, see Entry C3 for notarized Written Authority requirements.'],
                ],
                'requirements' => [
                    'Accomplished Request Form',
                    'Accomplished Student Clearance (cleared of all financial and property obligations)',
                    'Payment of document fee',
                ],
                'responsible_office' => 'Registrar\'s Office and Accounting Department / Cashier',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, pages 11, 21, 32 (PDF pages 20, 30, 41)',
                'validation_needed' => [
                    'Confirm current UCLM Registrar document fees for TOR, Certifications, and Authentication.',
                    'Confirm whether online request submission is available at UCLM or if requests must be filed in person.',
                ],
            ],
            [
                'entry_code' => 'C2',
                'title' => 'Requesting a Subsequent Copy (Re-Issuance) of a Lost Diploma',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'I lost my original graduation diploma. How can I request a second copy from the Registrar?',
                ],
                'approved_answer' => 'The Registrar\'s Office may issue a subsequent copy of a graduate\'s diploma upon submission of a notarized Affidavit explaining the circumstances of the loss of the original diploma and payment of the re-issuance fee.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Execute a Notarized Affidavit of Loss for the Diploma', 'instructions' => 'Execute a notarized Affidavit detailing the circumstances of the loss of your original diploma.', 'requirements' => ['Notarized Affidavit regarding the circumstance(s) of the loss of the original diploma'], 'office' => 'Licensed Lawyer / Notary Public', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'Must explain the specific circumstances of how the original diploma was lost.'],
                    ['step_number' => 2, 'title' => 'Submit Affidavit to the Registrar\'s Office', 'instructions' => 'Submit the notarized Affidavit to the Registrar\'s Office and secure a payment order for the diploma re-issuance fee.', 'requirements' => ['Notarized Affidavit'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'The Registrar verifies your graduation record and issues a payment order.'],
                    ['step_number' => 3, 'title' => 'Pay Diploma Re-Issuance Fee at Cashier', 'instructions' => 'Pay the re-issuance fee at the Cashier / Accounting Department.', 'requirements' => ['Payment of diploma re-issuance fee'], 'office' => 'Cashier / Accounting Department', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'Keep the official receipt.'],
                    ['step_number' => 4, 'title' => 'Submit Proof of Payment and Claim Subsequent Diploma Copy', 'instructions' => 'Submit the proof of payment to the Registrar\'s Office and wait for the scheduled release of the subsequent diploma copy.', 'requirements' => ['Official Receipt'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'The Registrar will advise you of the scheduled release date.'],
                ],
                'requirements' => [
                    'Notarized Affidavit regarding the circumstance(s) of the loss of the original diploma',
                    'Payment of diploma re-issuance fee',
                ],
                'responsible_office' => 'Registrar\'s Office and Accounting Department / Cashier',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, page 21 (PDF page 30)',
                'validation_needed' => [
                    'Confirm the current diploma re-issuance fee and processing turnaround time at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C3',
                'title' => 'Claiming Academic Records or Diploma Through a Proxy / Representative',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'I cannot go to school personally to claim my TOR or diploma. Can a relative or representative claim it for me?',
                ],
                'approved_answer' => 'Yes. Academic records, diplomas, and other official documents may be released to a proxy provided the representative presents a duly notarized Written Authority executed by the document owner. The notarized Written Authority is retained on file in the student\'s permanent record.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Execute a Duly Notarized Written Authority', 'instructions' => 'Execute a duly notarized Written Authority that explicitly states: (a) the full name of the authorized proxy, (b) the address of the proxy, (c) the relationship of the proxy to the student/owner granting the authority, and (d) the signatures of both the student/owner and the proxy.', 'requirements' => ['Duly notarized Written Authority stating the proxy\'s name, address, and relationship to the student, signed by both the student and the proxy'], 'office' => 'Student Owner & Notary Public', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'An unnotarized authorization letter is not sufficient under Page 21 of the Student Manual.'],
                    ['step_number' => 2, 'title' => 'Present Notarized Written Authority at the Registrar\'s Office', 'instructions' => 'Have the proxy present the notarized Written Authority (along with the required clearance and claim stub/receipt) to the Registrar\'s Office.', 'requirements' => ['Duly notarized Written Authority', 'Claim stub / Official Receipt and completed clearance'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 21', 'explanation' => 'The Registrar retains the notarized Written Authority in the student\'s permanent file.'],
                ],
                'requirements' => [
                    'Duly notarized Written Authority stating the proxy\'s name, address, and relationship to the student, signed by both the student and the proxy',
                ],
                'responsible_office' => 'Registrar\'s Office',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, page 21 (PDF page 30)',
                'validation_needed' => [
                    'Confirm what valid government IDs the proxy and student must attach to the notarized Written Authority under current Data Privacy Act protocols at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C4',
                'title' => 'Issuance and Re-Issuance of Study Load, Grade Slip, Evaluation Sheet, Graduation Application, and Clearance',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'Is there a fee to get my study load, grade slip, evaluation sheet, or clearance?',
                ],
                'approved_answer' => 'As a general rule, the initial issuance of a student\'s study load, grade slip, application for graduation, and clearances is free of charge, except for the evaluation sheet. However, any re-issuance (replacement printouts) of these documents requires the requesting student to pay minimal charges.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Request Initial Issuance of Document', 'instructions' => 'For initial issuance: Request the document from the Registrar\'s Office / designated college window during the scheduled period (if requesting an evaluation sheet, pay the required evaluation sheet fee).', 'requirements' => ['Valid School ID', 'Payment of evaluation sheet fee if requesting an evaluation sheet'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 21–22', 'explanation' => 'Initial printouts of study loads, grade slips, graduation applications, and clearances are free of charge.'],
                    ['step_number' => 2, 'title' => 'Request Re-Issuance (Replacement Printout)', 'instructions' => 'For re-issuance of a lost or additional copy: Request a payment assessment, pay the minimal re-issuance charge at the Cashier, and present the receipt to the Registrar\'s Office.', 'requirements' => ['Official Receipt of re-issuance fee from Cashier'], 'office' => 'Registrar\'s Office and Accounting Department / Cashier', 'source_reference' => 'Student Manual, pp. 21–22', 'explanation' => 'Applies whenever requesting a replacement or duplicate copy.'],
                ],
                'requirements' => [
                    'Payment of minimal charge for evaluation sheets or for any re-issued copy of study load, grade slip, graduation application, or clearance',
                ],
                'responsible_office' => 'Registrar\'s Office and Accounting Department / Cashier',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, pages 21–22 (PDF pages 30–31)',
                'validation_needed' => [
                    'Confirm whether study loads and grade slips at UCLM are now accessed digitally via the UC Student Portal or printed at the Registrar/EDP window, and confirm current re-issuance fees.',
                ],
            ],
            [
                'entry_code' => 'C5',
                'title' => 'Study Load Revision (Adding, Changing, or Adjusting Subjects)',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'How do I revise my study load or change/add a subject after enrolling, and what is the deadline?',
                ],
                'approved_answer' => 'Adjustment or revision of a student\'s study load is allowed only during the first week of the start of classes. No changes to the study load are permitted after the close of the enrollment period.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure an Adjustment Form from Your College Dean\'s Office', 'instructions' => 'During the first week of the start of classes, go to your College Dean\'s Office and secure an Adjustment Form.', 'requirements' => ['Official Study Load'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Must be initiated within the first week of classes before the close of enrollment.'],
                    ['step_number' => 2, 'title' => 'Have the Adjustment Form Evaluated and Approved by Your College Dean', 'instructions' => 'Fill out the Adjustment Form and have it evaluated and approved by your College Dean.', 'requirements' => ['Accomplished Adjustment Form'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'The Dean checks prerequisites, unit limits, and schedule conflicts.'],
                    ['step_number' => 3, 'title' => 'Submit Approved Copy to the University Registrar', 'instructions' => 'Submit one (1) copy of the approved Adjustment Form to the University Registrar.', 'requirements' => ['Dean-approved Adjustment Form (Registrar copy)'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Updates your official academic registration record.'],
                    ['step_number' => 4, 'title' => 'Submit Approved Copy to the Accounting Department', 'instructions' => 'Submit another copy of the approved Adjustment Form to the Accounting Department for the corresponding adjustment of your tuition and fees.', 'requirements' => ['Dean-approved Adjustment Form (Accounting copy)'], 'office' => 'Accounting Department', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Ensures your semester tuition assessment accurately reflects the added or changed units.'],
                ],
                'requirements' => [
                    'Adjustment Form secured from and approved by the College Dean',
                    'Must be processed within the first week of the start of classes (before the close of the enrollment period)',
                ],
                'responsible_office' => 'College Dean\'s Office, Registrar\'s Office, and Accounting Department',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, page 22 (PDF page 31)',
                'validation_needed' => [
                    'Confirm whether load revision at UCLM is currently initiated online or via physical Adjustment Forms at the Dean\'s Office.',
                ],
            ],
            [
                'entry_code' => 'C6',
                'title' => 'Withdrawal from Subjects or Program and Dropping from Class',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'How do I officially withdraw from a subject so I don\'t get a failing or Dropped (DR) mark, and how much tuition will I be charged?',
                ],
                'approved_answer' => 'A student may officially withdraw from a subject, even after the close of the enrollment period, upon the recommendation of the College Dean and with the approval of the University Registrar. Withdrawal from subjects is not ordinarily approved after the midterm examination. Failure to officially withdraw when stopping class attendance results in a mark of Dropped (DR). Conversely, a teacher-initiated Dropping from Class requires the recommendation of the teacher and approval of the Dean.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Consult Your College Dean or Chairperson Before Midterm Exams', 'instructions' => 'Consult your College Dean, Department Chairperson, or the Registrar regarding the withdrawal before the midterm examination.', 'requirements' => ['Filed prior to the midterm examination'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, pp. 19–20', 'explanation' => 'Withdrawals are not ordinarily approved after the midterm examination.'],
                    ['step_number' => 2, 'title' => 'Verify Eligibility and Present Parent/Guardian Written Permission', 'instructions' => 'Ensure you meet the two mandatory conditions for withdrawal approval: (a) your accumulated absences in the class have not reached 20% of the officially scheduled class meetings, and (b) you have written permission to withdraw from your parents or guardian.', 'requirements' => ['Written permission to withdraw from parents or guardian', 'Accumulated absences must be less than 20% of scheduled classes'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'Both conditions are required by Page 20 of the Student Manual.'],
                    ['step_number' => 3, 'title' => 'Secure Dean\'s Recommendation and Registrar Approval', 'instructions' => 'Secure the recommendation of your College Dean and submit the withdrawal request for approval by the University Registrar (if withdrawing a dissolved subject, process it before the prelim examination).', 'requirements' => ['Recommendation of the College Dean', 'Approval of the University Registrar'], 'office' => 'College Dean\'s Office & Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 19–20, 31', 'explanation' => 'Official withdrawal results in a mark of W (Withdrawn) rather than DR or 5.0.'],
                    ['step_number' => 4, 'title' => 'Settle Applicable Withdrawal Charges at Accounting', 'instructions' => 'Settle the applicable withdrawal charges at the Accounting Department based on when the withdrawal is filed: During enrollment period: Registration fees only; Within 1st week of classes: 10% of total tuition fees + registration fees; Within 2nd week of classes: 20% of total tuition fees + registration fees; After 2nd week of classes: Full (100%) charges regardless of whether or not the student attended classes.', 'requirements' => ['Approved withdrawal form'], 'office' => 'Accounting Department', 'source_reference' => 'Student Manual, pp. 31–32', 'explanation' => 'Tuition charges depend strictly on the week the official withdrawal is filed.'],
                ],
                'requirements' => [
                    'Written permission to withdraw from parents or guardian',
                    'Accumulated absences must be less than 20% of scheduled classes',
                    'Filed prior to the midterm examination',
                    'Recommendation of the College Dean and approval of the University Registrar',
                ],
                'responsible_office' => 'College Dean / Department Chairperson, Registrar\'s Office, and Accounting Department',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, pages 19–20, 26, 31–32 (PDF pages 28–29, 35, 40–41)',
                'validation_needed' => [
                    'Confirm the exact form name used at UCLM for single-subject withdrawal vs. full-term enrollment withdrawal.',
                ],
            ],
            [
                'entry_code' => 'C7',
                'title' => 'Withdrawal of Enrollment (Full-Term Enrollment Withdrawal)',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'I need to withdraw my entire enrollment for the semester. What documents do I need to submit?',
                ],
                'approved_answer' => 'A student withdrawing their enrollment must accomplish a Withdrawal Form at the Registrar\'s Office and attach their study load and student copy of the enrollment form. If the withdrawal is processed by a representative/proxy, a notarized Written Authority is required.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Obtain and Accomplish a Withdrawal Form', 'instructions' => 'Obtain and fill out a Withdrawal Form.', 'requirements' => ['Accomplished Withdrawal Form'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Used for withdrawing your entire semester enrollment.'],
                    ['step_number' => 2, 'title' => 'Attach Official Study Load and Enrollment Form Copy', 'instructions' => 'Attach your official Study Load and the student copy of your Enrollment Form.', 'requirements' => ['Official Study Load', 'Student copy of the Enrollment Form'], 'office' => 'Student Applicant', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Required attachments under Section B.10 of the Student Manual.'],
                    ['step_number' => 3, 'title' => 'Prepare Notarized Written Authority (If Filed by Proxy)', 'instructions' => 'If filing through a proxy, present a notarized Written Authority executed by the enrolled student stating the proxy\'s name and address and bearing the signatures of both.', 'requirements' => ['If filed by proxy: Notarized Written Authority stating proxy\'s name and address, signed by both student and proxy'], 'office' => 'Student Applicant / Proxy', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Only required if the enrolled student cannot file in person.'],
                    ['step_number' => 4, 'title' => 'Submit Documents to Registrar and Settle Charges at Accounting', 'instructions' => 'Submit the documents to the Registrar\'s Office and settle applicable fee charges at the Accounting Department (10% of tuition + registration fees within the 1st week; 20% within the 2nd week; full charges after the 2nd week, unless due to a justifiable reason under MORPS Sec. 66).', 'requirements' => ['Completed Withdrawal Form and attachments'], 'office' => 'Registrar\'s Office and Accounting Department', 'source_reference' => 'Student Manual, pp. 22, 31–32', 'explanation' => 'Completes official withdrawal from the semester.'],
                ],
                'requirements' => [
                    'Accomplished Withdrawal Form',
                    'Official Study Load',
                    'Student copy of the Enrollment Form',
                    'If filed by proxy: Notarized Written Authority stating proxy\'s name and address, signed by both student and proxy',
                ],
                'responsible_office' => 'Registrar\'s Office and Accounting Department',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Accounting and Cashier', 'SAO and CSU'],
                'manual_page_number' => 'Student Manual, pages 22, 31–32 (PDF pages 31, 40–41)',
                'validation_needed' => [
                    'Confirm whether Dean\'s endorsement and SAO/Guidance exit interview are also required at UCLM before the Registrar accepts a full Withdrawal Form.',
                ],
            ],
            [
                'entry_code' => 'C8',
                'title' => 'Applying for a Certificate of Transfer Credential (Honorable Dismissal)',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'I am transferring to another school. How do I get my Transfer Credential (Honorable Dismissal) and Official TOR?',
                ],
                'approved_answer' => 'A student who desires to transfer to another school must apply for a Certificate of Transfer Credential (formerly Honorable Dismissal) at the University Registrar. It is issued only after the student has been cleared of all financial and property liabilities and has surrendered their school ID.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Apply for Transfer Credential and Student Clearance at the Registrar', 'instructions' => 'Apply for a Certificate of Transfer Credential and student clearance at the University Registrar.', 'requirements' => ['Transfer Credential Application', 'Student Clearance Form'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'Initiates your official transfer out of the University.'],
                    ['step_number' => 2, 'title' => 'Settle All Financial and Property Obligations and Pay Fees', 'instructions' => 'Settle all financial and property obligations across all required offices and pay the required document fees at the Accounting Department / Cashier.', 'requirements' => ['Zero financial and property liabilities', 'Payment of Transfer Credential and TOR fees'], 'office' => 'Accounting Department / Cashier & Clearance Offices', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'All offices on the clearance form must sign off.'],
                    ['step_number' => 3, 'title' => 'Surrender School ID and File Completed Clearance', 'instructions' => 'Surrender your school ID to the Registrar\'s Office and file your completed clearance.', 'requirements' => ['Completed student clearance', 'Surrendered school ID'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'Your school ID must be surrendered upon filing for transfer credentials.'],
                    ['step_number' => 4, 'title' => 'Claim Transfer Credential and Informative TOR After 7 Days', 'instructions' => 'Claim your Certificate of Transfer Credential and an informative copy of your TOR seven (7) days after filing the completed clearance with the University Registrar (a Statement of Good Moral Character may also be issued upon request).', 'requirements' => ['Claim stub / Official Receipt'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'Released 7 days after filing the completed clearance.'],
                    ['step_number' => 5, 'title' => 'Submit Return Slip from New School for Official TOR Mailing', 'instructions' => 'Once your new school sends the "return slip" to UC, the University Registrar will mail your official TOR and other records directly to your new school within thirty (30) days of receiving the return slip.', 'requirements' => ['Official Return Slip from receiving school'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 20', 'explanation' => 'Official school-to-school TOR transmission occurs within 30 days of return slip receipt.'],
                ],
                'requirements' => [
                    'Completed student clearance (zero financial and property liabilities)',
                    'Surrendered school ID',
                    'Payment of Transfer Credential and TOR fees',
                ],
                'responsible_office' => 'Registrar\'s Office and Accounting Department / Cashier',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Accounting and Cashier', 'SAO and CSU', 'Guidance Center'],
                'manual_page_number' => 'Student Manual, page 20 (PDF page 29)',
                'validation_needed' => [
                    'Confirm current fees for the Certificate of Transfer Credential, Informative TOR, and Good Moral Character certificate at UCLM.',
                    'Confirm which office issues the Good Moral Character certificate (Issue 3: Registrar, Guidance, or SAO).',
                ],
            ],
            [
                'entry_code' => 'C9',
                'title' => 'Cross-Enrollment and Summer Study in Another School',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'Can I cross-enroll or take a summer subject in another school if the subject is not offered this semester?',
                ],
                'approved_answer' => 'No student may enroll simultaneously in two schools without the prior approval of the University Registrar. A Permit to Cross-Enroll is issued by the Registrar only upon the recommendation of the College Dean, and only if the applicant is a candidate for graduation during the school year and the subject is not offered in the college during that school year or is in conflict with other subjects (max 6 units during summer). Summer study in another school is not granted for any subject the student previously failed at UC or any major, specialized, or professional subject.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Verify Cross-Enrollment Eligibility with Your Dean/Chairperson', 'instructions' => 'Verify with your Department Chairperson / College Dean that you meet the eligibility rules (graduating student with an unoffered/conflicting subject for cross-enrollment; or non-failed, non-major subject for summer study, up to 6 units maximum in summer).', 'requirements' => ['Graduating status evaluation / study load verification'], 'office' => 'College Dean\'s Office / Department Chairperson', 'source_reference' => 'Student Manual, pp. 20–21', 'explanation' => 'Failed UC subjects and major/professional subjects cannot be taken in another school.'],
                    ['step_number' => 2, 'title' => 'Secure Written Recommendation from Your College Dean', 'instructions' => 'Secure a written recommendation from your College Dean (and/or Department Chairperson).', 'requirements' => ['Written recommendation of the College Dean'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, pp. 20–21', 'explanation' => 'Endorses the specific subject and host school.'],
                    ['step_number' => 3, 'title' => 'Obtain Permit to Cross-Enroll from the University Registrar', 'instructions' => 'Submit the Dean\'s recommendation to the University Registrar to obtain the official Permit to Cross-Enroll prior to enrolling at the other institution.', 'requirements' => ['College Dean\'s recommendation'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 20–21', 'explanation' => 'You must obtain the Permit to Cross-Enroll before enrolling at the external school.'],
                ],
                'requirements' => [
                    'Must be a candidate for graduation during the current school year (for regular cross-enrollment)',
                    'Subject must not be offered in the college or must conflict with other required subjects',
                    'For summer study: Subject must not be a failed UC subject and must not be a major, specialized, or professional subject (max 6 units)',
                    'Recommendation of the College Dean and prior Permit to Cross-Enroll from the University Registrar',
                ],
                'responsible_office' => 'College Dean\'s Office and Registrar\'s Office',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 20–21 (PDF pages 29–30)',
                'validation_needed' => [
                    'Confirm current processing fee and form requirements for the Permit to Cross-Enroll at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C10',
                'title' => 'Evaluation or Accreditation of Academic Records (Continuing Students & Transferees)',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'When should I have my grades and prospectus evaluated for graduation, or how do I get my previous school\'s subjects credited as a transferee?',
                ],
                'approved_answer' => 'For Continuing Students (3rd/4th Year): Students entering the third year of a 4-year course (or fourth year of a 5-year course) are advised to get a copy of their course prospectus and present it to the University Registrar for official evaluation of subjects and credits earned. This evaluation is extended only once and serves as the student\'s guide until graduation. For Transferees: Evaluation or accreditation of subjects and units earned from a previous institution must be referred to and determined by the College Dean or Department Chairperson.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Continuing 3rd/4th-Year Students: Request Official Registrar Evaluation', 'instructions' => 'Obtain a copy of your program prospectus, pay the evaluation sheet fee (if applicable), and submit it to the University Registrar for evaluation. Keep the issued evaluation sheet safely as your permanent enrollment guide until graduation.', 'requirements' => ['Copy of course prospectus', 'Payment of evaluation sheet fee'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 21–22', 'explanation' => 'Extended once upon entering 3rd year (or 4th year for 5-year programs).'],
                    ['step_number' => 2, 'title' => 'Transferees: Submit Informative TOR to College Dean/Chairperson for Accreditation', 'instructions' => 'Present your Informative TOR and course descriptions from your previous school to your College Dean or Department Chairperson for subject accreditation determination.', 'requirements' => ['Informative copy of TOR with descriptive titles, units, and grades'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, pp. 14, 21', 'explanation' => 'The Dean or Chairperson determines which prior subjects are credited toward your UC curriculum.'],
                ],
                'requirements' => [
                    'Copy of course prospectus (for continuing 3rd/4th-year students)',
                    'Informative copy of TOR with descriptive titles, units, and grades (for transferees)',
                    'Payment of evaluation sheet fee (manual notes evaluation sheet is not free on initial issuance)',
                ],
                'responsible_office' => 'Registrar\'s Office and College Dean / Department Chairperson',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, pages 14, 21–22 (PDF pages 23, 30–31)',
                'validation_needed' => [
                    'Confirm the current evaluation sheet fee and specific scheduling windows per college at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C11',
                'title' => 'Changing or Correction of Encoded Grades',
                'category' => 'Academic',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'My instructor made a mistake in my final grade that was already encoded. How can a grade entry be corrected?',
                ],
                'approved_answer' => 'As a strict rule, no changing or correction of a grade entry in the grade sheet is allowed once it has been submitted and finally encoded by the University Registrar, except upon the teacher\'s submission of an official petition approved by the Vice Chancellor for Academic Affairs.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Consult Your Subject Instructor Regarding the Grade Discrepancy', 'instructions' => 'Consult your subject instructor/teacher immediately regarding the grade discrepancy and present any supporting class records, exams, or receipts.', 'requirements' => ['Supporting class records, exam papers, or grade slip'], 'office' => 'Subject Instructor / College Department', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Only the subject instructor can verify a computation or encoding error and initiate a petition.'],
                    ['step_number' => 2, 'title' => 'Teacher Submits Formal Petition for Grade Correction', 'instructions' => 'If an encoding or computation error is verified by the teacher, the teacher must initiate and submit a formal petition for grade correction.', 'requirements' => ['Formal petition submitted by the subject teacher'], 'office' => 'Subject Teacher / College Dean\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Students cannot file a grade change petition directly without the instructor.'],
                    ['step_number' => 3, 'title' => 'VCAA Approval and Registrar Grade Update', 'instructions' => 'The petition must be reviewed and approved by the Vice Chancellor for Academic Affairs before the University Registrar can update the encoded grade.', 'requirements' => ['Approval of the Vice Chancellor for Academic Affairs'], 'office' => 'Office of the Vice Chancellor for Academic Affairs & Registrar\'s Office', 'source_reference' => 'Student Manual, p. 22', 'explanation' => 'Once approved by the VCAA, the Registrar updates the official grade entry.'],
                ],
                'requirements' => [
                    'Formal petition submitted by the subject teacher',
                    'Approval of the Vice Chancellor for Academic Affairs',
                ],
                'responsible_office' => 'Subject Teacher / College Dean\'s Office, Office of the Vice Chancellor for Academic Affairs, and Registrar\'s Office',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, page 22 (PDF page 31)',
                'validation_needed' => [
                    'Confirm whether there is a strict prescription deadline for teachers to file a grade correction petition at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C12',
                'title' => 'Application for Graduation and Graduation Clearance',
                'category' => 'Enrollment',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'What is the procedure and deadline for applying for graduation and completing graduation clearance?',
                ],
                'approved_answer' => 'Candidates for graduation must apply for evaluation of their academic records during the semester preceding their graduation so any deficiency can be settled early. In their graduating term, they must file an Application for Graduation (usually one week after the close of enrollment) and accomplish their graduation clearance one day before final examinations. Fines are imposed on late graduation applications.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Apply for Official Record Evaluation (Semester Before Graduation)', 'instructions' => 'Semester before graduation: Apply for official record evaluation at the Registrar\'s Office on the announced dates to identify and resolve any subject or unit deficiencies.', 'requirements' => ['Prior academic record evaluation and clearance of deficiencies'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 27', 'explanation' => 'Ensures you have no missing prerequisites or curriculum deficiencies entering your final semester.'],
                    ['step_number' => 2, 'title' => 'File Application for Graduation (1 Week After Enrollment Period)', 'instructions' => 'One (1) week after the enrollment period of your graduating semester: Obtain an Application for Graduation form from the Registrar\'s Office and file your formal application on time (late applications incur a fine).', 'requirements' => ['Application for Graduation form (obtained from the Registrar\'s Office)'], 'office' => 'Registrar\'s Office', 'source_reference' => 'Student Manual, p. 27', 'explanation' => 'File on or before the deadline to avoid late application fines.'],
                    ['step_number' => 3, 'title' => 'Accomplish Graduation Clearance (1 Day Before Final Examinations)', 'instructions' => 'One (1) day before the final examinations: Accomplish your graduation clearance across all required offices to clear any financial and property obligations.', 'requirements' => ['Completed Graduation Clearance (cleared of all financial and property obligations)'], 'office' => 'Registrar\'s Office, College Dean\'s Office, and Accounting Department', 'source_reference' => 'Student Manual, p. 27', 'explanation' => 'Required before final graduation deliberation and release of credentials.'],
                ],
                'requirements' => [
                    'Prior academic record evaluation and clearance of deficiencies',
                    'Application for Graduation form (obtained from the Registrar\'s Office)',
                    'Completed Graduation Clearance (cleared of all financial and property obligations)',
                ],
                'responsible_office' => 'Registrar\'s Office, College Dean\'s Office, and Accounting Department',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Accounting and Cashier'],
                'manual_page_number' => 'Student Manual, page 27 (PDF page 36)',
                'validation_needed' => [
                    'Confirm the late graduation application fine amount and current graduation application deadlines at UCLM.',
                ],
            ],
            [
                'entry_code' => 'C13',
                'title' => 'Application for Latin Honors / Honor Graduates',
                'category' => 'Academic',
                'manual_category' => 'Category C: Registrar Services, Academic Records, & Enrollment Changes',
                'common_questions' => [
                    'What GPA do I need to graduate Cum Laude, Magna Cum Laude, or Summa Cum Laude, and how do I apply for graduation honors?',
                ],
                'approved_answer' => 'Students who meet the GPA, residency, minimum 18-unit study load, and good moral behavior requirements (no 5.0, DR, or INC marks throughout their entire curricular program, and never found guilty of any serious offense) are encouraged to apply for honors by submitting an application letter to their College Dean/Chairperson two (2) weeks after the midterm examination of their graduating semester.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Verify Latin Honors GPA, Load, Residency, and Conduct Eligibility', 'instructions' => 'Verify that you meet the GPA and academic criteria (computed from 1st year 1st semester to last semester, including PE, NSTP, and summer grades): Summa Cum Laude / With Highest Distinction: 1.00–1.20 GPA (no grade below 2.00); Magna Cum Laude / With High Distinction: 1.21–1.45 GPA (no grade below 2.30); Cum Laude / With Distinction: 1.46–1.70 GPA (no grade below 2.50); no 5.0, DR, or INC marks; minimum 18 units per semester; and no record of any serious disciplinary offense.', 'requirements' => ['Required GPA with no grade below threshold and no 5.0, DR, or INC marks', 'Minimum 18 units per semester & residency requirement', 'No record of any serious disciplinary offense'], 'office' => 'Student Candidate', 'source_reference' => 'Student Manual, pp. 28–30', 'explanation' => 'Students who meet the GPA but overstayed by one (1) summer may qualify for Special Awards (Academic Excellence with First, Second, or Third Honors).'],
                    ['step_number' => 2, 'title' => 'Submit Formal Application Letter to Your College Dean (2 Weeks After Midterms)', 'instructions' => 'Two (2) weeks after the midterm examination: Submit a formal application letter for honors to your College Dean or Department Chairperson.', 'requirements' => ['Application letter submitted to the College Dean/Chairperson two (2) weeks after the midterm examination'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, p. 30', 'explanation' => 'Must be submitted within the 2-week post-midterm window.'],
                    ['step_number' => 3, 'title' => 'Dean Secures Scholastic Records and Endorses Candidate List', 'instructions' => 'The Dean/Chairperson secures an informative copy of your scholastic records from the Registrar\'s Office and forwards the endorsed list of candidates to the Office of the Vice Chancellors for Academic Affairs / Campus Director (who furnishes a copy to the SAO).', 'requirements' => ['Informative copy of scholastic records from Registrar'], 'office' => 'College Dean, Registrar\'s Office, VCAA / Campus Director, and SAO', 'source_reference' => 'Student Manual, p. 30', 'explanation' => 'SAO verifies that candidates have no serious disciplinary offense on record.'],
                    ['step_number' => 4, 'title' => 'Honors Committee Deliberation and Official List Publication', 'instructions' => 'The Honors Committee deliberates two (2) days after the final examinations for graduating students and publishes the official list at least fifteen (15) days before graduation. Any clarification or request for correction of published results must be filed within one (1) week after official publication.', 'requirements' => [], 'office' => 'Honors Committee', 'source_reference' => 'Student Manual, pp. 30–31', 'explanation' => 'Check the official published list at least 15 days before graduation.'],
                ],
                'requirements' => [
                    'Application letter submitted to the College Dean/Chairperson two (2) weeks after the midterm examination',
                    'Required GPA with no grade below the threshold and no 5.0, DR, or INC marks throughout the entire program',
                    'Residency: At least 2 successive years (4 semesters) for baccalaureate or 1 year (2 semesters) for associate courses; completed within max timeframe (8 semesters for 4-year course; 10 semesters for 5-year course)',
                    'Minimum 18 units carried per semester (except the final semester if prior semesters exceeded 18 units)',
                    'No record of any serious disciplinary offense',
                ],
                'responsible_office' => 'College Dean / Department Chairperson, Registrar\'s Office, Office of the Vice Chancellor for Academic Affairs / Campus Director, and Honors Committee',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'SAO and CSU'],
                'manual_page_number' => 'Student Manual, pages 28–31 (PDF pages 37–40)',
                'validation_needed' => [
                    'Confirm whether the June 2019 Honors criteria have had any recent CHED or UC system amendments regarding INC marks or K-12 curriculum credit loads.',
                ],
            ],

            // ── Category D: Admission, Retention, & Classroom Academic Policies ───
            [
                'entry_code' => 'D1',
                'title' => 'Admission Credentials for New, Transfer, Returnee, Shifting, and Special Students',
                'category' => 'Enrollment',
                'manual_category' => 'Category D: Admission, Retention, & Classroom Academic Policies',
                'common_questions' => [
                    'What requirements do I need to submit to enroll as a new freshman, transferee, returnee, or shiftee?',
                ],
                'approved_answer' => 'Admission requirements depend on the applicant\'s classification and degree program. Enrollment must be completed in person during the prescribed registration period (enrollment by proxy is not allowed).',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Prepare Required Admission Credentials Based on Your Student Classification', 'instructions' => 'Prepare the required credentials: For New Students (High School Graduates): High school report card (Form 138), NSO (now PSA) Birth Certificate, Certificate of Good Moral Character, plus DOH medical exams for Maritime courses. For Transfer Students: Certificate of Transfer Credential, Statement of Good Moral Character, Informative TOR (at least 30 units remaining, min 2.5 GPA, no more than 2 failures), and Guidance Office entrance exam. For Returnee & Shifting Students: Program-specific admission and retention clearances.', 'requirements' => ['Classification-specific admission credentials (Form 138 / Transfer Credential & Informative TOR, PSA Birth Certificate, Good Moral Character Certificate)'], 'office' => 'Student Applicant', 'source_reference' => 'Student Manual, pp. 14–19', 'explanation' => 'Enrollment by proxy is not allowed.'],
                    ['step_number' => 2, 'title' => 'Submit Credentials and Complete Assessment and Payment', 'instructions' => 'Submit credentials during the prescribed registration period and complete assessment and fee payment arrangements with the Registrar\'s Office, College Dean\'s Office, and Accounting Department.', 'requirements' => ['Complete admission dossier and down payment'], 'office' => 'Registrar\'s Office, College Dean\'s Office, Guidance Center, Medical Clinic, and Accounting Department', 'source_reference' => 'Student Manual, pp. 14–19', 'explanation' => 'Complete all registration steps within the scheduled enrollment window.'],
                ],
                'requirements' => [
                    'New Students: Certified Form 138, NSO/PSA Birth Certificate, Certificate of Good Moral Character (plus annual DOH medical tests for Maritime students)',
                    'Transfer Students: Certificate of Transfer Credential, Statement of Good Moral Character, Informative copy of TOR, Guidance Office entrance exam',
                ],
                'responsible_office' => 'Registrar\'s Office, College Dean\'s Office, Guidance Center, Medical Clinic (for Maritime), and Accounting Department',
                'primary_review_office' => 'Registrar\'s Office',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Guidance Center', 'Medical and Dental Clinic', 'Accounting and Cashier', 'SAO and CSU'],
                'manual_page_number' => 'Student Manual, pages 14–19 (PDF pages 23–28)',
                'validation_needed' => [
                    'Confirm updated Senior High School (SHS) Form 138 / SF9 and MARINA/CHED medical requirements currently enforced at UCLM.',
                ],
            ],
            [
                'entry_code' => 'D2',
                'title' => 'Academic Probation / Re-Admission After Failing One-Third (1/3) of Enrolled Units',
                'category' => 'Academic',
                'manual_category' => 'Category D: Admission, Retention, & Classroom Academic Policies',
                'common_questions' => [
                    'I failed several subjects last semester (one-third of my load). Can I still re-enroll in my course?',
                ],
                'approved_answer' => 'Under the University\'s Retention Criteria, a student who fails in one-third (1/3) or more of the units for which they are registered in any semester or summer session is subject to progressive retention restrictions and requires Dean approval to re-enroll.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'First Instance of Failing 1/3 of Registered Units: Secure Dean\'s Recommendation', 'instructions' => 'First time failing 1/3 of registered units: You may not re-enroll in the same program without securing the written recommendation of your College Dean.', 'requirements' => ['Written recommendation of the current College Dean'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, pp. 15, 18–19', 'explanation' => 'Consult your College Dean for evaluation and academic probation conditions.'],
                    ['step_number' => 2, 'title' => 'Second Instance of Failing 1/3 of Registered Units: Apply to Shift Program', 'instructions' => 'Second time failing 1/3 of registered units: You can no longer continue in the same program, but you may apply to shift to another program if accepted by the College Dean of the new program (or be referred for career evaluation by the Dean\'s Office, Guidance Center, or SAO). Note: Failing a third time in a similar manner results in permanent disqualification from registration in the University.', 'requirements' => ['Written acceptance from the College Dean of a new degree program (shifting)'], 'office' => 'College Dean\'s Office, Guidance Center, and Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 15, 18–19', 'explanation' => 'Career evaluation at the Guidance Center helps identify an appropriate new program.'],
                ],
                'requirements' => [
                    '1st instance: Written recommendation of the current College Dean',
                    '2nd instance: Written acceptance from the College Dean of a new degree program (shifting)',
                ],
                'responsible_office' => 'College Dean\'s Office, Guidance Center, and Registrar\'s Office',
                'primary_review_office' => 'Academic Deans and VCAA',
                'secondary_review_offices' => ['Registrar\'s Office', 'Guidance Center'],
                'manual_page_number' => 'Student Manual, pages 15, 18–19 (PDF pages 24, 27–28)',
                'validation_needed' => [
                    'Confirm whether specific UCLM colleges (Nursing, Accountancy, Engineering, Maritime, Customs) enforce stricter supplemental retention cutoffs.',
                ],
            ],
            [
                'entry_code' => 'D3',
                'title' => 'Study Load and Overload Request for Graduating Students',
                'category' => 'Enrollment',
                'manual_category' => 'Category D: Admission, Retention, & Classroom Academic Policies',
                'common_questions' => [
                    'How many units am I allowed to enroll in, and how can a graduating student request a 3-unit overload?',
                ],
                'approved_answer' => 'A full-time student carries 18 units or the regular load prescribed by their curriculum. Graduating students may be permitted an overload of up to three (3) units in excess of the regular load, provided the total load does not exceed 21 units (loads above 21 units are strictly prohibited). During the summer term, the maximum load is nine (9) units with no overload allowed.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Verify Graduating Status and Prerequisite Completion', 'instructions' => 'Verify that you are a graduating student and that you have passed all prerequisite subjects (no advance subject may be assigned unless its prerequisite has been satisfactorily passed).', 'requirements' => ['Must be in the graduating class', 'All prerequisite subjects satisfactorily passed'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, p. 23', 'explanation' => 'Overloads are strictly limited to graduating students.'],
                    ['step_number' => 2, 'title' => 'Confirm Unit Cap Compliance', 'instructions' => 'Confirm that your requested overload does not exceed three (3) additional units and that your total semester load does not exceed 21 units (summer maximum is 9 units with no overload).', 'requirements' => ['Total semester load must not exceed 21 units'], 'office' => 'College Dean / Department Chairperson', 'source_reference' => 'Student Manual, p. 23', 'explanation' => 'Loads above 21 units are prohibited under Page 23 of the Student Manual.'],
                    ['step_number' => 3, 'title' => 'Submit Study Load Overload Request for VCAA Approval', 'instructions' => 'Submit a request for study load overload for approval by the Vice Chancellor for Academic Affairs through your College Dean/Chairperson and the Registrar\'s Office.', 'requirements' => ['Approval of the Vice Chancellor for Academic Affairs'], 'office' => 'College Dean / Department Chairperson, Office of the Vice Chancellor for Academic Affairs, and Registrar\'s Office', 'source_reference' => 'Student Manual, p. 23', 'explanation' => 'Once approved, submit to the Registrar for enrollment encoding.'],
                ],
                'requirements' => [
                    'Must be in the graduating class',
                    'All prerequisite subjects satisfactorily passed',
                    'Total semester load must not exceed 21 units (summer load max 9 units, no overload)',
                    'Approval of the Vice Chancellor for Academic Affairs',
                ],
                'responsible_office' => 'College Dean / Department Chairperson, Office of the Vice Chancellor for Academic Affairs, and Registrar\'s Office',
                'primary_review_office' => 'Academic Deans and VCAA',
                'secondary_review_offices' => ['Registrar\'s Office'],
                'manual_page_number' => 'Student Manual, page 23 (PDF page 32)',
                'validation_needed' => [
                    'Confirm current CHED curriculum maximum unit caps for programs whose regular prescribed load already exceeds 21 units.',
                ],
            ],
            [
                'entry_code' => 'D4',
                'title' => 'Class Attendance, Tardiness, Cut System, and Teacher Absence Waiting Rule',
                'category' => 'Academic',
                'manual_category' => 'Category D: Admission, Retention, & Classroom Academic Policies',
                'common_questions' => [
                    'How many absences or lates are allowed before getting a failing/dropped mark, and how long should our class wait if the teacher hasn\'t arrived?',
                ],
                'approved_answer' => 'Tardiness: Arriving within 15 minutes after the second bell is marked late; arriving more than 15 minutes after the second bell is marked absent; three (3) instances of tardiness equal one (1) cut (absence). Maximum Absences / Cut System: Absences must not exceed 20% of prescribed class periods (10 absences for a 3-unit lecture or 1-unit lab course; or 3 x weekly meetings + 1). When a Teacher is Absent / Late: Students must wait quietly inside the classroom for the required waiting time before the class Beadle notifies the Dean.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Wait Quietly Inside the Classroom for the Prescribed Waiting Time', 'instructions' => 'When the teacher does not arrive on time, students must wait quietly inside the classroom for: 10 minutes for a 1-hour class; 15 minutes for a 1.5-hour class; or 30 minutes for a 3-hour class.', 'requirements' => ['Compliance with mandatory classroom waiting time'], 'office' => 'Classroom / Class Beadle', 'source_reference' => 'Student Manual, pp. 24–25', 'explanation' => 'Do not leave the classroom before the prescribed waiting time has elapsed.'],
                    ['step_number' => 2, 'title' => 'Class Beadle Notifies the College Dean for a Substitute Teacher', 'instructions' => 'If the teacher does not show up within the elapsed waiting time, the class Beadle must inform the College Dean so a substitute teacher can be designated immediately.', 'requirements' => ['Notification of the College Dean by the class Beadle'], 'office' => 'College Dean\'s Office / Department Chairperson', 'source_reference' => 'Student Manual, pp. 24–25', 'explanation' => 'Allows the Dean\'s Office to send a substitute instructor.'],
                    ['step_number' => 3, 'title' => 'Wait 5 Additional Minutes for Substitute Designation Before Dismissal', 'instructions' => 'If no substitute teacher is designated within the next five (5) minutes after informing the Dean, the students may consider themselves officially dismissed.', 'requirements' => [], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, p. 25', 'explanation' => 'Students are only officially dismissed after the 5-minute post-notification window.'],
                ],
                'requirements' => [
                    'Compliance with the mandatory classroom waiting time and notification of the College Dean by the class Beadle before leaving',
                ],
                'responsible_office' => 'College Dean\'s Office / Department Chairperson',
                'primary_review_office' => 'Academic Deans and VCAA',
                'secondary_review_offices' => ['Registrar\'s Office'],
                'manual_page_number' => 'Student Manual, pages 24–26 (PDF pages 33–35)',
                'validation_needed' => [
                    'Issue 1 Conflict Resolution Required: Page 19 and Page 25 state excessive absences result in a failing grade of 5.0, whereas Page 24 and Page 26 state exceeding 20% absences results in DR (Dropped). Confirm with UCLM Registrar and VCAA which mark is encoded.',
                ],
            ],
            [
                'entry_code' => 'D5',
                'title' => 'Taking Major Examinations, Assessment Slip Requirement, and Grading Remarks',
                'category' => 'Academic',
                'manual_category' => 'Category D: Admission, Retention, & Classroom Academic Policies',
                'common_questions' => [
                    'What do I need to present to take my Prelim, Midterm, Semi-Final, or Final exam, and what does an \'NG\' or \'3W\' mark on my grade slip mean?',
                ],
                'approved_answer' => 'There are four general examinations every term: Prelim, Midterm, Semi-final, and Final. No student is allowed to take an official examination unless they present a validated assessment slip issued by the Accounting Department. Two-thirds (2/3) of a subject grade comes from class standing and one-third (1/3) from major examinations.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Check Official Examination Dates', 'instructions' => 'Check the official examination dates posted on campus bulletin boards.', 'requirements' => [], 'office' => 'College Department / Registrar', 'source_reference' => 'Student Manual, p. 25', 'explanation' => 'Verify your exam schedule ahead of time.'],
                    ['step_number' => 2, 'title' => 'Pay Periodic Installment and Validate Assessment Slip at Accounting', 'instructions' => 'Pay the required 25% periodic installment (plus any old account balance, less down payment) and have your assessment slip validated by the Accounting Department / Cashier prior to the exam.', 'requirements' => ['Validated assessment slip issued by the Accounting Department'], 'office' => 'Accounting Department / Cashier', 'source_reference' => 'Student Manual, pp. 25, 31', 'explanation' => 'Required before each of the four major examinations.'],
                    ['step_number' => 3, 'title' => 'Present Validated Assessment Slip to Proctor/Instructor', 'instructions' => 'Present the validated assessment slip to your proctor/instructor to take the examination.', 'requirements' => ['Validated assessment slip'], 'office' => 'Subject Instructor / Exam Proctor', 'source_reference' => 'Student Manual, p. 25', 'explanation' => 'No student is allowed to take an official examination without a validated assessment slip.'],
                    ['step_number' => 4, 'title' => 'Review Official University Grading Remarks', 'instructions' => 'Understand official grading remarks: 1.0 to 3.0 (Passing); 3.1 to 3.5 (Conditional mid-term grade); 3W (Mid-term warning for 3.5 or below); 5.0 (Failed); W (Withdrawn before mid-term); DR (Dropped); NG (No Grade — failed to take major exam or complete requirements, applicable only during mid-term for undergraduate programs); NA (Not Attending).', 'requirements' => [], 'office' => 'Subject Instructor & Registrar\'s Office', 'source_reference' => 'Student Manual, pp. 26–27', 'explanation' => 'Final grades can only be Passed (1.0–3.0) or Failed (5.0).'],
                ],
                'requirements' => [
                    'Validated assessment slip issued by the Accounting Department',
                ],
                'responsible_office' => 'Accounting Department / Cashier, Subject Instructor, and College Dean\'s Office',
                'primary_review_office' => 'Accounting and Cashier',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Registrar\'s Office'],
                'manual_page_number' => 'Student Manual, pages 25–27, 31 (PDF pages 34–36, 40)',
                'validation_needed' => [
                    'Issue 5: Confirm the procedure at UCLM for securing a Promissory Note or temporary exam permit.',
                    'Issue 6: Confirm the deadline and procedure for completing a mid-term NG (No Grade) or taking a special/make-up examination.',
                ],
            ],

            // ── Category E: Accounting, Tuition, Scholarships, & Financial Aid ────
            [
                'entry_code' => 'E1',
                'title' => 'Tuition Payment Options (Installment Schedule and 10% Cash Discount)',
                'category' => 'Financial',
                'manual_category' => 'Category E: Accounting, Tuition, Scholarships, & Financial Aid',
                'common_questions' => [
                    'How is tuition divided if I pay in installments, and is there a discount if I pay in full cash?',
                ],
                'approved_answer' => 'All fees are computed on a semester/term basis and may be paid in cash or in four (4) equal installments corresponding to the four major examination periods. Students who pay their tuition in full cash on or before the posted deadline receive a 10% discount on tuition (excluding non-academic subjects).',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Option A — Full Cash Payment (10% Tuition Discount)', 'instructions' => 'Check the Accounting Department bulletin board for the full-payment discount deadline, and pay your full semester assessment at the Cashier on or before the deadline to receive the 10% tuition discount (excluding non-academic subjects).', 'requirements' => ['Student assessment form / statement of account'], 'office' => 'Accounting Department / Cashier', 'source_reference' => 'Student Manual, p. 31', 'explanation' => 'The 10% discount applies to tuition fees excluding non-academic subjects.'],
                    ['step_number' => 2, 'title' => 'Option B — Four (4) Equal Periodic Installments', 'instructions' => 'Pay the entrance/down payment upon enrollment, then pay the remaining school fees in four (4) equal installments before each major examination period (Prelim, Midterm, Semi-final, Final: each 25% of total assessment plus old account, less down payment).', 'requirements' => ['Student assessment form / statement of account'], 'office' => 'Accounting Department / Cashier', 'source_reference' => 'Student Manual, p. 31', 'explanation' => 'Each installment payment validates your assessment slip for the corresponding major exam.'],
                ],
                'requirements' => [
                    'Student assessment form / statement of account',
                ],
                'responsible_office' => 'Accounting Department / Cashier',
                'primary_review_office' => 'Accounting and Cashier',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, page 31 (PDF page 40)',
                'validation_needed' => [
                    'Confirm current payment channels accepted at UCLM (on-campus Cashier, bank over-the-counter, online banking, GCash/Maya) and how online receipts are validated for exam permits.',
                ],
            ],
            [
                'entry_code' => 'E2',
                'title' => 'Maritime Medical Examination Failure — Full Tuition Refund Policy',
                'category' => 'Financial',
                'manual_category' => 'Category E: Accounting, Tuition, Scholarships, & Financial Aid',
                'common_questions' => [
                    'If I enrolled in a Maritime course and failed the required medical examination upon enrollment, can I get a refund of my tuition payment?',
                ],
                'approved_answer' => 'Yes. While general withdrawal charges normally apply to students withdrawing from courses, the Student Manual provides a specific exception for Maritime courses only: if a Maritime student fails the required medical examination upon enrollment, their payment shall be refunded without charges.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Obtain Official Medical Examination Result Upon Enrollment', 'instructions' => 'Obtain the official medical examination result showing non-qualification/failure upon enrollment.', 'requirements' => ['Official medical examination result upon enrollment (Maritime courses only)'], 'office' => 'University Medical Clinic / Accredited Medical Facility', 'source_reference' => 'Student Manual, p. 32', 'explanation' => 'Applies specifically to Maritime courses upon enrollment.'],
                    ['step_number' => 2, 'title' => 'Present Medical Result and Payment Receipts for Full Refund', 'instructions' => 'Present the medical result and your enrollment/payment receipts to your Maritime Dean\'s Office, the Registrar\'s Office, and the Accounting Department to process the full refund without withdrawal charges.', 'requirements' => ['Official medical examination result', 'Enrollment form / study load and official payment receipt'], 'office' => 'Accounting Department, Registrar\'s Office, and College of Maritime Education Dean\'s Office', 'source_reference' => 'Student Manual, p. 32', 'explanation' => 'Refunded without the standard 10%/20% withdrawal charges.'],
                ],
                'requirements' => [
                    'Official medical examination result upon enrollment (Maritime courses only)',
                    'Enrollment form / study load and official payment receipt',
                ],
                'responsible_office' => 'Accounting Department, Registrar\'s Office, and College of Maritime Education Dean\'s Office',
                'primary_review_office' => 'Accounting and Cashier',
                'secondary_review_offices' => ['Registrar\'s Office', 'Academic Deans and VCAA', 'Medical and Dental Clinic'],
                'manual_page_number' => 'Student Manual, page 32 (PDF page 41)',
                'validation_needed' => [
                    'Confirm the step-by-step refund voucher turnaround time at the UCLM Accounting Department.',
                ],
            ],
            [
                'entry_code' => 'E3',
                'title' => 'Applying for Scholarships and Financial Aid (Academic, Dean\'s List, Working Scholar, Athletics, Cultural, Service Units)',
                'category' => 'Financial',
                'manual_category' => 'Category E: Accounting, Tuition, Scholarships, & Financial Aid',
                'common_questions' => [
                    'What scholarships are available at UC and what are the grade requirements to apply or maintain my scholarship?',
                ],
                'approved_answer' => 'The Scholarship Office disseminates scholarship information, accepts applications, and monitors scholars. Available grants include: (1) Academic & Dean\'s List Scholarships (Summa tier: 1.00–1.20 GPA, min 2.00; Magna tier: 1.21–1.45 GPA, min 2.30; Cum Laude tier: 1.46–1.70 GPA, min 2.50), (2) Working Scholarship (90%+ HS average or 2.5+ college GPA, Guidance IQ & personality test, family income), (3) Athletics Scholarship (10%–100%), (4) Cultural Scholarship (25%–100%), (5) Service Unit Scholarships (Campus Ministry Choir, SAO Volunteers, Student Org Leaders, Guidance Peer Facilitators, Student Publication, NSTP), and (6) Maritime & Corporate Scholarships.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Inquire at the Scholarship Office or Sponsoring Unit', 'instructions' => 'Visit the Scholarship Office (or the respective sponsoring unit: Athletics Department, Student Cultural Services, or Maritime Scholarship Office) to inquire about open application slots and specific application forms.', 'requirements' => ['Grade credentials meeting the specific scholarship cutoff'], 'office' => 'Scholarship Office', 'source_reference' => 'Student Manual, pp. 12, 32–34', 'explanation' => 'Check active scholarship openings and filing dates for the semester.'],
                    ['step_number' => 2, 'title' => 'For Working Scholarship Applicants: Submit Credentials and Take Guidance Tests', 'instructions' => 'Present your report card (90%+ HS average) or college grades (2.5+ GPA) and family income documents, and take the required IQ and personality tests at the Guidance Office.', 'requirements' => ['90%+ HS grade or 2.5+ college GPA', 'Guidance Office IQ & personality test results', 'Proof of family income'], 'office' => 'Scholarship Office & Guidance Center', 'source_reference' => 'Student Manual, p. 33', 'explanation' => 'Working scholar selection considers grades, family income, and psychological appraisal results.'],
                    ['step_number' => 3, 'title' => 'For Dean\'s List Discount Applicants: Verify Honor Tier GPA', 'instructions' => 'Ensure your semester GPA meets the 1.00–1.70 honor tier with no grade below the cutoff, and coordinate with your College Dean and the Scholarship Office.', 'requirements' => ['Official Grade Slip meeting 1.00–1.70 GPA tier and minimum subject grade cutoff'], 'office' => 'College Dean\'s Office & Scholarship Office', 'source_reference' => 'Student Manual, pp. 32–33', 'explanation' => 'Summa tier: 1.00–1.20 (no grade below 2.00); Magna tier: 1.21–1.45 (no grade below 2.30); Cum Laude tier: 1.46–1.70 (no grade below 2.50).'],
                ],
                'requirements' => [
                    'Grade credentials meeting the specific scholarship cutoff',
                    'For Working Scholars: 90%+ HS grade or 2.5+ college GPA, Guidance Office IQ & personality test results, and proof of family income',
                ],
                'responsible_office' => 'Scholarship Office, Guidance Center (for Working Scholar testing), Athletics Department, and Student Cultural Services',
                'primary_review_office' => 'Scholarship Office',
                'secondary_review_offices' => ['Guidance Center', 'Campus Ministry'],
                'manual_page_number' => 'Student Manual, pages 12, 32–34 (PDF pages 21, 41–43)',
                'validation_needed' => [
                    'Confirm current application windows, documentary checklists, and active scholarship programs specifically available at the UCLM campus.',
                ],
            ],

            // ── Category F: Medical, Dental, Guidance, & Campus Ministry ──────────
            [
                'entry_code' => 'F1',
                'title' => 'Availing University Medical Clinic Services and Free Initial Medicines',
                'category' => 'Medical',
                'manual_category' => 'Category F: Medical, Dental, Guidance, & Campus Ministry Services',
                'common_questions' => [
                    'How do I visit the school clinic for a check-up or medicine, and what do I need to bring?',
                ],
                'approved_answer' => 'Students with medical problems can avail themselves of the University clinic\'s services on a first-come, first-served basis during clinic hours (8:00 AM to 9:00 PM on school days, no noon break; hours may vary per campus). Free initial doses of medicine are given for minor ailments (headache, abdominal pain, diarrhea, cough, colds, and fever), but medicines are dispensed only to the student concerned in person (never to a representative) to avoid untoward side effects.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Proceed Personally to the University Medical Clinic', 'instructions' => 'Proceed personally to the University Medical Clinic during clinic hours.', 'requirements' => ['Personal appearance of the student (medicines are never dispensed to a representative)'], 'office' => 'Medical and Dental Services (University Medical Clinic)', 'source_reference' => 'Student Manual, pp. 57–58', 'explanation' => 'Services are rendered on a first-come, first-served basis.'],
                    ['step_number' => 2, 'title' => 'Present Proof of Official Enrollment', 'instructions' => 'Present any one of the following proofs of official enrollment: (a) valid school ID, (b) official study load, (c) library card, or (d) certification from any University personnel or teacher certifying that you are officially enrolled.', 'requirements' => ['Valid school ID, study load, library card, or enrollment certification from a teacher/personnel'], 'office' => 'University Medical Clinic', 'source_reference' => 'Student Manual, p. 57', 'explanation' => 'Verifies bona fide student status.'],
                    ['step_number' => 3, 'title' => 'Undergo Consultation or Treatment', 'instructions' => 'Undergo consultation, vital signs check, or treatment (blood pressure taking, wound dressing, nebulization, physical exam, or initial medicine dosage) with the clinic nurse or University physician.', 'requirements' => [], 'office' => 'University Medical Clinic', 'source_reference' => 'Student Manual, pp. 57–58', 'explanation' => 'Free initial medicine doses are provided directly to the student for minor ailments.'],
                ],
                'requirements' => [
                    'Personal appearance of the student (medicines are never dispensed to a representative)',
                    'Valid school ID, study load, library card, or enrollment certification from a teacher/personnel',
                ],
                'responsible_office' => 'Medical and Dental Services (University Medical Clinic)',
                'primary_review_office' => 'Medical and Dental Clinic',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 57–58 (PDF pages 66–67)',
                'validation_needed' => [
                    'Confirm current UCLM Medical Clinic operating hours and exact room/building location on the UCLM campus.',
                ],
            ],
            [
                'entry_code' => 'F2',
                'title' => 'Validation of Outside Medical Certificates and Medical Clearance for Absences or PE Exemption',
                'category' => 'Medical',
                'manual_category' => 'Category F: Medical, Dental, Guidance, & Campus Ministry Services',
                'common_questions' => [
                    'I was absent due to illness and have a medical certificate from an outside doctor. Will my instructor accept it directly?',
                ],
                'approved_answer' => 'No. Medical certificates issued by an outside physician must be validated by the school physician within two (2) weeks from issuance. Only medical certificates validated by the University physician will be accepted by instructors. Additionally, certifications for Physical Education (PE) exemption are granted only by the University physician after a thorough physical examination is completed at the University clinic.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Bring Original Outside Medical Certificate to the Clinic Within 2 Weeks', 'instructions' => 'Bring the original medical certificate issued by your attending outside physician to the University Medical Clinic within two (2) weeks from its date of issuance.', 'requirements' => ['Original medical certificate from the outside physician (submitted within 2 weeks of issuance)'], 'office' => 'Medical and Dental Services (University Physician)', 'source_reference' => 'Student Manual, p. 58', 'explanation' => 'Certificates submitted beyond two (2) weeks from issuance violate the validation window on Page 58.'],
                    ['step_number' => 2, 'title' => 'Have the University Physician Examine and Validate the Certificate', 'instructions' => 'Present your valid school ID or study load and have the University physician examine/validate the certificate (or undergo a physical examination if requesting a Medical Clearance or PE Exemption).', 'requirements' => ['Valid school ID or study load'], 'office' => 'University Physician (Medical Clinic)', 'source_reference' => 'Student Manual, pp. 57–58', 'explanation' => 'PE exemptions are granted only by the University physician after a physical exam.'],
                    ['step_number' => 3, 'title' => 'Present the Validated Medical Certificate to Your Instructor(s)', 'instructions' => 'Present the validated medical certificate to your subject instructor(s).', 'requirements' => ['Clinic-validated medical certificate'], 'office' => 'Subject Instructor(s)', 'source_reference' => 'Student Manual, p. 58', 'explanation' => 'Instructors only honor outside medical certificates that bear the University physician\'s validation.'],
                ],
                'requirements' => [
                    'Valid school ID or study load',
                    'Original medical certificate from the outside physician (must be submitted for validation within 2 weeks of issuance)',
                ],
                'responsible_office' => 'Medical and Dental Services (University Physician)',
                'primary_review_office' => 'Medical and Dental Clinic',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 57–58 (PDF pages 66–67)',
                'validation_needed' => [
                    'Confirm the current schedule of the University Physician at UCLM for medical certificate validation and PE exemption evaluations.',
                ],
            ],
            [
                'entry_code' => 'F3',
                'title' => 'Availing University Dental Clinic Services and Tooth Extraction',
                'category' => 'Medical',
                'manual_category' => 'Category F: Medical, Dental, Guidance, & Campus Ministry Services',
                'common_questions' => [
                    'What dental services are free at the school dental clinic, and what are the requirements for tooth extraction?',
                ],
                'approved_answer' => 'The University Dental Clinic is open from 8:00 AM–12:00 NN and 1:00 PM–5:00 PM during school days (hours may vary per campus) on a first-come, first-served basis. It provides daily consultations, annual dental exams (for 1st-year students, transferees, returnees, and PE 1 students), temporary tooth fillings, oral prophylaxis, simple tooth extractions, initial doses of prescribed post-extraction medicines, and dental certificates (issued only to students who underwent extraction or have consultation records at the clinic). Complicated cases (impacted/third molars, prosthodontics, orthodontics, endodontics, periodontal problems) are referred to private dentists or hospitals.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Visit the University Dental Clinic and Present Proof of Enrollment', 'instructions' => 'Visit the University Dental Clinic during operating hours and present your valid school ID, study load, library card, or teacher certification of enrollment.', 'requirements' => ['Valid school ID, study load, library card, or enrollment certification'], 'office' => 'Medical and Dental Services (University Dental Clinic)', 'source_reference' => 'Student Manual, pp. 57–59', 'explanation' => 'Served on a first-come, first-served basis.'],
                    ['step_number' => 2, 'title' => 'Comply with Special Clearance Requirements for Simple Tooth Extraction', 'instructions' => 'If seeking a simple tooth extraction: (a) If you are a minor (below 18 years old), you must be accompanied by your parent or guardian with a duly signed dental clearance; (b) If you have a medical condition (e.g., heart illness, asthma, diabetes), you must first submit a medical certificate/clearance from your attending physician.', 'requirements' => ['For minors: Personal accompaniment by parent/guardian + signed dental clearance', 'For medically compromised patients: Medical certificate from attending physician'], 'office' => 'University Dental Clinic', 'source_reference' => 'Student Manual, pp. 58–59', 'explanation' => 'Mandatory patient safety requirement prior to tooth extraction.'],
                    ['step_number' => 3, 'title' => 'Receive Treatment, Initial Medicine Dosage, and Dental Certificate', 'instructions' => 'After treatment or extraction, receive your initial medicine dosage and, if applicable, a dental certificate or temporary exemption from classes/duties.', 'requirements' => [], 'office' => 'University Dental Clinic', 'source_reference' => 'Student Manual, pp. 58–59', 'explanation' => 'Dental certificates are issued only to students with clinic extraction or consultation records.'],
                ],
                'requirements' => [
                    'Valid school ID, study load, library card, or enrollment certification',
                    'For minor students seeking tooth extraction: Personal accompaniment by a parent/guardian and a duly signed dental clearance',
                    'For patients with medical compromises (heart illness, asthma, diabetes, etc.): Medical certificate from an attending physician',
                ],
                'responsible_office' => 'Medical and Dental Services (University Dental Clinic)',
                'primary_review_office' => 'Medical and Dental Clinic',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 57–59 (PDF pages 66–68)',
                'validation_needed' => [
                    'Confirm current UCLM Dental Clinic hours and whether appointments are scheduled in advance or strictly walk-in.',
                ],
            ],
            [
                'entry_code' => 'F4',
                'title' => 'Availing Guidance and Counseling Services',
                'category' => 'Guidance',
                'manual_category' => 'Category F: Medical, Dental, Guidance, & Campus Ministry Services',
                'common_questions' => [
                    'How can I schedule counseling, take psychological/career tests, or get help from the Guidance Center?',
                ],
                'approved_answer' => 'The Guidance Center assists students with personal growth, academic adjustment, career evaluation, and emotional well-being. It provides eight (8) core services: Counseling Service, Follow-Up Service, Information Service, Orientation Service, Student Inventory, Appraisal Service (psychological tests for aptitude, achievement, intelligence, and personality), Consultation Service, and the Peer Facilitating Program, as well as job placement coordination.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Proceed to the Campus Guidance Center', 'instructions' => 'Proceed to the Guidance Center on campus (or approach a Guidance Counselor / Peer Facilitator).', 'requirements' => ['Bona fide enrolled student status'], 'office' => 'Guidance and Counseling Services (Guidance Center)', 'source_reference' => 'Student Manual, pp. 11, 59–60', 'explanation' => 'Open to all bona fide students seeking personal, academic, emotional, or career support.'],
                    ['step_number' => 2, 'title' => 'Request Counseling, Psychological Appraisal, or Career Assistance', 'instructions' => 'State the service you wish to avail of (individual counseling, psychological/appraisal testing, student inventory update, academic follow-up, or career/job placement inquiry).', 'requirements' => [], 'office' => 'Guidance Center', 'source_reference' => 'Student Manual, pp. 59–60', 'explanation' => 'A Guidance Counselor will assist you or schedule your session.'],
                ],
                'requirements' => [
                    'Not specified in the manual (open to all bona fide students)',
                ],
                'responsible_office' => 'Guidance and Counseling Services (Guidance Center)',
                'primary_review_office' => 'Guidance Center',
                'secondary_review_offices' => [],
                'manual_page_number' => 'Student Manual, pages 11, 59–60 (PDF pages 20, 68–69)',
                'validation_needed' => [
                    'Confirm the exact room locations of the UCLM Guidance Center counselors (by college assignment) and whether students can book counseling sessions online or via walk-in.',
                ],
            ],
            [
                'entry_code' => 'F5',
                'title' => 'Exemption from Roman Catholic Retreats and Recollections',
                'category' => 'Other Concerns',
                'manual_category' => 'Category F: Medical, Dental, Guidance, & Campus Ministry Services',
                'common_questions' => [
                    'I belong to a non-Catholic religious denomination. How can I request an exemption from attending a Catholic retreat or recollection?',
                ],
                'approved_answer' => 'Students whose religious practices, affiliations, or denominations do not conform to or run counter to the Roman Catholic retreat or recollection activity may be exempted from attending, provided they submit a written notification to their College Dean and furnish a copy to the Campus Ministry at least one (1) week before the scheduled activity.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Prepare a Formal Written Letter Requesting Exemption', 'instructions' => 'Prepare a formal written letter requesting exemption from the retreat/recollection on account of your religious affiliation/denomination.', 'requirements' => ['Written exemption letter stating religious affiliation/denomination'], 'office' => 'Student Applicant', 'source_reference' => 'Student Manual, pp. 38–39', 'explanation' => 'Clearly state your religious affiliation and the scheduled retreat/recollection date.'],
                    ['step_number' => 2, 'title' => 'Submit Written Notification to Your College Dean', 'instructions' => 'Submit the written notification to your College Dean prior to the scheduled activity.', 'requirements' => ['Written letter to the College Dean'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, pp. 38–39', 'explanation' => 'Notifies your academic college of your religious exemption.'],
                    ['step_number' => 3, 'title' => 'Furnish a Copy to Campus Ministry at Least 1 Week Before the Activity', 'instructions' => 'Furnish a copy of the written notification to the Campus Ministry (CM) at least one (1) week before the scheduled religious activity.', 'requirements' => ['Copy furnished to the Campus Ministry at least one (1) week before the scheduled retreat/recollection'], 'office' => 'Campus Ministry (CM)', 'source_reference' => 'Student Manual, pp. 38–39', 'explanation' => 'Must be received by Campus Ministry at least 1 week prior to the event.'],
                ],
                'requirements' => [
                    'Written letter to the College Dean',
                    'Copy furnished to the Campus Ministry at least one (1) week before the scheduled retreat/recollection',
                ],
                'responsible_office' => 'College Dean\'s Office and Campus Ministry (CM)',
                'primary_review_office' => 'Campus Ministry',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 38–39 (PDF pages 47–48)',
                'validation_needed' => [
                    'Confirm whether a certification from the student\'s religious pastor/minister or parent is also requested by UCLM Campus Ministry or Deans.',
                ],
            ],

            // ── Category G: Student Organizations, Campus Activities, & Facilities ─
            [
                'entry_code' => 'G1',
                'title' => 'Securing a Student Activity Permit and Reserving Campus Venues / Audio-Visual Equipment',
                'category' => 'Facilities',
                'manual_category' => 'Category G: Student Organizations, Campus Activities, & Facilities',
                'common_questions' => [
                    'Our student organization or class wants to hold an event on campus and use a function room or sound system. How do we get an Activity Permit?',
                ],
                'approved_answer' => 'Only duly accredited student organizations (or authorized class/college representatives) are allowed to conduct activities. An Activity Permit must be secured from the SAO at least one (1) week before the actual date of an on-campus activity. In the Lapu-Lapu and Mandaue (UCLM), Banilad, and Mambaling campuses, the Building Administration Office manages the reservation of function rooms, audio-visual rooms, sound systems, and university buses. No student activity is allowed one (1) school week before Prelim, Midterm, Semi-final, or Final examinations.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure Activity Permit Form and Waiver from SAO (At Least 1 Week Prior)', 'instructions' => 'At least one (1) week before the activity: The authorized representative of the student organization, classroom, or college secures the Activity Permit Form and Waiver/Quitclaim from the Student Affairs Office (SAO).', 'requirements' => ['SAO Activity Permit Form and Waiver/Quitclaim (secured at least 1 week prior)'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, pp. 46–47', 'explanation' => 'No student activity is allowed one (1) school week before major examinations.'],
                    ['step_number' => 2, 'title' => 'Reserve Venue, AV Room, Sound System, or Bus with Building Administration', 'instructions' => 'Secure and attach the reservation form from the Building Supervisor\'s / Building Administration Office for the venue, function/AV room, sound system, or University bus reservation (if needed).', 'requirements' => ['Building Administration venue / sound system / bus reservation form'], 'office' => 'Building Administration Office (UCLM)', 'source_reference' => 'Student Manual, pp. 11, 47', 'explanation' => 'At UCLM, Building Administration manages function rooms, AV rooms, sound systems, and school buses.'],
                    ['step_number' => 3, 'title' => 'Accomplish Forms and Attach Required Annexes', 'instructions' => 'Accomplish the forms completely and attach all required annexes.', 'requirements' => ['Completed forms and activity annexes'], 'office' => 'Student Organization Representative', 'source_reference' => 'Student Manual, p. 47', 'explanation' => 'Ensure all activity details, participants, and schedules are complete.'],
                    ['step_number' => 4, 'title' => 'Obtain Written Endorsement of Faculty Adviser and College Dean', 'instructions' => 'Obtain the written endorsement of your Faculty Adviser and College Dean.', 'requirements' => ['Endorsement of the Faculty Adviser and College Dean'], 'office' => 'Faculty Adviser & College Dean\'s Office', 'source_reference' => 'Student Manual, p. 47', 'explanation' => 'Verifies faculty supervision and college approval.'],
                    ['step_number' => 5, 'title' => 'Submit Forms to SAO and CSU for Review and Endorsement', 'instructions' => 'Submit the accomplished Activity Permit forms and attachments to the SAO and Civil Security Unit (CSU) for review, recommendation, and endorsement to the Campus Director.', 'requirements' => ['Review/endorsement by SAO and CSU'], 'office' => 'Student Affairs Office (SAO) & Civil Security Unit (CSU)', 'source_reference' => 'Student Manual, p. 47', 'explanation' => 'SAO and CSU verify policy and security compliance.'],
                    ['step_number' => 6, 'title' => 'Wait for Final Approval by the Campus Director', 'instructions' => 'Wait for the Campus Director\'s approval before holding the event (if disapproved, an appeal may be made to the Vice Chancellor for Administrative Affairs).', 'requirements' => ['Final approval by the Campus Director'], 'office' => 'Campus Director\'s Office', 'source_reference' => 'Student Manual, p. 47', 'explanation' => 'Do not hold the event without final signed approval.'],
                    ['step_number' => 7, 'title' => 'Ensure Compulsory Physical Presence of Faculty Adviser During the Event', 'instructions' => 'During the activity: The Faculty Adviser (or duly authorized representative) must be physically present for the entire duration of the activity.', 'requirements' => ['Compulsory presence of the Faculty Adviser during the event'], 'office' => 'Faculty Adviser', 'source_reference' => 'Student Manual, p. 48', 'explanation' => 'Mandatory supervision requirement under Page 48 of the Student Manual.'],
                    ['step_number' => 8, 'title' => 'Submit Accomplishment Report and Financial Statement Within 3 Days After Event', 'instructions' => 'Within three (3) days after the activity: Submit an Activity Accomplishment Report and signed Financial Statement (attested by the President and Treasurer) to the SAO. Failure to submit the report within 3 days is ground for non-issuance of future activity permits.', 'requirements' => ['Post-activity report and financial statement submitted to SAO within 3 days after the event'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 48', 'explanation' => 'Required to maintain good standing for future permits.'],
                ],
                'requirements' => [
                    'SAO Activity Permit Form and Waiver/Quitclaim (secured at least 1 week prior)',
                    'Building Administration venue / sound system / bus reservation form',
                    'Endorsement of the Faculty Adviser and College Dean',
                    'Review/endorsement by SAO and CSU, and final approval by the Campus Director',
                    'Compulsory presence of the Faculty Adviser during the event',
                    'Post-activity report and financial statement submitted to SAO within 3 days after the event',
                ],
                'responsible_office' => 'Student Affairs Office (SAO), Building Administration Office, Civil Security Unit (CSU), College Dean\'s Office, and Campus Director\'s Office',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Building Administration', 'Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 11, 46–48 (PDF pages 20, 55–57)',
                'validation_needed' => [
                    'Confirm whether UCLM requires additional electronic permit routing or CAD approval for specific campus facilities.',
                ],
            ],
            [
                'entry_code' => 'G2',
                'title' => 'Posting Notices, Posters, or Streamers on Campus Bulletin Boards',
                'category' => 'Student Services',
                'manual_category' => 'Category G: Student Organizations, Campus Activities, & Facilities',
                'common_questions' => [
                    'How do we get permission to post an announcement or poster on a campus bulletin board?',
                ],
                'approved_answer' => 'All notices, posters, or streamers posted on campus bulletin boards require prior approval from the College Dean (for department/organization materials), an approval stamp from the Student Affairs Office (SAO), and recording by the Civil Security Unit (CSU) before posting. Ground-floor bulletin boards are reserved for University-wide announcements, while college/department boards are for college-specific announcements. Posting on walls, doors, or columns is strictly prohibited.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Secure College Dean Approval', 'instructions' => 'If the notice, poster, or streamer originates from a college department or student organization, secure the approval of your College Dean.', 'requirements' => ['College Dean approval (for department/student organization materials)'], 'office' => 'College Dean\'s Office', 'source_reference' => 'Student Manual, p. 36', 'explanation' => 'Required for all college or organization announcements.'],
                    ['step_number' => 2, 'title' => 'Present Material to SAO for Review and Approval Stamp', 'instructions' => 'Present the material to the Student Affairs Office (SAO) for review and stamping of approval.', 'requirements' => ['SAO stamp of approval'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, p. 36', 'explanation' => 'Unstamped posters will be removed.'],
                    ['step_number' => 3, 'title' => 'Have the Approved Material Recorded by CSU', 'instructions' => 'Bring the SAO-approved material to the Civil Security Unit (CSU) for official recording before placing it on the designated bulletin board.', 'requirements' => ['CSU recording prior to posting'], 'office' => 'Civil Security Unit (CSU)', 'source_reference' => 'Student Manual, p. 36', 'explanation' => 'Logs the approved posting dates with campus security.'],
                    ['step_number' => 4, 'title' => 'Post Only on Designated Bulletin Board and Remove Promptly After Due Date', 'instructions' => 'Post the material only on the designated bulletin board for the approved duration (minimum of 1 week to a maximum of 1 month), and remove it promptly after the due date. Do not post on walls, doors, or columns.', 'requirements' => [], 'office' => 'Student Organization / Poster Owner', 'source_reference' => 'Student Manual, pp. 36–37', 'explanation' => 'Posting on walls, doors, or columns is strictly prohibited.'],
                ],
                'requirements' => [
                    'College Dean approval (for department/student organization materials)',
                    'SAO stamp of approval',
                    'CSU recording prior to posting',
                ],
                'responsible_office' => 'Student Affairs Office (SAO), College Dean\'s Office, and Civil Security Unit (CSU)',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Building Administration'],
                'manual_page_number' => 'Student Manual, pages 36–37 (PDF pages 45–46)',
                'validation_needed' => [
                    'Confirm whether digital display monitors or official UCLM social media pages have supplemental posting guidelines managed by SAO.',
                ],
            ],
            [
                'entry_code' => 'G3',
                'title' => 'Student Organization Accreditation, Renewal, and Officer Clearance',
                'category' => 'Student Services',
                'manual_category' => 'Category G: Student Organizations, Campus Activities, & Facilities',
                'common_questions' => [
                    'How do we accredit or renew a student organization, and what are the requirements to run as an officer?',
                ],
                'approved_answer' => 'Any student group must obtain a Certificate of Accreditation from the SAO to operate legally on campus. Certificates of Accreditation are valid for one school year until July 15 and must be renewed on or before June 30 of each year. All student organization officers must meet strict academic and conduct qualifications and must secure SAO clearance before the end of every semester.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'Compile Complete Accreditation or Renewal Dossier', 'instructions' => 'Compile the accreditation requirements (or renewal requirements on or before June 30): (a) Constitution and By-Laws, (b) Letter of Intent, (c) Complete list of elected/interim officers (each officer must carry at least 18 units, have at least a 3.0 GPA, not have failed 1/3 of units in the previous semester, be of good moral character, and have no SAO disciplinary record), (d) Complete list of members, (e) Official list of Faculty Advisers/Consultants (employed at UC for at least 1 year), (f) Vision, mission, and objectives, (g) Calendar of activities and program of expenditures, (h) Endorsement letter from College Dean/Chairperson (or UCCP / mother organization), and (i) For Annual Renewal: Accomplishment Report and audited Financial Statements of the preceding school year.', 'requirements' => ['Complete accreditation/renewal dossier', 'Officer qualification compliance (18 units, min 3.0 GPA, did not fail 1/3 of units, good moral character, no disciplinary record)'], 'office' => 'Student Organization Applicant & College Dean', 'source_reference' => 'Student Manual, pp. 39–45', 'explanation' => 'Ensure all officers meet the academic and conduct requirements on Page 43.'],
                    ['step_number' => 2, 'title' => 'Submit Dossier to SAO for Evaluation and Certificate of Accreditation', 'instructions' => 'Submit the complete dossier to the Student Affairs Office (SAO) for evaluation and issuance of the Certificate of Accreditation by the Student Affairs Director.', 'requirements' => ['Complete dossier submitted to SAO'], 'office' => 'Student Affairs Office (SAO)', 'source_reference' => 'Student Manual, pp. 41–42', 'explanation' => 'Only duly accredited student organizations may operate or apply for activity permits on campus.'],
                ],
                'requirements' => [
                    'Complete accreditation/renewal dossier (Constitution & By-Laws, Letter of Intent, List of Officers, List of Members, Faculty Advisers, Vision/Mission, Calendar of Activities, Dean/UCCP Endorsement, plus Accomplishment & Audited Financial Reports for renewal)',
                    'Officer qualifications: Bona fide student, minimum 18-unit load, minimum 3.0 GPA, did not fail 1/3 of units in previous semester, good moral character, no SAO disciplinary record',
                ],
                'responsible_office' => 'Student Affairs Office (SAO) and College Dean / Department Chairperson',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 39–45 (PDF pages 48–54)',
                'validation_needed' => [
                    'Confirm whether the June 30 renewal date and July 15 expiration date have been adjusted at UCLM to align with the current academic calendar opening.',
                ],
            ],
            [
                'entry_code' => 'G4',
                'title' => 'Off-Campus Activities (Educational Tours / Field Trips) and Rights of Non-Participating Students',
                'category' => 'Academic',
                'manual_category' => 'Category G: Student Organizations, Campus Activities, & Facilities',
                'common_questions' => [
                    'Am I required to join an off-campus field trip or educational tour, and what happens to my grade if I cannot join?',
                ],
                'approved_answer' => 'Consistent with CHED Memorandum Order No. 63, series of 2017, participation in any off-campus activity is strictly voluntary and not compulsory. An off-campus activity cannot be made a substitute for a major examination to compel students to join. Furthermore, the proponent/instructor must provide a complementary or substitute activity for students who cannot participate or opt not to join. A non-participating student must not be given a failing mark for not joining the off-campus trip and shall instead be graded based on their accomplishment of the substitute activity.',
                'steps' => [
                    ['step_number' => 1, 'title' => 'If Opting NOT to Join: Request the Complementary / Substitute Activity', 'instructions' => 'If you are opting NOT to join an off-campus activity: Inform your subject instructor/proponent that you cannot participate and request and accomplish the required complementary/substitute academic activity so you can be graded fairly without penalty.', 'requirements' => ['Accomplishment of the assigned substitute academic activity'], 'office' => 'Subject Proponent / College Dean\'s Office', 'source_reference' => 'Student Manual, pp. 49–53', 'explanation' => 'Non-participating students must never be given a failing mark for not joining an off-campus trip.'],
                    ['step_number' => 2, 'title' => 'If Participating: Attend Pre-Departure Briefing and Submit Medical & Notarized Parental Consent', 'instructions' => 'If you ARE participating in an approved off-campus activity: (a) Attend the mandatory pre-activity consultation/orientation and pre-departure briefing, (b) Secure and submit a Medical Clearance issued by the University Medical Clinic, (c) Submit a duly notarized Parent\'s/Guardian\'s Permit/Consent (mandatory), and (d) Complete the post-activity debriefing and learning/observation journal.', 'requirements' => ['University Clinic Medical Clearance', 'Duly notarized Parent\'s/Guardian\'s Permit/Consent', 'Individual/group insurance', 'Observation/learning journal'], 'office' => 'Subject Proponent, College Dean\'s Office, SAO, and University Medical Clinic', 'source_reference' => 'Student Manual, pp. 49–53', 'explanation' => 'Failure to submit the duly notarized Parent\'s/Guardian\'s Permit/Consent disallows participation.'],
                ],
                'requirements' => [
                    'For participating students: University Clinic Medical Clearance, duly notarized Parent\'s/Guardian\'s Permit/Consent, individual/group insurance, and observation/learning journal',
                    'For proponents/organizers: SAO Activity Permit submitted at least 1 month prior, notarized Certificate/Report of Compliance submitted at least 15 days prior, 1:35–50 teacher-student ratio, accredited tour guides, and valid vehicle registration/insurance',
                ],
                'responsible_office' => 'Subject Proponent / College Dean\'s Office, Student Affairs Office (SAO), Medical Clinic, and Campus Director / Vice Chancellor for Academics/Administration',
                'primary_review_office' => 'Academic Deans and VCAA',
                'secondary_review_offices' => ['SAO and CSU', 'Medical and Dental Clinic'],
                'manual_page_number' => 'Student Manual, pages 49–53 (PDF pages 58–62)',
                'validation_needed' => [
                    'Confirm current CHED Regional Office VII off-campus compliance timelines enforced at UCLM.',
                ],
            ],

            // ── Section 4: Sensitive, Urgent, & Safety Concerns (S1–S7) ───────────
            [
                'entry_code' => 'S1',
                'title' => 'Reporting Sexual Harassment (Policy Against Sexual Harassment — RA 7877)',
                'category' => 'Safety and Security',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'sexual_harassment',
                'common_questions' => [
                    'How do I report sexual harassment by an instructor, coach, or person in authority?',
                    'Where do I file a formal sexual harassment complaint?',
                ],
                'approved_answer' => 'FORMAL REPORTING PATHWAY: Under the University Policy Against Sexual Harassment (Student Manual, pp. 84–87), any student, trainee, employee, or parent/guardian on behalf of a ward-student may file a written complaint (in the form of a letter, affidavit, or report) with: (1) The University President, (2) The University Chancellor, (3) Any University Official, or (4) The Disciplinary Committee through its Chairperson (SAO Director). Do not delay reporting through ordinary self-help checklists. You may submit a formal concern directly in SCMS or report in person to the Student Affairs Office (SAO) / Committee on Decorum and Investigation (CODI).',
                'steps' => [],
                'requirements' => [
                    'Written complaint (letter, affidavit, or report) containing, when practicable: (1) Name(s) and address(es) of complainant(s), (2) Name(s) and address(es) of respondent(s), (3) Substance and cause/grounds of the complaint, (4) When and where the incident happened, (5) Name(s) of any witness(es), and (6) Pertinent supporting papers or documents attached whenever possible.',
                ],
                'responsible_office' => 'University President, University Chancellor, Student Disciplinary Committee Chairperson (SAO Director), or any University Official (with Guidance Center support)',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Guidance Center'],
                'manual_page_number' => 'Student Manual, pages 84–87 (PDF pages 94–97)',
                'validation_needed' => [
                    'Confirm the current designated Committee on Decorum and Investigation (CODI) / Anti-Sexual Harassment Desk officers and confidential contact channels at the UCLM campus, as well as alignment with Republic Act No. 11313 (Safe Spaces Act).',
                ],
            ],
            [
                'entry_code' => 'S2',
                'title' => 'Reporting Physical Assault, Violence, Threats, Bullying, or Intimidation',
                'category' => 'Safety and Security',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'violence_bullying',
                'common_questions' => [
                    'I was physically assaulted, threatened, or bullied by another student. Who should I report to immediately?',
                ],
                'approved_answer' => 'FORMAL REPORTING & IMMEDIATE SAFETY PATHWAY: For immediate physical danger or active campus security incidents, report immediately to the Civil Security Unit (CSU), which is mandated to protect the safety of persons and property on campus (Student Manual, p. 10). For formal disciplinary investigation (Student Manual, pp. 67, 69–80), file a written complaint with the Office of the Student Affairs (SAO), College Dean, or Principal. The Investigating Officer / Student Disciplinary Committee has authority to impose Preventive Suspension when the respondent\'s continued presence poses a real or imminent threat or danger to persons and property.',
                'steps' => [],
                'requirements' => [
                    'Immediate safety alert to the Civil Security Unit (CSU) if in active danger',
                    'Written disciplinary complaint filed with the Student Affairs Office (SAO) or College Dean',
                ],
                'responsible_office' => 'Civil Security Unit (CSU) (for immediate safety response), Student Affairs Office (SAO Director), College Dean, and Guidance Center (for victim counseling/support)',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA', 'Guidance Center'],
                'manual_page_number' => 'Student Manual, pages 10, 67, 69–80 (PDF pages 19, 77, 79–90)',
                'validation_needed' => [
                    'Confirm the 24/7 emergency hotline/local extension number and exact station location of the UCLM Civil Security Unit (CSU) and SAO Discipline Desk.',
                ],
            ],
            [
                'entry_code' => 'S3',
                'title' => 'Reporting Hazing or Forced Fraternity / Sorority Initiation and Recruitment',
                'category' => 'Safety and Security',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'hazing',
                'common_questions' => [
                    'Someone is forcing or threatening me to join a fraternity/sorority or undergo hazing initiation rites. How do I report this?',
                ],
                'approved_answer' => 'FORMAL REPORTING & EMERGENCY PATHWAY: Under the University Policy Against Hazing (Student Manual, pp. 47, 72, 87–93), any initiation rite or practice that subjects a recruit, neophyte, or applicant to physical or psychological suffering, injury, humiliation, or forced recruitment is strictly prohibited and carries the maximum penalties of Expulsion or Exclusion in addition to criminal liability under the Anti-Hazing Law. Report immediately to the Student Affairs Office (SAO Director), Civil Security Unit (CSU Head), College Dean/Chairperson, or police authorities.',
                'steps' => [],
                'requirements' => [
                    'Immediate report to the Student Affairs Office (SAO Director), Civil Security Unit (CSU Head), College Dean/Chairperson, or police authorities',
                ],
                'responsible_office' => 'Student Affairs Office (SAO Director), Civil Security Unit (CSU Head), College Dean/Chairperson, and Campus Director',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 47, 72, 87–93 (PDF pages 56, 82, 97–103)',
                'validation_needed' => [
                    'Confirm updated compliance protocols under Republic Act No. 11053 (Anti-Hazing Act of 2018) as implemented by UCLM SAO.',
                ],
            ],
            [
                'entry_code' => 'S4',
                'title' => 'Filing a Formal Student Disciplinary Complaint (Disciplinary Investigation Procedure)',
                'category' => 'Safety and Security',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'disciplinary_complaint',
                'common_questions' => [
                    'How do I file a formal disciplinary complaint against a student for theft, vandalism, forgery, or serious misconduct?',
                ],
                'approved_answer' => 'FORMAL REPORTING PATHWAY: Under the Student Disciplinary Investigation Procedure (Student Manual, pp. 77–82), any student, trainee, teacher, employee, administrator, concerned person, or parent/guardian on behalf of a ward-student may file a written complaint with the Office of the Student Affairs (SAO), College Dean, or Principal. Note that Page 77 states that no anonymous complaint shall be entertained in formal disciplinary prosecution so due process can be observed.',
                'steps' => [],
                'requirements' => [
                    'Written complaint filed with SAO, College Dean, or Principal containing: (1) Name(s) and address(es)/contact details of complainant(s), (2) Name(s) and address(es)/contact details of respondent(s), (3) Cause of action, (4) Specific circumstances, (5) Name(s) of any witness(es), and (6) Pertinent supporting papers/documents.',
                ],
                'responsible_office' => 'Student Affairs Office (SAO Director), College Dean, and Student Disciplinary Committee',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 77–82 (PDF pages 87–92)',
                'validation_needed' => [
                    'Confirm whether UCLM SAO provides a standard printed Complaint Form at the SAO office or accepts formal narrative letters/SCMS submissions to initiate a preliminary inquiry.',
                ],
            ],
            [
                'entry_code' => 'S5',
                'title' => 'Medical Emergencies, Complicated Medical/Dental Cases, and Medico-Legal Referrals',
                'category' => 'Medical',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'medical_emergency',
                'common_questions' => [
                    'There is an urgent medical emergency or injury on campus. What is the emergency procedure?',
                ],
                'approved_answer' => 'EMERGENCY ASSISTANCE PATHWAY: For acute medical emergencies on campus, injuries, complicated medical or dental cases requiring hospital care, or medico-legal cases (injuries resulting from assault, accidents, or crimes), proceed to or alert the University Medical and Dental Clinic and the Civil Security Unit (CSU) immediately (Student Manual, pp. 57–59). Complicated cases and cases requiring emergency injections are referred immediately by the University clinic to nearby hospitals, while medico-legal cases are explicitly referred to a government hospital (Page 58, Section N.1.3.5).',
                'steps' => [],
                'requirements' => [
                    'Immediate alert to the University Medical and Dental Clinic and Civil Security Unit (CSU)',
                ],
                'responsible_office' => 'Medical and Dental Services (University Physician / Clinic Nurses) and Civil Security Unit (CSU)',
                'primary_review_office' => 'Medical and Dental Clinic',
                'secondary_review_offices' => ['SAO and CSU'],
                'manual_page_number' => 'Student Manual, pages 57–59 (PDF pages 66–68)',
                'validation_needed' => [
                    'Confirm the exact emergency contact number of the UCLM Medical Clinic and CSU emergency response team, as well as the designated referral hospitals in Lapu-Lapu/Mandaue.',
                ],
            ],
            [
                'entry_code' => 'S6',
                'title' => 'Protection Against Pregnancy Discrimination (Gender Sensitivity Policy)',
                'category' => 'Guidance',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'pregnancy_protection',
                'common_questions' => [
                    'Can a female student be expelled or refused admission because of pregnancy outside of marriage?',
                ],
                'approved_answer' => 'FORMAL PROTECTION & SUPPORT PATHWAY: Under Section C.1.2 (Policy on Gender Sensitivity, Student Manual, pp. 93–94): "Discrimination of female students who became pregnant out of wedlock shall be avoided. The University shall not turn out or refuse admission to a female student solely on account of her having contracted pregnancy outside of marriage during her term in the University." Pregnant students are also explicitly qualified to apply for a Uniform Exemption Card at the Student Affairs Office (SAO) (Page 63). If you experience discrimination or need support, contact the Student Affairs Office (SAO), Guidance Center, or your College Dean.',
                'steps' => [],
                'requirements' => [],
                'responsible_office' => 'Student Affairs Office (SAO), Guidance and Counseling Services, Medical Clinic, and College Dean\'s Office',
                'primary_review_office' => 'SAO and CSU',
                'secondary_review_offices' => ['Guidance Center', 'Medical and Dental Clinic', 'Academic Deans and VCAA'],
                'manual_page_number' => 'Student Manual, pages 63, 93–94 (PDF pages 72, 103–104)',
                'validation_needed' => [
                    'Confirm maternity leave-of-absence / academic accommodation guidelines for pregnant students during childbirth/recovery at UCLM.',
                ],
            ],
            [
                'entry_code' => 'S7',
                'title' => 'Confidential Random Drug Testing & Positive Result Intervention Protocol',
                'category' => 'Guidance',
                'manual_category' => 'Section 4: Sensitive, Urgent, & Safety Concerns (Formal Reporting Pathways Only)',
                'is_sensitive' => true,
                'sensitive_pathway_type' => 'confidential_drug_testing',
                'common_questions' => [
                    'Are random drug testing results confidential, and can a student be expelled for a positive random drug test?',
                ],
                'approved_answer' => 'STRICT CONFIDENTIALITY & HEALTH PATHWAY: Under the Guidelines on the Conduct of Random Drug Testing (Student Manual, pp. 97–105 / RA 9165 / Dangerous Drugs Board Regulation No. 6, s. 2003 as amended by Regulation No. 3, s. 2009), drug test results are treated with utmost secrecy and confidentiality (unauthorized disclosure is punishable under Section 72 of RA 9165). A positive confirmatory result under random drug testing shall NOT be a ground for expulsion or any disciplinary action, shall not be reflected in any academic record, and shall not be used to incriminate the student. Confirmed cases are handled confidentially as a health/counseling matter with the Drug Testing Coordinator, Drug Counselor (Guidance Counselor), the student, the parent, and a DOH-accredited physician. Do NOT submit confidential medical or drug-testing results through online forms; consult the designated UCLM Drug Counselor / Guidance Center directly in person.',
                'steps' => [],
                'requirements' => [],
                'responsible_office' => 'University Drug Testing Coordinator, Drug Counselor (Guidance and Counseling Services), and Medical Clinic',
                'primary_review_office' => 'Guidance Center',
                'secondary_review_offices' => ['Medical and Dental Clinic'],
                'manual_page_number' => 'Student Manual, pages 97–105 (PDF pages 107–115)',
                'validation_needed' => [
                    'Confirm the designated Drug Testing Coordinator and Drug Counselor at the UCLM campus.',
                ],
            ],
        ];
    }
}
