<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    public function index($id= null){
        $user=Auth::user();
        $friends= User::where('id','!=',Auth::id())->orderBy('name')->paginate(5);
        $chats=$user->conversations()->with(['lastMessage',
        'participants'=>function($builder) use($user){
            $builder->where('id','<>',$user->id);
        }])->get();

        $messages=[];
        if($id){
            $chat=$chats->where('id',$id)->first();
            $messages=$chat->messages()->with('user')->paginate(5);

        }
        return view('chat',[
            'friends'=>$friends,
            'chats'=>$chats,
            'messages'=>$messages
        ]);
    }
}
