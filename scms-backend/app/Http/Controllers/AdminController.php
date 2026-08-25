<?php

namespace App\Http\Controllers;

use App\Models\AcademicUnit;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    /**
     * Create a dean or dept_staff account.
     * Only accessible by admin role.
     */
    public function createAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:dean,dept_staff',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id' => 'nullable|exists:departments,id',
        ]);

        if ($validated['role'] === 'dean') {
            if (empty($validated['academic_unit_id'])) {
                return response()->json([
                    'message' => 'Academic unit is required for dean accounts.',
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

        if ($validated['role'] === 'dept_staff' && empty($validated['dept_id']) && empty($validated['academic_unit_id'])) {
            return response()->json([
                'message' => 'Department staff must be assigned to either an Academic Unit or an Operational Department.',
            ], 422);
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
            'message' => ucfirst($validated['role']) . ' account created successfully.',
            'user' => $user->load(['academicUnit', 'department']),
        ], 201);
    }

    /**
     * Get all academic units.
     */
    public function getUnits()
    {
        return response()->json([
            'academic_units' => AcademicUnit::orderBy('name')->get(),
        ]);
    }

    /**
     * Get all departments.
     */
    public function getDepartments()
    {
        return response()->json([
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    /**
     * Get all users (with optional role filter).
     */
    public function getUsers(Request $request)
    {
        $query = User::with(['academicUnit', 'department']);

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        return response()->json([
            'users' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Delete an account.
     */
    public function deleteAccount(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // Prevent admin from deleting themselves
        if ($user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot delete your own account.',
            ], 403);
        }

        // Optional: Prevent deleting other admins if desired
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Admin accounts cannot be deleted.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }

    /**
     * Update an existing account.
     */
    public function updateAccount(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        // Optional: prevent changing other admins
        if ($user->role === 'admin' && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Cannot modify other admin accounts.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|in:dean,dept_staff,student,admin',
            'academic_unit_id' => 'nullable|exists:academic_units,id',
            'dept_id' => 'nullable|exists:departments,id',
        ]);

        $newRole = $validated['role'] ?? $user->role;
        $newUnit = array_key_exists('academic_unit_id', $validated) ? $validated['academic_unit_id'] : $user->academic_unit_id;
        $newDept = array_key_exists('dept_id', $validated) ? $validated['dept_id'] : $user->dept_id;

        if ($newRole === 'dean') {
            if (empty($newUnit)) {
                return response()->json(['message' => 'Academic unit is required for dean accounts.'], 422);
            }

            // Enforce only ONE dean per academic unit (exclude the current user)
            $existingDean = User::where('role', 'dean')
                ->where('academic_unit_id', $newUnit)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingDean) {
                return response()->json(['message' => 'This Academic Unit already has an assigned Dean.'], 422);
            }
        }

        if ($newRole === 'dept_staff' && empty($newDept) && empty($newUnit)) {
            return response()->json(['message' => 'Department staff must be assigned to either an Academic Unit or an Operational Department.'], 422);
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
}
