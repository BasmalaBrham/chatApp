<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class ConversationsController extends Controller
{
    public function index(){
        $user=Auth::user();
        return $user->conversations()->paginate();
    }

    public function show(Conversation $conversation){
        return $conversation->load('participants');
    }

    public function addParticipant(Request $request, Conversation $conversation)
    {
        $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'], // ← تصليح الإملاء
        ]);

        $userId = $request->integer('user_id');

        if ($conversation->participants()->where('user_id', $userId)->exists()) {
            return response()->json([
                'message' => 'User already exists in request'
            ], 422);
        }

        $conversation->participants()->attach($userId, ['joined_at' => now()]);
        return response()->json([
            'message' => 'User added successfully',
            'conversation' => $conversation->load('participants.user')
        ], 201);
    }

    public function removeParticipant(Request $request,Conversation $conversation){
        $request->validate([
            'user_id'=>['required','int','exists:users,id']
        ]);
        $conversation->participants()->syncWithoutDetaching($request->integer('user_id'));
    }
}
