<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\PostAd;
use App\Models\PostAdRoom;
use App\Models\PostAdRoomMessage;
use App\Mail\ChatMessageMail;
use App\Services\DealContactAccess;
use Session;
use Mail;

class PostAdMessageController extends Controller
{
    public function createMessageRoom(Request $request){

        if($request->isMethod('post')){

            $input=$request->validate(['post_ads_id'=>'required|integer|min:1','deal_id'=>'sometimes|nullable|integer|min:1',
                'from_user_id'=>'sometimes|integer','to_user_id'=>'sometimes|integer','message'=>DealContactAccess::webMessageRules()]);
            $viewer=(int)Auth::id();
            $deal=DealContactAccess::webDeal((int)$input['post_ads_id'],$viewer,isset($input['deal_id'])?(int)$input['deal_id']:null);
            $recipient=(int)$deal->sender_user_id===$viewer?(int)$deal->carrier_user_id:(int)$deal->sender_user_id;
            abort_if(isset($input['from_user_id']) && (int)$input['from_user_id']!==$viewer,403);
            abort_if(isset($input['to_user_id']) && (int)$input['to_user_id']!==$recipient,403);
            $opened=DealContactAccess::open((int)$deal->id,$viewer,$input['message']);
            $room_id=$opened['room']->id;
            $data=['post_ads_id'=>$opened['room']->post_ads_id,'from_user_id'=>$viewer,'to_user_id'=>$recipient,'message'=>$input['message']];
            $chk_post =  DB::table('post_ads')->where(['id' => $data['post_ads_id']])->first();
            $post_title = str_replace(' ', '-', strtolower($chk_post->title));

            $from_user =  DB::table('users')->where(['id' => $data['from_user_id']])->first();
            $to_user =  DB::table('users')->where(['id' => $data['to_user_id']])->first();

            $mail_data = array(
                'ad_title'      =>  $chk_post->title,
                'from_name'      =>  $from_user->name,
                'to_name'      =>  $to_user->name,
                'message'       => $data['message'],
            );

            if ($opened['created']) Mail::to($to_user->email)->send(new ChatMessageMail($mail_data));

            return redirect('/user/messages/'.$data['post_ads_id'].'/'.$post_title.'/'.$room_id.'='.md5($room_id));
        }

    }

    public function getRoomMessages($post_id, $post_slug, $room_id, $room_slug){
        abort_unless(DealContactAccess::customer((int)Auth::id()),403);
        $privacyRoom=DealContactAccess::room((int)$room_id,(int)Auth::id());
        abort_unless((int)$privacyRoom->post_ads_id===(int)$post_id,404);

        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        DealContactAccess::messages($privacyRoom)->where('to_user_id',Auth::id())->whereNull('read_by')->update
        ([
            'read_by'=>Auth::user()->id
        ]);

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if (($chk_post->created_by ?? $privacyRoom->to_user_id) == Auth::user()->id) {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where('room.post_ads_id', $post_id)
                    ->where('room.to_user_id', Auth::user()->id)
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();
        } else {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where('room.post_ads_id', $post_id)
                    ->where('room.from_user_id', Auth::user()->id)
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();
        }

        $post_id = $post_id;

        $current_room = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                        ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                        ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                        ->select('room.*', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage')
                        ->where(['room.id' => $room_id])
                        ->first();

        $room_messages =  DealContactAccess::messages($privacyRoom)->get();

        return view('frontend.user.messages')->with(compact('post_id', 'rooms', 'current_room', 'room_messages'));        

    }

    public function userMessageSend(Request $request){

        if($request->isMethod('post')){

            $input=$request->validate(['post_ad_room_id'=>'required|integer|min:1','from_user_id'=>'sometimes|integer',
                'to_user_id'=>'sometimes|integer','message'=>DealContactAccess::webMessageRules()]);
            $viewer=(int)Auth::id();
            $privacyRoom=DealContactAccess::room((int)$input['post_ad_room_id'],$viewer);
            $recipient=$viewer===(int)$privacyRoom->from_user_id?(int)$privacyRoom->to_user_id:(int)$privacyRoom->from_user_id;
            abort_if(isset($input['from_user_id']) && (int)$input['from_user_id']!==$viewer,403);
            abort_if(isset($input['to_user_id']) && (int)$input['to_user_id']!==$recipient,403);
            DealContactAccess::send((int)$privacyRoom->id,$viewer,$input['message']);
            $data=['post_ad_room_id'=>$privacyRoom->id,'from_user_id'=>$viewer,'to_user_id'=>$recipient,'message'=>$input['message']];
            $room =  DB::table('post_ad_rooms')->where(['id' => $data['post_ad_room_id']])->first();
            $chk_post =  DB::table('post_ads')->where(['id' => $room->post_ads_id])->first();
            $from_user =  DB::table('users')->where(['id' => $data['from_user_id']])->first();
            $to_user =  DB::table('users')->where(['id' => $data['to_user_id']])->first();

            $mail_data = array(
                'ad_title'      =>  $chk_post->title,
                'from_name'      =>  $from_user->name,
                'to_name'      =>  $to_user->name,
                'message'       => $data['message'],
            );

            Mail::to($to_user->email)->send(new ChatMessageMail($mail_data));

            return 'send';
        }

    }

    public function getUserChatList($post_id)
    {
        DealContactAccess::webListing((int)$post_id,(int)Auth::id());
        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if (($chk_post->created_by ?? 0) == Auth::user()->id) {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where('room.post_ads_id', $post_id)
                    ->where('room.to_user_id', Auth::user()->id)
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();
        } else {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where('room.post_ads_id', $post_id)
                    ->where('room.from_user_id', Auth::user()->id)
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();
        }

        return view('frontend.user.partials.chat-user-list', compact('rooms'))->render();
    }

    public function getUserChats($post_ad_room_id)
    {
        abort_unless(DealContactAccess::customer((int)Auth::id()),403);
        $privacyRoom=DealContactAccess::room((int)$post_ad_room_id,(int)Auth::id());
        $room_messages =  DealContactAccess::messages($privacyRoom)->get();

        return view('frontend.user.partials.chats', compact('room_messages'))->render();
    }

    public function getSearchChatUsers($post_id, $search)
    {
        DealContactAccess::webListing((int)$post_id,(int)Auth::id());
        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if (($chk_post->created_by ?? 0) == Auth::user()->id) {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where(['room.post_ads_id' => $post_id])
                    ->where(['room.to_user_id' => Auth::user()->id])
                    ->when($search, function ($query, $search) {
                        return $query->where('f_user.name', 'LIKE', '%' . $search . '%');
                    })
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();

        } else {
            $rooms = DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                    ->joinSub($subQuery, 'recent_msg', function ($join) {
                        $join->on('room.id', '=', 'recent_msg.post_ad_room_id');
                    })
                    ->join('post_ad_room_messages as msg', function ($join) {
                        $join->on('msg.post_ad_room_id', '=', 'recent_msg.post_ad_room_id')
                             ->on('msg.created_at', '=', 'recent_msg.last_message_time');
                    })
                    ->join('post_ads as ads', 'room.post_ads_id', '=', 'ads.id')
                    ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                    ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                    ->select('room.*', 'msg.message', 'msg.from_user_id as msg_from_user', 'msg.message_on', 'ads.title as ad_title', 'ads.created_by', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count')
                    )
                    ->where(['room.post_ads_id' => $post_id])
                    ->where(['room.from_user_id' => Auth::user()->id])
                    ->when($search, function ($query, $search) {
                        return $query->where('t_user.name', 'LIKE', '%' . $search . '%');
                    })
                    ->orderBy('recent_msg.last_message_time', 'desc')
                    ->get();
        }

        return view('frontend.user.partials.chat-user-list', compact('rooms'))->render();
    }
}
