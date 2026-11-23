<?php

namespace Database\Seeders;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create System Admin
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@admin.com',
            'password' => Hash::make('admin12345678'),
            'role' => 'admin',
        ]);

        // 2. Seed 12 Academic Units
        $academicUnits = [
            'Basic Education',
            'Senior High School',
            'Computer Studies',
            'Criminal Justice',
            'Customs Administrations',
            'Engineering',
            'Hospitality/Management',
            'Marine Engineering',
            'Marine Transportation',
            'Business/Accountancy',
            'Nursing',
            'Teacher Education',
        ];

        foreach ($academicUnits as $unitName) {
            AcademicUnit::create(['name' => $unitName]);
        }

        // 3. Seed 13 Administrative & Operational Departments
        $departments = [
            "Campus Director's Office",
            'Maritime Superintendent Office',
            'Cashier',
            'CSU',
            'EDP',
            'Guidance Center',
            'HRD',
            'Medical Clinic',
            'Registrar',
            'SAO',
            'Scholarship',
            'STO',
            "Student's Accounting",
            'PCO',
        ];

        foreach ($departments as $deptName) {
            Department::firstOrCreate(['name' => $deptName]);
        }

        // 4. Seed Student Self-Service Assistance Knowledge Base (Pending UCLM Validation / Unpublished by default)
        $this->call(SelfServiceKnowledgeBaseSeeder::class);
    }
}
