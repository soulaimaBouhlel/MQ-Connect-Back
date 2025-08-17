<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/messages/send",
     *     summary="Send a new message",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"receiver_id","content"},
     *             @OA\Property(property="receiver_id", type="integer", example=2),
     *             @OA\Property(property="content", type="string", example="Hello, how are you?")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="object")
     *         )
     *     ),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function send(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'content' => 'required|string',
            ]);

            $message = DB::transaction(function () use ($request) {
                return Message::create([
                    'sender_id'   => auth()->id(),
                    'receiver_id' => $request->receiver_id,
                    'content'     => $request['content'],
                ]);
            });

            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => $message->fresh()
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/messages/{userId}",
     *     summary="Retrieve conversation with a specific user",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="The other user's ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Conversation retrieved successfully"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function retrieve(Request $request, $userId)
    {
        try {
            $messages = Message::where(function ($q) use ($userId) {
                $q->where('sender_id', auth()->id())
                    ->where('receiver_id', $userId);
            })
                ->orWhere(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', auth()->id());
                })
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'status'   => 'success',
                'messages' => $messages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/messages/{id}/read",
     *     summary="Mark a message as read",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Message marked as read"),
     *     @OA\Response(response=404, description="Message not found")
     * )
     */
    public function markAsRead($id)
    {
        try {
            $message = Message::findOrFail($id);
            $message->update(['is_read' => true]);

            return response()->json(['status' => 'success', 'message' => 'Message marked as read']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/messages/{id}",
     *     summary="Edit a message",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="content", type="string", example="Updated message text")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Message updated successfully"),
     *     @OA\Response(response=404, description="Message not found")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $request->validate(['content' => 'required|string']);
            $message = Message::findOrFail($id);

            if ($message->sender_id !== auth()->id()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $message->update(['content' => $request['content']]);

            return response()->json(['status' => 'success', 'message' => $message]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/messages/{id}",
     *     summary="Delete a message",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Message deleted successfully"),
     *     @OA\Response(response=404, description="Message not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $message = Message::findOrFail($id);

            if ($message->sender_id !== auth()->id()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            $message->delete();

            return response()->json(['status' => 'success', 'message' => 'Message deleted']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
