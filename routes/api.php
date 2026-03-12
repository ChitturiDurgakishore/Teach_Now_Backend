<?php


use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\JobBrowseController;



Route::prefix('auth')->group(function () {


    // Public routes
    // Job-Seeker
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Employer
    Route::post('/create-employer', [EmployerController::class, 'createCompany']);
    Route::post('/employer-login', [EmployerController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
    });
});



// ---------------------------------------------------------------------

// Open routes

Route::prefix('open')->group(function () {


    Route::get('/categories', [CategoryController::class, 'index']);  //Categories
    Route::get('/locations', [LocationController::class, 'index']);  //Locations

    // Open Routes for job browsing and viewing job details

    Route::get('/jobs', [JobBrowseController::class, 'browseJobs']);
    Route::get('/jobs/{id}', [JobBrowseController::class, 'viewJob']);
});


// ---------------------------------------------------------------------


// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    // Category management
    Route::get('/categories', [CategoryController::class, 'all']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // Location management
    Route::get('/locations', [LocationController::class, 'all']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::put('/locations/{id}', [LocationController::class, 'update']);
    Route::delete('/locations/{id}', [LocationController::class, 'destroy']);
});


// ----------------------------------------------------------------------------------
// Employer routes

Route::middleware(['auth:employer', 'role:employer'])->prefix('employer')->group(function () {

    Route::post('/logout', [EmployerController::class, 'logout']);

    Route::post('/users', [EmployerController::class, 'createEmployerUser']); //Employer user creation

    Route::get('/users', [EmployerController::class, 'getEmployerUsers']); //Get employer users

    Route::get('/dashboard', [EmployerController::class, 'dashboard']); //Employer dashboard

    Route::get('/jobs', [EmployerController::class, 'getCompanyJobs']); //Get company jobs

    Route::get('/applications', [EmployerController::class, 'getApplications']); //Get applications for company jobs

    Route::put('/Update-Company', [EmployerController::class, 'updateCompanyProfile']); //Update company profile

    Route::delete('/users/{id}', [EmployerController::class, 'deleteEmployerUser']); //Delete employer user

});

// ----------------------------------------------------------------------------------
// Recruiter routes

use App\Http\Controllers\Api\RecruiterController;

Route::prefix('recruiter')->group(function () {

    Route::post('/login', [RecruiterController::class, 'login']);

    Route::middleware('auth:employer_user')->group(function () {

        Route::post('/logout', [RecruiterController::class, 'logout']);

        // Jobs management

        Route::post('/jobs', [RecruiterController::class, 'createJob']); //Create job posting

        Route::put('/jobs/{id}', [RecruiterController::class, 'updateJob']); //Update job posting

        Route::put('/jobs/{id}/filled', [RecruiterController::class, 'markJobFilled']); //Mark job as filled

        Route::get('/jobs', [RecruiterController::class, 'getRecruiterJobs']); //Get recruiter jobs

        //Applications management

        Route::get('/jobs/{id}/applications', [RecruiterController::class, 'getJobApplications']);

        Route::get('/applications/{id}', [RecruiterController::class, 'viewApplicantProfile']);

        Route::post('/applications/{id}/shortlist', [RecruiterController::class, 'shortlistCandidate']);

        Route::get('/jobs/{id}/shortlisted', [RecruiterController::class, 'getShortlistedCandidates']);

        Route::get('/dashboard', [RecruiterController::class, 'dashboard']);
    });
});


// ----------------------------------------------------------------------------------
// Job-Seeker routes

use App\Http\Controllers\Api\JobSeekerController;
use App\Http\Controllers\Api\ResumeController;

Route::middleware(['auth', 'role:job_seeker'])->prefix('jobseeker')->group(function () {

    //Profile management

    Route::post('/profile', [JobSeekerController::class, 'createProfile']); //Create job seeker profile

    Route::get('/profile', [JobSeekerController::class, 'getProfile']); //Get job seeker profile

    Route::put('/profile', [JobSeekerController::class, 'updateProfile']); //Update job seeker profile

    Route::delete('/profile', [JobSeekerController::class, 'deleteProfile']); //Delete job seeker profile

    //Resume management

    Route::post('/resumes', [ResumeController::class, 'uploadResume']);

    Route::get('/resumes', [ResumeController::class, 'getResumes']);

    Route::put('/resumes/{id}/default', [ResumeController::class, 'setDefaultResume']);

    Route::delete('/resumes/{id}', [ResumeController::class, 'deleteResume']);

    // Jobs application management
    Route::post('/jobs/{id}/apply', [JobBrowseController::class, 'applyJob']);

    Route::get('/applications', [JobBrowseController::class, 'getAppliedJobs']);

    Route::delete('/applications/{id}', [JobBrowseController::class, 'withdrawApplication']);

    Route::get('/shortlisted', [JobBrowseController::class, 'getShortlistedJobs']);

    // Bookmark management

    Route::post('/jobs/{id}/bookmark', [JobBrowseController::class, 'bookmarkJob']);

    Route::delete('/jobs/{id}/bookmark', [JobBrowseController::class, 'removeBookmark']);

    Route::get('/bookmarks', [JobBrowseController::class, 'getBookmarkedJobs']);

});
