<?php


use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\EmployerController;
use App\Http\Controllers\Api\JobBrowseController;
use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\Admin\AdminSEOController;
use App\Http\Controllers\Api\Admin\AdminCMSController;
use App\Http\Controllers\Api\PublicAPIController;
use App\Models\Employer;
use App\Models\FAQ;
use App\Http\Controllers\Api\RecruiterController;
use App\Http\Controllers\Api\JobSeekerController;
use App\Http\Controllers\Api\ResumeController;
use App\Http\Controllers\Api\CVController;
use App\Http\Controllers\Api\Admin\AdminDeletedController;
use App\Http\Controllers\Api\Admin\ContentPagesController;
use App\Http\Controllers\Api\Admin\MailController;
use App\Http\Controllers\Api\Admin\PlanController;
use App\Http\Controllers\Payment\OrderController;
use App\Http\Controllers\Api\ResourceController;

Route::prefix('auth')->group(function () {


    // Public routes
    // Job-Seeker
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // Employer
    Route::post('/create-employer', [EmployerController::class, 'createCompany']);
    Route::post('/employer-login', [EmployerController::class, 'login']);
});


Route::prefix('auth')->group(function () {

    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});


Route::prefix('auth')->middleware('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// ---------------------------------------------------------------------

// Open routes

Route::prefix('open')->group(function () {


    //Dashboard APIs Combination for optimization

    Route::get('/categories', [CategoryController::class, 'index']);  //Categories
    //Categories jobs
    Route::get('/category/{slug}', [PublicAPIController::class, 'getJobsByCategory']);
    Route::get('/locations', [LocationController::class, 'index']);  //Locations
    Route::get('/location/{slug}/jobs', [PublicAPIController::class, 'getJobsByLocation']);

    // Company details and jobs
    Route::get('/company/{slug}/profile', [PublicAPIController::class, 'getCompanyPublicProfile']);

    // Open Routes for job browsing and viewing job details

    Route::get('/jobs', [JobBrowseController::class, 'browseJobs']);
    Route::get('/jobs/{slug}', [JobBrowseController::class, 'viewJob']);

    // Search filter

    Route::get('/search/suggestions', [PublicAPIController::class, 'searchSuggestions']);
    Route::get('search/jobs/search', [PublicAPIController::class, 'searchJobs']);
    Route::get('/filters', [PublicAPIController::class, 'getFilters']);
    // Public APIs for frontend


    //hero Section API
    Route::get('/home/hero-section', [PublicAPIController::class, 'getHeroSectionData']);

    //nav bar
    Route::get('/home/navigation', [PublicAPIController::class, 'getNavbarData']);

    //stats
    Route::get('/home/stats', [PublicAPIController::class, 'getStats']);

    //testimonials
    Route::get('/home/testimonials', [PublicAPIController::class, 'getTestimonials']);


    Route::get('home/footer', [PublicAPIController::class, 'getFooter']);

    // Featured jobs and employers
    Route::get('home/featured-companies', [PublicAPIController::class, 'getFeaturedCompanies']);
    Route::get('home/featured-jobs', [PublicAPIController::class, 'getFeaturedJobs']);


    // FAQs
    Route::get('/home/faqs', [PublicAPIController::class, 'getFAQs']);

    // Blogs

    Route::get('/blogs', [PublicAPIController::class, 'getBlogs']);
    Route::get('/blogs/latest', [PublicAPIController::class, 'getLatestBlogs']);
    Route::get('/blogs/{slug}', [PublicAPIController::class, 'getBlogBySlug']);

    //Teacher Resources
    Route::get('/resources/{slug}', [PublicAPIController::class, 'viewResource']);
    Route::get('/resources', [PublicAPIController::class, 'listResources']);

    //Open Pages Content
    Route::get('/about-us', [PublicAPIController::class, 'aboutUs']);
    Route::get('/privacy-policy', [PublicAPIController::class, 'privacyPolicy']);
    Route::get('/terms-conditions', [PublicAPIController::class, 'termsConditions']);
});

//resource download
Route::middleware(['auth'])->get('/download/{slug}', [PublicAPIController::class, 'download']);

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

    // Dashboard and analytics
    Route::get('/dashboard', [AdminController::class, 'dashboard']);

    //Jobs Management for Admin

    //Expired Jobs

    Route::get('/jobs/expired', [AdminController::class, 'getExpiredJobsForAdmin']);
    Route::post('/jobs/{id}/republish', [AdminController::class, 'adminRepublishJob']);
    Route::get('/jobs', [AdminController::class, 'getAllJobs']);
    Route::get('/jobs/{id}', [AdminController::class, 'getJobDetails']);
    Route::patch('/jobs/{id}/approve', [AdminController::class, 'approveJob']);
    Route::patch('/jobs/{id}/reject', [AdminController::class, 'rejectJob']);
    Route::patch('/jobs/{id}/feature', [AdminController::class, 'featureJob']);
    Route::delete('/jobs/{id}', [AdminController::class, 'deleteJob']);


    //Employer Management for Admin

    Route::get('/employers', [AdminController::class, 'getEmployers']);
    Route::get('/employers/{id}', [AdminController::class, 'getEmployerDetails']);
    Route::put('/employers/{id}', [AdminController::class, 'updateEmployer']);
    Route::delete('/employers/{id}', [AdminController::class, 'deleteEmployer']);
    Route::patch('/employers/{id}/verify', [AdminController::class, 'verifyEmployer']);
    Route::patch('/employers/{id}/feature', [AdminController::class, 'featureEmployer']);

    //Recruiters Management for Admin
    Route::get('/recruiters', [AdminController::class, 'getRecruiters']);
    Route::get('/recruiters/{id}', [AdminController::class, 'getRecruiterDetails']);
    Route::patch('/recruiters/{id}/disable', [AdminController::class, 'disableRecruiter']);
    Route::delete('/recruiters/{id}', [AdminController::class, 'deleteRecruiter']);

    //Job Seekers Management for Admin
    Route::get('/jobseekers', [AdminController::class, 'getJobSeekers']);
    Route::get('/jobseekers/{id}', [AdminController::class, 'getJobSeekerDetails']);
    Route::patch('/jobseekers/{id}/disable', [AdminController::class, 'disableJobSeeker']);
    Route::delete('/jobseekers/{id}', [AdminController::class, 'deleteJobSeeker']);

    //Application Management for Admin
    Route::get('/applications', [AdminController::class, 'getApplications']);
    Route::get('/applications/{id}', [AdminController::class, 'getApplicationDetails']);
    Route::get('/jobs/{jobId}/applications', [AdminController::class, 'getApplicationsByJob']);
    Route::delete('/applications/{id}', [AdminController::class, 'deleteApplication']);
});

// ----------------------------------------------------------------------------------
//Admin SEO Management routes


Route::prefix('admin/seo')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

    Route::put('/job/{id}', [AdminSEOController::class, 'updateJobSEO']);
    Route::put('/category/{id}', [AdminSEOController::class, 'updateCategorySEO']);
    Route::put('/location/{id}', [AdminSEOController::class, 'updateLocationSEO']);
    Route::put('/employer/{id}', [AdminSEOController::class, 'updateEmployerSEO']);

    Route::put('/homepage-section/{id}', [AdminSEOController::class, 'updateHomepageSectionSEO']);
    Route::put('/navigation/{id}', [AdminSEOController::class, 'updateNavigationLinkSEO']);
    Route::put('/footer-section/{id}', [AdminSEOController::class, 'updateFooterSectionSEO']);
    Route::put('/footer-link/{id}', [AdminSEOController::class, 'updateFooterLinkSEO']);
});


// ----------------------------------------------------------------------------------
//Admin CMS Management routes

Route::prefix('admin/cms')->middleware(['auth:sanctum', 'role:admin'])->group(function () {

    //HeroSection
    Route::get('/hero', [AdminCMSController::class, 'getHeroSection']);
    Route::post('/hero', [AdminCMSController::class, 'updateHeroSection']);

    //StatsSection
    Route::get('stats', [AdminCMSController::class, 'getStats']);
    Route::post('stats', [AdminCMSController::class, 'updateStats']);

    // Testimonials
    Route::get('testimonials', [AdminCMSController::class, 'getTestimonials']);
    Route::post('testimonials', [AdminCMSController::class, 'createTestimonial']);
    Route::put('testimonials/{id}', [AdminCMSController::class, 'updateTestimonial']);
    Route::delete('testimonials/{id}', [AdminCMSController::class, 'deleteTestimonial']);
    Route::patch('testimonials/{id}/toggle', [AdminCMSController::class, 'toggleTestimonial']);

    // CTA Section

    Route::get('/cta', [AdminCMSController::class, 'getCTASection']);
    Route::post('/cta', [AdminCMSController::class, 'storeCTASection']);
    Route::put('/cta/{id}', [AdminCMSController::class, 'updateCTASection']);
    Route::delete('/cta/{id}', [AdminCMSController::class, 'deleteCTA']);
    Route::patch('/cta/{id}/toggle', [AdminCMSController::class, 'toggleCTA']);

    // Navigation Links
    //Updated function Dynamic nav bar
    // Route::get('navigation', [AdminCMSController::class, 'getNavigationLinks']);
    // Route::post('navigation', [AdminCMSController::class, 'createNavigationLink']);
    // Route::put('navigation/{id}', [AdminCMSController::class, 'updateNavigationLink']);
    // Route::delete('navigation/{id}', [AdminCMSController::class, 'deleteNavigationLink']);
    // Route::patch('navigation/{id}/toggle', [AdminCMSController::class, 'toggleNavigationLink']);

    // Create
    Route::post('navigation', [AdminCMSController::class, 'store']);
    // Get all (with children)
    Route::get('navigation', [AdminCMSController::class, 'index']);
    // Update
    Route::put('navigation/{id}', [AdminCMSController::class, 'update']);
    // Delete
    Route::delete('navigation/{id}', [AdminCMSController::class, 'delete']);
    // Toggle Active
    Route::patch('navigation/{id}/toggle-active', [AdminCMSController::class, 'toggleActive']);
    // Toggle Show in Navbar
    Route::patch('navigation/{id}/toggle-nav', [AdminCMSController::class, 'toggleShowInNav']);


    // Footer Sections

    Route::get('footer-sections', [AdminCMSController::class, 'getFooterSections']);
    Route::post('footer-sections', [AdminCMSController::class, 'createFooterSection']);
    Route::put('footer-sections/{id}', [AdminCMSController::class, 'updateFooterSection']);
    Route::delete('footer-sections/{id}', [AdminCMSController::class, 'deleteFooterSection']);
    Route::patch('footer-sections/{id}/toggle', [AdminCMSController::class, 'toggleFooterSection']);

    // Footer Links

    Route::get('footer-links', [AdminCMSController::class, 'getFooterLinks']);
    Route::post('footer-links', [AdminCMSController::class, 'createFooterLink']);
    Route::put('footer-links/{id}', [AdminCMSController::class, 'updateFooterLink']);
    Route::delete('footer-links/{id}', [AdminCMSController::class, 'deleteFooterLink']);
    Route::patch('footer-links/{id}/toggle', [AdminCMSController::class, 'toggleFooterLink']);


    // Homepage Company Logos

    Route::get('company-logos', [AdminCMSController::class, 'getCompanyLogos']);
    Route::post('company-logos', [AdminCMSController::class, 'createCompanyLogo']);
    Route::put('company-logos/{id}', [AdminCMSController::class, 'updateCompanyLogo']);
    Route::delete('company-logos/{id}', [AdminCMSController::class, 'deleteCompanyLogo']);

    // FAQs

    Route::get('faqs', [AdminCMSController::class, 'getFAQs']);
    Route::post('faqs', [AdminCMSController::class, 'createFAQ']);
    Route::put('faqs/{id}', [AdminCMSController::class, 'updateFAQ']);
    Route::delete('faqs/{id}', [AdminCMSController::class, 'deleteFAQ']);

    // Blogs

    Route::get('blogs', [AdminCMSController::class, 'getBlogs']);
    Route::post('blogs', [AdminCMSController::class, 'createBlog']);
    Route::put('blogs/{id}', [AdminCMSController::class, 'updateBlog']);
    Route::delete('blogs/{id}', [AdminCMSController::class, 'deleteBlog']);
    Route::patch('blogs/{id}/toggle', [AdminCMSController::class, 'toggleBlogStatus']);

    // Skills management
    Route::get('/skills', [AdminCMSController::class, 'getSkills']);
    Route::post('/skills', [AdminCMSController::class, 'createSkill']);
    Route::put('/skills/{id}', [AdminCMSController::class, 'updateSkill']);
    Route::delete('/skills/{id}', [AdminCMSController::class, 'deleteSkill']);
    Route::patch('/skills/{id}/toggle', [AdminCMSController::class, 'toggleSkill']);

    // Email Templates management
    Route::get('/email-templates', [AdminCMSController::class, 'getEmailTemplates']);
    Route::post('/email-templates', [AdminCMSController::class, 'createEmailTemplate']);
    Route::put('/email-templates/{id}', [AdminCMSController::class, 'updateEmailTemplate']);
    Route::delete('/email-templates/{id}', [AdminCMSController::class, 'deleteEmailTemplate']);
    Route::patch('/email-templates/{id}/toggle', [AdminCMSController::class, 'toggleEmailTemplate']);

    //CORN Jobs for sending emails
    Route::post('corn/save', [MailController::class, 'saveTemplate']);
    Route::get('corn/all', [MailController::class, 'getAllTemplates']);
    Route::get('corn/{type}', [MailController::class, 'getTemplate']);
    Route::post('corn/toggle/{id}', [MailController::class, 'toggleTemplate']);

    //Corn Job Time settings
    Route::post('/mail/settings', [MailController::class, 'saveEmailSettings']);
    Route::get('/mail/settings', [MailController::class, 'getEmailSettings']);

    // Teaching Resources management
    Route::get('/resources', [AdminCMSController::class, 'getResources']);
    Route::post('/resources', [AdminCMSController::class, 'createResource']);
    Route::put('/resources/update/{id}', [AdminCMSController::class, 'updateResource']);
    Route::delete('resources/{id}', [AdminCMSController::class, 'deleteResource']);
    Route::patch('resources/{id}/toggle-visibility', [AdminCMSController::class, 'toggleResourceVisibility']);

    // CV Templates management
    Route::get('/cv-templates', [AdminCMSController::class, 'GetAllTemplates']);
    Route::post('/cv-templates', [AdminCMSController::class, 'CreateCV']);
    Route::get('/cv-templates/{id}', [AdminCMSController::class, 'showCV']);
    Route::put('/cv-templates/{id}', [AdminCMSController::class, 'updateCVTemplate']);
    Route::delete('/cv-templates/{id}', [AdminCMSController::class, 'destroyCVTemplate']);
    Route::patch('/cv-templates/{id}/toggle', [AdminCMSController::class, 'toggleStatusCVTemplate']);


    //About Us Page management
    Route::get('/about-us', [ContentPagesController::class, 'AboutUsIndex']);
    Route::post('/about-us', [ContentPagesController::class, 'AboutUsStore']);
    Route::put('/about-us/{id}', [ContentPagesController::class, 'AboutUsUpdate']);
    Route::delete('/about-us/{id}', [ContentPagesController::class, 'AboutUsDestroy']);

    //Terms and Conditions management
    Route::get('/terms-and-conditions', [ContentPagesController::class, 'TermsAndConditionsIndex']);
    Route::post('/terms-and-conditions', [ContentPagesController::class, 'TermsAndConditionsStore']);
    Route::put('/terms-and-conditions/{id}', [ContentPagesController::class, 'TermsAndConditionsUpdate']);
    Route::delete('/terms-and-conditions/{id}', [ContentPagesController::class, 'TermsAndConditionsDestroy']);

    //Privacy Policy management
    Route::get('/privacy-policy', [ContentPagesController::class, 'PrivacyPolicyIndex']);
    Route::post('/privacy-policy', [ContentPagesController::class, 'PrivacyPolicyStore']);
    Route::put('/privacy-policy/{id}', [ContentPagesController::class, 'PrivacyPolicyUpdate']);
    Route::delete('/privacy-policy/{id}', [ContentPagesController::class, 'PrivacyPolicyDestroy']);
});

// =================================================================================

// Admin Deleted Items Management routes

Route::prefix('admin/deleted')->group(function () {

    // LIST
    Route::get('users', [AdminDeletedController::class, 'users']);
    Route::get('job-seekers', [AdminDeletedController::class, 'jobSeekers']);
    Route::get('jobs', [AdminDeletedController::class, 'jobs']);
    Route::get('employers', [AdminDeletedController::class, 'employers']);
    Route::get('cvs', [AdminDeletedController::class, 'cvs']);
    Route::get('testimonials', [AdminDeletedController::class, 'testimonials']);
    Route::get('resumes', [AdminDeletedController::class, 'resumes']);

    // RESTORE
    Route::post('users/{id}/restore', [AdminDeletedController::class, 'restoreUser']);
    Route::post('job-seekers/{id}/restore', [AdminDeletedController::class, 'restoreJobSeeker']);
    Route::post('jobs/{id}/restore', [AdminDeletedController::class, 'restoreJob']);
    Route::post('employers/{id}/restore', [AdminDeletedController::class, 'restoreEmployer']);
    Route::post('cvs/{id}/restore', [AdminDeletedController::class, 'restoreCV']);
    Route::post('testimonials/{id}/restore', [AdminDeletedController::class, 'restoreTestimonial']);
    Route::post('resumes/{id}/restore', [AdminDeletedController::class, 'restoreResume']);
});

//Plans Management for Admin

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {

    Route::post('/plans', [PlanController::class, 'store']);
    Route::get('/plans', [PlanController::class, 'index']);
    Route::put('/plans/{id}', [PlanController::class, 'update']);
    Route::patch('/plans/{id}', [PlanController::class, 'toggle']);
});


// =========================================================================================
// ----------------------------------------------------------------------------------
// Employer routes

// Payments and Orders route

Route::middleware(['auth:employer', 'role:employer'])->prefix('employer/payment')->group(function () {
    Route::post('/create-order', [OrderController::class, 'createOrder']);
    Route::post('/verify-payment', [OrderController::class, 'verifyPayment']);
});


//Profile flag API
Route::middleware(['auth:employer,employer_user'])
    ->get('/profile-flag', [EmployerController::class, 'profileFlag']);


Route::middleware(['auth:employer', 'role:employer'])->prefix('employer')->group(function () {

    // Recruiter management for employer
    Route::post('/users', [EmployerController::class, 'createEmployerUser']); //Employer user creation
    Route::get('/users', [EmployerController::class, 'getEmployerUsers']); //Get employer users
    Route::delete('/users/{id}', [EmployerController::class, 'deleteEmployerUser']); //Delete employer user
    //Dashboard
    Route::get('/dashboard', [EmployerController::class, 'dashboard']); //Employer dashboard

    //Document verification
    Route::post('/documents/upload', [EmployerController::class, 'uploadDocument']);
    Route::get('/documents', [EmployerController::class, 'getMyDocuments']);


    Route::get('/applications', [EmployerController::class, 'getApplications']); //Get applications for company jobs

    Route::get('/profile', [EmployerController::class, 'getCompanyProfile']); //Get company profile
    Route::put('/Update-Company', [EmployerController::class, 'updateCompanyProfile']); //Update company profile


    //Jobs management
    Route::get('/jobs', [EmployerController::class, 'getCompanyJobs']); //Get company jobs
    //separate api for specific job details
    Route::get('/jobs/{id}', [EmployerController::class, 'getJobDetails']); //Get specific job details
    Route::post('/jobs/create', [EmployerController::class, 'createJob']); //Create job posting
    Route::put('/jobs/update/{id}', [EmployerController::class, 'updateJob']); //Update job posting
    Route::delete('/jobs/delete/{id}', [EmployerController::class, 'deleteJob']); //Delete job posting
    Route::put('/jobs/{id}/filled', [EmployerController::class, 'markJobFilled']); //Mark job as filled

    Route::get('/jobs/expired', [EmployerController::class, 'getExpiredJobsForEmployer']); //Employer expiry jobs
    Route::put('/jobs/{id}/republish', [EmployerController::class, 'republishJob']); //Republish job
    // Application management

    Route::get('/jobs/{jobId}', [EmployerController::class, 'getJobApplications']);
    Route::get('/profile/{applicationId}', [EmployerController::class, 'viewApplicantProfile']);
    Route::patch('/shortlist/{applicationId}', [EmployerController::class, 'shortlistCandidate']);
    Route::patch('/reject/{applicationId}', [EmployerController::class, 'rejectCandidate']);
    Route::get('/shortlisted/{jobId}', [EmployerController::class, 'getShortlistedCandidates']);

    // Featuring
    Route::post('/job/{id}/toggle-feature', [EmployerController::class, 'toggleJobFeatured']);
    Route::post('/{id}/toggle-feature', [EmployerController::class, 'toggleEmployerFeatured']);

    // Testimonials management
    Route::get('testimonials', [RecruiterController::class, 'getTestimonials']);
    Route::post('testimonials', [RecruiterController::class, 'createTestimonial']);
    Route::put('testimonials/{id}', [RecruiterController::class, 'updateTestimonial']);
    Route::delete('testimonials/{id}', [RecruiterController::class, 'deleteTestimonial']);

    //Invoice management
    Route::get('/invoices', [EmployerController::class, 'getInvoices']);
    Route::get('/invoices', [EmployerController::class, 'getInvoicePdf']);
    Route::get('/invoices/{id}', [EmployerController::class, 'downloadInvoice']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [EmployerController::class, 'logout']);
    });
});

// ----------------------------------------------------------------------------------
// Recruiter routes



Route::prefix('recruiter')->group(function () {

    Route::post('/login', [RecruiterController::class, 'login']);
    Route::middleware('auth:employer_user')->group(function () {



        //Profile management


        Route::get('/profile', [RecruiterController::class, 'profileflag']); //Get recruiter profile

        // Jobs management
        Route::get('/jobs/expired', [RecruiterController::class, 'getExpiredJobsForRecruiter']); //Expired jobs
        Route::post('/jobs', [RecruiterController::class, 'createJob']); //Create job posting
        Route::put('/jobs/{id}', [RecruiterController::class, 'updateJob']); //Update job posting
        Route::put('/jobs/{id}/filled', [RecruiterController::class, 'markJobFilled']); //Mark job as filled
        Route::get('/jobs', [RecruiterController::class, 'getRecruiterJobs']); //Get recruiter jobs
        Route::put('/jobs/{id}/republish', [RecruiterController::class, 'republishJob']); //Republish job


        // Feature JOb
        Route::post('/job/{id}/toggle-feature', [RecruiterController::class, 'toggleJobFeatured']);

        //Applications management

        Route::get('/applications', [RecruiterController::class, 'getApplications']); //Get all applications for recruiter
        Route::get('/jobs/{id}/applications', [RecruiterController::class, 'getJobApplications']);
        Route::get('/applications/{id}', [RecruiterController::class, 'viewApplicantProfile']);
        Route::post('/applications/{id}/shortlist', [RecruiterController::class, 'shortlistCandidate']);
        Route::post('/applications/{id}/reject', [RecruiterController::class, 'rejectCandidate']);
        Route::get('/jobs/{id}/shortlisted', [RecruiterController::class, 'getShortlistedCandidates']);
        Route::get('/shortlisted', [RecruiterController::class, 'getAllShortlistedCandidates']); //Get all shortlisted candidates
        Route::get('/dashboard', [RecruiterController::class, 'dashboard']);

        //Testimonials management
        Route::get('testimonials', [RecruiterController::class, 'getTestimonials']);
        Route::post('testimonials', [RecruiterController::class, 'createTestimonial']);
        Route::put('testimonials/{id}', [RecruiterController::class, 'updateTestimonial']);
        Route::delete('testimonials/{id}', [RecruiterController::class, 'deleteTestimonial']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [RecruiterController::class, 'logout']);
    });
});


// ----------------------------------------------------------------------------------
// Job-Seeker routes



Route::middleware(['auth:sanctum', 'role:job_seeker'])->prefix('jobseeker')->group(function () {

    //Profile management

    Route::post('/profile', [JobSeekerController::class, 'createProfile']); //Create job seeker profile
    Route::get('/profile', [JobSeekerController::class, 'getProfile']); //Get job seeker profile
    Route::put('/profile', [JobSeekerController::class, 'updateProfile']); //Update job seeker profile
    Route::delete('/profile', [JobSeekerController::class, 'deleteProfile']); //Delete job seeker profile

    // Education management

    Route::post('/education', [JobSeekerController::class, 'addEducation']);
    Route::put('/education/{id}', [JobSeekerController::class, 'updateEducation']);
    Route::delete('/education/{id}', [JobSeekerController::class, 'deleteEducation']);

    // Experience management

    Route::post('experience', [JobSeekerController::class, 'addExperience']);
    Route::put('experience/{id}', [JobSeekerController::class, 'updateExperience']);
    Route::delete('experience/{id}', [JobSeekerController::class, 'deleteExperience']);

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

    // Testimonials management
    Route::get('testimonials', [JobSeekerController::class, 'getTestimonials']);
    Route::post('testimonials', [JobSeekerController::class, 'createTestimonial']);
    Route::put('testimonials/{id}', [JobSeekerController::class, 'updateTestimonial']);
    Route::delete('testimonials/{id}', [RecruiterController::class, 'deleteTestimonial']);


    //CV Generation

    Route::post('cv/generate-base', [CVController::class, 'generateBaseCV']);
    Route::post('cv/generate-job', [CVController::class, 'generateJobCV']);
    //cv deletion
    Route::delete('cv/{id}', [JobSeekerController::class, 'deleteResumeOrCV']);
    //get active cvs
    Route::get('cv/templates', [CVController::class, 'getActiveTemplates']);

    //Dashboard
    Route::get('/dashboard', [JobSeekerController::class, 'JobSeekerDashboard']);

    //Logout for jobseeker
    Route::post('/logout', [JobSeekerController::class, 'logout']);
});


// ----------------------------------------------------------------------------------
