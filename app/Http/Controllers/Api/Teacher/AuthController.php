<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'nullable|string',
        ]);

        $teacher = Teacher::where('email', $request->email)->first();

        if (! $teacher || ! Hash::check($request->password, $teacher->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة.',
            ], 401);
        }

        if (! $teacher->is_approved) {
            return response()->json([
                'message' => 'لم يتم تفعيل حسابك من قبل الإدارة بعد.',
            ], 403);
        }

        $token = $teacher->createToken($request->device_name ?? 'mobile-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'teacher' => new TeacherResource($teacher),
        ]);
    }
}
