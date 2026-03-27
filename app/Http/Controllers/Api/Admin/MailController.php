<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailSetting;
use Illuminate\Http\Request;
use App\Models\EmailTemplate;

class MailController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | ✅ CREATE / UPDATE TEMPLATE
    |--------------------------------------------------------------------------
    */
    public function saveTemplate(Request $request)
    {
        try {

            $request->validate([
                'type' => 'required|in:weekly,recommendation',
                'subject' => 'required|string|max:255',
                'html_template' => 'required|string',
                'is_active' => 'nullable|boolean'
            ]);

            $template = EmailTemplate::updateOrCreate(
                ['type' => $request->type],
                [
                    'subject' => $request->subject,
                    'html_template' => $request->html_template,
                    'is_active' => $request->is_active ?? true
                ]
            );

            return response()->json([
                'status' => true,
                'message' => 'Email template saved successfully',
                'data' => $template
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to save template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ GET TEMPLATE BY TYPE
    |--------------------------------------------------------------------------
    */
    public function getTemplate($type)
    {
        try {

            $template = EmailTemplate::where('type', $type)->first();

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ GET ALL TEMPLATES
    |--------------------------------------------------------------------------
    */
    public function getAllTemplates()
    {
        try {

            $templates = EmailTemplate::latest()->get();

            return response()->json([
                'status' => true,
                'total' => $templates->count(),
                'data' => $templates
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ✅ TOGGLE ACTIVE STATUS
    |--------------------------------------------------------------------------
    */
    public function toggleTemplate($id)
    {
        try {

            $template = EmailTemplate::find($id);

            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            $template->update([
                'is_active' => !$template->is_active
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Template status updated',
                'data' => $template
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'status' => false,
                'message' => 'Failed to update status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    //Time Controlling by Admiin

    public function saveEmailSettings(Request $request)
    {
        $request->validate([
            'day' => 'required|string',
            'time' => 'required',
            'is_active' => 'nullable|boolean'
        ]);

        $setting = EmailSetting::updateOrCreate(
            ['type' => 'weekly'],
            [
                'day' => strtolower($request->day),
                'time' => $request->time,
                'is_active' => $request->is_active ?? true
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Email settings saved',
            'data' => $setting
        ]);
    }

    //Get Email Settings

    public function getEmailSettings()
    {
        $setting = EmailSetting::where('type', 'weekly')->first();

        return response()->json([
            'status' => true,
            'data' => $setting
        ]);
    }
}
