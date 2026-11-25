<?php

namespace App\Services;

use App\Models\User;
use App\Services\Api\ListingDeals;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The same lifecycle gate for API and browser chat; never sends mail itself. */
final class DealContactAccess
{
    public static function ready(): bool
    {
        return Schema::hasColumns('listing_deals',['conversation_id']);
    }

    public static function requireReady(): void
    {
        abort_unless(self::ready(),503,'Deal contact infrastructure is unavailable.');
    }

    public static function customer(int $id): bool
    {
        $user=User::find($id);
        return $user && (int)$user->isActive===1 && (int)$user->isVerified===1
            && app(EmailActivationService::class)->customer($user);
    }

    public static function participant(object $deal, int $viewer): bool
    {
        return in_array($viewer,[(int)$deal->sender_user_id,(int)$deal->carrier_user_id],true);
    }

    public static function owned(int $id, int $viewer): object
    {
        self::requireReady();
        $d=DB::table('listing_deals')->where('id',$id)->where(fn ($q)=>$q
            ->where('sender_user_id',$viewer)->orWhere('carrier_user_id',$viewer))->first();
        abort_unless($d,404);
        return $d;
    }

    public static function locked(int $id, int $viewer, callable $operation): mixed
    {
        $before=self::owned($id,$viewer);
        return DB::transaction(function () use ($before,$id,$viewer,$operation) {
            // Same lock order as accept/confirm/cancel and listing safeguards.
            $listings=DB::table('post_ads')->whereIn('id',[$before->sender_listing_id,$before->carrier_listing_id])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $deal=DB::table('listing_deals')->where('id',$id)->lockForUpdate()->first();
            abort_unless($deal && self::participant($deal,$viewer),404);
            self::assertWritable($deal,$listings);
            return $operation($deal,$listings);
        },3);
    }

    private static function assertWritable(object $d, $listings): void
    {
        abort_unless($d->status==='confirmed' || ListingDeals::live($d),403);
        abort_unless(self::customer((int)$d->sender_user_id) && self::customer((int)$d->carrier_user_id),403);
        $s=$listings->get($d->sender_listing_id); $c=$listings->get($d->carrier_listing_id);
        abort_unless($s && $c && (int)$s->created_by===(int)$d->sender_user_id && (int)$c->created_by===(int)$d->carrier_user_id
            && $s->ad_type==='Send' && $c->ad_type==='Carring' && (int)$s->created_by!==(int)$c->created_by,403);
        foreach (['from_country_id','from_city_id','to_country_id','to_city_id'] as $field) {
            abort_unless($s->$field!==null && $c->$field!==null && (int)$s->$field===(int)$c->$field,403);
        }
        // Publication/capacity were checked at acceptance. A confirmed journey may
        // pass departure/expiry; it must not lose its valid operational contact.
    }

    public static function scopeRooms(Builder $q, int $viewer, string $alias='room'): Builder
    {
        $q->where(fn ($q)=>$q->where($alias.'.from_user_id',$viewer)->orWhere($alias.'.to_user_id',$viewer))
            ->whereColumn($alias.'.from_user_id','<>',$alias.'.to_user_id');
        return $q->where(function ($q) use ($alias) {
            $legacy=function ($q) use ($alias) {
                $q->whereExists(fn ($p)=>$p->selectRaw('1')->from('post_ads as privacy_listing')
                    ->whereColumn('privacy_listing.id',$alias.'.post_ads_id')->whereColumn('privacy_listing.created_by',$alias.'.to_user_id'));
                if (self::ready()) $q->whereNotExists(fn ($d)=>$d->selectRaw('1')->from('listing_deals as privacy_bound')
                    ->whereColumn('privacy_bound.conversation_id',$alias.'.id'));
            };
            $q->where($legacy);
            if (self::ready()) $q->orWhereExists(fn ($d)=>$d->selectRaw('1')->from('listing_deals as privacy_deal')
                ->whereColumn('privacy_deal.conversation_id',$alias.'.id')
                ->whereColumn('privacy_deal.carrier_listing_id',$alias.'.post_ads_id')
                ->whereColumn('privacy_deal.sender_user_id',$alias.'.from_user_id')
                ->whereColumn('privacy_deal.carrier_user_id',$alias.'.to_user_id'));
        });
    }

    public static function room(int $id, int $viewer): object
    {
        $room=self::scopeRooms(DB::table('post_ad_rooms as room'),$viewer)->where('room.id',$id)->select('room.*')->first();
        abort_unless($room,404);
        return $room;
    }

    public static function messages(object $room): Builder
    {
        return DB::table('post_ad_room_messages')->where('post_ad_room_id',$room->id)->where(fn ($q)=>$q
            ->where(fn ($q)=>$q->where('from_user_id',$room->from_user_id)->where('to_user_id',$room->to_user_id))
            ->orWhere(fn ($q)=>$q->where('from_user_id',$room->to_user_id)->where('to_user_id',$room->from_user_id)));
    }

    public static function messageRules(): array
    {
        return ['required','string','max:10000',function ($key,$value,$fail) {
            if (is_string($value) && (trim($value)==='' || strlen($value)>65535 || str_contains($value,"\0"))) $fail('Enter a valid nonempty text message.');
        }];
    }

    public static function webMessageRules(): array
    {
        // Preserve the existing website's TEXT capacity; do not impose the mobile
        // API's separate 10,000-character policy on its existing forms.
        return ['required','string',function ($key,$value,$fail) {
            if (is_string($value) && strlen($value)>65535) $fail('The message exceeds the existing storage capacity.');
        }];
    }

    private static function insert(object $room, int $viewer, string $message): object
    {
        $id=DB::table('post_ad_room_messages')->insertGetId(['post_ad_room_id'=>$room->id,'from_user_id'=>$viewer,
            'to_user_id'=>$viewer===(int)$room->from_user_id?$room->to_user_id:$room->from_user_id,
            'message'=>$message,'message_on'=>now()->format('d M Y'),'read_by'=>null,'created_at'=>now(),'updated_at'=>now()]);
        return DB::table('post_ad_room_messages')->where('id',$id)->first();
    }

    public static function open(int $dealId, int $viewer, string $message): array
    {
        return self::locked($dealId,$viewer,function ($deal) use ($viewer,$message) {
            $created=$deal->conversation_id===null;
            if ($created) {
                $roomId=DB::table('post_ad_rooms')->insertGetId(['post_ads_id'=>$deal->carrier_listing_id,
                    'from_user_id'=>$deal->sender_user_id,'to_user_id'=>$deal->carrier_user_id,'created_at'=>now(),'updated_at'=>now()]);
                // Existing message room references are signed INT; do not truncate an oversized ID.
                abort_unless($roomId<=2147483647,503);
                DB::table('listing_deals')->where('id',$deal->id)->update(['conversation_id'=>$roomId,'updated_at'=>now()]);
            } else $roomId=(int)$deal->conversation_id;
            DB::table('post_ad_rooms')->where('id',$roomId)->lockForUpdate()->first();
            $room=self::room($roomId,$viewer);
            if ($created) self::insert($room,$viewer,$message);
            return ['room'=>$room,'created'=>$created];
        });
    }

    public static function send(int $roomId, int $viewer, string $message): object
    {
        self::requireReady(); self::room($roomId,$viewer);
        $deal=DB::table('listing_deals')->where('conversation_id',$roomId)->first();
        abort_unless($deal && self::participant($deal,$viewer),403);
        return self::locked((int)$deal->id,$viewer,function ($deal) use ($roomId,$viewer,$message) {
            abort_unless((int)$deal->conversation_id===$roomId,404);
            DB::table('post_ad_rooms')->where('id',$roomId)->lockForUpdate()->first();
            return self::insert(self::room($roomId,$viewer),$viewer,$message);
        });
    }

    public static function webListing(int $listing, int $viewer): void
    {
        abort_unless(self::customer($viewer),403);
        abort_unless(DB::table('post_ads')->where('id',$listing)->where('created_by',$viewer)->exists()
            || self::scopeRooms(DB::table('post_ad_rooms as room'),$viewer)->where('room.post_ads_id',$listing)->exists(),404);
    }

    public static function webDeal(int $listing, int $viewer, ?int $explicit=null): object
    {
        self::requireReady(); abort_unless(self::customer($viewer),403);
        $q=DB::table('listing_deals')->where(fn ($q)=>$q->where('sender_user_id',$viewer)->orWhere('carrier_user_id',$viewer))
            ->where(fn ($q)=>$q->where('sender_listing_id',$listing)->orWhere('carrier_listing_id',$listing))
            ->where(fn ($q)=>$q->where('status','confirmed')->orWhere(fn ($q)=>$q->where('status','accepted')->where('acceptance_expires_at','>',now())));
        if ($explicit!==null) $q->where('id',$explicit);
        $rows=$q->limit(2)->get();
        abort_unless($rows->count()>0,403); abort_unless($rows->count()===1,409,'Choose an explicit deal.');
        return $rows->first();
    }
}
