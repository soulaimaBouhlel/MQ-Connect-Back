<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;

class FollowController extends Controller
{
    /**
     * Toggle follow/unfollow (like Instagram).
     */
    public function toggleFollow(Request $request, $id): JsonResponse
    {
        $authUser = Auth::user();

        if ($authUser->id == $id) {
            return response()->json(['message' => 'You cannot follow yourself'], 400);
        }

        DB::beginTransaction();
        try {
            $follow = Follow::where('follower_id', $authUser->id)
                ->where('following_id', $id)
                ->first();

            if ($follow) {
                $follow->delete();
                DB::commit();
                return response()->json(['message' => 'Unfollowed successfully']);
            } else {
                Follow::create([
                    'follower_id' => $authUser->id,
                    'following_id' => $id,
                ]);
                DB::commit();
                return response()->json(['message' => 'Followed successfully']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Something went wrong', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get list of followers (people who follow the auth user).
     */
    public function followers(): JsonResponse
    {
        try {
            $followers = Auth::user()->followers()->get();
            return response()->json($followers);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch followers', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Get list of followings (people the auth user follows).
     */
    public function followings(): JsonResponse
    {
        try {
            $followings = Auth::user()->followings()->get();
            return response()->json($followings);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to fetch followings', 'details' => $e->getMessage()], 500);
        }
    }
}
