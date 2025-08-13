<?php


namespace App\Http\Controllers;

use App\Models\Offer;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/offers",
     *     summary="Create a new offer",
     *     tags={"Offers"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Instagram Campaign"),
     *             @OA\Property(property="description", type="string", example="Promote our new product"),
     *             @OA\Property(property="goals", type="string", example="Increase brand awareness"),
     *             @OA\Property(property="budget", type="number", format="float", example=1500.00),
     *             @OA\Property(property="criteria", type="string", example="At least 10k followers"),
     *             @OA\Property(property="niche", type="string", example="Fashion"),
     *             @OA\Property(property="status", type="string", example="open")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Offer created successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'goals' => 'nullable|string',
                'budget' => 'required|numeric|min:0',
                'criteria' => 'nullable|string',
                'niche' => 'nullable|string',
                'status' => 'required|in:open,closed,draft',
            ]);

            $validated['user_id'] = auth()->id();

            $offer = Offer::create($validated);

            return response()->json([
                'status' => 'success',
                'data' => $offer
            ], 201);

        } catch (QueryException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database error: ' . $e->getMessage(),
                'hint' => 'Check if the provided "criteria" matches allowed values or remove column constraints in migration.'
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/offers/{id}",
     *     summary="Get a specific offer",
     *     tags={"Offers"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Offer retrieved successfully"),
     *     @OA\Response(response=404, description="Offer not found")
     * )
     */
    public function show($id)
    {
        try {
            $offer = Offer::find($id);

            if (!$offer) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Offer not found'
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Offer retrieved successfully',
                'data'    => $offer
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server error',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/offers",
     *     summary="List all offers",
     *     tags={"Offers"},
     *     @OA\Response(response=200, description="List of offers")
     * )
     */
    public function index()
    {
        try {
            $offers = Offer::orderBy('created_at', 'desc')->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Offers retrieved successfully',
                'data'    => $offers
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Server error',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/offers/{id}",
     *     summary="Update an offer (publisher only)",
     *     description="Allows a publisher to update their own offer.",
     *     operationId="updateOffer",
     *     tags={"Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the offer to update",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *        @OA\JsonContent(
     *              @OA\Property(property="user_id", type="integer", example=1),
     *              @OA\Property(property="title", type="string", example="Instagram Campaign"),
     *              @OA\Property(property="description", type="string", example="Promote our new product"),
     *              @OA\Property(property="goals", type="string", example="Increase brand awareness"),
     *              @OA\Property(property="budget", type="number", format="float", example=1500.00),
     *              @OA\Property(property="criteria", type="string", example="At least 10k followers"),
     *              @OA\Property(property="niche", type="string", example="Fashion"),
     *              @OA\Property(property="status", type="string", example="open")
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Offer updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Offer updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Offer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to update this offer",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized to update this offer"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Offer not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Offer not found"))
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Validation error"))
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $offer = Offer::find($id);

            if (!$offer) {
                return response()->json([
                    'message' => 'Offer not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if the logged-in publisher is the owner
            if ($offer->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized to update this offer'
                ], Response::HTTP_FORBIDDEN);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'goals' => 'nullable|string',
                'budget' => 'required|numeric|min:0',
                'criteria' => 'nullable|string',
                'niche' => 'nullable|string',
                'status' => 'required|in:open,closed,draft',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation error',
                    'errors'  => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            DB::transaction(function () use ($offer, $request) {
                $offer->update($request->only(['title', 'description', 'goals', 'budget', 'criteria', 'status']));
            });

            return response()->json([
                'message' => 'Offer updated successfully',
                'data'    => $offer
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/offers/{id}",
     *     summary="Delete an offer (publisher only)",
     *     description="Allows a publisher to delete their own offer.",
     *     operationId="deleteOffer",
     *     tags={"Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the offer to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Offer deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Offer deleted successfully"))
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized to delete this offer",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Unauthorized to delete this offer"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Offer not found",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Offer not found"))
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $offer = Offer::find($id);

            if (!$offer) {
                return response()->json([
                    'message' => 'Offer not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // Check if the logged-in publisher is the owner
            if ($offer->user_id !== Auth::id()) {
                return response()->json([
                    'message' => 'Unauthorized to delete this offer'
                ], Response::HTTP_FORBIDDEN);
            }

            DB::transaction(function () use ($offer) {
                $offer->delete();
            });

            return response()->json([
                'message' => 'Offer deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error',
                'error'   => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}

