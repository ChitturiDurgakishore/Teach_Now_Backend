<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Plan;

class PlanController extends Controller
{
    public function store(Request $request)
    {
        try {

            $request->validate([
                'name' => 'required|string|max:100',
                'actual_price' => 'required|numeric|min:0',
                'offer_price' => 'nullable|numeric|min:0',
                'job_posts_limit' => 'required|integer|min:1',
                'validity_days' => 'required|integer|min:1',
                'job_live_days' => 'required|integer|min:1',
                'features' => 'nullable|array',
                // 'featured_jobs_limit' => 'required|integer|min:0',
                'company_featured' => 'required|boolean',
                // 'feature_days'=>'required|integer'
            ]);
            $request->merge([
                'featured_jobs_limit' => $request->featured_jobs_limit === "" ? 0 : $request->featured_jobs_limit,
                'feature_days' => $request->feature_days === "" ? 0 : $request->feature_days,
            ]);

            $plan = Plan::create([
                'name' => $request->name,
                'actual_price' => $request->actual_price,
                'offer_price' => $request->offer_price,
                'job_posts_limit' => $request->job_posts_limit,
                'validity_days' => $request->validity_days,
                'job_live_days' => $request->job_live_days,
                'features' => $request->features,
                'is_active' => true,
                'featured_jobs_limit' => $request->featured_jobs_limit ?? 0,
                'company_featured' => $request->company_featured,
                'display_order' => Plan::max('display_order') + 1,
                'is_highlighted' => $request->is_highlighted ?? false,
                'feature_days' => $request->feature_days ?? 0,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Plan created successfully',
                'data' => $plan
            ], 201);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Plan creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // list

    public function index()
    {
        $plans = Plan::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $plans
        ]);
    }

    public function update(Request $request, $id)
    {
        try {

            $plan = Plan::findOrFail($id);

            $request->validate([
                'name' => 'nullable|string|max:100',
                'actual_price' => 'nullable|numeric|min:0',
                'offer_price' => 'nullable|numeric|min:0',
                'job_posts_limit' => 'nullable|integer|min:1',
                'validity_days' => 'nullable|integer|min:1',
                'job_live_days' => 'nullable|integer|min:1',
                'features' => 'nullable|array',
                'featured_jobs_limit' => 'required|integer|min:0',
                'company_featured' => 'required|boolean',
                'feature_days' => 'required'
            ]);

            $plan->update($request->only([
                'name',
                'actual_price',
                'offer_price',
                'job_posts_limit',
                'validity_days',
                'job_live_days',
                'features',
                'featured_jobs_limit',
                'company_featured',
                'is_highlighted',
                'display_order',
                'feature_days'
            ]));

            return response()->json([
                'status' => true,
                'message' => 'Plan updated successfully',
                'data' => $plan
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Plan update failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function toggle($id)
    {
        $plan = Plan::findOrFail($id);

        $plan->is_active = !$plan->is_active;
        $plan->save();

        return response()->json([
            'status' => true,
            'message' => 'Plan status updated',
            'data' => $plan
        ]);
    }
}
