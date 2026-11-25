<?php

namespace App\Services\Api;

use App\Models\User;
use App\Services\EmailActivationService;
use App\Support\Api\PublicData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DealReviews
{
    private const PAIR="(review.from_user_id<>review.to_user_id AND (
        (review.from_user_id=deal.sender_user_id AND review.to_user_id=deal.carrier_user_id AND review.post_ads_id=deal.carrier_listing_id)
        OR (review.from_user_id=deal.carrier_user_id AND review.to_user_id=deal.sender_user_id AND review.post_ads_id=deal.sender_listing_id)))";

    public static function available(): bool
    {
        return Schema::hasColumn('reviews','deal_id') && Schema::hasTable('listing_deals');
    }

    public static function requireReady(): void
    {
        abort_unless(self::available(),503,'Completed-deal reviews are not available yet.');
    }

    public static function rules(): array
    {
        return ['rating'=>'required|integer|between:0,5','review'=>['required','string',function ($key,$value,$fail) {
            if (is_string($value) && (trim($value)==='' || str_contains($value,"\0"))) $fail('Enter a valid nonempty review.');
        }]];
    }

    public static function query()
    {
        $query=DB::table('reviews as review')->leftJoin('post_ads as listing','listing.id','=','review.post_ads_id')
            ->leftJoin('users as reviewer','reviewer.id','=','review.from_user_id')
            ->leftJoin('users as recipient','recipient.id','=','review.to_user_id')
            ->select('review.id','review.post_ads_id','review.from_user_id','review.to_user_id','review.rating','review.review',
                'review.date_time','review.created_at','review.updated_at','listing.title',
                'reviewer.name as reviewer_name','recipient.name as recipient_name');
        if (self::available()) {
            $query->leftJoin('listing_deals as deal',function ($join) {
                $join->on('deal.id','=','review.deal_id')->whereRaw(self::PAIR);
            })->addSelect('review.deal_id','deal.listing_snapshot as deal_snapshot','deal.sender_user_id as deal_sender_user_id');
        } else $query->selectRaw('NULL AS deal_id, NULL AS deal_snapshot');
        return $query;
    }

    public static function verified($query)
    {
        self::requireReady();
        return $query->whereNotNull('review.deal_id')->where('deal.status','completed')->whereBetween('review.rating',[0,5]);
    }

    public static function data(object $row): array
    {
        $snapshot=json_decode($row->deal_snapshot??'null',true);
        $title=$row->title; $reviewer=$row->reviewer_name; $recipient=$row->recipient_name;
        if (is_array($snapshot)) {
            $fromSender=(int)$row->deal_sender_user_id===(int)$row->from_user_id;
            $from=$fromSender?'sender':'carrier'; $to=$fromSender?'carrier':'sender';
            // Once recorded, use historical fields rather than a later edited/re-owned listing.
            $title=$snapshot[$to]['title']??null;
            $reviewer=$snapshot[$from]['display_name']??null;
            $recipient=$snapshot[$to]['display_name']??null;
        }
        return ['id'=>(int)$row->id,'deal_id'=>isset($row->deal_id)?(int)$row->deal_id:null,
            'listing'=>['id'=>(int)$row->post_ads_id,'title'=>PublicData::text($title,255)],
            'reviewer'=>['id'=>(int)$row->from_user_id,'display_name'=>PublicData::text($reviewer,255)],
            'reviewed_customer'=>['id'=>(int)$row->to_user_id,'display_name'=>PublicData::text($recipient,255)],
            'rating'=>(int)$row->rating,'review'=>$row->review,'date_time'=>$row->date_time,
            'created_at'=>$row->created_at,'updated_at'=>$row->updated_at];
    }

    /** Website inference is allowed only when exactly one completed deal matches. */
    public static function submit(int $viewer,array $input,?int $tokenId=null,bool $web=false): int
    {
        self::requireReady();
        return DB::transaction(function () use ($viewer,$input,$tokenId,$web) {
            $reviewer=User::whereKey($viewer)->lockForUpdate()->first();
            abort_unless($reviewer && (int)$reviewer->isActive===1 && (int)$reviewer->isVerified===1
                && app(EmailActivationService::class)->customer($reviewer),403);
            if ($tokenId!==null) abort_unless($reviewer->tokens()->whereKey($tokenId)->exists(),401);
            if ($web && isset($input['from_user_id'])) abort_unless((int)$input['from_user_id']===$viewer,403);
            $deals=DB::table('listing_deals')->where('status','completed')
                ->where(fn($q)=>$q->where('sender_user_id',$viewer)->orWhere('carrier_user_id',$viewer));
            if (isset($input['deal_id'])) $deals->where('id',$input['deal_id']);
            else {
                abort_unless($web && isset($input['post_ads_id']),404);
                $deals->where(fn($q)=>$q
                    ->where(fn($q)=>$q->where('sender_user_id',$viewer)->where('carrier_listing_id',$input['post_ads_id']))
                    ->orWhere(fn($q)=>$q->where('carrier_user_id',$viewer)->where('sender_listing_id',$input['post_ads_id'])));
            }
            $matches=$deals->orderBy('id')->lockForUpdate()->limit(2)->get();
            abort_if($matches->count()>1,409,'Select the completed deal explicitly.');
            $deal=$matches->first(); abort_unless($deal,404);
            $recipient=(int)$deal->sender_user_id===$viewer?(int)$deal->carrier_user_id:(int)$deal->sender_user_id;
            $listing=(int)$deal->sender_user_id===$viewer?(int)$deal->carrier_listing_id:(int)$deal->sender_listing_id;
            abort_if($recipient===$viewer || $recipient<1 || $listing<1 || $listing>2147483647,409,'Invalid completed-deal participants.');
            if ($web && isset($input['to_user_id'])) abort_unless((int)$input['to_user_id']===$recipient,403);
            if ($web && isset($input['post_ads_id'])) abort_unless((int)$input['post_ads_id']===$listing,404);
            abort_if(DB::table('reviews')->where('deal_id',$deal->id)->where('from_user_id',$viewer)->exists(),409,'You have already reviewed this deal.');
            return DB::table('reviews')->insertGetId(['deal_id'=>$deal->id,'post_ads_id'=>$listing,'from_user_id'=>$viewer,
                'to_user_id'=>$recipient,'rating'=>$input['rating'],'review'=>$input['review'],
                'date_time'=>now()->format('Y-m-d'),'created_at'=>now(),'updated_at'=>now()]);
        },3);
    }
}
