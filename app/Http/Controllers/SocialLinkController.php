<?php

namespace App\Http\Controllers;

use App\Models\SocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SocialLinkController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/social-links",
     *     summary="Get all social links of the authenticated influencer",
     *     tags={"Social Links"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of social links",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="links",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="platform", type="string", example="Instagram"),
     *                     @OA\Property(property="url", type="string", example="https://instagram.com/influencer")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $links = Auth::user()->socialLinks;
            return response()->json([
                'status' => 'success',
                'links'  => $links
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/social-links",
     *     summary="Create a new social link",
     *     tags={"Social Links"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"platform","url"},
     *             @OA\Property(property="platform", type="string", example="YouTube"),
     *             @OA\Property(property="url", type="string", example="https://youtube.com/influencer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Social link created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="link", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="platform", type="string", example="YouTube"),
     *                 @OA\Property(property="url", type="string", example="https://youtube.com/influencer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'platform' => 'required|string|max:255',
                'url'      => 'required|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $link = Auth::user()->socialLinks()->create($validator->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'link'   => $link
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/social-links/{id}",
     *     summary="Update a social link",
     *     tags={"Social Links"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the social link to update",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="platform", type="string", example="TikTok"),
     *             @OA\Property(property="url", type="string", example="https://tiktok.com/@influencer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Social link updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="link", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="platform", type="string", example="TikTok"),
     *                 @OA\Property(property="url", type="string", example="https://tiktok.com/@influencer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Social link not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, SocialLink $socialLink)
    {
        DB::beginTransaction();
        try {
            if ($socialLink->influencer_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'platform' => 'sometimes|required|string|max:255',
                'url'      => 'sometimes|required|url|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $socialLink->update($validator->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'link'   => $socialLink
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/social-links/{id}",
     *     summary="Delete a social link",
     *     tags={"Social Links"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the social link to delete",
     *         @OA\Schema(type="integer", example=2)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Social link deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Social link deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Social link not found"),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function destroy(SocialLink $socialLink)
    {
        DB::beginTransaction();
        try {
            if ($socialLink->influencer_id !== Auth::id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $socialLink->delete();

            DB::commit();

            return response()->json([
                'status'  => 'success',
                'message' => 'Social link deleted successfully'
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
