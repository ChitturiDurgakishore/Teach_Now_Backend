<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\JobSeeker;
use App\Models\Job;
use App\Models\Employer;
use App\Models\JobSeekerCV;
use App\Models\HomepageTestimonial;
use App\Models\Resume;

class AdminDeletedController extends Controller
{
    //List APIs
    public function users(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = User::onlyTrashed()
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }

    public function jobSeekers(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = JobSeeker::onlyTrashed()
            ->with([
                'user:id,name,email',
                'educations',
                'experiences',
                'skills'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }

    public function jobs(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = Job::onlyTrashed()
            ->with([
                'employer:id,company_name,company_logo',
                'questions',
                'jobApplications:id,job_id,status'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }

    public function employers()
    {
        return response()->json([
            'status' => true,
            'data' => Employer::onlyTrashed()->latest()->get()
        ]);
    }

    public function cvs(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = JobSeekerCV::onlyTrashed()
            ->with([
                'jobSeeker:id,user_id',
                'jobSeeker.user:id,name,email'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }

    public function testimonials(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = HomepageTestimonial::onlyTrashed()
            ->with('user:id,name,email')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }

    public function resumes(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $data = Resume::onlyTrashed()
            ->with([
                'jobSeeker:id,user_id',
                'jobSeeker.user:id,name,email'
            ])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'total' => $data->total(),
            'current_page' => $data->currentPage(),
            'last_page' => $data->lastPage(),
            'data' => $data->items()
        ]);
    }


    //Restore APIs

    public function restoreUser($id)
    {
        $user = User::onlyTrashed()->find($id);

        if (!$user) {
            return response()->json(['status' => false, 'message' => 'User not found'], 404);
        }

        $user->restore();

        // 🔥 restore related job seeker if exists
        JobSeeker::withTrashed()
            ->where('user_id', $user->id)
            ->restore();

        return response()->json(['status' => true, 'message' => 'User restored']);
    }

    public function restoreJobSeeker($id)
    {
        $jobSeeker = JobSeeker::onlyTrashed()->find($id);

        if (!$jobSeeker) {
            return response()->json(['status' => false, 'message' => 'Job seeker not found'], 404);
        }

        $jobSeeker->restore();

        return response()->json(['status' => true, 'message' => 'Job seeker restored']);
    }

    public function restoreJob($id)
    {
        $job = Job::onlyTrashed()->find($id);

        if (!$job) {
            return response()->json(['status' => false, 'message' => 'Job not found'], 404);
        }

        $job->restore();

        return response()->json(['status' => true, 'message' => 'Job restored']);
    }

    public function restoreEmployer($id)
    {
        $employer = Employer::onlyTrashed()->find($id);

        if (!$employer) {
            return response()->json(['status' => false, 'message' => 'Employer not found'], 404);
        }

        $employer->restore();

        // 🔥 restore user also
        User::withTrashed()
            ->where('id', $employer->user_id)
            ->restore();

        return response()->json(['status' => true, 'message' => 'Employer restored']);
    }

    public function restoreCV($id)
    {
        $cv = JobSeekerCV::onlyTrashed()->find($id);

        if (!$cv) {
            return response()->json(['status' => false, 'message' => 'CV not found'], 404);
        }

        $cv->restore();

        return response()->json(['status' => true, 'message' => 'CV restored']);
    }

    public function restoreTestimonial($id)
    {
        $item = HomepageTestimonial::onlyTrashed()->find($id);

        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Testimonial not found'], 404);
        }

        $item->restore();

        return response()->json(['status' => true, 'message' => 'Testimonial restored']);
    }

    public function restoreResume($id)
    {
        $resume = Resume::onlyTrashed()->find($id);

        if (!$resume) {
            return response()->json(['status' => false, 'message' => 'Resume not found'], 404);
        }

        $resume->restore();

        return response()->json(['status' => true, 'message' => 'Resume restored']);
    }
}
