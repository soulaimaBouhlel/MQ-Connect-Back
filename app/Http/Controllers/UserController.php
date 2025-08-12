<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:publisher,influencer',
            'bio' => 'nullable|string',
            'location' => 'nullable|string',
            'profile_photo' => 'nullable|string',
            'cover_photo' => 'nullable|string',
            'company' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'verified' => $validated['role'] === 'publisher' ? false : null,
                'bio' => $validated['bio'] ?? null,
                'location' => $validated['location'] ?? null,
                'profile_photo' => $validated['profile_photo'] ?? null,
                'cover_photo' => $validated['cover_photo'] ?? null,
                'company' => $validated['role'] === 'publisher' ? $validated['company'] ?? null : null,
                'position' => $validated['role'] === 'publisher' ? $validated['position'] ?? null : null,
            ]);

            DB::commit();
            return response()->json(['message' => 'User created', 'user' => $user], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('User creation failed: ' . $e->getMessage());
            return response()->json(['error' => 'User creation failed'], 500);
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

        // Restrict update to the user themself or admin
        if (auth()->id() !== $user->id && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'bio' => 'nullable|string',
            'location' => 'nullable|string',
            'profile_photo' => 'nullable|string',
            'cover_photo' => 'nullable|string',
            'company' => 'nullable|string',
            'position' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user->update($request->only([
                'name',
                'bio',
                'location',
                'profile_photo',
                'cover_photo',
                'company',
                'position'
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
                'portfolioItems.mediaItems'
            ]);

            return response()->json(['user' => $user], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Something went wrong'], 500);
        }
    }

}
