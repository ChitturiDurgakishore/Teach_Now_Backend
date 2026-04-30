<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HomepageCompanyLogo;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;


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
            $otpRecord = DB::table('email_otps')
                ->where('email', $request->email)
                ->where('is_verified', true)
                ->first();

            if($request->role!='admin' && !$otpRecord) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email not verified'
                ], 403);
            }


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
                'role' => 'job_seeker',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function AdminLogin(Request $request)
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
                'role' => 'admin',
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

    public function profile(Request $request)
    {
        try {

            $user = $request->user();

            /*
        |--------------------------------------------------------------------------
        | 🔥 GET PLATFORM COMPANY DETAILS
        |--------------------------------------------------------------------------
        */

            $platformCompany = HomepageCompanyLogo::select(
                'company_name',
                'company_logo',
                'company_url'
            )->first();

            return response()->json([
                'status' => true,

                'user' => $user,
                'auth_id' => Auth::id(),

                // 🔥 Platform company details
                'platform' => [
                    'company_name' => $platformCompany->company_name ?? null,
                    'company_logo' => $platformCompany->company_logo ?? null,
                    'company_url' => $platformCompany->company_url ?? null
                ]

            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email'
            ]);

            $email = $request->email;
            $role = null;

            /*
        |--------------------------------------------------------------------------
        | 🔥 DETECT ROLE
        |--------------------------------------------------------------------------
        */

            if (\App\Models\Employer::where('email', $email)->exists()) {
                $role = 'employer';
            } elseif (\App\Models\EmployerUser::where('email', $email)->exists()) {
                $role = 'recruiter';
            } elseif (\App\Models\User::where('email', $email)->exists()) {
                $role = 'jobseeker';
            }

            if (!$role) {
                return response()->json([
                    'status' => false,
                    'message' => 'Email not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 GENERATE OTP
        |--------------------------------------------------------------------------
        */

            $otp = rand(100000, 999999);

            /*
        |--------------------------------------------------------------------------
        | 🔥 SAVE / UPDATE OTP
        |--------------------------------------------------------------------------
        */

            DB::table('password_resets')->updateOrInsert(
                ['email' => $email],
                [
                    'role' => $role,
                    'otp' => $otp,
                    'expires_at' => now()->addMinutes(10),
                    'created_at' => now(),
                    'updated_at' => now()
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | 🔥 SEND MAIL
        |--------------------------------------------------------------------------
        */

            try {
                $mailService = new \App\Services\MailService();

                $mailService->send('forgot_password', [
                    'otp' => $otp
                ], $email);
            } catch (\Exception $mailException) {
                Log::error('Forgot password mail failed', [
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'OTP sent to email'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to process request',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyOtp(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'otp' => 'required'
            ]);

            $record = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$record) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid request'
                ], 400);
            }

            if ($record->otp != $request->otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            if (now()->gt($record->expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP expired'
                ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => 'OTP verified'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'otp' => 'required',
                'password' => 'required|min:6|confirmed'
            ]);

            $record = DB::table('password_resets')
                ->where('email', $request->email)
                ->first();

            if (!$record || $record->otp != $request->otp) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            if (now()->gt($record->expires_at)) {
                return response()->json([
                    'status' => false,
                    'message' => 'OTP expired'
                ], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 UPDATE PASSWORD BASED ON ROLE
        |--------------------------------------------------------------------------
        */

            $hashedPassword = bcrypt($request->password);

            switch ($record->role) {

                case 'employer':
                    \App\Models\Employer::where('email', $request->email)
                        ->update(['password' => $hashedPassword]);
                    break;

                case 'recruiter':
                    \App\Models\EmployerUser::where('email', $request->email)
                        ->update(['password' => $hashedPassword]);
                    break;

                case 'jobseeker':
                    \App\Models\User::where('email', $request->email)
                        ->update(['password' => $hashedPassword]);
                    break;
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 DELETE OTP
        |--------------------------------------------------------------------------
        */

            DB::table('password_resets')
                ->where('email', $request->email)
                ->delete();

            return response()->json([
                'status' => true,
                'message' => 'Password reset successful'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Reset failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
