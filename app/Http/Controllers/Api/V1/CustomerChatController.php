<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Queries\Api\PublicListingQuery;
use App\Services\EmailActivationService;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** API-only two-party chat. No web controllers, Blade, mail or acceptance workflow. */
final class CustomerChatController
{
    private function input(Request $request, array $rules): array
    {
        $unknown = array_diff(array_keys($request->all()), array_keys($rules));
        if ($unknown) throw ValidationException::withMessages(array_fill_keys($unknown, ['This field is not allowed.']));
        return $request->validate($rules);
    }

    private function limit(Request $request): void
    {
        foreach (['account:'.$request->user()->id, 'ip:'.hash('sha256', (string)$request->ip())] as $scope) {
            $key = 'customer-chat:'.$scope;
            if (RateLimiter::tooManyAttempts($key, 60)) throw new HttpException(429, '', null,
                ['Retry-After'=>(string)max(1, RateLimiter::availableIn($key))]);
            RateLimiter::hit($key, 60);
        }
    }

    private function rooms(int $viewer): Builder
    {
        return DB::table('post_ad_rooms as room')
            ->join('post_ads as listing', 'listing.id', '=', 'room.post_ads_id')
            ->join('users as initiator', 'initiator.id', '=', 'room.from_user_id')
            ->join('users as owner', 'owner.id', '=', 'room.to_user_id')
            ->whereColumn('room.to_user_id', 'listing.created_by')
            ->whereColumn('room.from_user_id', '<>', 'room.to_user_id')
            ->where(fn ($q) => $q->where('room.from_user_id', $viewer)->orWhere('room.to_user_id', $viewer))
            ->select('room.id','room.post_ads_id','room.from_user_id','room.to_user_id','room.created_at',
                'listing.title','initiator.name as initiator_name','owner.name as owner_name');
    }

    private function room(Request $request, string $id, bool $lock = false): object
    {
        abort_unless(filter_var($id, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) !== false, 404);
        $query = $this->rooms((int)$request->user()->id)->where('room.id', $id);
        $row = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($row, 404);
        return $row;
    }

    private function lockCustomer(Request $request): void
    {
        $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
        abort_unless((int)$user->isActive === 1 && (int)$user->isVerified === 1
            && app(EmailActivationService::class)->customer($user), 403);
        abort_unless($user->tokens()->whereKey($request->user()->currentAccessToken()->getKey())->exists(), 401);
    }

    private function messagesFor(object $room): Builder
    {
        return DB::table('post_ad_room_messages as message')->where('message.post_ad_room_id', $room->id)
            ->where(function ($q) use ($room) {
                $q->where(fn ($q) => $q->where('message.from_user_id',$room->from_user_id)->where('message.to_user_id',$room->to_user_id))
                    ->orWhere(fn ($q) => $q->where('message.from_user_id',$room->to_user_id)->where('message.to_user_id',$room->from_user_id));
            });
    }

    private function messageData(object $message, object $room): array
    {
        return ['id'=>(int)$message->id,'conversation_id'=>(int)$room->id,
            'sender'=>['id'=>(int)$message->from_user_id,'display_name'=>PublicData::text(
                (int)$message->from_user_id === (int)$room->from_user_id ? $room->initiator_name : $room->owner_name,255)],
            'message'=>$message->message,'created_at'=>$message->created_at,'message_on'=>$message->message_on,
            'is_read'=>$message->read_by !== null && (string)$message->read_by === (string)$message->to_user_id];
    }

    private function metadata(object $room, int $viewer): array
    {
        $otherIsOwner = (int)$room->from_user_id === $viewer;
        return ['id'=>(int)$room->id,'listing'=>['id'=>(int)$room->post_ads_id,'title'=>PublicData::text($room->title,255)],
            'other_participant'=>['id'=>(int)($otherIsOwner ? $room->to_user_id : $room->from_user_id),
                'display_name'=>PublicData::text($otherIsOwner ? $room->owner_name : $room->initiator_name,255)],
            'created_at'=>$room->created_at];
    }

    public function index(Request $request)
    {
        $input = PublicData::input($request, PublicData::paginationRules(50));
        $viewer = (int)$request->user()->id;
        $page = $this->rooms($viewer)->selectSub(function ($q) {
            $q->from('post_ad_room_messages as m')->selectRaw('MAX(m.id)')->whereColumn('m.post_ad_room_id','room.id')
                ->where(fn ($q) => $q->where(fn ($q) => $q->whereColumn('m.from_user_id','room.from_user_id')->whereColumn('m.to_user_id','room.to_user_id'))
                    ->orWhere(fn ($q) => $q->whereColumn('m.from_user_id','room.to_user_id')->whereColumn('m.to_user_id','room.from_user_id')));
        }, 'last_message_id')->orderByDesc('last_message_id')->orderByDesc('room.id')
            ->paginate($input['per_page'] ?? 20,['*'],'page',$input['page'] ?? 1);
        // Batched last-message/unread lookup; never one query per conversation.
        $rows = $page->getCollection();
        $last = DB::table('post_ad_room_messages')->whereIn('id',$rows->pluck('last_message_id')->filter())->get()->keyBy('id');
        $unread = DB::table('post_ad_room_messages as m')->join('post_ad_rooms as r','r.id','=','m.post_ad_room_id')
            ->whereIn('r.id',$rows->pluck('id'))->where('m.to_user_id',$viewer)->whereNull('m.read_by')
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereColumn('m.from_user_id','r.from_user_id')->whereColumn('m.to_user_id','r.to_user_id'))
                ->orWhere(fn ($q) => $q->whereColumn('m.from_user_id','r.to_user_id')->whereColumn('m.to_user_id','r.from_user_id')))
            ->groupBy('m.post_ad_room_id')->selectRaw('m.post_ad_room_id, COUNT(*) AS unread')->pluck('unread','post_ad_room_id');
        return ApiResponse::success('Conversations retrieved successfully.',PublicData::page($page,function ($room) use ($viewer,$last,$unread) {
            $message = $last->get($room->last_message_id);
            return $this->metadata($room,$viewer) + ['last_message'=>$message ? mb_substr($message->message,0,200) : null,
                'last_message_at'=>$message?->created_at,'unread_count'=>(int)($unread[$room->id] ?? 0)];
        }))->header('Cache-Control','no-store, private');
    }

    public function show(Request $request, string $id)
    {
        PublicData::input($request,[]);
        return ApiResponse::success('Conversation retrieved successfully.',$this->metadata($this->room($request,$id),(int)$request->user()->id))
            ->header('Cache-Control','no-store, private');
    }

    public function messages(Request $request, string $id)
    {
        $room = $this->room($request,$id);
        $input = PublicData::input($request,PublicData::paginationRules(50));
        $page = $this->messagesFor($room)->select('message.*')->orderBy('message.id')
            ->paginate($input['per_page'] ?? 20,['*'],'page',$input['page'] ?? 1);
        return ApiResponse::success('Messages retrieved successfully.',PublicData::page($page,fn ($m) => $this->messageData($m,$room)))
            ->header('Cache-Control','no-store, private');
    }

    private function messageRules(): array
    {
        // TEXT storage is 65,535 bytes. Bound both characters and encoded bytes; web has no explicit maximum.
        return ['required','string','max:10000',function ($key,$value,$fail) {
            if (is_string($value) && (trim($value) === '' || strlen($value) > 65535 || str_contains($value,"\0"))) $fail('Enter a valid nonempty text message.');
        }];
    }

    private function insertMessage(object $room, int $sender, string $message): object
    {
        $id = DB::table('post_ad_room_messages')->insertGetId(['post_ad_room_id'=>$room->id,'from_user_id'=>$sender,
            'to_user_id'=>$sender === (int)$room->from_user_id ? $room->to_user_id : $room->from_user_id,
            'message'=>$message,'message_on'=>now()->format('d M Y'),'read_by'=>null,'created_at'=>now(),'updated_at'=>now()]);
        return DB::table('post_ad_room_messages')->where('id',$id)->first();
    }

    public function store(Request $request)
    {
        $this->limit($request);
        $input = $this->input($request,['post_ads_id'=>'required|integer|min:1|max:2147483647','message'=>$this->messageRules()]);
        $created = false;
        $data = DB::transaction(function () use ($request,$input,&$created) {
            $created = false; // Reset attempt-local state if Laravel retries a deadlock.
            $this->lockCustomer($request);
            $listing = DB::table('post_ads')->where('id',$input['post_ads_id'])->lockForUpdate()->first();
            abort_unless($listing,404);
            abort_if((int)$listing->created_by === (int)$request->user()->id,422);
            $owner = User::find($listing->created_by);
            abort_unless($owner && (int)$owner->isActive === 1 && (int)$owner->isVerified === 1
                && app(EmailActivationService::class)->customer($owner),404);
            $roomId = DB::table('post_ad_rooms')->where('post_ads_id',$listing->id)
                ->where('from_user_id',$request->user()->id)->where('to_user_id',$owner->id)->orderBy('id')->value('id');
            if ($roomId === null) {
                // Website Message Now requires a future departure; do not target private listings by guessed ID.
                abort_unless(PublicListingQuery::make()->where('ads.id',$listing->id)
                    ->where('ads.departure_on','>',now()->toDateString())->exists(),404);
                $roomId = DB::table('post_ad_rooms')->insertGetId(['post_ads_id'=>$listing->id,'from_user_id'=>$request->user()->id,
                    'to_user_id'=>$owner->id,'created_at'=>now(),'updated_at'=>now()]);
                $created = true;
            }
            $room = $this->room($request,(string)$roomId,true);
            // Existing rooms are returned without duplicating the first message on retries.
            if ($created) $this->insertMessage($room,(int)$request->user()->id,$input['message']);
            return $this->metadata($room,(int)$request->user()->id);
        }, 3);
        return ApiResponse::success($created ? 'Conversation created successfully.' : 'Conversation already exists.',$data,$created ? 201 : 200);
    }

    public function send(Request $request, string $id)
    {
        $this->limit($request);$this->room($request,$id);
        $input = $this->input($request,['message'=>$this->messageRules()]);
        $data = DB::transaction(function () use ($request,$id,$input) {
            $this->lockCustomer($request);$room = $this->room($request,$id,true);
            return $this->messageData($this->insertMessage($room,(int)$request->user()->id,$input['message']),$room);
        }, 3);
        return ApiResponse::success('Message sent successfully.',$data,201);
    }

    public function read(Request $request, string $id)
    {
        $this->limit($request);$this->room($request,$id);$this->input($request,[]);
        $data = DB::transaction(function () use ($request,$id) {
            $this->lockCustomer($request);$room = $this->room($request,$id,true);
            $updated = $this->messagesFor($room)->where('message.to_user_id',$request->user()->id)->whereNull('message.read_by')
                ->update(['read_by'=>(string)$request->user()->id,'updated_at'=>now()]);
            $remaining = $this->messagesFor($room)->where('message.to_user_id',$request->user()->id)->whereNull('message.read_by')->count();
            return ['conversation_id'=>(int)$room->id,'marked_read'=>$updated,'unread_count'=>$remaining];
        }, 3);
        return ApiResponse::success('Messages marked as read.',$data);
    }
}
