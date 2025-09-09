<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\PortfolioItem;
use App\Models\SocialLink;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(name="Users", description="User management")
 */
class UserController extends Controller
{
    /**
     * @OA\Get(
     *     path="/users",
     *     tags={"Users"},
     *     summary="Get all users",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of users",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/User"))
     *     )
     * )
     */

    public function index(): JsonResponse
    {
        try {
            $users = User::all();
            return response()->json([
                'success' => true,
                'users' => $users
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching users.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Get a user by ID with portfolio",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="User found with portfolio"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = User::with(['portfolioItems.mediaItems'])->findOrFail($id);
            return response()->json(['user' => $user], 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'User not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/users",
     *     tags={"Users"},
     *     summary="Create a new user (admin or publisher)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "role"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string"),
     *             @OA\Property(property="role", type="string", enum={"publisher", "influencer"}),
     *             @OA\Property(property="bio", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="profile_photo", type="string"),
     *             @OA\Property(property="cover_photo", type="string"),
     *             @OA\Property(property="company", type="string"),
     *             @OA\Property(property="position", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="User created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,publisher,influencer',

            // Publisher fields
            'companyName' => 'nullable|string',
            'position' => 'nullable|string',

            // Influencer fields
            'socialHandle' => 'nullable|string',
            'category' => 'nullable|string',
            'social_links' => 'nullable|array',

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
            // === 1. Handle Profile & Cover Photos ===
            $profilePhotoPath = $request->hasFile('profile_photo')
                ? $request->file('profile_photo')->store('uploads/profile_photos', 'public')
                : 'defaults/profile-photo.jpg';

            $coverPhotoPath = $request->hasFile('cover_photo')
                ? $request->file('cover_photo')->store('uploads/cover_photos', 'public')
                : 'defaults/cover-photo.jpg';

            // === 2. Create User ===
            $user = User::create([
                'name' => $validated['fullName'],
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

            // === 3. Save Social Links if Influencer ===
            if ($validated['role'] === 'influencer' && isset($validated['social_links'])) {
                foreach ($validated['social_links'] as $platform => $url) {
                    if (!empty($url)) {
                        SocialLink::create([
                            'influencer_id' => $user->id,
                            'platform' => $platform,
                            'url' => $url,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'User created successfully',
                'user' => $user->load('socialLinks'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create user'], 500);
        }
    }



    /**
     * @OA\Put(
     *     path="/users/{id}",
     *     tags={"Users"},
     *     summary="Update user by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="bio", type="string"),
     *             @OA\Property(property="location", type="string"),
     *             @OA\Property(property="profile_photo", type="string"),
     *             @OA\Property(property="cover_photo", type="string"),
     *             @OA\Property(property="company", type="string"),
     *             @OA\Property(property="position", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Restrict update to self or admin
        if (auth()->id() !== $user->id && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'location' => 'nullable|string',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'cover_photo' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:4096',
            'company' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Update profile photo if new file uploaded
            if ($request->hasFile('profile_photo')) {
                // Delete old file if not default
                if ($user->profile_photo && $user->profile_photo !== 'defaults/person-icon.png') {
                    Storage::disk('public')->delete($user->profile_photo);
                }
                $user->profile_photo = $request->file('profile_photo')->store('uploads/profile_photos', 'public');
            }

            // Update cover photo if new file uploaded
            if ($request->hasFile('cover_photo')) {
                if ($user->cover_photo) {
                    Storage::disk('public')->delete($user->cover_photo);
                }
                $user->cover_photo = $request->file('cover_photo')->store('uploads/cover_photos', 'public');
            }

            // Update other fields
            $user->update($request->only([
                'name', 'bio', 'location', 'company', 'position'
            ]));

            DB::commit();
            return response()->json(['message' => 'User updated', 'user' => $user]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User update failed: ' . $e->getMessage());
            return response()->json(['error' => 'Update failed'], 500);
        }
    }


    /**
     * @OA\Delete(
     *     path="/users/{id}",
     *     tags={"Users"},
     *     summary="Delete user by ID",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User deleted"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy($id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        // Only self or admin can delete
        if (auth()->id() !== $user->id ) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $user->delete();
            DB::commit();
            return response()->json(['message' => 'User deleted']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User delete failed: ' . $e->getMessage());
            return response()->json(['error' => 'Delete failed'], 500);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/profile",
     *     summary="Get the authenticated user's profile with portfolio",
     *     tags={"Users"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(response=200, description="Profile data with portfolio items"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function profile(): JsonResponse
    {
        try {
            $user = Auth::user()->load([
                'portfolioItems.mediaItems',
                'socialLinks'
            ]);

            return response()->json(['user' => $user], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/profile/update",
     *     summary="Update user profile",
     *     description="Update the authenticated user's profile information, including basic details, profile and cover photos, social links, portfolio items, and associated media.",
     *     tags={"Profile"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="name", type="string", example="Soulaima 2 Bouhlel"),
     *             @OA\Property(property="email", type="string", example="soulaima12@example.com"),
     *             @OA\Property(property="profile_photo", type="string", format="binary", example="uploads/images/project1.jpg"),
     *             @OA\Property(property="cover_photo", type="string", format="binary", example="uploads/images/project1.jpg"),
     *             @OA\Property(
     *                 property="portfolio_items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="type", type="string", example="media"),
     *                     @OA\Property(property="title", type="string", example="Updated Title"),
     *                     @OA\Property(property="description", type="string", example="Updated Description"),
     *                     @OA\Property(
     *                         property="media_items",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="file_path", type="string", example="uploads/images/new-image.jpg")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="social_links",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="platform", type="string", example="Facebook"),
     *                     @OA\Property(property="url", type="string", example="https://facebook.com/new-link")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Soulaima 2 Bouhlel"),
     *                 @OA\Property(property="email", type="string", example="soulaima12@example.com"),
     *                 @OA\Property(property="profile_photo", type="string", example="uploads/images/project1.jpg"),
     *                 @OA\Property(property="cover_photo", type="string", example="uploads/images/project1.jpg"),
     *                 @OA\Property(
     *                     property="portfolio_items",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=5),
     *                         @OA\Property(property="type", type="string", example="media"),
     *                         @OA\Property(property="title", type="string", example="Updated Title"),
     *                         @OA\Property(property="description", type="string", example="Updated Description"),
     *                         @OA\Property(
     *                             property="media_items",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="file_path", type="string", example="uploads/images/new-image.jpg")
     *                             )
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="social_links",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="platform", type="string", example="Facebook"),
     *                         @OA\Property(property="url", type="string", example="https://facebook.com/new-link")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid data provided")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Something went wrong")
     *         )
     *     )
     * )
     */


    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        DB::beginTransaction();
        try {
            // ✅ Validate user input
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',

                // Role stays fixed but we allow resending it for reference
                'role' => 'sometimes|in:admin,publisher,influencer',

                // Publisher fields
                'companyName' => 'nullable|string|max:255',
                'position' => 'nullable|string|max:255',

                // Influencer fields
                'socialHandle' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'platforms' => 'nullable|array',
                'platforms.*' => 'nullable|url',

                // Common fields
                'bio' => 'nullable|string|max:500',
                'location' => 'nullable|string|max:255',

                // Photos
                'profile_photo' => 'nullable|file|mimes:jpg,jpeg,png|max:5120',
                'cover_photo'   => 'nullable|file|mimes:jpg,jpeg,png|max:5120',

                // Social Links
                'social_links' => 'nullable|array',
                'social_links.*.platform' => 'required_with:social_links|string|max:255',
                'social_links.*.url' => 'required_with:social_links|url|max:500',

                // Portfolio items
                'portfolio_items' => 'nullable|array',
                'portfolio_items.*.id' => 'nullable|exists:portfolio_items,id',
                'portfolio_items.*.type' => 'required_with:portfolio_items|in:media,collaboration',
                'portfolio_items.*.title' => 'required_with:portfolio_items|string|max:255',
                'portfolio_items.*.description' => 'nullable|string',
                'portfolio_items.*.media_items' => 'nullable|array',
                'portfolio_items.*.media_items.*.file_path' => 'required_with:portfolio_items.*.media_items|string'
            ]);

            // ✅ Handle profile photo upload
            if ($request->hasFile('profile_photo')) {
                $profilePath = $request->file('profile_photo')->store('profile_photos', 'public');
                $validated['profile_photo'] = $profilePath;
            }

            // ✅ Handle cover photo upload
            if ($request->hasFile('cover_photo')) {
                $coverPath = $request->file('cover_photo')->store('cover_photos', 'public');
                $validated['cover_photo'] = $coverPath;
            }

            // ✅ Update basic profile info
            $user->update($validated);

            // ✅ Update or create social links
            if ($request->has('social_links')) {
                foreach ($request->social_links as $link) {
                    SocialLink::updateOrCreate(
                        [
                            'influencer_id' => $user->id,
                            'platform' => $link['platform']
                        ],
                        [
                            'url' => $link['url']
                        ]
                    );
                }
            }

            // ✅ Update or create portfolio items and media items
            if ($request->has('portfolio_items')) {
                foreach ($request->portfolio_items as $itemData) {
                    $portfolioItem = PortfolioItem::updateOrCreate(
                        [
                            'id' => $itemData['id'] ?? null,
                            'user_id' => $user->id
                        ],
                        [
                            'type' => $itemData['type'],
                            'title' => $itemData['title'],
                            'description' => $itemData['description'] ?? null
                        ]
                    );

                    if (!empty($itemData['media_items'])) {
                        foreach ($itemData['media_items'] as $mediaData) {
                            MediaItem::updateOrCreate(
                                [
                                    'portfolio_item_id' => $portfolioItem->id,
                                    'file_path' => $mediaData['file_path']
                                ],
                                [
                                    'file_path' => $mediaData['file_path']
                                ]
                            );
                        }
                    }
                }
            }

            DB::commit();

            // ✅ Load all updated relations
            $user->load(['socialLinks', 'portfolioItems.mediaItems']);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'user' => $user
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Profile update failed',
                'debug' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }



}
