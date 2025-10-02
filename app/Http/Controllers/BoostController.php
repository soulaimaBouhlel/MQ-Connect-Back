<?php

namespace App\Http\Controllers;

use App\Models\Boost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BoostController extends Controller
{
    /**
     * Display all boosts for the authenticated user.
     */
    public function index()
    {
        try {
            $boosts = Boost::where('user_id', Auth::id())->get();

            return response()->json([
                'success' => true,
                'data' => $boosts
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch boosts.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new boost.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $boost = Boost::create([
                'user_id' => Auth::id(),
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Boost created successfully.',
                'data' => $boost
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create boost.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing boost.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $boost = Boost::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $boost->update([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Boost updated successfully.',
                'data' => $boost
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update boost.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a boost.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $boost = Boost::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $boost->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Boost deleted successfully.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete boost.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
