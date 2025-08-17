<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('chat.{receiverId}', function ($user, $receiverId) {
    // Allow only sender or receiver to listen to the channel
    return (int) $user->id === (int) $receiverId || $user->id === auth()->id();
});
