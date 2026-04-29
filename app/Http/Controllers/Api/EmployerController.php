<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditHistories;
use Illuminate\Http\Request;
use App\Models\Employer;
use App\Models\EmployerUser;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Models\JobQuestion;
use App\Models\JobAnswer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\DocumentVerification;
use App\Models\HomepageCompanyLogo;
use App\Services\MailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\SubscriptionService;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\Notification;
use App\Models\JobRepublishHistory;


class EmployerController extends Controller
{


    //Notification Service Injection
    //Subscription Service Injection
    // Properties
    protected $notification;
    protected $subscriptionService;

    // ✅ SINGLE constructor
    public function __construct(
        Notification $notification,
        SubscriptionService $subscriptionService
    ) {
        $this->notification = $notification;
        $this->subscriptionService = $subscriptionService;
    }

    //Helper function for Media Uploads


    public function uploadFile($file, $folder)
    {
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        // ✅ FORCE public disk (NO duplication)
        $path = Storage::disk('public')->putFileAs("media/$folder", $file, $filename);

        return 'storage/' . $path;
    }


    // Create Company


    public function createCompany(Request $request)
    {
        try {

            $request->validate([
                'company_name' => 'required|string|max:200',
                'email' => 'required|email',
                'phone' => 'nullable|string',
                'password' => 'required|min:6',
            ]);

            // 🔥 Upload logo
            $logoPath = null;

            if ($request->hasFile('company_logo')) {

                $file = $request->file('company_logo');
                $path = Storage::disk('public')->putFile('media/company_logos', $file);
                $logoPath = 'storage/' . $path;
            }

            $employer = Employer::create([
                'company_name' => $request->company_name,
                'company_description' => $request->company_description,
                'industry' => $request->industry,
                'website' => $request->website,
                'company_logo' => $logoPath,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'map_link' => $request->map_link,
                'password' => Hash::make($request->password),
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'institution_type' => $request->institution_type,
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATION (ADMIN)
        |--------------------------------------------------------------------------
        */

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'company_created',
                    'admin',
                    $admin->id,
                    'New Company Registered',
                    "A new company '{$employer->company_name}' has registered",
                    [
                        'employer_id' => $employer->id,
                        'company_name' => $employer->company_name
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {
                $mailService = new MailService();

                $mailService->send('employer_welcome', [
                    'name' => $employer->company_name,
                    'email' => $employer->email
                ], $employer->email);
            } catch (\Exception $mailException) {

                Log::error('Employer welcome mail failed', [
                    'employer_id' => $employer->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Company created successfully',
                'data' => $employer
            ], 201);
        } catch (\Exception $e) {

            Log::error('Employer registration failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Company creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // EMployer Feature

    public function toggleEmployerFeatured($id)
    {
        try {

            $authEmployer = Auth::guard('employer')->user();

            if (!$authEmployer || $authEmployer->id != $id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $employer = Employer::findOrFail($id);

            /*
        |--------------------------------------------------------------------------
        | 🔥 CHECK SUBSCRIPTION (ONLY THIS)
        |--------------------------------------------------------------------------
        */

            $result = $this->subscriptionService
                ->getFeatureEnabledPlan($employer->id);

            if (!$result['status']) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'] // correct reason
                ], 403);
            }

            $subscription = $result['subscription'];

            /*
        |--------------------------------------------------------------------------
        | 🔥 CURRENT STATE (NO ADMIN CHECK HERE)
        |--------------------------------------------------------------------------
        */

            $isCurrentlyFeatured =
                $employer->company_featured &&
                $employer->featured_until &&
                $employer->featured_until > now();

            $isNowFeatured = !$isCurrentlyFeatured;

            /*
        |--------------------------------------------------------------------------
        | 🔥 TOGGLE
        |--------------------------------------------------------------------------
        */

            if ($isNowFeatured) {

                // ✅ Employer requests feature
                $validTill = now()->addDays($subscription->plan->validity_days);
                if ($validTill > $subscription->expires_at) {
                    $validTill = $subscription->expires_at;
                }
                $employer->update([
                    'company_featured' => true,
                    'featured_until' => $validTill
                ]);
            } else {

                // ✅ Employer removes feature
                $employer->update([
                    'company_featured' => false,
                    'featured_until' => null
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            $statusText = $isNowFeatured ? 'requested for featuring' : 'removed from featuring';

            $this->notification->send(
                'company_featured',
                'employer',
                $employer->id,
                'Company Feature Update',
                "Your company '{$employer->company_name}' has been {$statusText}",
                [
                    'employer_id' => $employer->id,
                    'status' => $statusText
                ]
            );

            /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,
                'message' => $isNowFeatured
                    ? 'Feature request submitted successfully (waiting for admin approval)'
                    : 'Company unfeatured successfully',
                'data' => $employer->fresh()
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to update employer feature status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Employer Login

    public function login(Request $request)
    {
        try {

            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 TRY EMPLOYER LOGIN
        |--------------------------------------------------------------------------
        */
            $employer = Employer::where('email', $request->email)->first();

            if ($employer && Hash::check($request->password, $employer->password)) {

                Auth::guard('employer')->login($employer);
                $request->session()->regenerate();

                return response()->json([
                    'status' => true,
                    'message' => 'Employer login successful',
                    'role' => 'employer',
                    'user' => $employer
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 TRY RECRUITER LOGIN
        |--------------------------------------------------------------------------
        */
            $user = EmployerUser::where('email', $request->email)->first();

            if ($user && Hash::check($request->password, $user->password)) {

                if ($user->is_active != 1) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Account disabled'
                    ], 403);
                }

                Auth::guard('employer_user')->login($user);
                $request->session()->regenerate();

                // 🔥 Employer
                $employer = \App\Models\Employer::find($user->employer_id);

                // 🔥 Platform
                $platformCompany = HomepageCompanyLogo::select(
                    'company_name',
                    'company_logo',
                    'company_url'
                )->first();

                return response()->json([
                    'status' => true,
                    'message' => 'Recruiter login successful',
                    'role' => 'recruiter',

                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'employer_id' => $user->employer_id
                    ],

                    'employer' => [
                        'company_logo' => $employer->company_logo ?? null
                    ],

                    'platform' => [
                        'company_name' => $platformCompany->company_name ?? null,
                        'company_logo' => $platformCompany->company_logo ?? null,
                        'company_link' => $platformCompany->company_url ?? null
                    ]

                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | ❌ INVALID
        |--------------------------------------------------------------------------
        */
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials'
            ], 401);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Employer Logout

    public function logout(Request $request)
    {
        try {

            Auth::guard('employer')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'status' => true,
                'message' => 'Employer logged out successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCompanyProfile(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // 🔥 FETCH COMPANY LOGOS (ACTIVE + ORDERED)
            $companyLogos = HomepageCompanyLogo::where('is_active', 1)
                ->orderBy('display_order', 'asc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'employer' => $employer,
                    'company_logos' => $companyLogos
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateCompanyProfile(Request $request)
    {
        try {

            $request->validate([
                'company_name' => 'nullable|string|max:200',
                'company_description' => 'nullable|string',
                'industry' => 'nullable|string|max:150',
                'website' => 'nullable|string',
                'company_logo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
                'address' => 'nullable|string',
                'phone' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'map_link' => 'nullable|string',
                'latitude' => 'nullable|numeric|between:-90,90',     // ✅ NEW
                'longitude' => 'nullable|numeric|between:-180,180', // ✅ NEW
                'institution_type' => 'nullable|in:School,Intermediate,Diploma,UG,PG',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $logoPath = $employer->company_logo;

            // 🔥 Handle logo update
            if ($request->hasFile('company_logo')) {

                // delete old logo
                if ($employer->company_logo) {
                    $oldPath = str_replace('storage/', 'public/', $employer->company_logo);

                    if (Storage::exists($oldPath)) {
                        Storage::delete($oldPath);
                    }
                }

                // upload new logo
                $file = $request->file('company_logo');
                $path = Storage::disk('public')->putFile('media/company_logos', $file);
                $logoPath = 'storage/' . $path;
            }

            // 🔥 UPDATE DATA
            $employer->update([
                'company_name' => $request->company_name ?? $employer->company_name,
                'company_description' => $request->company_description ?? $employer->company_description,
                'industry' => $request->industry ?? $employer->industry,
                'website' => $request->website ?? $employer->website,
                'company_logo' => $logoPath,
                'address' => $request->address ?? $employer->address,
                'phone' => $request->phone ?? $employer->phone,
                'country' => $request->country ?? $employer->country,
                'city' => $request->city ?? $employer->city,
                'map_link' => $request->map_link ?? $employer->map_link,

                // ✅ NEW FIELDS
                'latitude' => $request->latitude ?? $employer->latitude,
                'longitude' => $request->longitude ?? $employer->longitude,
                'institution_type' => $request->institution_type ?? $employer->institution_type,


            ]);
            if (
                $employer->company_name &&
                $employer->company_description &&
                $employer->industry &&
                $employer->phone
            ) {
                $employer->is_profile_verified = 1;
                $employer->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Company profile updated successfully',
                'data' => $employer
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Company update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Employer Profile flag API

    public function profileFlag()
    {
        try {

            /*
        |--------------------------------------------------------------------------
        | 🔥 CHECK EMPLOYER LOGIN
        |--------------------------------------------------------------------------
        */

            $employer = Auth::guard('employer')->user();

            if ($employer) {
                return response()->json([
                    'status' => true,
                    'is_profile_complete' => $employer->is_profile_verified
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 CHECK RECRUITER LOGIN
        |--------------------------------------------------------------------------
        */

            $recruiter = Auth::guard('employer_user')->user();

            if ($recruiter) {

                // 🔥 get employer
                $employer = \App\Models\Employer::find($recruiter->employer_id);

                return response()->json([
                    'status' => true,
                    'is_profile_complete' => $employer ? $employer->is_profile_verified : false
                ], 200);
            }

            /*
        |--------------------------------------------------------------------------
        | ❌ NO AUTH
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to check profile status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Create Employer User (Recruiter)


    public function createEmployerUser(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:150',
                'email' => 'required|email|unique:employer_users,email',
                'password' => 'required|min:6'
            ]);

            $employer = Auth::guard('employer')->user();

            $user = EmployerUser::create([
                'employer_id' => $employer->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password)
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Recruiter (MAIN)
            $this->notification->send(
                'recruiter_created',
                'recruiter',
                $user->id,
                'Welcome to Company 🎉',
                "You have been added as a recruiter for '{$employer->company_name}'",
                [
                    'employer_id' => $employer->id,
                    'recruiter_id' => $user->id
                ]
            );

            // ✅ Employer (confirmation)
            $this->notification->send(
                'recruiter_created',
                'employer',
                $employer->id,
                'Recruiter Added',
                "You added '{$user->name}' as recruiter",
                [
                    'recruiter_id' => $user->id
                ]
            );

            // ✅ Admin (optional tracking)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'recruiter_created',
                    'admin',
                    $admin->id,
                    'New Recruiter Created',
                    "Recruiter '{$user->name}' added to '{$employer->company_name}'",
                    [
                        'employer_id' => $employer->id,
                        'recruiter_id' => $user->id
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAILS (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                // Recruiter mail
                $mailService->send('recruiter_added', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $employer->company_name
                ], $user->email);

                // Employer mail
                $mailService->send('recruiter_created_employer', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'company_name' => $employer->company_name
                ], $employer->email);
            } catch (\Exception $mailException) {

                Log::error('Recruiter mail failed', [
                    'recruiter_id' => $user->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {

            Log::error('Recruiter creation failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Recruiter creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Get Employer Users (Recruiters)


    public function getEmployerUsers(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $perPage = $request->get('per_page', 10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 PAGINATED USERS
        |--------------------------------------------------------------------------
        */

            $users = EmployerUser::where('employer_id', $employer->id)
                ->latest()
                ->paginate($perPage);

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,

                'total_users' => $users->total(),

                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl(),
                'has_more_pages' => $users->hasMorePages(),

                'data' => collect($users->items())->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone ?? null,
                        'created_at' => $user->created_at,
                    ];
                })

            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch employer users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteEmployerUser($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $user = EmployerUser::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            // 🔥 Store details before delete
            $name = $user->name;
            $email = $user->email;
            $recruiterId = $user->id;

            $user->delete();

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Recruiter (MAIN)
            $this->notification->send(
                'recruiter_deleted',
                'recruiter',
                $recruiterId,
                'Account Removed',
                "You have been removed from '{$employer->company_name}'",
                [
                    'employer_id' => $employer->id
                ]
            );

            // ✅ Employer (confirmation)
            $this->notification->send(
                'recruiter_deleted',
                'employer',
                $employer->id,
                'Recruiter Removed',
                "You removed '{$name}' from your company",
                [
                    'recruiter_id' => $recruiterId
                ]
            );

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'recruiter_deleted',
                    'admin',
                    $admin->id,
                    'Recruiter Removed',
                    "Recruiter '{$name}' removed from '{$employer->company_name}'",
                    [
                        'employer_id' => $employer->id,
                        'recruiter_id' => $recruiterId
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAILS (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                // Recruiter mail
                $mailService->send('recruiter_removed', [
                    'name' => $name,
                    'company_name' => $employer->company_name
                ], $email);

                // Employer mail
                $mailService->send('recruiter_deleted_employer', [
                    'name' => $name,
                    'email' => $email,
                    'company_name' => $employer->company_name
                ], $employer->email);
            } catch (\Exception $mailException) {

                Log::error('Recruiter deletion mail failed', [
                    'recruiter_email' => $email,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Recruiter deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Recruiter delete failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //EMployer Recruiter Toggle Active

    public function toggleEmployerRecruiterStatus($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // ✅ Only recruiter under this employer
            $recruiter = EmployerUser::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 TOGGLE
        |--------------------------------------------------------------------------
        */

            $isNowActive = $recruiter->is_active ? 0 : 1;

            $recruiter->update([
                'is_active' => $isNowActive
            ]);

            $statusText = $isNowActive ? 'enabled' : 'disabled';
            $titleText = $isNowActive ? 'Account Enabled' : 'Account Disabled';

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Recruiter
            $this->notification->send(
                'recruiter_status',
                'recruiter',
                $recruiter->id,
                $titleText,
                "Your recruiter account has been {$statusText} by employer",
                [
                    'status' => $isNowActive
                ]
            );

            // ✅ Employer (self confirmation)
            $this->notification->send(
                'recruiter_status',
                'employer',
                $employer->id,
                $isNowActive ? 'Recruiter Enabled' : 'Recruiter Disabled',
                "You {$statusText} '{$recruiter->name}'",
                [
                    'recruiter_id' => $recruiter->id,
                    'status' => $isNowActive
                ]
            );

            // ✅ Admins
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'recruiter_status',
                    'admin',
                    $admin->id,
                    $isNowActive ? 'Recruiter Enabled' : 'Recruiter Disabled',
                    "Recruiter '{$recruiter->name}' {$statusText} by employer '{$employer->company_name}'",
                    [
                        'recruiter_id' => $recruiter->id,
                        'employer_id' => $employer->id,
                        'status' => $isNowActive
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAILS
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                if ($isNowActive) {
                    $mailService->send('recruiter_enabled', [
                        'name' => $recruiter->name,
                        'company_name' => $employer->company_name
                    ], $recruiter->email);
                } else {
                    $mailService->send('recruiter_disabled', [
                        'name' => $recruiter->name,
                        'company_name' => $employer->company_name
                    ], $recruiter->email);
                }
            } catch (\Exception $mailException) {

                Log::error('Employer toggle recruiter mail failed', [
                    'recruiter_id' => $recruiter->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            /*
        |--------------------------------------------------------------------------
        | ✅ RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,
                'message' => "Recruiter {$statusText} successfully",
                'data' => [
                    'recruiter_id' => $recruiter->id,
                    'is_active' => $isNowActive
                ]
            ], 200);
        } catch (\Exception $e) {

            Log::error('Employer toggle recruiter failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Employer Dashboard

    public function dashboard(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $employerdetails = Employer::where('id', $employer->id)->first();

            $totalRecruiters = EmployerUser::where('employer_id', $employer->id)->count();

            $totalJobs = Job::where('employer_id', $employer->id)->count();

            $totalApplications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })->count();

            $shortlisted = JobApplication::where('status', 'shortlisted')
                ->whereHas('job', function ($q) use ($employer) {
                    $q->where('employer_id', $employer->id);
                })->count();

            /*
        |--------------------------------------------------------------------------
        | 🔥 ACTIVE SUBSCRIPTION (FIFO)
        |--------------------------------------------------------------------------
        */

            $subscription = Subscription::with('plan')
                ->where('employer_id', $employer->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->where(function ($q) {
                    $q->whereColumn('job_posts_used', '<', 'job_posts_total')
                        ->orWhereColumn('featured_jobs_used', '<', 'featured_jobs_total');
                })
                ->orderBy('starts_at', 'asc')
                ->first();

            $subscriptionData = null;

            if ($subscription) {
                $subscriptionData = [
                    'plan_name' => $subscription->plan->name ?? null,

                    'job_credits_total' => $subscription->job_posts_total,
                    'job_credits_used' => $subscription->job_posts_used,
                    'job_credits_remaining' => $subscription->job_posts_total - $subscription->job_posts_used,

                    'feature_credits_total' => $subscription->featured_jobs_total ?? 0,
                    'feature_credits_used' => $subscription->featured_jobs_used ?? 0,
                    'feature_credits_remaining' => ($subscription->featured_jobs_total ?? 0) - ($subscription->featured_jobs_used ?? 0),

                    'expires_at' => $subscription->expires_at,
                ];
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 ALL ACTIVE SUBSCRIPTIONS SUMMARY
        |--------------------------------------------------------------------------
        */

            $activeSubscriptions = Subscription::where('employer_id', $employer->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->get();

            $totalJobCredits = $activeSubscriptions->sum('job_posts_total');
            $totalJobUsed = $activeSubscriptions->sum('job_posts_used');
            $totalJobRemaining = $totalJobCredits - $totalJobUsed;

            $totalFeatureCredits = $activeSubscriptions->sum('featured_jobs_total');
            $totalFeatureUsed = $activeSubscriptions->sum('featured_jobs_used');
            $totalFeatureRemaining = $totalFeatureCredits - $totalFeatureUsed;

            /*
        |--------------------------------------------------------------------------
        | 🔥 RECRUITER USAGE (PAGINATED)
        |--------------------------------------------------------------------------
        */

            $perPage = $request->get('per_page', 5);

            $recruiters = EmployerUser::where('employer_id', $employer->id)
                ->select('id', 'name', 'email')
                ->paginate($perPage);

            $recruiterData = collect($recruiters->items())->map(function ($rec) {

                $jobsUsed = Job::where('created_by', $rec->id)->count();

                $featuredUsed = Job::where('created_by', $rec->id)
                    ->where('featured', true)
                    ->count();

                return [
                    'id' => $rec->id,
                    'name' => $rec->name,
                    'email' => $rec->email,
                    'jobs_used' => $jobsUsed,
                    'featured_jobs_used' => $featuredUsed,
                ];
            });

            /*
        |--------------------------------------------------------------------------
        | 🔥 OTHER DATA
        |--------------------------------------------------------------------------
        */

            $subscriptionHistory = Subscription::with('plan')
                ->where('employer_id', $employer->id)
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($sub) {
                    return [
                        'plan_name' => $sub->plan->name ?? null,
                        'purchase_date' => $sub->purchase_date,
                        'starts_at' => $sub->starts_at,
                        'expires_at' => $sub->expires_at,
                        'status' => $sub->status
                    ];
                });

            $activeFeaturedJobs = Job::where('employer_id', $employer->id)
                ->where('featured', true)
                ->where('featured_until', '>', now())
                ->count();

            $latestJobs = Job::where('employer_id', $employer->id)
                ->latest()
                ->limit(5)
                ->select('id', 'title', 'job_status', 'created_at')
                ->get();

            $latestApplications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
                ->with([
                    'job:id,title',
                    'jobSeeker.user:id,name,email'
                ])
                ->latest()
                ->limit(5)
                ->get();

            $expiringSoon = $subscription && $subscription->expires_at <= now()->addDays(3);

            /*
        |--------------------------------------------------------------------------
        | ✅ FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,
                'data' => [
                    'employer' => $employerdetails,
                    'total_recruiters' => $totalRecruiters,
                    'total_jobs' => $totalJobs,
                    'total_applications' => $totalApplications,
                    'shortlisted_candidates' => $shortlisted,

                    'active_subscription' => $subscriptionData,

                    'credits_summary' => [
                        'job_credits' => [
                            'total' => $totalJobCredits,
                            'used' => $totalJobUsed,
                            'remaining' => $totalJobRemaining,
                        ],
                        'feature_credits' => [
                            'total' => $totalFeatureCredits,
                            'used' => $totalFeatureUsed,
                            'remaining' => $totalFeatureRemaining,
                        ],
                        'active_subscriptions_count' => $activeSubscriptions->count()
                    ],

                    // 🔥 NEW RECRUITER SECTION
                    'recruiters' => [
                        'data' => $recruiterData,
                        'current_page' => $recruiters->currentPage(),
                        'last_page' => $recruiters->lastPage(),
                        'per_page' => $recruiters->perPage(),
                        'total' => $recruiters->total(),
                        'next_page_url' => $recruiters->nextPageUrl(),
                        'prev_page_url' => $recruiters->previousPageUrl(),
                    ],

                    'subscription_history' => $subscriptionHistory,
                    'active_featured_jobs' => $activeFeaturedJobs,
                    'subscription_expiring_soon' => $expiringSoon,
                    'latest_jobs' => $latestJobs,
                    'latest_applications' => $latestApplications,

                    'company_verification' => $employer->is_verified,

                    'company_featured' => $employer->company_featured
                        && $employer->featured_until
                        && $employer->featured_until > now(),

                    'company_featured_until' => $employer->featured_until,
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Dashboard fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    // Jobs Created by Company


    public function getCompanyJobs(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $perPage = $request->get('per_page', 10);
            $search = $request->get('search');

            /*
        |--------------------------------------------------------------------------
        | 🔥 ACTIVE JOBS (WITH SEARCH)
        |--------------------------------------------------------------------------
        */

            $activeQuery = Job::where('employer_id', $employer->id)
                ->where('expires_at', '>', now());

            if ($search) {
                $activeQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%")
                        ->orWhere('location', 'like', "%$search%");
                });
            }

            $activeJobs = $activeQuery
                ->latest()
                ->paginate($perPage, ['*'], 'active_page');

            /*
        |--------------------------------------------------------------------------
        | 🔥 EXPIRED JOBS (WITH SEARCH)
        |--------------------------------------------------------------------------
        */

            $expiredQuery = Job::where('employer_id', $employer->id)
                ->where('expires_at', '<=', now());

            if ($search) {
                $expiredQuery->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%$search%")
                        ->orWhere('location', 'like', "%$search%");
                });
            }

            $expiredJobs = $expiredQuery
                ->latest()
                ->paginate($perPage, ['*'], 'expired_page');

            return response()->json([
                'status' => true,
                'active_jobs' => $activeJobs,
                'expired_jobs' => $expiredJobs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Job details specific for company

    public function getJobDetails($id)
    {
        try {
            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->with(['questions', 'category'])
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $job
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch job details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Creating new Job for Company


    public function createJob(Request $request)
    {
        try {

            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'salary_min' => 'nullable|numeric',
                'salary_max' => 'nullable|numeric',
                'vacancies' => 'nullable|integer',
                'location' => 'nullable|string|max:200',
                'experience_required' => 'nullable|integer',
                'job_type' => 'required|in:full_time,part_time,internship,contract',
                'application_deadline' => 'nullable|date',
                'keywords' => 'nullable|string',
                'gender' => 'nullable|in:male,female,both',
                'questions' => 'nullable|array',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            DB::beginTransaction();

            // 🔥 CHECK & CONSUME CREDIT
            $subscriptionService = app(SubscriptionService::class);

            $result = $subscriptionService->consumeJobCredit($employer->id);

            if (!$result['status']) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 403);
            }

            $subscription = $result['subscription'];

            // 🔥 CREATE JOB
            $job = Job::create([
                'employer_id' => $employer->id,
                'created_by' => null,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'description' => $request->description,
                'salary_min' => $request->salary_min,
                'salary_max' => $request->salary_max,
                'vacancies' => $request->vacancies,
                'location' => $request->location,
                'experience_required' => $request->experience_required,
                'job_type' => $request->job_type,
                'application_deadline' => $request->application_deadline,
                'keywords' => $request->keywords,
                'gender' => $request->gender ?? 'both',
                'experience_type' => $request->experience_type,

                // 🔥 IMPORTANT: use plan job_live_days
                'expires_at' => now()->addDays($subscription->plan->job_live_days),
                'job_subscription_id' => $subscription->id,

                'is_active' => true
            ]);

            $questionsCreated = [];

            // 🔥 ADD QUESTIONS
            if ($request->has('questions')) {
                foreach ($request->questions as $q) {

                    $question = JobQuestion::create([
                        'job_id' => $job->id,
                        'question' => $q['question'],
                        'question_type' => $q['question_type'],
                        'recruiter_answer' => $q['recruiter_answer'] ?? null
                    ]);

                    $questionsCreated[] = $question;
                }
            }

            // 🔥 STORE CREDIT HISTORY
            CreditHistories::create([
                'job_id' => $job->id,
                'employer_id' => $employer->id,
                'recruiter_id' => null, // employer action
                'subscription_id' => $subscription->id,
                'type' => 'job'
            ]);

            DB::commit();

            /*
|--------------------------------------------------------------------------
| 🔔 NOTIFICATIONS
|--------------------------------------------------------------------------
*/

            // ✅ Employer (confirmation)
            $this->notification->send(
                'job_created',
                'employer',
                $employer->id,
                'Job Created',
                "Your job '{$job->title}' has been created successfully",
                [
                    'job_id' => $job->id
                ]
            );

            // ✅ Admin (IMPORTANT)
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_created',
                    'admin',
                    $admin->id,
                    'New Job Posted',
                    "A new job '{$job->title}' has been posted by '{$employer->company_name}'",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $employer->id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job created successfully',
                'data' => [
                    'job' => $job,
                    'questions' => $questionsCreated
                ]
            ], 201);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Job creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update Job for Company


    public function updateJob(Request $request, $id)
    {
        try {

            $request->validate([
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'category_id' => 'nullable|exists:categories,id',
                'salary_min' => 'nullable|numeric',
                'salary_max' => 'nullable|numeric',
                'vacancies' => 'nullable|integer',
                'location' => 'nullable|string|max:200',
                'experience_required' => 'nullable|integer',
                'job_type' => 'nullable|in:full_time,part_time,internship,contract',
                'application_deadline' => 'nullable|date',
                'keywords' => 'nullable|string', // 🔥 NEW
                'gender' => 'nullable|in:male,female,both',

                'questions' => 'nullable|array',
                'questions.*.id' => 'nullable|exists:job_questions,id',
                'questions.*.question' => 'required_with:questions|string',
                'questions.*.question_type' => 'required_with:questions|in:mcq,boolean,numeric,text',
                'questions.*.recruiter_answer' => 'nullable|string',
                'experience_type' => 'required|in:fresher,experienced',
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 Update Job (INCLUDING keywords)
            $job->update($request->only([
                'title',
                'description',
                'category_id',
                'salary_min',
                'salary_max',
                'vacancies',
                'location',
                'experience_required',
                'job_type',
                'application_deadline',
                'keywords', // ✅ NEW
                'gender',
                'experience_type'
            ]));

            $questionsUpdated = [];

            // 🔥 Handle questions
            if ($request->has('questions')) {

                foreach ($request->questions as $q) {

                    if (isset($q['id'])) {

                        $question = JobQuestion::where('id', $q['id'])
                            ->where('job_id', $job->id)
                            ->first();

                        if ($question) {
                            $question->update([
                                'question' => $q['question'],
                                'question_type' => $q['question_type'],
                                'recruiter_answer' => $q['recruiter_answer'] ?? null
                            ]);
                        }
                    } else {

                        $question = JobQuestion::create([
                            'job_id' => $job->id,
                            'question' => $q['question'],
                            'question_type' => $q['question_type'],
                            'recruiter_answer' => $q['recruiter_answer'] ?? null
                        ]);
                    }

                    $questionsUpdated[] = $question;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Job updated successfully',
                'data' => [
                    'job' => $job,
                    'questions' => $questionsUpdated
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Republish Job


    public function republishJob($id)
    {
        try {

            $job = Job::find($id);

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $employer = auth('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            // ✅ Ownership check
            if ($job->employer_id !== $employer->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            DB::beginTransaction();

            /*
        |--------------------------------------------------------------------------
        | 🔥 CONSUME JOB CREDIT (FIFO)
        |--------------------------------------------------------------------------
        */
            $subscriptionService = app(SubscriptionService::class);

            $result = $subscriptionService->consumeJobCredit($employer->id);

            if (!$result['status']) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $result['message']
                ], 403);
            }

            $subscription = $result['subscription'];

            /*
        |--------------------------------------------------------------------------
        | 🔥 OPTIONAL CHECK (avoid republishing active job)
        |--------------------------------------------------------------------------
        */
            if ($job->is_active && $job->expires_at > now()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Job is already active'
                ], 400);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 REPUBLISH JOB
        |--------------------------------------------------------------------------
        */
            $job->update([
                'expires_at' => now()->addDays($subscription->plan->job_live_days),
                'is_active' => true,
                'job_status' => 'open',

                // 🔥 OPTIONAL (if you want latest tracking also)
                'job_subscription_id' => $subscription->id
            ]);

            CreditHistories::create([
                'job_id' => $job->id,
                'employer_id' => $employer->id,
                'recruiter_id' => null,
                'subscription_id' => $subscription->id,
                'type' => 'republish'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔥 STORE REPUBLISH HISTORY (IMPORTANT)
        |--------------------------------------------------------------------------
        */


            DB::commit();

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer
            $this->notification->send(
                'job_republished',
                'employer',
                $employer->id,
                'Job Republished',
                "Your job '{$job->title}' has been republished successfully",
                [
                    'job_id' => $job->id
                ]
            );

            // ✅ Admins
            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_republished',
                    'admin',
                    $admin->id,
                    'Job Republished',
                    "Job '{$job->title}' has been republished by '{$employer->company_name}'",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $employer->id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job republished successfully',
                'data' => $job->fresh()
            ]);
        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'status' => false,
                'message' => 'Republish failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Expired Jobs

    public function getExpiredJobsForEmployer()
    {
        $employer = Auth::guard('employer')->user();

        if (!$employer) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $jobs = Job::where('employer_id', $employer->id)
            ->where('expires_at', '<', now())
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $jobs
        ]);
    }


    //mark job as filled

    public function markJobFilled($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $job->update([
                'job_status' => 'filled'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer (confirmation)
            $this->notification->send(
                'job_filled',
                'employer',
                $employer->id,
                'Job Filled',
                "Your job '{$job->title}' has been marked as filled",
                [
                    'job_id' => $job->id
                ]
            );

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_filled',
                    'admin',
                    $admin->id,
                    'Job Filled',
                    "Job '{$job->title}' has been marked as filled by '{$employer->company_name}'",
                    [
                        'job_id' => $job->id,
                        'employer_id' => $employer->id
                    ]
                );
            }

            // ✅ Recruiter (if exists)
            if ($job->created_by) {
                $this->notification->send(
                    'job_filled',
                    'recruiter',
                    $job->created_by,
                    'Job Filled',
                    "Job '{$job->title}' has been marked as filled",
                    [
                        'job_id' => $job->id
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job marked as filled'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Operation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete Job for Company

    public function deleteJob($id)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            // 🔥 Store job info BEFORE delete
            $jobId = $job->id;
            $jobTitle = $job->title;
            $createdBy = $job->created_by;

            // Delete questions first
            JobQuestion::where('job_id', $job->id)->delete();

            $job->delete();

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer (confirmation)
            $this->notification->send(
                'job_deleted',
                'employer',
                $employer->id,
                'Job Deleted',
                "Your job '{$jobTitle}' has been deleted",
                [
                    'job_id' => $jobId
                ]
            );

            $admins = \App\Models\User::where('role', 'admin')->get();

            foreach ($admins as $admin) {
                $this->notification->send(
                    'job_deleted',
                    'admin',
                    $admin->id,
                    'Job Deleted',
                    "Job '{$jobTitle}' has been deleted by '{$employer->company_name}'",
                    [
                        'job_id' => $jobId,
                        'employer_id' => $employer->id
                    ]
                );
            }

            // ✅ Recruiter (if exists)
            if ($createdBy) {
                $this->notification->send(
                    'job_deleted',
                    'recruiter',
                    $createdBy,
                    'Job Deleted',
                    "Job '{$jobTitle}' has been deleted",
                    [
                        'job_id' => $jobId
                    ]
                );
            }

            return response()->json([
                'status' => true,
                'message' => 'Job deleted successfully'
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job deletion failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Job featuring

    public function toggleJobFeatured($id)
    {
        try {

            $job = Job::findOrFail($id);

            $employer = Auth::guard('employer')->user();

            if (!$employer || $job->employer_id !== $employer->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $subscriptionService = app(\App\Services\SubscriptionService::class);

            $isNowFeatured = !$job->featured;

            /*
        |--------------------------------------------------------------------------
        | 🔥 ENABLE FEATURE
        |--------------------------------------------------------------------------
        */
            if ($isNowFeatured) {

                // ✅ Handles: validation + locking + increment
                $result = $subscriptionService->consumeFeatureCredit($employer->id);

                if (!$result['status']) {
                    return response()->json([
                        'status' => false,
                        'message' => $result['message']
                    ], 403);
                }

                $subscription = $result['subscription'];

                $job->update([
                    'featured' => true,
                    'featured_until' => now()->addDays($subscription->plan->feature_days),
                    'admin_featured' => false,

                    // 🔥 IMPORTANT: TRACK WHICH SUBSCRIPTION USED
                    'feature_subscription_id' => $subscription->id
                ]);

                CreditHistories::create([
                    'job_id' => $job->id,
                    'employer_id' => $employer->id,
                    'recruiter_id' => null,
                    'subscription_id' => $subscription->id,
                    'type' => 'feature'
                ]);
            }
            /*
        |--------------------------------------------------------------------------
        | 🔥 DISABLE FEATURE
        |--------------------------------------------------------------------------
        */

            //Present In Frontend we are implement rule for no Unfeature option
            else {

                // ❌ NO CREDIT REFUND (BUSINESS RULE)
                $job->update([
                    'featured' => false,
                    'featured_until' => null
                    // keep feature_subscription_id as is (history tracking)
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => $isNowFeatured
                    ? 'Job featured successfully'
                    : 'Job unfeatured successfully',
                'data' => $job->fresh()
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {

            return response()->json([
                'status' => false,
                'message' => 'Job not found'
            ], 404);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to update job feature status',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Applications for Company Jobs
    public function getApplications(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $perPage = $request->get('per_page', 10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 FETCH APPLICATIONS WITH RELATIONS
        |--------------------------------------------------------------------------
        */

            $applications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })
                ->with([
                    // 🔥 Job details
                    'job:id,title,job_status,expires_at',

                    // 🔥 Applicant details
                    'jobSeeker',
                    'jobSeeker.user',
                    // 🔥 Resume (if needed)
                    'resume:id,file_name,file_url'
                ])
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => $applications
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Application management get applicaion

    //EMployer jobs
    public function getJobApplications($jobId)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $job = Job::where('id', $jobId)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 FETCH APPLICATIONS
        |--------------------------------------------------------------------------
        */

            $applications = JobApplication::where('job_id', $jobId)
                ->with([
                    'jobSeeker.user:id,name,email,role',
                    'jobSeeker:id,user_id,title,phone,location,experience_years,availability,dob,portfolio_website,bio,profile_photo'
                ])
                ->latest()
                ->get();

            /*
        |--------------------------------------------------------------------------
        | 🔥 TRANSFORM DATA
        |--------------------------------------------------------------------------
        */

            $data = $applications->map(function ($app) {

                // 🔥 Answers
                $answers = \App\Models\JobAnswer::where('job_id', $app->job_id)
                    ->where('job_seeker_id', $app->job_seeker_id)
                    ->with('question:id,question,question_type,recruiter_answer')
                    ->get()
                    ->map(function ($ans) {
                        return [
                            'id' => $ans->question->id ?? null,
                            'question_type' => $ans->question->question_type ?? null,
                            'question' => $ans->question->question ?? null,
                            'recruiter_answer' => $ans->question->recruiter_answer ?? null,
                            'candidate_answer' => $ans->candidate_answer
                        ];
                    });

                return [
                    'id' => $app->id,
                    'job_id' => $app->job_id,
                    'job_seeker_id' => $app->job_seeker_id,
                    'resume_id' => $app->resume_id,
                    'cover_letter' => $app->cover_letter,
                    'status' => $app->status,
                    'contact_status' => $app->contact_status,

                    'answers' => $answers,

                    /*
                |--------------------------------------------------------------------------
                | 🔥 MERGED JOB SEEKER + USER
                |--------------------------------------------------------------------------
                */

                    'job_seeker' => [
                        'id' => $app->jobSeeker->id ?? null,
                        'user_id' => $app->jobSeeker->user_id ?? null,
                        'title' => $app->jobSeeker->title ?? null,
                        'phone' => $app->jobSeeker->phone ?? null,
                        'location' => $app->jobSeeker->location ?? null,
                        'experience_years' => $app->jobSeeker->experience_years ?? 0,
                        'availability' => $app->jobSeeker->availability ?? null,
                        'dob' => $app->jobSeeker->dob ?? null,
                        'portfolio_website' => $app->jobSeeker->portfolio_website ?? null,
                        'bio' => $app->jobSeeker->bio ?? null,
                        'profile_photo' => $app->jobSeeker->profile_photo ?? null,

                        // 🔥 merged user fields
                        'name' => $app->jobSeeker->user->name ?? null,
                        'email' => $app->jobSeeker->user->email ?? null,
                        'role' => $app->jobSeeker->user->role ?? null,
                    ]
                ];
            });

            return response()->json([
                'status' => true,
                'total_applications' => $data->count(),
                'data' => $data
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function viewApplicantProfile($applicationId)
    {
        try {

            $application = JobApplication::with([
                'jobSeeker.user:id,name,email',
                'jobSeeker.skills:id,name',
                'jobSeeker.educations',
                'jobSeeker.experiences'
            ])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 HANDLE RESUME / CV (FIXED)
        |--------------------------------------------------------------------------
        */

            $resume = null;

            if ($application->resume_type === 'cv') {
                $resume = \App\Models\JobSeekerCV::find($application->cv_id); // ✅ FIXED
            } else {
                $resume = \App\Models\Resume::find($application->resume_id);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 FORMAT RESUME RESPONSE
        |--------------------------------------------------------------------------
        */

            $resumeData = null;

            if ($resume) {

                if ($application->resume_type === 'cv') {
                    $filePath = $resume->pdf_path ?? null;
                    $fileName = $resume->title ?? 'CV';
                } else {
                    $filePath = $resume->file_url ?? null;
                    $fileName = $resume->file_name ?? 'Resume';
                }

                $resumeData = [
                    'id' => $resume->id,
                    'file_name' => $fileName,
                    'file_url' => $filePath,
                    'type' => $application->resume_type
                ];
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 QUESTIONS + ANSWERS
        |--------------------------------------------------------------------------
        */

            $answers = JobAnswer::where('job_id', $application->job_id)
                ->where('job_seeker_id', $application->job_seeker_id)
                ->with('question:id,question,question_type,recruiter_answer')
                ->get()
                ->map(function ($ans) {
                    return [
                        'question_id' => $ans->question->id,
                        'question' => $ans->question->question,
                        'question_type' => $ans->question->question_type,
                        'expected_answer' => $ans->question->recruiter_answer,
                        'candidate_answer' => $ans->candidate_answer,
                        'is_correct' => strtolower($ans->candidate_answer) === strtolower($ans->question->recruiter_answer)
                    ];
                })
                ->values();

            /*
        |--------------------------------------------------------------------------
        | 🔥 FINAL RESPONSE
        |--------------------------------------------------------------------------
        */

            $data = [
                'application_id' => $application->id,
                'job_id' => $application->job_id,
                'status' => $application->status,
                'contact_status' => $application->contact_status,

                'resume' => $resumeData,

                'answers' => $answers,

                'job_seeker' => [
                    'id' => $application->jobSeeker->id,
                    'title' => $application->jobSeeker->title,
                    'phone' => $application->jobSeeker->phone,
                    'location' => $application->jobSeeker->location,
                    'experience_years' => $application->jobSeeker->experience_years,
                    'availability' => $application->jobSeeker->availability,
                    'bio' => $application->jobSeeker->bio,
                    'profile_photo' => $application->jobSeeker->profile_photo,

                    'name' => $application->jobSeeker->user->name ?? null,
                    'email' => $application->jobSeeker->user->email ?? null,

                    'skills' => $application->jobSeeker->skills->pluck('name'),
                    'educations' => $application->jobSeeker->educations,
                    'experiences' => $application->jobSeeker->experiences,
                ]
            ];

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Shortlist applicant
    public function shortlistCandidate($applicationId)
    {
        try {

            $application = JobApplication::with(['job', 'jobSeeker.user'])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->update([
                'status' => 'shortlisted'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Job Seeker (MAIN)
            $this->notification->send(
                'candidate_shortlisted',
                'job_seeker',
                $application->job_seeker_id,
                'Application Shortlisted',
                "You have been shortlisted for '{$application->job->title}'",
                [
                    'job_id' => $application->job_id,
                    'application_id' => $application->id
                ]
            );

            // ✅ Employer (confirmation)
            $this->notification->send(
                'candidate_shortlisted',
                'employer',
                $application->job->employer_id,
                'Candidate Shortlisted',
                "You shortlisted a candidate for '{$application->job->title}'",
                [
                    'job_id' => $application->job_id,
                    'application_id' => $application->id
                ]
            );

            // ✅ Recruiter (if exists)
            if ($application->job->created_by) {
                $this->notification->send(
                    'candidate_shortlisted',
                    'recruiter',
                    $application->job->created_by,
                    'Candidate Shortlisted',
                    "A candidate has been shortlisted for '{$application->job->title}'",
                    [
                        'job_id' => $application->job_id,
                        'application_id' => $application->id
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $user = $application->jobSeeker->user;

                $mailService = new MailService();

                $mailService->send('candidate_shortlisted', [
                    'name' => $user->name,
                    'job_title' => $application->job->title
                ], $user->email);
            } catch (\Exception $mailException) {

                Log::error('Shortlist mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate shortlisted successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Shortlisting failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Shortlisting failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Reject Candidate

    public function rejectCandidate($applicationId)
    {
        try {

            $application = JobApplication::with(['job', 'jobSeeker.user'])->find($applicationId);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $application->update([
                'status' => 'rejected'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Job Seeker (MAIN)
            $this->notification->send(
                'candidate_rejected',
                'job_seeker',
                $application->job_seeker_id,
                'Application Update',
                "Your application for '{$application->job->title}' was rejected",
                [
                    'job_id' => $application->job_id,
                    'application_id' => $application->id
                ]
            );

            // ✅ Employer (confirmation)
            $this->notification->send(
                'candidate_rejected',
                'employer',
                $application->job->employer_id,
                'Candidate Rejected',
                "You rejected a candidate for '{$application->job->title}'",
                [
                    'job_id' => $application->job_id,
                    'application_id' => $application->id
                ]
            );

            // ✅ Recruiter (if exists)
            if ($application->job->created_by) {
                $this->notification->send(
                    'candidate_rejected',
                    'recruiter',
                    $application->job->created_by,
                    'Candidate Rejected',
                    "A candidate was rejected for '{$application->job->title}'",
                    [
                        'job_id' => $application->job_id,
                        'application_id' => $application->id
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $user = $application->jobSeeker->user;

                $mailService = new MailService();

                $mailService->send('candidate_rejected', [
                    'name' => $user->name,
                    'job_title' => $application->job->title
                ], $user->email);
            } catch (\Exception $mailException) {

                Log::error('Reject mail failed', [
                    'application_id' => $applicationId,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Candidate rejected successfully'
            ], 200);
        } catch (\Exception $e) {

            Log::error('Rejection failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Rejection failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Get shortlisted candidates

    public function getShortlistedCandidates($jobId)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $job = Job::where('id', $jobId)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$job) {
                return response()->json([
                    'status' => false,
                    'message' => 'Job not found'
                ], 404);
            }

            $shortlisted = JobApplication::where('job_id', $jobId)
                ->where('status', 'shortlisted')
                ->with('jobSeeker.user')
                ->get();

            return response()->json([
                'status' => true,
                'total_shortlisted' => $shortlisted->count(),
                'data' => $shortlisted
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch shortlisted candidates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //=======================================================================================

    //Upload verification documents


    public function uploadDocument(Request $request)
    {
        try {

            $request->validate([
                'document_type' => 'required|string',
                'document_name' => 'nullable|string',
                'document_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240'
            ]);

            $employer = Auth::guard('employer')->user();
            $file = $request->file('document_file');
            $file_type = $request->document_type;
            $docName = $request->document_name ?? $file->getClientOriginalName();

            $filePath = $this->uploadFile($file, 'documents');

            $doc = DocumentVerification::create([
                'employer_id' => $employer->id,
                'document_type' => $file_type,
                'document_name' => $docName,
                'document_file' => $filePath,
                'status' => 'pending'
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATIONS
        |--------------------------------------------------------------------------
        */

            // ✅ Employer (confirmation)
            $this->notification->send(
                'document_uploaded',
                'employer',
                $employer->id,
                'Document Uploaded',
                "Your document '{$file_type}' has been uploaded successfully",
                [
                    'document_id' => $doc->id
                ]
            );

            // ✅ Admin (ACTION REQUIRED 🔥)
            // ✅ GET ALL ADMINS
            $admins = \App\Models\User::where('role', 'admin')->get();

            // ✅ LOOP
            foreach ($admins as $admin) {
                $this->notification->send(
                    'document_uploaded',
                    'admin',
                    $admin->id, // ✅ FIXED
                    'New Document Uploaded',
                    "New '{$file_type}' document uploaded by '{$employer->company_name}'",
                    [
                        'document_id' => $doc->id,
                        'employer_id' => $employer->id
                    ]
                );
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 MAIL (UNCHANGED)
        |--------------------------------------------------------------------------
        */

            try {

                $mailService = new MailService();

                $mailService->send('document_uploaded', [
                    'name' => $employer->company_name,
                    'document_name' => $file_type
                ], $employer->email);
            } catch (\Exception $mailException) {

                Log::error('Document upload mail failed', [
                    'employer_id' => $employer->id,
                    'error' => $mailException->getMessage()
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Document uploaded successfully',
                'data' => $doc
            ]);
        } catch (\Exception $e) {

            Log::error('Document upload failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //View uploaded documents
    public function getMyDocuments()
    {
        $employer = Auth::guard('employer')->user();

        $docs = DocumentVerification::where('employer_id', $employer->id)->get();

        return response()->json([
            'status' => true,
            'data' => $docs
        ]);
    }

    //======================================================


    //Invoices

    //get invoice pdf
    public function getInvoicePdf($id)
    {
        $invoice = Invoice::findOrFail($id);

        if (!$invoice->pdf_path || !Storage::exists($invoice->pdf_path)) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice PDF not found'
            ], 404);
        }

        $pdfContent = base64_encode(Storage::get($invoice->pdf_path));

        return response()->json([
            'status' => true,
            'data' => [
                'pdf_base64' => $pdfContent
            ]
        ]);
    }

    public function downloadInvoice($id)
    {
        $invoice = Invoice::findOrFail($id);

        if (!$invoice->pdf_path || !Storage::exists($invoice->pdf_path)) {
            return response()->json([
                'status' => false,
                'message' => 'Invoice PDF not found'
            ], 404);
        }

        return Storage::download(
            $invoice->pdf_path,
            'invoice_' . $invoice->invoice_number . '.pdf'
        );
    }

    public function getInvoices()
    {
        $employer = Auth::guard('employer')->user();

        $invoices = Invoice::where('employer_id', $employer->id)->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $invoices
        ]);
    }

    //payments history

    public function getPaymentHistory(Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 CURRENT ACTIVE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */
            $currentSubscription = Subscription::where('employer_id', $employer->id)
                ->where('status', 'active')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            $currentPlanId = $currentSubscription?->plan_id;

            /*
        |--------------------------------------------------------------------------
        | 🔥 ALL PLANS (WITH CURRENT FLAG)
        |--------------------------------------------------------------------------
        */
            $plans = Plan::select(
                'id',
                'name',
                'actual_price',
                'offer_price',
                'job_posts_limit',
                'validity_days',
                'job_live_days'
            )
                ->get()
                ->map(function ($plan) use ($currentPlanId) {
                    return [
                        'id' => $plan->id,
                        'name' => $plan->name,
                        'actual_price' => $plan->actual_price,
                        'offer_price' => $plan->offer_price,
                        'job_posts_limit' => $plan->job_posts_limit,
                        'validity_days' => $plan->validity_days,
                        'job_live_days' => $plan->job_live_days,
                        'is_current' => $plan->id == $currentPlanId
                    ];
                });

            /*
        |--------------------------------------------------------------------------
        | 🔥 SUBSCRIPTIONS (PAGINATED + PLAN MAPPED)
        |--------------------------------------------------------------------------
        */
            $subscriptions = Subscription::where('employer_id', $employer->id)
                ->with('plan:id,name,job_posts_limit,validity_days')
                ->latest()
                ->paginate($perPage);

            $subscriptionData = collect($subscriptions->items())->map(function ($sub) {
                return [
                    'id' => $sub->id,
                    'plan_id' => $sub->plan_id,
                    'plan_name' => $sub->plan->name ?? null,
                    'job_posts_total' => $sub->job_posts_total,
                    'job_posts_used' => $sub->job_posts_used,
                    'featured_jobs_total' => $sub->featured_jobs_total,
                    'featured_jobs_used' => $sub->featured_jobs_used,
                    'starts_at' => $sub->starts_at,
                    'expires_at' => $sub->expires_at,
                    'status' => $sub->status,
                    'is_active' => now()->lt($sub->expires_at)
                ];
            });

            /*
        |--------------------------------------------------------------------------
        | 🔥 PAYMENT HISTORY (PAGINATED)
        |--------------------------------------------------------------------------
        */
            $payments = Payment::where('employer_id', $employer->id)
                ->with(['subscription.plan:id,name'])
                ->latest()
                ->paginate($perPage);

            $paymentData = collect($payments->items())->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_id' => $payment->transaction_id,
                    'created_at' => $payment->created_at,
                    'plan_name' => $payment->subscription->plan->name ?? null
                ];
            });

            /*
        |--------------------------------------------------------------------------
        | 🔥 INVOICES (OPTIONAL PAGINATION)
        |--------------------------------------------------------------------------
        */
            $invoices = Invoice::where('employer_id', $employer->id)
                ->latest()
                ->paginate($perPage);

            return response()->json([
                'status' => true,
                'data' => [
                    'plans' => $plans,
                    'current_subscription' => $currentSubscription,

                    // 🔥 NEW
                    'subscriptions' => [
                        'data' => $subscriptionData,
                        'total' => $subscriptions->total(),
                        'current_page' => $subscriptions->currentPage(),
                        'last_page' => $subscriptions->lastPage(),
                    ],

                    'payments' => [
                        'data' => $paymentData,
                        'total' => $payments->total(),
                        'current_page' => $payments->currentPage(),
                        'last_page' => $payments->lastPage(),
                    ],

                    'invoices' => [
                        'data' => $invoices->items(),
                        'total' => $invoices->total(),
                        'current_page' => $invoices->currentPage(),
                        'last_page' => $invoices->lastPage(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch payment data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Subscription usage details
    public function getSubscriptionUsage($id, Request $request)
    {
        try {

            $perPage = $request->get('per_page', 10);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 VALIDATE SUBSCRIPTION
        |--------------------------------------------------------------------------
        */
            $subscription = Subscription::with('plan')
                ->where('id', $id)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => false,
                    'message' => 'Subscription not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 SUMMARY
        |--------------------------------------------------------------------------
        */
            $jobUsed = CreditHistories::where('subscription_id', $id)
                ->where('type', 'job')
                ->count();

            $featureUsed = CreditHistories::where('subscription_id', $id)
                ->where('type', 'feature')
                ->count();

            $summary = [
                'total_job_credits' => $subscription->job_posts_total,
                'used_job_credits' => $jobUsed,
                'remaining_job_credits' => max(0, $subscription->job_posts_total - $jobUsed),

                'total_feature_credits' => $subscription->featured_jobs_total,
                'used_feature_credits' => $featureUsed,
                'remaining_feature_credits' => max(0, $subscription->featured_jobs_total - $featureUsed),
            ];

            /*
        |--------------------------------------------------------------------------
        | 🔥 JOBS CREATED USING THIS SUBSCRIPTION
        |--------------------------------------------------------------------------
        */
            $jobsQuery = CreditHistories::with([
                'job:id,title,location',
                'recruiter:id,name'
            ])
                ->where('subscription_id', $id)
                ->where('type', 'job')
                ->latest();

            $jobs = $jobsQuery->paginate($perPage, ['*'], 'jobs_page');

            $jobsData = collect($jobs->items())->map(function ($item) {

                return [
                    'job_id' => $item->job->id ?? null,
                    'title' => $item->job->title ?? null,
                    'location' => $item->job->location ?? null,

                    'created_by' => $item->recruiter_id ? 'recruiter' : 'employer',

                    'recruiter' => $item->recruiter ? [
                        'id' => $item->recruiter->id,
                        'name' => $item->recruiter->name
                    ] : null,

                    'created_at' => $item->created_at
                ];
            });

            /*
        |--------------------------------------------------------------------------
        | 🔥 FEATURED JOBS USING THIS SUBSCRIPTION
        |--------------------------------------------------------------------------
        */
            $featureQuery = CreditHistories::with([
                'job:id,title,location',
                'recruiter:id,name'
            ])
                ->where('subscription_id', $id)
                ->where('type', 'feature')
                ->latest();

            $featured = $featureQuery->paginate($perPage, ['*'], 'feature_page');

            $featuredData = collect($featured->items())->map(function ($item) {

                return [
                    'job_id' => $item->job->id ?? null,
                    'title' => $item->job->title ?? null,
                    'location' => $item->job->location ?? null,

                    'featured_by' => $item->recruiter_id ? 'recruiter' : 'employer',

                    'recruiter' => $item->recruiter ? [
                        'id' => $item->recruiter->id,
                        'name' => $item->recruiter->name
                    ] : null,

                    'featured_at' => $item->created_at
                ];
            });

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESPONSE
        |--------------------------------------------------------------------------
        */
            return response()->json([
                'status' => true,
                'data' => [
                    'subscription' => [
                        'id' => $subscription->id,
                        'plan_name' => $subscription->plan->name ?? null,
                        'starts_at' => $subscription->starts_at,
                        'expires_at' => $subscription->expires_at,
                    ],

                    'summary' => $summary,

                    'jobs' => [
                        'data' => $jobsData,
                        'total' => $jobs->total(),
                        'current_page' => $jobs->currentPage(),
                        'last_page' => $jobs->lastPage(),
                    ],

                    'featured_jobs' => [
                        'data' => $featuredData,
                        'total' => $featured->total(),
                        'current_page' => $featured->currentPage(),
                        'last_page' => $featured->lastPage(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch subscription usage',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    //Contact status update
    public function updateContactStatusByEmployer(Request $request, $id)
    {
        try {

            $request->validate([
                'contact_status' => 'required|in:called,messaged,not_picked,not_reachable'
            ]);

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $application = JobApplication::with('job')->find($id);

            if (!$application) {
                return response()->json([
                    'status' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // 🔥 Employer owns the job
            if ($application->job->employer_id != $employer->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $application->update([
                'contact_status' => $request->contact_status
            ]);

            /*
        |--------------------------------------------------------------------------
        | 🔔 NOTIFICATION (ONLY JOB SEEKER)
        |--------------------------------------------------------------------------
        */

            $this->notification->send(
                'contact_status_updated',
                'job_seeker',
                $application->job_seeker_id,
                'Contact Update',
                "Your application for '{$application->job->title}' status updated to '{$request->contact_status}'",
                [
                    'job_id' => $application->job_id,
                    'application_id' => $application->id,
                    'contact_status' => $request->contact_status
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Contact status updated (Employer)',
                'data' => $application
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getFeaturedJobs()
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $featuredJobs = Job::where('employer_id', $employer->id)
                ->where('is_featured', true)
                ->select('id', 'title', 'is_featured')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'employer_featured' => $employer->is_featured,
                    'jobs' => $featuredJobs
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch featured status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRecruiterDetails(Request $request, $recruiterId)
    {
        try {

            $employer = Auth::guard('employer')->user();

            if (!$employer) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $perPage = $request->get('per_page', 10);

            /*
        |--------------------------------------------------------------------------
        | 🔥 FETCH RECRUITER (SECURE)
        |--------------------------------------------------------------------------
        */

            $recruiter = EmployerUser::where('id', $recruiterId)
                ->where('employer_id', $employer->id)
                ->first();

            if (!$recruiter) {
                return response()->json([
                    'status' => false,
                    'message' => 'Recruiter not found'
                ], 404);
            }

            /*
        |--------------------------------------------------------------------------
        | 🔥 PAGINATED JOBS
        |--------------------------------------------------------------------------
        */

            $jobs = Job::where('created_by', $recruiter->id)
                ->where('employer_id', $employer->id)
                ->latest()
                ->paginate($perPage);

            /*
        |--------------------------------------------------------------------------
        | 🔥 RESPONSE
        |--------------------------------------------------------------------------
        */

            return response()->json([
                'status' => true,
                'data' => [
                    'recruiter' => [
                        'id' => $recruiter->id,
                        'name' => $recruiter->name,
                        'email' => $recruiter->email,
                        'phone' => $recruiter->phone ?? null,
                        'created_at' => $recruiter->created_at,
                    ],

                    'jobs' => [
                        'total' => $jobs->total(),
                        'current_page' => $jobs->currentPage(),
                        'last_page' => $jobs->lastPage(),
                        'per_page' => $jobs->perPage(),
                        'next_page_url' => $jobs->nextPageUrl(),
                        'prev_page_url' => $jobs->previousPageUrl(),
                        'has_more_pages' => $jobs->hasMorePages(),
                        'data' => collect($jobs->items())->map(function ($job) {
                            return [
                                'id' => $job->id,
                                'title' => $job->title,
                                'job_status' => $job->job_status,
                                'status' => $job->status,
                                'featured' => $job->featured,
                                'created_at' => $job->created_at,
                            ];
                        })
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch recruiter details',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
