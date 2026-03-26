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
    public function users()
    {
        return response()->json([
            'status' => true,
            'data' => User::onlyTrashed()->latest()->get()
        ]);
    }

    public function jobSeekers()
    {
        return response()->json([
            'status' => true,
            'data' => JobSeeker::onlyTrashed()->latest()->get()
        ]);
    }

    public function jobs()
    {
        return response()->json([
            'status' => true,
            'data' => Job::onlyTrashed()->latest()->get()
        ]);
    }

    public function employers()
    {
        return response()->json([
            'status' => true,
            'data' => Employer::onlyTrashed()->latest()->get()
        ]);
    }

    public function cvs()
    {
        return response()->json([
            'status' => true,
            'data' => JobSeekerCV::onlyTrashed()->latest()->get()
        ]);
    }

    public function testimonials()
    {
        return response()->json([
            'status' => true,
            'data' => HomepageTestimonial::onlyTrashed()->latest()->get()
        ]);
    }

    public function resumes()
    {
        return response()->json([
            'status' => true,
            'data' => Resume::onlyTrashed()->latest()->get()
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
