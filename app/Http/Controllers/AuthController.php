<?php

namespace App\Http\Controllers;

use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="Authentication", description="User authentication")
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "password_confirmation", "role"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="role", type="string", enum={"admin", "publisher", "influencer"})
     *         )
     *     ),
     *     @OA\Response(response=201, description="User registered successfully"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,publisher,influencer',

            // Publisher fields
            'companyName' => 'nullable|string',
            'position' => 'nullable|string',

            // Influencer fields
            'socialHandle' => 'nullable|string',
            'category' => 'nullable|string',
            'social_links' => 'nullable|array',          // associative array: platform => url

            // Common fields
            'location' => 'nullable|string',

            // Photos
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::beginTransaction();
        try {
            // Handle profile & cover photos
            $profilePhotoPath = $request->hasFile('profile_photo')
                ? $request->file('profile_photo')->store('uploads/profile_photos', 'public')
                : 'defaults/profile-photo.jpg';

            $coverPhotoPath = $request->hasFile('cover_photo')
                ? $request->file('cover_photo')->store('uploads/cover_photos', 'public')
                : 'defaults/cover-photo.jpg';

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'verified' => $validated['role'] === 'publisher' ? false : null,
                'bio' => $validated['bio'] ?? null,
                'location' => $validated['location'] ?? null,
                'profile_photo' => $profilePhotoPath,
                'cover_photo' => $coverPhotoPath,
                'company' => $validated['role'] === 'publisher' ? $validated['companyName'] ?? null : null,
                'position' => $validated['role'] === 'publisher' ? $validated['position'] ?? null : null,
                'social_handle' => $validated['role'] === 'influencer' ? $validated['socialHandle'] ?? null : null,
                'category' => $validated['role'] === 'influencer' ? $validated['category'] ?? null : null,
            ]);

            // Save social links if influencer
            if ($validated['role'] === 'influencer' && isset($validated['social_links'])) {
                foreach ($validated['social_links'] as $link) {
                    if (!empty($link['url'])) {
                        SocialLink::create([
                            'influencer_id' => $user->id,
                            'platform' => $link['platform'],
                            'url' => $link['url'],
                        ]);
                    }
                }
            }


            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            DB::commit();

            return response()->json([
                'message' => 'User registered successfully',
                'token' => $token,
                'user' => $user->load('socialLinks'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Register failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500); // show actual error for debugging
        }
    }

    /**
     * @OA\Post(
     *     path="/login",
     *     tags={"Authentication"},
     *     summary="Login a user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Login successful"),
     *     @OA\Response(response=401, description="Invalid credentials")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                Log::warning('Failed login attempt', ['email' => $request->email]);
                return response()->json([
                    'message' => 'Invalid credentials'
                ], 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            Log::info('User logged in successfully', ['user_id' => $user->id]);

            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            Log::error('Login error', [
                'message' => $e->getMessage(),
                'stack' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'An error occurred during login'
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/logout",
     *     tags={"Authentication"},
     *     summary="Logout the current user",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Logout successful")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
