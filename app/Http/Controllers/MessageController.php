<?php

namespace App\Http\Controllers;
use App\Events\MessageSent;
use App\Models\Message;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function send(Request $request)
    {
        try {
            $request->validate([
                'receiver_id' => 'required|exists:users,id',
                'content' => 'required|string',
            ]);

            $message = Message::create([
                'sender_id' => auth()->id(),
                'receiver_id' => $request->receiver_id,
                'content' => $request['content'],
            ]);

            broadcast(new MessageSent($message))->toOthers();

            return response()->json([
                'status' => 'success',
                'message' => $message->fresh()->toArray()
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ], 500);
        }
    }


    public function retrieve(Request $request, $userId)
    {
        try {
            $messages = Message::where(function ($query) use ($userId) {
                $query->where('sender_id', auth()->id())
                    ->where('receiver_id', $userId);
            })
                ->orWhere(function ($query) use ($userId) {
                    $query->where('sender_id', $userId)
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

}
