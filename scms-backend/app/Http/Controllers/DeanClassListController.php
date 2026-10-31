<?php

namespace App\Http\Controllers;

use App\Models\ClassList;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DeanClassListController extends Controller
{
    /**
     * Get the class list for the authenticated dean's academic unit.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Get class list for the dean's academic unit
        $classList = ClassList::where('academic_unit_id', $user->academic_unit_id)->get();

        // Fetch corresponding registered users
        $studentIds = $classList->pluck('student_id_number');
        $registeredUsers = User::whereIn('student_id_number', $studentIds)
            ->where('role', 'student')
            ->get()
            ->keyBy('student_id_number');

        // Map data for response
        $mappedList = $classList->map(function ($item) use ($registeredUsers) {
            $user = $registeredUsers->get($item->student_id_number);
            return [
                'id' => $item->id,
                'student_id_number' => $item->student_id_number,
                'name' => $user ? $user->name : 'Pending Registration',
                'email' => $user ? $user->email : '—',
                'is_registered' => $user ? true : false,
            ];
        });

        return response()->json([
            'class_list' => $mappedList,
        ]);
    }

    /**
     * Add a single student to the class list.
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_id_number' => 'required|string|size:8|regex:/^\d{8}$/|unique:class_lists,student_id_number',
        ]);

        $user = $request->user();

        $classList = ClassList::create([
            'student_id_number' => $request->student_id_number,
            'academic_unit_id' => $user->academic_unit_id,
        ]);

        return response()->json([
            'message' => 'Student added to class list successfully.',
            'data' => [
                'id' => $classList->id,
                'student_id_number' => $classList->student_id_number,
                'name' => 'Pending Registration',
                'email' => '—',
                'is_registered' => false,
            ],
        ], 201);
    }

    /**
     * Batch upload students to the class list via a file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048', // max 2MB
        ]);

        $user = $request->user();
        $file = $request->file('file');
        
        $contents = file_get_contents($file->getRealPath());
        $lines = explode(PHP_EOL, $contents);
        
        $addedCount = 0;
        $duplicateCount = 0;
        $invalidCount = 0;

        foreach ($lines as $line) {
            $studentId = trim($line);
            
            // Skip empty lines
            if (empty($studentId)) {
                continue;
            }

            // Validate student ID format
            if (!preg_match('/^\d{8}$/', $studentId)) {
                $invalidCount++;
                continue;
            }

            // Check if it already exists
            if (ClassList::where('student_id_number', $studentId)->exists()) {
                $duplicateCount++;
                continue;
            }

            // Insert
            ClassList::create([
                'student_id_number' => $studentId,
                'academic_unit_id' => $user->academic_unit_id,
            ]);
            $addedCount++;
        }

        return response()->json([
            'message' => 'File processed successfully.',
            'added' => $addedCount,
            'duplicates' => $duplicateCount,
            'invalid' => $invalidCount,
        ]);
    }
}
