<?php

namespace App\Http\Controllers;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SaoUserController extends Controller
{
    /**
     * Get managed users (Deans and Department Staff) with statistics and filters.
     * Accessible by SAO and Admin.
     */
    public function index(Request $request)
    {
        $query = User::with(['academicUnit', 'department']);

        // Default to managing academic leadership and staff (dean, dept_staff)
        // If a specific role is requested, validate and apply
        if ($request->has('role') && !empty($request->input('role'))) {
            $role = $request->input('role');
            if (in_array($role, ['dean', 'dept_staff'])) {
                $query->where('role', $role);
            }
        } else {
            // By default, SAO User Management focuses on Dean and Dept Staff
            $query->whereIn('role', ['dean', 'dept_staff']);
        }

        // Search by name or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by Academic Unit
        if ($unitId = $request->input('academic_unit_id')) {
            $query->where('academic_unit_id', $unitId);
        }

        // Filter by Operational Department
        if ($deptId = $request->input('dept_id')) {
            $query->where('dept_id', $deptId);
        }

        // Compute system statistics for Dean and Dept Staff
        $totalDeans = User::where('role', 'dean')->count();
        $totalStaff = User::where('role', 'dept_staff')->count();
        $totalManaged = $totalDeans + $totalStaff;

        $hasIsActive = Schema::hasColumn('users', 'is_active');
        $totalActive = $hasIsActive 
            ? User::whereIn('role', ['dean', 'dept_staff'])->where('is_active', true)->count()
            : $totalManaged; // All accounts are active if no column exists

        return response()->json([
            'users' => $query->orderBy('name', 'asc')->get(),
            'stats' => [
                'total_deans' => $totalDeans,
                'total_dept_staff' => $totalStaff,
                'total_active' => $totalActive,
                'total_managed' => $totalManaged,
                'schema_supports_deactivation' => $hasIsActive,
            ],
        ]);
    }

    /**
     * Create a new Dean or Department Staff account.
     * Enforces strict role and assignment boundaries.
     */
    public function store(Request $request)
    {
        // SAO can only create dean or dept_staff accounts
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:dean,dept_staff',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id' => 'nullable|exists:departments,id',
        ], [
            'role.in' => 'SAO may only provision Dean or Department Staff accounts.',
            'email.unique' => 'This email address is already registered in the system.',
        ]);

        if ($validated['role'] === 'dean') {
            if (empty($validated['academic_unit_id'])) {
                return response()->json([
                    'message' => 'Academic unit is required for Dean accounts.',
                ], 422);
            }

            // Enforce only ONE dean per academic unit
            $existingDean = User::where('role', 'dean')
                ->where('academic_unit_id', $validated['academic_unit_id'])
                ->first();

            if ($existingDean) {
                return response()->json([
                    'message' => 'This Academic Unit already has an assigned Dean. Only one Dean is allowed per unit.',
                ], 422);
            }
        }

        if ($validated['role'] === 'dept_staff') {
            if (empty($validated['dept_id']) && empty($validated['academic_unit_id'])) {
                return response()->json([
                    'message' => 'Department staff must be assigned to either an Academic Unit or an Operational Department.',
                ], 422);
            }
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'academic_unit_id' => $validated['academic_unit_id'] ?? null,
            'dept_id' => $validated['dept_id'] ?? null,
        ]);

        return response()->json([
            'message' => ucfirst(str_replace('_', ' ', $validated['role'])) . ' account created successfully.',
            'user' => $user->load(['academicUnit', 'department']),
        ], 201);
    }

    /**
     * Update an existing Dean or Department Staff account.
     * Prevents role elevation, self-lockout, or modification of Admin/SAO/Student accounts.
     */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // Security guards
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Cannot modify System Administrator accounts.',
            ], 403);
        }

        if ($user->role === 'sao') {
            return response()->json([
                'message' => 'SAO accounts cannot be modified through this management module. Please use your Profile settings.',
            ], 403);
        }

        if ($user->role === 'student') {
            return response()->json([
                'message' => 'Student accounts cannot be modified through the SAO User Management module.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:dean,dept_staff',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id' => 'nullable|exists:departments,id',
        ], [
            'role.in' => 'SAO can only assign roles between Dean and Department Staff.',
        ]);

        $newRole = $validated['role'] ?? $user->role;
        $newUnit = array_key_exists('academic_unit_id', $validated) ? $validated['academic_unit_id'] : $user->academic_unit_id;
        $newDept = array_key_exists('dept_id', $validated) ? $validated['dept_id'] : $user->dept_id;

        if ($newRole === 'dean') {
            if (empty($newUnit)) {
                return response()->json([
                    'message' => 'Academic unit is required for Dean accounts.',
                ], 422);
            }

            // Enforce only ONE dean per academic unit (exclude the current user)
            $existingDean = User::where('role', 'dean')
                ->where('academic_unit_id', $newUnit)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingDean) {
                return response()->json([
                    'message' => 'This Academic Unit already has an assigned Dean.',
                ], 422);
            }
        }

        if ($newRole === 'dept_staff' && empty($newDept) && empty($newUnit)) {
            return response()->json([
                'message' => 'Department staff must be assigned to either an Academic Unit or an Operational Department.',
            ], 422);
        }

        $updateData = [];
        if (isset($validated['name'])) $updateData['name'] = $validated['name'];
        if (isset($validated['email'])) $updateData['email'] = $validated['email'];
        if (isset($validated['role'])) $updateData['role'] = $validated['role'];
        if (array_key_exists('academic_unit_id', $validated)) $updateData['academic_unit_id'] = $validated['academic_unit_id'];
        if (array_key_exists('dept_id', $validated)) $updateData['dept_id'] = $validated['dept_id'];
        if (!empty($validated['password'])) $updateData['password'] = Hash::make($validated['password']);

        $user->update($updateData);

        return response()->json([
            'message' => 'Account updated successfully.',
            'user' => $user->fresh(['academicUnit', 'department']),
        ]);
    }

    /**
     * Account deactivation status endpoint.
     * Prevents destructive record deletion while informing about schema requirements.
     */
    public function deactivate(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 403);
        }

        $hasIsActive = Schema::hasColumn('users', 'is_active');

        if (!$hasIsActive) {
            return response()->json([
                'supported' => false,
                'message' => 'Account deactivation is currently not supported by the database schema. User accounts and historical concern routing records are preserved. To enable deactivation safely without deleting records, a database migration adding an is_active column is required.',
            ], 422);
        }

        // If schema supported in future:
        $user->update(['is_active' => false]);
        $user->tokens()->delete();

        return response()->json([
            'supported' => true,
            'message' => 'Account deactivated successfully.',
        ]);
    }
}
