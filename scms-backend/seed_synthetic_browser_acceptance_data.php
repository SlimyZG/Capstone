<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Concern;
use App\Models\RecurringIncident;
use App\Models\ConcernRouting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

echo "Seeding synthetic acceptance test data in database: " . config('database.connections.mysql.database') . "\n";

$pw = Hash::make('Password123!');

// 1. Synthetic Users
$student1 = User::updateOrCreate(
    ['email' => 'student.test@scms.test'],
    [
        'name' => 'Synthetic Test Student',
        'student_id_number' => '99999999',
        'password' => $pw,
        'role' => 'student',
        'academic_unit_id' => 3
    ]
);

$student2 = User::updateOrCreate(
    ['email' => 'student2.test@scms.test'],
    [
        'name' => 'Synthetic Student Two',
        'student_id_number' => '88888888',
        'password' => $pw,
        'role' => 'student',
        'academic_unit_id' => 3
    ]
);

$deanCCS = User::updateOrCreate(
    ['email' => 'dean.test@scms.test'],
    [
        'name' => 'Synthetic Test Dean CCS',
        'password' => $pw,
        'role' => 'dean',
        'academic_unit_id' => 3
    ]
);

$deanMarine = User::updateOrCreate(
    ['email' => 'dean.other@scms.test'],
    [
        'name' => 'Synthetic Test Dean Marine',
        'password' => $pw,
        'role' => 'dean',
        'academic_unit_id' => 9
    ]
);

$staffCCS = User::updateOrCreate(
    ['email' => 'staff.ccs@scms.test'],
    [
        'name' => 'Synthetic Staff CCS',
        'password' => $pw,
        'role' => 'dept_staff',
        'academic_unit_id' => 3
    ]
);

$staffEDP = User::updateOrCreate(
    ['email' => 'staff.edp@scms.test'],
    [
        'name' => 'Synthetic Staff EDP',
        'password' => $pw,
        'role' => 'dept_staff',
        'dept_id' => 5
    ]
);

$staffRegistrar = User::updateOrCreate(
    ['email' => 'staff.registrar@scms.test'],
    [
        'name' => 'Synthetic Staff Registrar',
        'password' => $pw,
        'role' => 'dept_staff',
        'dept_id' => 9
    ]
);

$saoUser = User::updateOrCreate(
    ['email' => 'sao.test@scms.test'],
    [
        'name' => 'Synthetic SAO Officer',
        'password' => $pw,
        'role' => 'sao',
        'dept_id' => 10
    ]
);

// 2. Clean previous test concerns/incidents marked with SYNTH- prefix
ConcernRouting::whereHas('concern', function($q) {
    $q->where('title', 'like', 'SYNTH:%');
})->delete();

Concern::where('title', 'like', 'SYNTH:%')->delete();
RecurringIncident::where('title', 'like', 'SYNTH:%')->delete();

// Long description for text-wrap testing
$longDescription = "Detailed inspection notes: The equipment in question has been malfunctioning continuously across multiple back-to-back lecture periods. Students and faculty have repeatedly reported that this issue severely hinders their scheduled instructional activities, laboratory demonstrations, and capstone presentation sessions. Immediate technical assistance, cabling inspection, and hardware diagnostics are urgently requested by all affected class representatives.";

// 3. Create the 6 Verified AI Display Concerns

// Concern 1: Classroom Projector (Academic / CCS)
$c1 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'title' => 'SYNTH: Classroom Projector in Room 301 is flickering and losing HDMI display',
    'description' => "The classroom projector in Room 301 is flickering intermittently and loses HDMI signal every few minutes. " . $longDescription,
    'category' => 'academic_units',
    'workflow_type' => 'academic',
    'status' => 'escalated',
    'ai_status' => 'complete',
    'priority' => 'high',
    'ai_priority' => 'high',
    'ai_priority_explanation' => 'Audiovisual failure in a shared instructional room directly impairs ongoing lectures and class demonstrations.',
    'issue_category' => 'Academic Concerns',
    'issue_subcategory' => 'Classroom Equipment / AV',
    'classification_explanation' => 'Mentions classroom projector, HDMI connection, and display flicker in Room 301.',
    'classification_confidence' => 0.96,
    'sentiment_score' => 0.75,
    'sentiment_label' => 'negative',
    'ai_summary' => 'Students report recurring HDMI signal drop and display flickering on the ceiling projector in Room 301 during lectures.',
    'submitted_at' => now()->subHours(2),
]);

// Concern 1b: Second Projector in Room 301 (Groups with C1)
$c1b = Concern::create([
    'student_id' => $student2->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'title' => 'SYNTH: Room 301 Projector display remains completely black',
    'description' => "Another report regarding Room 301 projector. The HDMI cable is loose and the display went black during afternoon classes. " . $longDescription,
    'category' => 'academic_units',
    'workflow_type' => 'academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'high',
    'ai_priority' => 'high',
    'ai_priority_explanation' => 'Audiovisual failure in Room 301 instructional facility.',
    'issue_category' => 'Academic Concerns',
    'issue_subcategory' => 'Classroom Equipment / AV',
    'classification_explanation' => 'Projector failure in Room 301.',
    'classification_confidence' => 0.95,
    'sentiment_score' => 0.70,
    'sentiment_label' => 'negative',
    'ai_summary' => 'Second report confirming Room 301 projector display has failed completely due to loose cabling.',
    'submitted_at' => now()->subHours(1),
]);

// Create Recurring Incident for Projector Grouping
$incProjector = RecurringIncident::create([
    'incident_code' => 'INC-SYNTH-001',
    'title' => 'SYNTH: Recurring Projector Failure in Room 301',
    'summary' => 'Multiple reports of projector display failure in Room 301.',
    'workflow_type' => 'academic',
    'issue_category' => 'Academic Concerns',
    'issue_subcategory' => 'Classroom Equipment / AV',
    'academic_unit_id' => 3,
    'unique_students_count' => 2,
    'total_reports_count' => 2,
    'status' => 'investigating',
    'priority' => 'high',
    'recurrence_tier' => 'normal',
    'first_reported_at' => now()->subHours(2),
    'latest_reported_at' => now()->subHours(1),
]);
$c1->update(['recurring_incident_id' => $incProjector->id]);
$c1b->update(['recurring_incident_id' => $incProjector->id]);

// Concern 2: Computer Hardware (Academic / CCS) - In Room 301, but DIFFERENT subcategory! Must NOT group!
$c2 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'title' => 'SYNTH: Computer monitor in Room 301 is broken with cracked panel',
    'description' => "Workstation 12 desktop monitor in Room 301 has horizontal colored artifacts and physical damage. " . $longDescription,
    'category' => 'academic_units',
    'workflow_type' => 'academic',
    'status' => 'escalated',
    'ai_status' => 'complete',
    'priority' => 'medium',
    'ai_priority' => 'medium',
    'ai_priority_explanation' => 'Individual workstation hardware damage. Students can safely use neighboring terminals.',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Computer Hardware',
    'classification_explanation' => 'Refers to physical computer monitor breakdown and hardware artifacts.',
    'classification_confidence' => 0.94,
    'sentiment_score' => 0.60,
    'sentiment_label' => 'negative',
    'ai_summary' => 'Single computer monitor at station 12 in Room 301 has cracked LCD panel and vertical colored lines.',
    'submitted_at' => now()->subHours(3),
]);

// Concern 3: Malware Infection (Operational / EDP)
$c3 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'dept_id' => 5, // EDP
    'title' => 'SYNTH: Classroom computer has malware infection with ransomware popup warnings',
    'description' => "A terminal in the computer laboratory triggered suspicious trojan alerts and ransomware lock screen popups. " . $longDescription,
    'category' => 'operational_departments',
    'workflow_type' => 'non_academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'critical',
    'ai_priority' => 'critical',
    'ai_priority_explanation' => 'High-severity security vulnerability and malware activity risking lateral campus network penetration.',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Virus / Malware',
    'classification_explanation' => 'Identified malware, ransomware popups, and trojan alerts.',
    'classification_confidence' => 0.98,
    'sentiment_score' => 0.95,
    'sentiment_label' => 'urgent',
    'ai_summary' => 'Suspicious ransomware popup detected on lab terminal indicating potential active malware infection.',
    'submitted_at' => now()->subMinutes(45),
]);

// Concern 4: Network Outage (Operational / EDP)
$c4 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'dept_id' => 5, // EDP
    'title' => 'SYNTH: Classroom internet is unavailable and Wi-Fi access point disconnected',
    'description' => "The primary Wi-Fi SSID is offline across the building and students cannot access online quiz forms. " . $longDescription,
    'category' => 'operational_departments',
    'workflow_type' => 'non_academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'high',
    'ai_priority' => 'high',
    'ai_priority_explanation' => 'Network disruption halts access to online examination and campus learning management systems.',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Network / Internet',
    'classification_explanation' => 'Wi-Fi connectivity failure and internet unavailable.',
    'classification_confidence' => 0.97,
    'sentiment_score' => 0.80,
    'sentiment_label' => 'negative',
    'ai_summary' => 'Widespread Wi-Fi network disconnection impacting student ability to access course assessments.',
    'submitted_at' => now()->subHours(4),
]);

// Concern 5: Software Issue (Academic / CCS)
$c5 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'dean',
    'title' => 'SYNTH: Compiler software crash during practical lab exercises in Lab 4',
    'description' => "The installed IDE and C++ compiler crashes immediately upon running sample programs on all lab PCs. " . $longDescription,
    'category' => 'academic_units',
    'workflow_type' => 'academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'medium',
    'ai_priority' => 'medium',
    'ai_priority_explanation' => 'Software licensing / binary crash affecting programming laboratory exercises.',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Software Problems',
    'classification_explanation' => 'Mentions IDE and compiler crashes during code compilation.',
    'classification_confidence' => 0.93,
    'sentiment_score' => 0.65,
    'sentiment_label' => 'negative',
    'ai_summary' => 'IDE and development tools crash on startup during laboratory programming activities.',
    'submitted_at' => now()->subHours(5),
]);

// Concern 6: Registrar Concern (Operational / Registrar)
$c6 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'dept_id' => 9, // Registrar
    'title' => 'SYNTH: Transcript of records request delayed past official evaluation window',
    'description' => "Request for certified transcript has been pending for over 3 weeks past the standard window. " . $longDescription,
    'category' => 'operational_departments',
    'workflow_type' => 'non_academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'low',
    'ai_priority' => 'low',
    'ai_priority_explanation' => 'Routine document request follow-up without physical security or broad academic emergency.',
    'issue_category' => 'Operational Departments',
    'issue_subcategory' => 'Registrar',
    'classification_explanation' => 'Document issuance and evaluation delay at Registrar office.',
    'classification_confidence' => 0.91,
    'sentiment_score' => 0.45,
    'sentiment_label' => 'neutral',
    'ai_summary' => 'Student inquiries regarding processing timeline of certified transcript of records.',
    'submitted_at' => now()->subHours(6),
]);

// Concern 7: SAO Assigned Concern (Operational / SAO dept_id: 10)
$c7 = Concern::create([
    'student_id' => $student1->id,
    'academic_unit_id' => 3,
    'target_type' => 'department',
    'dept_id' => 10, // SAO
    'title' => 'SYNTH: Student organization activity permit review pending SAO signature',
    'description' => "Student council requesting endorsement for campus leadership workshop scheduled for next Friday. " . $longDescription,
    'category' => 'operational_departments',
    'workflow_type' => 'non_academic',
    'status' => 'pending',
    'ai_status' => 'complete',
    'priority' => 'medium',
    'ai_priority' => 'medium',
    'ai_priority_explanation' => 'Standard student activity permit processing awaiting department review.',
    'issue_category' => 'Operational Departments',
    'issue_subcategory' => 'Student Affairs Office',
    'classification_explanation' => 'SAO permit application for student club event.',
    'classification_confidence' => 0.95,
    'sentiment_score' => 0.30,
    'sentiment_label' => 'neutral',
    'ai_summary' => 'Request for SAO endorsement and facility permit for upcoming student leadership activity.',
    'submitted_at' => now()->subHours(1),
]);

// Concern 8: Marine Transportation Concern (For Dean CCS Isolation verification)
$cMarine = Concern::create([
    'student_id' => $student2->id,
    'academic_unit_id' => 9, // Marine Transportation
    'target_type' => 'dean',
    'title' => 'SYNTH: Bridge simulator radar display alignment fault in Marine Building',
    'description' => "The navigation simulator radar display requires re-calibration. " . $longDescription,
    'category' => 'academic_units',
    'workflow_type' => 'academic',
    'status' => 'escalated',
    'ai_status' => 'complete',
    'priority' => 'high',
    'ai_priority' => 'high',
    'ai_priority_explanation' => 'Specialized simulator equipment fault for maritime cadets.',
    'issue_category' => 'Academic Concerns',
    'issue_subcategory' => 'Laboratory Issues',
    'classification_explanation' => 'Maritime simulator calibration.',
    'classification_confidence' => 0.95,
    'sentiment_score' => 0.70,
    'sentiment_label' => 'negative',
    'ai_summary' => 'Bridge simulator display misalignment reported in Marine Transportation laboratory.',
    'submitted_at' => now()->subHours(2),
]);

// 4. Create Demonstrative Recurrence Tier Incidents
// High Tier (10 distinct students)
$incHigh = RecurringIncident::create([
    'incident_code' => 'INC-SYNTH-HIGH',
    'title' => 'SYNTH: Recurrent Wi-Fi Drops in Engineering Hall (10 Students)',
    'summary' => '10 distinct student reports of persistent network interruptions in Engineering Hall.',
    'workflow_type' => 'non_academic',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Network / Internet',
    'dept_id' => 5,
    'unique_students_count' => 10,
    'total_reports_count' => 14,
    'status' => 'monitoring',
    'priority' => 'high',
    'recurrence_tier' => 'high',
    'first_reported_at' => now()->subDays(3),
    'latest_reported_at' => now()->subHours(2),
]);

// Critical Tier (20 distinct students)
$incCritical = RecurringIncident::create([
    'incident_code' => 'INC-SYNTH-CRIT',
    'title' => 'SYNTH: Repeated Power Outages in Computer Labs (20 Students)',
    'summary' => '20 distinct student reports of recurrent electrical breaker trips in Computer Labs.',
    'workflow_type' => 'academic',
    'issue_category' => 'Academic Concerns',
    'issue_subcategory' => 'Classroom Issues',
    'academic_unit_id' => 3,
    'unique_students_count' => 20,
    'total_reports_count' => 28,
    'status' => 'monitoring',
    'priority' => 'critical',
    'recurrence_tier' => 'critical',
    'first_reported_at' => now()->subDays(5),
    'latest_reported_at' => now()->subHours(1),
]);

// Major Critical Tier (30 distinct students)
$incMajor = RecurringIncident::create([
    'incident_code' => 'INC-SYNTH-MAJOR',
    'title' => 'SYNTH: Campus-Wide Student Portal Authentication Outage (30 Students)',
    'summary' => '30 distinct student reports of total authentication lockout from student portal.',
    'workflow_type' => 'non_academic',
    'issue_category' => 'IT and Technology',
    'issue_subcategory' => 'Account / Login Issues',
    'dept_id' => 5,
    'unique_students_count' => 30,
    'total_reports_count' => 45,
    'status' => 'monitoring',
    'priority' => 'critical',
    'recurrence_tier' => 'major_critical',
    'first_reported_at' => now()->subDays(6),
    'latest_reported_at' => now()->subMinutes(30),
]);

echo "Synthetic acceptance test data successfully generated:\n";
echo " - Projector Grouping Incident ID: {$incProjector->id} (Concerns: {$c1->concern_id}, {$c1b->concern_id})\n";
echo " - Unrelated Hardware Concern ID: {$c2->concern_id} (Not linked to Projector Incident)\n";
echo " - Malware Concern ID: {$c3->concern_id}\n";
echo " - Network Concern ID: {$c4->concern_id}\n";
echo " - Software Concern ID: {$c5->concern_id}\n";
echo " - Registrar Concern ID: {$c6->concern_id}\n";
echo " - SAO Assigned Concern ID: {$c7->concern_id}\n";
echo " - Marine Unit Concern ID: {$cMarine->concern_id}\n";
echo " - Recurrence Tiers: High ({$incHigh->id}), Critical ({$incCritical->id}), Major Critical ({$incMajor->id})\n";
