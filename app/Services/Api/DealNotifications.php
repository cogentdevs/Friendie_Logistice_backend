<?php

namespace App\Services\Api;

use App\Support\Api\PublicData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DealNotifications
{
    public static function requireTable(): void
    {
        abort_unless(Schema::hasTable('deal_notifications'),503,'Deal notifications are not available yet.');
    }

    /** Called only inside the lifecycle transaction, under the deal lock for transitions. */
    public static function record(object $deal, string $event, int $actor): void
    {
        self::requireTable();
        $sender=(int)$deal->sender_user_id; $carrier=(int)$deal->carrier_user_id;
        $initiator=(int)$deal->initiated_by;
        $recipients=match($event) {
            'request_submitted'=>[$initiator===$sender?$carrier:$sender],
            'request_accepted','request_rejected'=>[$initiator],
            'deal_cancelled'=>[$actor===$sender?$carrier:$sender],
            'deal_confirmed','parcel_picked_up','delivery_completed'=>[$sender,$carrier],
        };
        foreach (array_unique($recipients) as $recipient) {
            $key=['deal_id'=>$deal->id,'event'=>$event,'recipient_user_id'=>$recipient];
            if (!DB::table('deal_notifications')->where($key)->exists()) {
                // A real insert failure must roll back the deal, never be ignored.
                DB::table('deal_notifications')->insert($key+['created_at'=>now(),'read_at'=>null]);
            }
        }
    }

    private static function taskSql(): string
    {
        return "((n.event='request_submitted' AND d.status='pending' AND n.recipient_user_id<>d.initiated_by)
            OR (n.event='request_accepted' AND d.status='accepted' AND d.acceptance_expires_at>?
                AND n.recipient_user_id=d.initiated_by)
            OR (n.event='deal_confirmed' AND d.status='confirmed' AND d.picked_up_at IS NULL
                AND n.recipient_user_id=d.carrier_user_id)
            OR (n.event='parcel_picked_up' AND d.status='confirmed' AND d.picked_up_at IS NOT NULL
                AND n.recipient_user_id=d.carrier_user_id))";
    }

    public static function query(int $user)
    {
        self::requireTable();
        return DB::table('deal_notifications as n')->join('listing_deals as d','d.id','=','n.deal_id')
            ->where('n.recipient_user_id',$user)
            ->where(fn($q)=>$q->where('d.sender_user_id',$user)->orWhere('d.carrier_user_id',$user));
    }

    public static function visible(int $user)
    {
        return self::query($user)->where(fn($q)=>$q->whereNull('n.read_at')->orWhereRaw(self::taskSql(),[now()]));
    }

    public static function taskFilter($query)
    {
        return $query->whereRaw(self::taskSql(),[now()]);
    }

    public static function rows($query)
    {
        return $query->select('n.id','n.deal_id','n.event','n.read_at','n.created_at','d.status','d.acceptance_expires_at',
            'd.sender_user_id','d.carrier_user_id','d.agreed_kg','d.listing_snapshot')
            ->selectRaw(self::taskSql().' AS is_pending_task',[now()]);
    }

    public static function counts(int $user): array
    {
        $row=self::visible($user)->selectRaw('COUNT(CASE WHEN n.read_at IS NULL THEN 1 END) AS unread_count,
            COALESCE(SUM('.self::taskSql().'),0) AS pending_task_count',[now()])->first();
        return ['unread_count'=>(int)$row->unread_count,'pending_task_count'=>(int)$row->pending_task_count];
    }

    public static function data(object $row): array
    {
        $snapshot=json_decode($row->listing_snapshot,true) ?: [];
        $sender=$snapshot['sender']??[];
        $route=[];
        foreach (['from','to'] as $direction) foreach (['country','city'] as $type) {
            $field=$direction.'_'.$type;
            $route[$field.'_id']=isset($sender[$field.'_id'])?(int)$sender[$field.'_id']:null;
            $name=$sender[$field.'_name']??null;
            $route[$field.'_name']=is_string($name)?PublicData::text($name,255):null;
        }
        $pending=(bool)$row->is_pending_task;
        $actions=$pending ? match($row->event) {
            'request_submitted'=>['accept','reject'],
            'request_accepted'=>['confirm','cancel'],
            'deal_confirmed'=>['cancel','pickup'],
            'parcel_picked_up'=>['complete'],
            default=>[],
        } : [];
        $status=$row->status==='accepted' && $row->acceptance_expires_at<=now()->toDateTimeString()?'expired':$row->status;
        return ['id'=>(int)$row->id,'event'=>$row->event,'deal_id'=>(int)$row->deal_id,'deal_status'=>$status,
            'is_read'=>$row->read_at!==null,'read_at'=>$row->read_at,'created_at'=>$row->created_at,
            'is_pending_task'=>$pending,'visible'=>$pending || $row->read_at===null,'allowed_actions'=>$actions,
            'summary'=>['parcel_title'=>PublicData::text($sender['title']??null,255),'weight_kg'=>(int)$row->agreed_kg,'route'=>$route,
                'sender'=>['id'=>(int)$row->sender_user_id,'display_name'=>PublicData::text($sender['display_name']??null,255)],
                'carrier'=>['id'=>(int)$row->carrier_user_id,'display_name'=>PublicData::text($snapshot['carrier']['display_name']??null,255)]]];
    }
}
