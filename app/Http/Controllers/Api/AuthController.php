<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Database\QueryException;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        try {
            // 1. Validation
            $request->validate([
                'name' => 'required|string|max:150',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|min:6',
                'role' => 'required|in:admin,employer_member,job_seeker'
            ]);

            // 2. Database Action
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role
            ]);

            // 3. Mail Service Action
            // We wrap this specifically so that if mail fails, the user still exists
            try {
                $mailService = new MailService();
                $mailService->send('jobseeker_welcome', [
                    'name' => $user->name,
                    'email' => $user->email
                ], $user->email);
            } catch (\Exception $mailEx) {
                // Log the error but don't stop the registration success response
                Log::error("Mail failed for user {$user->email}: " . $mailEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'User registered successfully',
                'user' => $user
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return specific validation errors
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Generic catch for database or server errors
            return response()->json([
                'status' => false,
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            // Validate Input
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            //  Attempt Authentication
            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid email or password'
                ], 401);
            }

            // 3. Check Account Status
            $user = Auth::user();
            if ($user->is_active == 0) {
                Auth::logout();
                return response()->json([
                    'status' => false,
                    'message' => 'Your account is disabled. Please contact support.'
                ], 403);
            }

            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => 'Login successful',
                'user' => $user
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Log the actual error for debugging
            Log::error("Login Error: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'An unexpected error occurred during login',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }

    public function profile()
    {
        return response()->json([
            'status' => true,
            'user' => Auth::user()
        ]);
    }
}
