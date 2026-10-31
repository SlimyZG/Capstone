<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$pw = Hash::make('Password123!');

User::updateOrCreate(
    ['email' => 'student.test@scms.test'],
    [
        'name' => 'Synthetic Test Student',
        'student_id_number' => '99999999',
        'password' => $pw,
        'role' => 'student',
        'academic_unit_id' => 3
    ]
);

User::updateOrCreate(
    ['email' => 'dean.test@scms.test'],
    [
        'name' => 'Synthetic Test Dean CCS',
        'password' => $pw,
        'role' => 'dean',
        'academic_unit_id' => 3
    ]
);

User::updateOrCreate(
    ['email' => 'dean.other@scms.test'],
    [
        'name' => 'Synthetic Test Dean Marine',
        'password' => $pw,
        'role' => 'dean',
        'academic_unit_id' => 9
    ]
);

User::updateOrCreate(
    ['email' => 'staff.edp@scms.test'],
    [
        'name' => 'Synthetic Test Staff EDP',
        'password' => $pw,
        'role' => 'dept_staff',
        'dept_id' => 5
    ]
);

User::updateOrCreate(
    ['email' => 'staff.registrar@scms.test'],
    [
        'name' => 'Synthetic Test Staff Registrar',
        'password' => $pw,
        'role' => 'dept_staff',
        'dept_id' => 9
    ]
);

User::updateOrCreate(
    ['email' => 'staff.ccs@scms.test'],
    [
        'name' => 'Synthetic Test Staff CCS',
        'password' => $pw,
        'role' => 'dept_staff',
        'academic_unit_id' => 3
    ]
);

User::updateOrCreate(
    ['email' => 'sao.test@scms.test'],
    [
        'name' => 'Synthetic Test SAO User',
        'password' => $pw,
        'role' => 'sao',
        'dept_id' => 10
    ]
);

echo "Synthetic users seeded successfully.\n";
