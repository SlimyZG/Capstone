<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new student account.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'student_id_number' => [
                'required',
                'string',
                'size:8',
                'regex:/^\d{8}$/',
                'unique:users,student_id_number',
            ],
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'academic_unit_id' => 'required|exists:academic_units,id',
        ], [
            'student_id_number.size' => 'Student ID must be exactly 8 digits.',
            'student_id_number.regex' => 'Student ID must contain only numeric digits.',
            'student_id_number.unique' => 'This Student ID is already registered.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'student_id_number' => $validated['student_id_number'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
            'academic_unit_id' => $validated['academic_unit_id'],
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user->load('academicUnit'),
            'token' => $token,
        ], 201);
    }

    /**
     * Smart Login: auto-detect Student ID (8-digit numeric) vs Email login.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginValue = $request->input('login');
        $password = $request->input('password');

        // Determine if login is a student ID (8-digit numeric) or email
        $isStudentId = preg_match('/^\d{8}$/', $loginValue);

        if ($isStudentId) {
            $user = User::where('student_id_number', $loginValue)->first();
        } else {
            $user = User::where('email', $loginValue)->first();
        }

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revoke existing tokens and create a new one
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user->load(['academicUnit', 'department']),
            'token' => $token,
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load(['academicUnit', 'department']),
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        }
        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->load(['academicUnit', 'department']),
        ]);
    }

    /**
     * Logout and revoke current token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Handle forgot password requests.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // In a real application, you would generate a reset token and send an email here.
        // For this local/demo environment, we simply return a success response to simulate the flow.
        return response()->json([
            'message' => 'If your email is registered in our system, you will receive a password reset link shortly.'
        ]);
    }
}
