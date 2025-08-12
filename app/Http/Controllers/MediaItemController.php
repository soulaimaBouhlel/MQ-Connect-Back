<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(
 *     name="Media Items",
 *     description="API Endpoints for managing media files linked to portfolio items"
 * )
 */
class MediaItemController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/media/portfolio/{portfolio_item_id}",
     *     summary="Get all media items for a specific portfolio item",
     *     tags={"Media Items"},
     *     @OA\Parameter(
     *         name="portfolio_item_id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer"),
     *         description="ID of the portfolio item"
     *     ),
     *     @OA\Response(response=200, description="List of media items"),
     *     @OA\Response(response=404, description="Portfolio item not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index($portfolio_item_id)
    {
        try {
            // Ensure portfolio item exists
            $portfolioItem = PortfolioItem::find($portfolio_item_id);
            if (!$portfolioItem) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Portfolio item with ID {$portfolio_item_id} not found"
                ], Response::HTTP_NOT_FOUND);
            }

            $mediaItems = MediaItem::where('portfolio_item_id', $portfolio_item_id)->get();

            // Return empty array if no media found
            return response()->json([
                'status' => 'success',
                'data'   => $mediaItems
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'An unexpected error occurred while retrieving media items',
                'error'   => $e->getMessage(),
                'trace'   => config('app.debug') ? $e->getTrace() : null
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/media",
     *     summary="Create a new media item for a portfolio item",
     *     tags={"Media Items"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"portfolio_item_id","file_path"},
     *             @OA\Property(property="portfolio_item_id", type="integer", example=1, description="ID of the portfolio item"),
     *             @OA\Property(property="file_path", type="string", example="uploads/images/project1.jpg", description="Relative path to the media file")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Media item created successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Portfolio item not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'portfolio_item_id' => 'required|integer|exists:portfolio_items,id',
                'file_path' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Double-check portfolio item exists before creating
            $portfolioItem = PortfolioItem::find($request->portfolio_item_id);
            if (!$portfolioItem) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Portfolio item not found'
                ], Response::HTTP_NOT_FOUND);
            }

            $mediaItem = DB::transaction(function () use ($request) {
                return MediaItem::create([
                    'portfolio_item_id' => $request->portfolio_item_id,
                    'file_path' => $request->file_path,
                ]);
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Media item created successfully',
                'data' => $mediaItem
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(), // TEMPORARY: show actual error
                'trace' => $e->getTraceAsString()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/media/{id}",
     *     summary="Get a media item by ID",
     *     tags={"Media Items"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Media item details"),
     *     @OA\Response(response=404, description="Media item not found")
     * )
     */
    public function show($id)
    {
        try {
            $mediaItem = MediaItem::find($id);

            if (!$mediaItem) {
                return response()->json([], Response::HTTP_OK); // Return empty instead of 404
            }

            return response()->json($mediaItem, Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * @OA\Put(
     *     path="/api/media/{id}",
     *     summary="Update a media item",
     *     tags={"Media Items"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="file_path", type="string", example="uploads/videos/demo.mp4")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Media item updated successfully"),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Media item not found")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $mediaItem = MediaItem::find($id);

            if (!$mediaItem) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Media item with ID {$id} not found"
                ], Response::HTTP_NOT_FOUND);
            }

            $validator = Validator::make($request->all(), [
                'file_path' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Validation error',
                    'errors'  => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            DB::transaction(function () use ($mediaItem, $request) {
                $mediaItem->update($request->only(['file_path']));
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Media item updated successfully',
                'data'    => $mediaItem
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'An unexpected error occurred while updating the media item',
                'error'   => $e->getMessage(), // Exact error message
                'trace'   => config('app.debug') ? $e->getTrace() : null // Optional stack trace
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/media/{id}",
     *     summary="Delete a media item",
     *     tags={"Media Items"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Media item deleted successfully"),
     *     @OA\Response(response=404, description="Media item not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $mediaItem = MediaItem::find($id);

            if (!$mediaItem) {
                return response()->json([], Response::HTTP_OK); // Empty if not found
            }

            DB::transaction(function () use ($mediaItem) {
                $mediaItem->delete();
            });

            return response()->json([
                'message' => 'Media item deleted successfully'
            ], Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Server error',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
