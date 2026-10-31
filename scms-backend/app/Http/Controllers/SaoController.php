<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SaoController extends Controller
{
    /**
     * Return foundation status for authenticated SAO users.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'message' => 'SAO foundation is operational.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'status' => 'foundation_active',
        ]);
    }
}
