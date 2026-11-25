<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Api\ListingDeals;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerDealController
{
    public function contact(Request $request, string $id)
    {
        PublicData::input($request,[]);
        $data=\App\Services\DealContactAccess::locked((int)$id,(int)$request->user()->id,function ($deal,$listings) use ($request) {
            $otherSide=(int)$deal->sender_user_id===(int)$request->user()->id?'carrier':'sender';
            $otherId=(int)$deal->{$otherSide.'_user_id'};
            $listing=$listings->get($deal->{$otherSide.'_listing_id'});
            $pins=[];
            foreach (['from','to'] as $side) {
                $lat=$listing->{$side.'_latitude'}??null; $lng=$listing->{$side.'_longitude'}??null;
                $valid=is_numeric($lat) && is_numeric($lng) && is_finite((float)$lat) && is_finite((float)$lng)
                    && abs((float)$lat)<=90 && abs((float)$lng)<=180;
                $pins[$side.'_latitude']=$valid?(float)$lat:null;
                $pins[$side.'_longitude']=$valid?(float)$lng:null;
            }
            return ['deal_id'=>(int)$deal->id,'counterpart'=>['id'=>$otherId,'phone'=>DB::table('users')->where('id',$otherId)->value('phone')],
                'listing'=>['id'=>(int)$listing->id]+$pins];
        });
        return ApiResponse::success('Deal contact retrieved.',$data)->header('Cache-Control','no-store, private');
    }
    private function input(Request $request, array $rules): array
    {
        $unknown=array_diff(array_keys($request->all()),array_keys($rules));
        if ($unknown) throw \Illuminate\Validation\ValidationException::withMessages(array_fill_keys($unknown,['This field is not allowed.']));
        return validator($request->all(),$rules)->validate();
    }
    private function scope(int $user)
    {
        return DB::table('listing_deals')->where(fn ($q) => $q->where('sender_user_id',$user)->orWhere('carrier_user_id',$user));
    }

    private function owned(Request $request, string $id): object
    {
        $deal = $this->scope((int)$request->user()->id)->where('id',$id)->first();
        abort_unless($deal,404);
        return $deal;
    }

    private function limit(Request $request): void
    {
        foreach (['user:'.$request->user()->id, 'ip:'.hash('sha256',(string)$request->ip())] as $scope) {
            $key = 'customer-deal:'.$scope;
            if (RateLimiter::tooManyAttempts($key,60)) {
                throw new HttpException(429,'',null,['Retry-After'=>(string)RateLimiter::availableIn($key)]);
            }
            RateLimiter::hit($key,60);
        }
    }

    private function listings(array $ids): array
    {
        sort($ids,SORT_NUMERIC);
        $rows = DB::table('post_ads')->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($rows->count() === 2,404);
        return $rows->all();
    }

    private function pair(object $sender, object $carrier): void
    {
        abort_unless($sender->ad_type === 'Send' && $carrier->ad_type === 'Carring'
            && (int)$sender->created_by !== (int)$carrier->created_by,409,'Complementary listings are required.');
        abort_unless(ListingDeals::eligible($sender) && ListingDeals::eligible($carrier),404);
        foreach (['from_country_id','from_city_id','to_country_id','to_city_id'] as $field) {
            abort_unless((int)$sender->$field === (int)$carrier->$field,409,'Listing routes must match.');
        }
    }

    private function canReserve(object $sender, object $carrier, int $kg): void
    {
        $s = ListingDeals::capacity($sender,true); $c = ListingDeals::capacity($carrier,true);
        abort_unless($kg > 0 && $kg === (int)$sender->weight && $s['available'] && $c['available']
            && $s['committed_kg'] === 0 && $s['remaining_kg'] >= $kg && $c['remaining_kg'] >= $kg,
            409,'The listings do not have available capacity for this parcel.');
    }

    /** Resolve only unrecorded labels, once per page, using snapshot IDs rather than live listings. */
    private function routeNames(iterable $deals): array
    {
        $ids=['country'=>[],'city'=>[]];
        foreach ($deals as $deal) {
            $snapshot=json_decode($deal->listing_snapshot,true) ?: [];
            foreach (['sender','carrier'] as $side) {
                $record=$snapshot[$side]??[];
                foreach (['from','to'] as $direction) foreach (['country','city'] as $type) {
                    $field=$direction.'_'.$type;
                    if (!array_key_exists($field.'_name',$record) && isset($record[$field.'_id'])) {
                        $ids[$type][]=(int)$record[$field.'_id'];
                    }
                }
            }
        }
        $names=[];
        foreach (['country'=>'countries','city'=>'cities'] as $type=>$table) {
            $names[$type]=$ids[$type] ? DB::table($table)->whereIn('id',array_unique($ids[$type]))->pluck('name','id')->all() : [];
        }
        return $names;
    }

    private function data(object $deal, int $user, ?array $routeNames = null): array
    {
        $routeNames ??= $this->routeNames([$deal]);
        $status = $deal->status === 'accepted' && !ListingDeals::live($deal) ? 'expired' : $deal->status;
        $listings = DB::table('post_ads')->whereIn('id',[$deal->sender_listing_id,$deal->carrier_listing_id])->get()->keyBy('id');
        $capacity = []; $actions = [];
        foreach (['sender','carrier'] as $side) {
            $listing = $listings->get($deal->{$side.'_listing_id'});
            $capacity[$side] = $listing ? ListingDeals::capacity($listing) : null;
        }
        $initiator = (int)$deal->initiated_by === $user;
        if ($status === 'pending') {
            if ($initiator) $actions[] = 'cancel';
            else {
                $actions[] = 'reject';
                $s = $listings->get($deal->sender_listing_id); $c = $listings->get($deal->carrier_listing_id);
                if ($s && $c && $capacity['sender']['available'] && $capacity['carrier']['available']
                    && (int)$s->weight === (int)$deal->agreed_kg && $capacity['sender']['committed_kg'] === 0
                    && $capacity['carrier']['remaining_kg'] >= (int)$deal->agreed_kg
                    && $s->ad_type === 'Send' && $c->ad_type === 'Carring'
                    && (int)$s->created_by === (int)$deal->sender_user_id && (int)$c->created_by === (int)$deal->carrier_user_id
                    && collect(['from_country_id','from_city_id','to_country_id','to_city_id'])->every(fn ($f)=>(int)$s->$f === (int)$c->$f)) $actions[]='accept';
            }
        }
        if ($status === 'accepted') {
            $actions[]='cancel';
            if ($initiator && $listings->count() === 2 && $capacity['sender'] && $capacity['carrier']) {
                $s=$listings->get($deal->sender_listing_id); $c=$listings->get($deal->carrier_listing_id);
                if (ListingDeals::eligible($s) && ListingDeals::eligible($c)) $actions[]='confirm';
            }
        }
        if ($status === 'confirmed' && $deal->picked_up_at === null) $actions[]='cancel';
        if ($status === 'confirmed' && (int)$deal->carrier_user_id === $user) {
            $actions[]=$deal->picked_up_at === null ? 'pickup' : 'complete';
        }
        $result = [];
        foreach (['id','sender_listing_id','carrier_listing_id','sender_user_id','carrier_user_id','initiated_by','requested_kg','agreed_kg'] as $field) $result[$field]=(int)$deal->$field;
        $result['status']=$status;
        $result['conversation_id']=isset($deal->conversation_id)?(int)$deal->conversation_id:null;
        foreach (['accepted_at','acceptance_expires_at','confirmed_at','rejected_at','cancelled_at','picked_up_at','completed_at','created_at','updated_at'] as $field) $result[$field]=$deal->$field;
        $result['capacity']=$capacity; $result['allowed_actions']=$actions;
        $snapshot=json_decode($deal->listing_snapshot,true) ?: [];
        $result['history']=[];
        foreach (['sender','carrier'] as $side) {
            $record=$snapshot[$side]??[];
            $route=[];
            foreach (['from_country_id','from_city_id','to_country_id','to_city_id'] as $field) {
                $route[$field]=isset($record[$field])?(int)$record[$field]:null;
            }
            foreach (['from','to'] as $direction) foreach (['country','city'] as $type) {
                $field=$direction.'_'.$type;
                $name=array_key_exists($field.'_name',$record) ? $record[$field.'_name']
                    : ($routeNames[$type][$route[$field.'_id']]??null);
                $route[$field.'_name']=is_string($name) && trim($name)!=='' ? PublicData::text($name,255) : null;
            }
            $result['history'][$side]=[
                'user'=>['id'=>(int)$deal->{$side.'_user_id'},'display_name'=>PublicData::text($record['display_name']??null,255)],
                'listing'=>['id'=>(int)$deal->{$side.'_listing_id'},'title'=>PublicData::text($record['title']??null,255),
                    'ad_type'=>in_array($record['ad_type']??null,['Send','Carring'],true)?$record['ad_type']:null,
                    'weight_kg'=>isset($record['weight'])?(int)$record['weight']:null,'route'=>$route],
            ];
        }
        return $result;
    }

    public function index(Request $request)
    {
        ListingDeals::requireTable();
        $input = PublicData::input($request,PublicData::paginationRules(50)+['status'=>'sometimes|in:pending,completed']);
        $query=$this->scope((int)$request->user()->id);
        if (($input['status']??null)==='completed') $query->where('status','completed');
        if (($input['status']??null)==='pending') $query->where(fn ($q)=>$q->whereIn('status',['pending','confirmed'])
            ->orWhere(fn ($q)=>$q->where('status','accepted')->where('acceptance_expires_at','>',now())));
        $page = $query->orderByDesc('id')->paginate($input['per_page']??20,['*'],'page',$input['page']??1);
        $routeNames=$this->routeNames($page->getCollection());
        return ApiResponse::success('Deals retrieved.',PublicData::page($page,fn ($d)=>$this->data($d,(int)$request->user()->id,$routeNames)))->header('Cache-Control','no-store, private');
    }

    public function show(Request $request, string $id)
    {
        ListingDeals::requireTable(); PublicData::input($request,[]);
        return ApiResponse::success('Deal retrieved.',$this->data($this->owned($request,$id),(int)$request->user()->id))->header('Cache-Control','no-store, private');
    }

    public function store(Request $request)
    {
        ListingDeals::requireTable(); $this->limit($request);
        $input = $this->input($request,[
            'sender_listing_id'=>'required|integer|min:1|max:2147483647',
            'carrier_listing_id'=>'required|integer|min:1|max:2147483647|different:sender_listing_id',
            'requested_kg'=>'required|integer|min:1|max:2147483647',
        ]);
        $id = DB::transaction(function () use ($request,$input) {
            $rows=$this->listings([$input['sender_listing_id'],$input['carrier_listing_id']]);
            $s=$rows[$input['sender_listing_id']]; $c=$rows[$input['carrier_listing_id']];
            abort_unless(in_array((int)$request->user()->id,[(int)$s->created_by,(int)$c->created_by],true),404);
            $this->pair($s,$c); ListingDeals::expire(array_keys($rows));
            $this->canReserve($s,$c,(int)$input['requested_kg']);
            $key=$s->id.':'.$c->id;
            abort_if(DB::table('listing_deals')->where('active_pair',$key)->lockForUpdate()->exists(),409,'An active request already exists.');
            $snapshot=[];
            foreach (['sender'=>$s,'carrier'=>$c] as $side=>$listing) {
                foreach (['id','created_by','ad_type','weight','from_country_id','from_city_id','to_country_id','to_city_id'] as $field) $snapshot[$side][$field]=$listing->$field;
                $snapshot[$side]['title']=PublicData::text($listing->title,255);
                $snapshot[$side]['display_name']=PublicData::text(DB::table('users')->where('id',$listing->created_by)->value('name'),255);
            }
            $routeNames=$this->routeNames([(object)['listing_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR)]]);
            foreach (['sender','carrier'] as $side) foreach (['from','to'] as $direction) foreach (['country','city'] as $type) {
                $field=$direction.'_'.$type;
                $name=$routeNames[$type][$snapshot[$side][$field.'_id']]??null;
                $snapshot[$side][$field.'_name']=is_string($name) && trim($name)!=='' ? PublicData::text($name,255) : null;
            }
            $id=DB::table('listing_deals')->insertGetId([
                'sender_listing_id'=>$s->id,'carrier_listing_id'=>$c->id,'sender_user_id'=>$s->created_by,'carrier_user_id'=>$c->created_by,
                'initiated_by'=>$request->user()->id,'requested_kg'=>$input['requested_kg'],'agreed_kg'=>$input['requested_kg'],
                'status'=>'pending','active_pair'=>$key,'listing_snapshot'=>json_encode($snapshot,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now(),
            ]);
            \App\Services\Api\DealNotifications::record(DB::table('listing_deals')->where('id',$id)->first(),'request_submitted',(int)$request->user()->id);
            return $id;
        },3);
        return ApiResponse::success('Request submitted.',$this->data($this->owned($request,(string)$id),(int)$request->user()->id),201)->header('Cache-Control','no-store, private');
    }

    public function transition(Request $request, string $id, string $action)
    {
        ListingDeals::requireTable(); $this->limit($request); $this->input($request,[]);
        $before=$this->owned($request,$id);
        $result=DB::transaction(function () use ($request,$before,$id,$action) {
            // Lock whichever listing rows still exist; historical cancellation/rejection
            // must remain possible even after an older legacy deletion.
            $rows=DB::table('post_ads')->whereIn('id',[$before->sender_listing_id,$before->carrier_listing_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            ListingDeals::expire([$before->sender_listing_id,$before->carrier_listing_id]);
            $d=$this->scope((int)$request->user()->id)->where('id',$id)->lockForUpdate()->first(); abort_unless($d,404);
            $initiator=(int)$d->initiated_by === (int)$request->user()->id;
            abort_if(in_array($action,['pickup','complete'],true) && (int)$d->carrier_user_id !== (int)$request->user()->id,403);
            abort_if(in_array($action,['accept','reject'],true) ? $initiator : ($action==='confirm' && !$initiator),403);
            abort_if($action==='cancel' && $d->status==='pending' && !$initiator,403);
            $target=['accept'=>'accepted','reject'=>'rejected','confirm'=>'confirmed','cancel'=>'cancelled','pickup'=>'confirmed','complete'=>'completed'][$action];
            if ($action==='pickup' && in_array($d->status,['confirmed','completed'],true) && $d->picked_up_at!==null) return true;
            if ($action!=='pickup' && $d->status===$target) return true; // Authorized retries do not extend holds or recommit KG.
            $permitted=match($action) {
                'accept','reject'=>$d->status==='pending',
                'confirm'=>$d->status==='accepted',
                'cancel'=>in_array($d->status,['pending','accepted','confirmed'],true) && $d->picked_up_at===null,
                'pickup'=>$d->status==='confirmed' && $d->picked_up_at===null,
                'complete'=>$d->status==='confirmed' && $d->picked_up_at!==null,
            };
            if (!$permitted) return false; // Commit lazy expiry, then return conflict outside transaction.
            $changes=['status'=>$target,'updated_at'=>now()];
            if (in_array($action,['accept','confirm'],true)) {
                $s=$rows->get($d->sender_listing_id); $c=$rows->get($d->carrier_listing_id);
                abort_unless($s && $c && (int)$s->created_by===(int)$d->sender_user_id && (int)$c->created_by===(int)$d->carrier_user_id,409);
                $this->pair($s,$c);
                if ($action==='accept') {
                    $this->canReserve($s,$c,(int)$d->agreed_kg);
                    $changes+=['accepted_at'=>now(),'acceptance_expires_at'=>now()->addHours(24)];
                } else {
                    $sc=ListingDeals::capacity($s,true); $cc=ListingDeals::capacity($c,true);
                    abort_unless((int)$s->weight===(int)$d->agreed_kg && $sc['committed_kg']===0
                        && $sc['reserved_kg']===(int)$d->agreed_kg && $cc['reserved_kg']===(int)$d->agreed_kg
                        && $cc['committed_kg']+$cc['reserved_kg'] <= (int)$c->weight,409);
                    $changes['confirmed_at']=now();
                }
            }
            if ($action==='reject') $changes+=['rejected_at'=>now(),'active_pair'=>null];
            if ($action==='cancel') $changes+=['cancelled_at'=>now(),'cancelled_by'=>$request->user()->id,'active_pair'=>null];
            if ($action==='pickup') $changes['picked_up_at']=now();
            if ($action==='complete') $changes+=['completed_at'=>now(),'active_pair'=>null];
            DB::table('listing_deals')->where('id',$id)->update($changes);
            $event=['accept'=>'request_accepted','reject'=>'request_rejected','confirm'=>'deal_confirmed',
                'cancel'=>'deal_cancelled','pickup'=>'parcel_picked_up','complete'=>'delivery_completed'][$action];
            \App\Services\Api\DealNotifications::record($d,$event,(int)$request->user()->id);
            return true;
        },3);
        if (!$result) return ApiResponse::error('The deal cannot perform this action in its current state.',409);
        return ApiResponse::success('Deal updated.',$this->data($this->owned($request,$id),(int)$request->user()->id))->header('Cache-Control','no-store, private');
    }
}
