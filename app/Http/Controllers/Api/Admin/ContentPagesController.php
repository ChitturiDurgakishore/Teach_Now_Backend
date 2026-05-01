<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AboutUsSection;
use App\Models\PrivacyPolicySections;
use App\Models\TermsConditionsSections;
use Illuminate\Http\Request;

class ContentPagesController extends Controller
{
    //About Us page management

    public function AboutUsIndex()
    {
        try {

            $data = AboutUsSection::whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => function ($q) {
                    $q->where('is_active', true)->orderBy('display_order');
                }])
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function AboutUsStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $data = AboutUsSection::create([
            'parent_id' => $request->parent_id,
            'title' => $request->title,
            'content' => $request->content,
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Created',
            'data' => $data
        ]);
    }

    public function AboutUsUpdate(Request $request, $id)
    {
        $section = AboutUsSection::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->update($request->only([
            'parent_id',
            'title',
            'content',
            'display_order',
            'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Updated',
            'data' => $section
        ]);
    }

    public function AboutUsDestroy($id)
    {
        $section = AboutUsSection::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted'
        ]);
    }

    //Privacy Policy

    //Privacy Policy page management

    public function PrivacyPolicyIndex()
    {
        try {

            $data = PrivacyPolicySections::whereNull('parent_id')
                ->where('is_active', true)
                ->with(['children' => function ($q) {
                    $q->where('is_active', true)->orderBy('display_order');
                }])
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function PrivacyPolicyStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $data = PrivacyPolicySections::create([
            'parent_id' => $request->parent_id,
            'title' => $request->title,
            'content' => $request->content,
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Created',
            'data' => $data
        ]);
    }

    public function PrivacyPolicyUpdate(Request $request, $id)
    {
        $section = PrivacyPolicySections::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->update($request->only([
            'parent_id',
            'title',
            'content',
            'display_order',
            'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Updated',
            'data' => $section
        ]);
    }

    public function PrivacyPolicyDestroy($id)
    {
        $section = PrivacyPolicySections::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted'
        ]);
    }


    //Terms n Conditions page management

    public function TermsAndConditionsIndex()
    {
        try {

            $data = TermsConditionsSections::whereNull('parent_id')->with(['children' => function ($q) {
                $q->orderBy('display_order');
            }])
                ->orderBy('display_order')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Fetch failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function TermsAndConditionsStore(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
        ]);

        $data = TermsConditionsSections::create([
            'parent_id' => $request->parent_id,
            'title' => $request->title,
            'content' => $request->content,
            'display_order' => $request->display_order ?? 0,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Created',
            'data' => $data
        ]);
    }

    public function TermsAndConditionsUpdate(Request $request, $id)
    {
        $section = TermsConditionsSections::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->update($request->only([
            'parent_id',
            'title',
            'content',
            'display_order',
            'is_active'
        ]));

        return response()->json([
            'status' => true,
            'message' => 'Updated',
            'data' => $section
        ]);
    }

    public function TermsAndConditionsDestroy($id)
    {
        $section = TermsConditionsSections::find($id);

        if (!$section) {
            return response()->json(['status' => false, 'message' => 'Not found'], 404);
        }

        $section->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted'
        ]);
    }
}
