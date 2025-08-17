<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageDelivered;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
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
     *     @OA\Response(response=200, description="Message sent successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function send(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'content'     => 'required|string',
            ]);

            $message = DB::transaction(function () use ($request) {
                return Message::create([
                    'sender_id'   => auth()->id(),
                    'receiver_id' => $request->receiver_id,
                    'content'     => encrypt($request['content']),
                    'status'      => 'sent',
                ]);
            });

            broadcast(new MessageSent($message))->toOthers();

            return response()->json(['status' => 'success', 'message' => $message->fresh()]);
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
     *     summary="Retrieve conversation with a specific user and mark unread messages as read",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="The other user's ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversation retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="conversation",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="sender_id", type="integer", example=3),
     *                     @OA\Property(property="receiver_id", type="integer", example=5),
     *                     @OA\Property(property="content", type="string", example="Hey, how are you?"),
     *                     @OA\Property(property="status", type="string", example="read"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-08-17T18:30:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-08-17T18:35:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Unable to fetch conversation")
     *         )
     *     )
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


            $unread = $messages->where('receiver_id', auth()->id())
                ->where('status', '!=', 'read')
                ->pluck('id')
                ->toArray();

            if (!empty($unread)) {
                DB::transaction(function () use ($messages, $unread) {
                    foreach ($messages->whereIn('id', $unread) as $msg) {
                        $msg->update([
                            'status'  => 'read',
                            'read_at' => now(),
                        ]);
                    }
                });

                broadcast(new MessageRead($unread, auth()->id()))->toOthers();
            }

            $messages->transform(function ($msg) {
                try {
                    $content = decrypt($msg->content);
                } catch (\Exception $e) {
                    $content = '[Cannot decrypt]';
                }

                return [
                    'id'          => $msg->id,
                    'sender_id'   => $msg->sender_id,
                    'receiver_id' => $msg->receiver_id,
                    'content'     => $content,
                    'status'      => $msg->status,
                    'read_at'     => $msg->read_at,
                    'created_at'  => $msg->created_at,
                ];
            });

            return response()->json(['status' => 'success', 'messages' => $messages]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Patch(
     *     path="/api/messages/{id}/read",
     *     summary="Mark a single message as read",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Message marked as read"),
     *     @OA\Response(response=404, description="Message not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function markAsRead($id)
    {
        try {
            $message = Message::where('id', $id)
                ->where('receiver_id', auth()->id())
                ->firstOrFail();

            DB::transaction(function () use ($message) {
                $message->update([
                    'status'  => 'read',
                    'read_at' => now(),
                ]);
            });
            broadcast(new MessageRead([$message->id], auth()->id()))->toOthers();

            return response()->json(['status' => 'success', 'message' => 'Message marked as read']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/messages/{id}",
     *     summary="Edit a message (sender only)",
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
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Message not found"),
     *     @OA\Response(response=500, description="Server error")
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

            DB::transaction(function () use ($message, $request) {
                $message->update(['content' => encrypt($request['content'])]);
            });
            broadcast(new MessageUpdated($message))->toOthers();

            return response()->json(['status' => 'success', 'message' => $message]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/messages/{id}",
     *     summary="Delete a message (sender only)",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Message deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Message not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy($id)
    {
        try {
            $message = Message::findOrFail($id);

            if ($message->sender_id !== auth()->id()) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
            }

            DB::transaction(function () use ($message) {
                $message->delete();
            });
            broadcast(new MessageDeleted($message->id, $message->sender_id, $message->receiver_id))->toOthers();

            return response()->json(['status' => 'success', 'message' => 'Message deleted']);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/messages/delivered",
     *     summary="Mark messages as delivered",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_ids"},
     *             @OA\Property(property="message_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Messages marked as delivered"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function markAsDelivered(Request $request)
    {
        try {
            $request->validate([
                'message_ids'   => 'required|array',
                'message_ids.*' => 'integer|exists:messages,id'
            ]);

            $messages = Message::whereIn('id', $request->message_ids)
                ->where('receiver_id', auth()->id())
                ->where('status', 'sent')
                ->get();

            DB::transaction(function () use ($messages) {
                foreach ($messages as $msg) {
                    $msg->update(['status' => 'delivered']);
                    broadcast(new MessageDelivered($msg))->toOthers();
                }
            });

            return response()->json(['status' => 'success', 'updated_count' => $messages->count()]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    /**
     * @OA\Delete(
     *     path="/api/messages/conversation/{userId}",
     *     summary="Delete entire conversation with a specific user",
     *     tags={"Messages"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="userId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Conversation deleted successfully"),
     *     @OA\Response(response=404, description="No messages found")
     * )
     */
    public function destroyConversation($userId)
    {
        try {
            // Wrap in transaction for safety
            $deletedCount = DB::transaction(function () use ($userId) {
                return Message::where(function ($q) use ($userId) {
                    $q->where('sender_id', auth()->id())
                        ->where('receiver_id', $userId);
                })->orWhere(function ($q) use ($userId) {
                    $q->where('sender_id', $userId)
                        ->where('receiver_id', auth()->id());
                })->delete();
            });

            if ($deletedCount === 0) {
                return response()->json(['status' => 'error', 'message' => 'No messages found to delete'], 404);
            }

            return response()->json(['status' => 'success', 'deleted_count' => $deletedCount]);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

}
