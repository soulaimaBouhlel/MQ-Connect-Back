<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Offer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Exception;

class ApplicationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/applications",
     *     summary="Get all applications (restricted access)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="List of applications"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
            }

            if ($user->role === 'admin') {
                // Admin sees all applications
                $applications = Application::with(['user', 'offer.publisher'])->get();
            } elseif ($user->role === 'influencer') {
                // Influencer sees only their own applications
                $applications = Application::with(['user', 'offer.publisher'])
                    ->where('user_id', $user->id)
                    ->get();
            } elseif ($user->role === 'publisher') {
                // Publisher sees applications for their offers
                $applications = Application::with(['user', 'offer.publisher'])
                    ->whereHas('offer', function ($q) use ($user) {
                        $q->where('user_id', $user->id); // offer's publisher
                    })
                    ->get();
            } else {
                return response()->json(['status' => 'error', 'message' => 'Forbidden'], 403);
            }

            return response()->json(['status' => 'success', 'data' => $applications], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while fetching applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/applications/{id}",
     *     summary="Get a single application by ID (restricted access)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Application ID"
     *     ),
     *     @OA\Response(response=200, description="Application retrieved successfully"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function show($id)
    {
        $application = Application::with(['user', 'offer.publisher'])->find($id);

        if (!$application) {
            return response()->json(['message' => 'Application not found'], 404);
        }

        $user = auth()->user();

        if (
            $user->role !== 'admin' &&
            $user->id !== $application->user_id &&
            $user->id !== $application->offer->publisher_id
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json($application, 200);
    }

    /**
     * @OA\Post(
     *     path="/api/applications",
     *     summary="Create a new application for an offer",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"offer_id", "pricing_type"},
     *             @OA\Property(property="offer_id", type="integer", example=1),
     *             @OA\Property(property="pricing_type", type="string", enum={"fixed","commission","cost-per-post"}, example="fixed"),
     *             @OA\Property(property="goals", type="string", example="Promote brand awareness"),
     *             @OA\Property(property="budget", type="string", example="500"),
     *             @OA\Property(property="criteria", type="string", example="At least 5k followers"),
     *             @OA\Property(property="niche", type="string", example="Fashion")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Application created successfully"),
     *     @OA\Response(response=400, description="Validation or business logic error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'offer_id' => 'required|integer|exists:offers,id',
            'pricing_type' => 'required|in:fixed,commission,cost-per-post',
            'goals' => 'nullable|string',
            'budget' => 'nullable|string',
            'criteria' => 'nullable|string',
            'niche' => 'nullable|string',
        ]);
        $user = Auth::user();

        // Ensure user is an influencer
        if ($user->role !== 'influencer') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only influencers can apply to offers.'
            ], 403);
        }

        // Transaction logic
        DB::beginTransaction();

        try {
            // Check offer status
            $offer = Offer::findOrFail($request->offer_id);
            if ($offer->status !== 'open') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot apply to this offer. Offer status must be open.'
                ], 400);
            }

            // Prevent duplicate application
            if (Application::where('user_id', $user->id)->where('offer_id', $request->offer_id)->exists()) {
                return response()->json(['error' => 'You have already applied to this offer'], 400);
            }
            // Create application
            $application = Application::create([
                'user_id' => Auth::id(),
                'offer_id' => $request->offer_id,
                'pricing_type' => $request->pricing_type,
                'goals' => $request->goals,
                'budget' => $request->budget,
                'criteria' => $request->criteria,
                'niche' => $request->niche
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Application created successfully.',
                'data' => $application
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage()
            ], 500);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * @OA\Put(
     *     path="/api/applications/{id}",
     *     summary="Update an application (only owner)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         @OA\JsonContent(
     *             @OA\Property(property="pricing_type", type="string", enum={"fixed","commission","cost-per-post"}, example="commission"),
     *             @OA\Property(property="goals", type="string", example="Increase engagement"),
     *             @OA\Property(property="budget", type="string", example="300"),
     *             @OA\Property(property="criteria", type="string", example="Beauty niche influencers"),
     *             @OA\Property(property="niche", type="string", example="Beauty")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Application updated"),
     *     @OA\Response(response=403, description="Forbidden - Not owner"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $application = Application::findOrFail($id);

        if ($application->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'pricing_type' => 'nullable|in:fixed,commission,cost-per-post',
            'goals' => 'nullable|string',
            'budget' => 'nullable|string',
            'criteria' => 'nullable|string',
            'niche' => 'nullable|string',
        ]);

        $application->update($validated);

        return response()->json(['status' => 'success', 'data' => $application]);
    }

    /**
     * @OA\Delete(
     *     path="/api/applications/{id}",
     *     summary="Delete an application (only owner)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Application deleted"),
     *     @OA\Response(response=403, description="Forbidden - Not owner"),
     *     @OA\Response(response=404, description="Application not found")
     * )
     */
    public function destroy($id)
    {
        $application = Application::findOrFail($id);

        if ($application->user_id !== Auth::id()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $application->delete();

        return response()->json(['status' => 'success', 'message' => 'Application deleted']);
    }
    /**
     * @OA\Get(
     *     path="/api/applications/user/{userId}",
     *     summary="Get all applications by a specific user (restricted access)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="User ID"
     *     ),
     *     @OA\Response(response=200, description="List of applications by the user"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function getByUser($userId)
    {
        $user = auth()->user();

        if ($user->role !== 'admin' && $user->id !== $userId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $applications = Application::with('offer')
            ->where('user_id', $userId)
            ->get();

        return response()->json($applications, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/applications/offer/{offerId}",
     *     summary="Get all applications for a specific offer (restricted access)",
     *     tags={"Applications"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="offerId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="Offer ID"
     *     ),
     *     @OA\Response(response=200, description="List of applications for the offer"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function getByOffer($offerId)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthenticated'
                ], 401);
            }

            $offer = Offer::find($offerId);

            if (!$offer) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Offer not found'
                ], 404);
            }

            // Check permissions
            if ($user->role === 'admin' || $user->id === $offer->user_id) {
                // Admin or publisher: can see all applications
                $applications = Application::with('user')
                    ->where('offer_id', $offerId)
                    ->get();
            } elseif ($user->role === 'influencer') {
                // Influencer: can see only their own application
                $applications = Application::with('user')
                    ->where('offer_id', $offerId)
                    ->where('user_id', $user->id)
                    ->get();
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Forbidden'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $applications
            ], 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while fetching applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
