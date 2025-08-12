<?php

namespace App\Http\Controllers;

use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * @OA\Tag(
 *     name="Portfolio Items",
 *     description="API Endpoints for managing portfolio items"
 * )
 */
class PortfolioItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/portfolio",
     *     tags={"Portfolio Items"},
     *     summary="Get all portfolio items",
     *     @OA\Response(
     *         response=200,
     *         description="List of portfolio items"
     *     )
     * )
     */
    public function index()
    {
        try {
            $items = PortfolioItem::all();
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch portfolio items'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/portfolio",
     *     tags={"Portfolio Items"},
     *     summary="Create a new portfolio item",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "type", "title"},
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="type", type="string", enum={"media","collaboration"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string", nullable=true),
     *             @OA\Property(property="publisher_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Portfolio item created")
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'type' => 'required|in:media,collaboration',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'publisher_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $item = PortfolioItem::create($request->all());
            DB::commit();
            return response()->json($item, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to create portfolio item'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/portfolio/{id}",
     *     tags={"Portfolio Items"},
     *     summary="Get a portfolio item by ID",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Portfolio item details"),
     *     @OA\Response(response=404, description="Portfolio item not found")
     * )
     */
    public function show($id)
    {
        try {
            $item = PortfolioItem::findOrFail($id);
            return response()->json($item, 200);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Portfolio item not found'], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/portfolio/{id}",
     *     tags={"Portfolio Items"},
     *     summary="Update a portfolio item",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="type", type="string", enum={"media","collaboration"}),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="publisher_id", type="integer", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Portfolio item updated"),
     *     @OA\Response(response=404, description="Portfolio item not found")
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'in:media,collaboration',
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'publisher_id' => 'nullable|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $item = PortfolioItem::findOrFail($id);
            $item->update($request->all());
            DB::commit();
            return response()->json($item, 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['error' => 'Portfolio item not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Failed to update portfolio item'], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/portfolio/{id}",
     *     tags={"Portfolio Items"},
     *     summary="Delete a portfolio item",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio item ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Portfolio item deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Portfolio item not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Portfolio item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to delete portfolio item")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $item = PortfolioItem::find($id);

            if (!$item) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Portfolio item not found'
                ], 404);
            }

            $item->delete();

            DB::commit();
            return response()->json([
                'message' => 'Portfolio item deleted successfully',
                'deleted_id' => $id
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to delete portfolio item',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/users/{id}/portfolio",
     *     tags={"Portfolio Items"},
     *     summary="Get all portfolio items for a user",
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="User's portfolio items (can be empty array)")
     * )
     */
    public function getByUser($id)
    {
        try {
            $items = PortfolioItem::where('user_id', $id)->get();
            return response()->json($items, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Unable to fetch user portfolio items'], 500);
        }
    }
}
