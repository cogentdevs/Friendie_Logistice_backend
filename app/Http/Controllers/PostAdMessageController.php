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
use Session;
use Mail;

class PostAdMessageController extends Controller
{
    public function createMessageRoom(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();

            $post_ad_room = new PostAdRoom();
            $post_ad_room->post_ads_id = $data['post_ads_id'];
            $post_ad_room->from_user_id = $data['from_user_id'];
            $post_ad_room->to_user_id = $data['to_user_id'];
            $post_ad_room->save();

            $room_id =  $post_ad_room->id;

            $post_ad_room_message = new PostAdRoomMessage();
            $post_ad_room_message->post_ad_room_id = $room_id;
            $post_ad_room_message->from_user_id = $data['from_user_id'];
            $post_ad_room_message->to_user_id = $data['to_user_id'];
            $post_ad_room_message->message = $data['message'];
            $post_ad_room_message->message_on = date('d M Y');
            $post_ad_room_message->save();

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

            Mail::to($to_user->email)->send(new ChatMessageMail($mail_data));

            return redirect('/user/messages/'.$data['post_ads_id'].'/'.$post_title.'/'.$room_id.'='.md5($room_id));
        }

    }

    public function getRoomMessages($post_id, $post_slug, $room_id, $room_slug){

        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        PostAdRoomMessage::where(['post_ad_room_id'=>$room_id, 'to_user_id' => Auth::user()->id])->update
        ([
            'read_by'=>Auth::user()->id
        ]);

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if ($chk_post->created_by == Auth::user()->id) {
            $rooms = DB::table('post_ad_rooms as room')
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
            $rooms = DB::table('post_ad_rooms as room')
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

        $current_room = DB::table('post_ad_rooms as room')
                        ->join('users as f_user', 'room.from_user_id', '=', 'f_user.id')
                        ->join('users as t_user', 'room.to_user_id', '=', 't_user.id')
                        ->select('room.*', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage')
                        ->where(['room.id' => $room_id])
                        ->first();

        $room_messages =  PostAdRoomMessage::where(['post_ad_room_id' => $room_id])->get();

        return view('frontend.user.messages')->with(compact('post_id', 'rooms', 'current_room', 'room_messages'));        

    }

    public function userMessageSend(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();

            $post_ad_room_message = new PostAdRoomMessage();
            $post_ad_room_message->post_ad_room_id = $data['post_ad_room_id'];
            $post_ad_room_message->from_user_id = $data['from_user_id'];
            $post_ad_room_message->to_user_id = $data['to_user_id'];
            $post_ad_room_message->message = $data['message'];
            $post_ad_room_message->message_on = date('d M Y');
            $post_ad_room_message->save();

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
        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if ($chk_post->created_by == Auth::user()->id) {
            $rooms = DB::table('post_ad_rooms as room')
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
            $rooms = DB::table('post_ad_rooms as room')
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
        $room_messages =  PostAdRoomMessage::where(['post_ad_room_id' => $post_ad_room_id])->get();

        return view('frontend.user.partials.chats', compact('room_messages'))->render();
    }

    public function getSearchChatUsers($post_id, $search)
    {
        $chk_post =  DB::table('post_ads')->where(['id' => $post_id])->first();

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
            ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
            ->groupBy('msg.post_ad_room_id');

        // Main query
        if ($chk_post->created_by == Auth::user()->id) {
            $rooms = DB::table('post_ad_rooms as room')
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
            $rooms = DB::table('post_ad_rooms as room')
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
