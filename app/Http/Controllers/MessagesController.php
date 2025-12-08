<?php

namespace App\Http\Controllers;

use App\Events\MessageCreated;
use Throwable;
use App\Models\User;
use App\Models\Recipient;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class MessagesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($id)
    {
        $user = User::find(1);//Auth::user();
        $conversation = $user->conversations()->findOrFail($id);
        return $conversation->messages()->with('user')->latest()->paginate(20);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'message'=>['required','string'],
            'conversation_id'=>[
                Rule::requiredIf(function() use($request) {
                    return !$request->input('user_id');
                }),
                'int','exists:conversations,id'],
            'user_id'=>[
                Rule::requiredIf(function() use($request) {
                    return !$request->input('conversation_id');
                }),
                'int','exists:users,id']
        ]);
        $user= User::findOrFail(1);
        $conversation_id = $request->integer('conversation_id');
        $user_id = $request->integer('user_id');

        DB::beginTransaction();
        try {
            if($conversation_id){
                $conversation=$user->conversations()->findOrFail($conversation_id);
            }
            // else{
            //     $conversation = Conversation::where('type','=','peer')
            //         ->whereHas('participants',function($builder) use ($user_id,$user){
            //         $builder->join('participants as participans2','participants2.conversation_id','=','participants.conversation_id')
            //         ->where('participants.user_id','=',$user_id)
            //         ->where('participants2.user_id','=',$user->id);
            //     })->first();
            // }
            else {
                $conversation = Conversation::where('type', 'peer')
                    ->whereHas('participants', function ($q) use ($user) {
                        $q->where('user_id', $user->id);
                    })
                    ->whereHas('participants', function ($q) use ($user_id) {
                        $q->where('user_id', $user_id);
                    })
                    ->first();

                if (!$conversation) {
                    $conversation = Conversation::create([
                        'user_id' => $user->id,
                        'type'    => 'peer',
                    ]);

                    $conversation->participants()->attach([
                        $user->id=>['joined_at'=>now()],
                        $user_id=>['joined_at'=>now()]
                    ]);
                }
            }
            $message=$conversation->messages()->create([
            'user_id'=>$user->id,
            'body'=>$request->post('message'),
            ]);

            DB::statement('
                INSERT INTO recipients(user_id,message_id)
                SELECT user_id, ? FROM participants
                WHERE conversation_id = ?
            ',[$message->id,$conversation->id]);
            $conversation->update([
                'last_message_id'=>$message->id
            ]);
            DB::commit();
            broadcast(new \App\Events\MessageCreated($message))->toOthers();
        }catch(Throwable $e){
            DB::rollBack();
            throw $e;
        }
        return $message;

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Recipient::where([
            'user_id'=>Auth::id(),
            'message_id'=>$id
        ])->delete();

        return[
            'message'=>'deleted',
        ];
    }
}
