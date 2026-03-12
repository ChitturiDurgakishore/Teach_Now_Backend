<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Employer;
use App\Models\EmployerUser;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class EmployerController extends Controller
{

    // Create Company


    public function createCompany(Request $request)
    {
        try {

            $request->validate([
                'company_name' => 'required|string|max:200',
                'company_description' => 'nullable|string',
                'industry' => 'nullable|string|max:150',
                'website' => 'nullable|string',
                'company_logo' => 'nullable|string',
                'address' => 'nullable|string',
                'email' => 'required|email',
                'phone' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'map_link' => 'nullable|string',
                'password' => 'required|min:6'
            ]);

            $employer = Employer::create([
                'company_name' => $request->company_name,
                'company_description' => $request->company_description,
                'industry' => $request->industry,
                'website' => $request->website,
                'company_logo' => $request->company_logo,
                'address' => $request->address,
                'email' => $request->email,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'map_link' => $request->map_link,
                'password' => Hash::make($request->password),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Company created successfully',
                'data' => $employer
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Company creation failed',
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

            $user = Employer::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {

                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            // login user with session
            Auth::guard('employer')->login($user);

            $request->session()->regenerate();

            return response()->json([
                'status' => true,
                'message' => 'Employer login successful',
                'user' => $user
            ], 200);
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

    public function updateCompanyProfile(Request $request)
    {
        try {

            $employer = Auth::guard('employer')->user();

            $request->validate([
                'company_name' => 'required|string|max:200',
                'company_description' => 'nullable|string',
                'industry' => 'nullable|string|max:150',
                'website' => 'nullable|string',
                'company_logo' => 'nullable|string',
                'address' => 'nullable|string',
                'phone' => 'nullable|string',
                'country' => 'nullable|string',
                'city' => 'nullable|string',
                'map_link' => 'nullable|string'
            ]);

            $employer->update([
                'company_name' => $request->company_name,
                'company_description' => $request->company_description,
                'industry' => $request->industry,
                'website' => $request->website,
                'company_logo' => $request->company_logo,
                'address' => $request->address,
                'phone' => $request->phone,
                'country' => $request->country,
                'city' => $request->city,
                'map_link' => $request->map_link
            ]);

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

            return response()->json([
                'status' => true,
                'message' => 'Recruiter created successfully',
                'data' => $user
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Recruiter creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    //Get Employer Users (Recruiters)


    public function getEmployerUsers()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $users = EmployerUser::where('employer_id', $employer->id)->get();

            return response()->json([
                'status' => true,
                'total_users' => $users->count(),
                'data' => $users
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

            $user->delete();

            return response()->json([
                'status' => true,
                'message' => 'Recruiter deleted successfully'
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Delete failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Employer Dashboard


    public function dashboard()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $totalRecruiters = EmployerUser::where('employer_id', $employer->id)->count();

            $totalJobs = Job::where('employer_id', $employer->id)->count();

            $totalApplications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })->count();

            $shortlisted = JobApplication::where('status', 'shortlisted')
                ->whereHas('job', function ($q) use ($employer) {
                    $q->where('employer_id', $employer->id);
                })->count();

            return response()->json([
                'status' => true,
                'data' => [
                    'total_recruiters' => $totalRecruiters,
                    'total_jobs' => $totalJobs,
                    'total_applications' => $totalApplications,
                    'shortlisted_candidates' => $shortlisted
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


    public function getCompanyJobs()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $jobs = Job::where('employer_id', $employer->id)->latest()->get();

            return response()->json([
                'status' => true,
                'total_jobs' => $jobs->count(),
                'data' => $jobs
            ], 200);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Unable to fetch jobs',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Applications for Company Jobs

    public function getApplications()
    {
        try {

            $employer = Auth::guard('employer')->user();

            $applications = JobApplication::whereHas('job', function ($q) use ($employer) {
                $q->where('employer_id', $employer->id);
            })->latest()->get();

            return response()->json([
                'status' => true,
                'total_applications' => $applications->count(),
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
}
