<?php

namespace App\Services\Api;

use App\Models\User;
use App\Queries\Api\PublicListingQuery;
use App\Services\EmailActivationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laravel\Sanctum\Guard;
use Laravel\Sanctum\PersonalAccessToken;

final class ListingDeals
{
    public static function available(): bool
    {
        return Schema::hasTable('listing_deals');
    }

    public static function requireTable(): void
    {
        abort_unless(self::available(), 503, 'Deal management is not available yet.');
    }

    public static function forListing(int $id)
    {
        return DB::table('listing_deals')->where(fn ($q) => $q
            ->where('sender_listing_id', $id)->orWhere('carrier_listing_id', $id));
    }

    public static function live(object $deal): bool
    {
        return $deal->status === 'accepted' && $deal->acceptance_expires_at > now()->toDateTimeString();
    }

    public static function eligible(object $listing): bool
    {
        $owner = User::find($listing->created_by);
        return $owner && (int)$owner->isActive === 1 && (int)$owner->isVerified === 1
            && app(EmailActivationService::class)->customer($owner)
            && PublicListingQuery::make()->where('ads.id', $listing->id)->exists();
    }

    // The caller holds the listing row lock for every mutation. Locking reads avoid
    // repeatable-read snapshots missing a just-committed competing reservation.
    public static function capacity(object $listing, bool $lock = false): array
    {
        $query = self::forListing((int)$listing->id)->where(fn ($q) => $q
            ->whereIn('status',['confirmed','completed'])
            ->orWhere(fn ($q) => $q->where('status','accepted')->where('acceptance_expires_at','>',now())))
            ->orderBy('id');
        $deals = ($lock ? $query->lockForUpdate() : $query)->get();
        $committed = 0; $reserved = 0;
        foreach ($deals as $deal) {
            if (in_array($deal->status, ['confirmed', 'completed'], true)) $committed += (int)$deal->agreed_kg;
            if (self::live($deal)) $reserved += (int)$deal->agreed_kg;
        }
        return self::totals((int)$listing->weight,$reserved,$committed,self::eligible($listing));
    }

    private static function totals(int $total, int $reserved, int $committed, bool $eligible): array
    {
        $remaining = max(0, $total - $committed - $reserved);
        return ['total_kg'=>$total, 'reserved_kg'=>$reserved, 'committed_kg'=>$committed,
            'remaining_kg'=>$remaining, 'available'=>$reserved === 0 && $remaining > 0 && $eligible];
    }

    /** Optional bearer context; public browsing never requires a token or writes last_used_at. */
    private static function caller(Request $request): ?User
    {
        if (!$request->bearerToken()) return null;
        $user=$request->user();
        if ($user instanceof User && $user->currentAccessToken() instanceof PersonalAccessToken) return $user;
        if (!Schema::hasTable('personal_access_tokens')) return null;
        $guard=new Guard(Auth::getFacadeRoot(),config('sanctum.expiration'),config('auth.guards.sanctum.provider'),trackLastUsedAt:false);
        $user=$guard($request);
        return $user instanceof User && $user->currentAccessToken() instanceof PersonalAccessToken ? $user : null;
    }

    /**
     * Batch listing response enrichment. No per-listing queries, participant/deal
     * disclosure, GET writes, cached capacity, or changes to the public query scope.
     */
    public static function listingData(iterable $listings, Request $request): array
    {
        $targets=collect($listings)->keyBy('id');
        if ($targets->isEmpty()) return [];
        if (!self::available()) {
            return $targets->map(fn ($l)=>[
                'availability'=>['available'=>null,'total_kg'=>(int)$l->weight,'reserved_kg'=>null,'committed_kg'=>null,'remaining_kg'=>null],
                'request_eligibility'=>['eligible'=>null,'reason'=>'infrastructure_unavailable'],
            ])->all();
        }
        $caller=self::caller($request);
        $columns=['id','created_by','ad_type','weight','from_country_id','from_city_id','to_country_id','to_city_id'];
        $rows=DB::table('post_ads')->whereIn('id',$targets->keys())->select($columns)->get()->keyBy('id');
        $candidates=collect();
        if ($caller) {
            $candidates=PublicListingQuery::make()->where('ads.created_by',$caller->id)
                ->whereIn('ads.ad_type',['Send','Carring'])->where('ads.weight','>',0)
                ->select(array_map(fn ($c)=>'ads.'.$c,$columns))->get()->keyBy('id');
        }
        $all=$rows->union($candidates);
        $owners=User::query()->select('id','isActive','isVerified')->with('roles:id,name,guard_name')
            ->whereIn('id',$all->pluck('created_by')->push($caller?->id)->filter()->unique())->get()->keyBy('id');
        $eligibleOwners=$owners->filter(fn ($u)=>(int)$u->isActive===1 && (int)$u->isVerified===1
            && $u->roles->where('guard_name','web')->pluck('name')->values()->all()===['user']);
        $public=PublicListingQuery::make()->whereIn('ads.id',$all->keys())->select('ads.id')->pluck('ads.id')->flip();
        $deals=DB::table('listing_deals')->where(fn ($q)=>$q->whereIn('sender_listing_id',$all->keys())
            ->orWhereIn('carrier_listing_id',$all->keys()))->whereIn('status',['pending','accepted','confirmed','completed'])
            ->select('sender_listing_id','carrier_listing_id','agreed_kg','status','acceptance_expires_at')->get();
        $reserved=[]; $committed=[]; $pairs=[]; $at=now()->toDateTimeString();
        foreach ($deals as $d) {
            $live=$d->status==='accepted' && $d->acceptance_expires_at>$at;
            if ($live || in_array($d->status,['pending','confirmed'],true)) $pairs[$d->sender_listing_id.':'.$d->carrier_listing_id]=true;
            foreach ([$d->sender_listing_id,$d->carrier_listing_id] as $id) {
                if ($live) $reserved[$id]=($reserved[$id]??0)+(int)$d->agreed_kg;
                if (in_array($d->status,['confirmed','completed'],true)) $committed[$id]=($committed[$id]??0)+(int)$d->agreed_kg;
            }
        }
        $capacity=[];
        foreach ($all as $id=>$l) $capacity[$id]=self::totals((int)$l->weight,$reserved[$id]??0,$committed[$id]??0,
            $public->has($id) && $eligibleOwners->has($l->created_by));
        $result=[];
        foreach ($targets as $id=>$target) {
            $listing=$rows->get($id);
            if (!$listing) {
                // A concurrent deletion after the listing query must not invent capacity.
                $result[$id]=['availability'=>['available'=>null,'total_kg'=>(int)$target->weight,'reserved_kg'=>null,'committed_kg'=>null,'remaining_kg'=>null],
                    'request_eligibility'=>['eligible'=>null,'reason'=>'listing_unavailable']];
                continue;
            }
            $availability=$capacity[$id];
            $eligible=false;
            $reason=match(true) {
                !$caller=>'authentication_required',
                !$eligibleOwners->has($caller->id) || !$caller->currentAccessToken()->can('mobile:account')=>'ineligible_account',
                (int)$listing->created_by===(int)$caller->id=>'self_listing',
                !$availability['available']=>'listing_unavailable',
                default=>'no_eligible_complementary_listing',
            };
            if ($reason==='no_eligible_complementary_listing') {
                foreach ($candidates as $candidate) {
                    if (!$capacity[$candidate->id]['available'] || $candidate->ad_type===$listing->ad_type) continue;
                    $match=true;
                    foreach (['from_country_id','from_city_id','to_country_id','to_city_id'] as $field) {
                        if ((int)$listing->$field!==(int)$candidate->$field) { $match=false; break; }
                    }
                    if (!$match) continue;
                    $sender=$listing->ad_type==='Send'?$listing:$candidate;
                    $carrier=$listing->ad_type==='Carring'?$listing:$candidate;
                    if ($sender->ad_type!=='Send' || $carrier->ad_type!=='Carring') continue;
                    $kg=(int)$sender->weight;
                    if ($kg>0 && $capacity[$sender->id]['committed_kg']===0
                        && $capacity[$sender->id]['remaining_kg']>=$kg && $capacity[$carrier->id]['remaining_kg']>=$kg
                        && !isset($pairs[$sender->id.':'.$carrier->id])) { $eligible=true; $reason='eligible'; break; }
                }
            }
            $result[$id]=['availability'=>$availability,'request_eligibility'=>[
                'eligible'=>$caller?$eligible:null,'reason'=>$reason,
            ]];
        }
        return $result;
    }

    public static function guardEdit(object $listing, array $changes): void
    {
        if (!self::available()) return;
        $deals = self::forListing((int)$listing->id)->whereIn('status',['accepted','confirmed','completed'])
            ->orderBy('id')->lockForUpdate()->get();
        $obligations = $deals->filter(fn ($d) => self::live($d) || in_array($d->status, ['confirmed','completed'], true));
        $hold = $deals->contains(fn ($d) => self::live($d));
        $route = ['ad_type','from_country_id','from_city_id','to_country_id','to_city_id'];
        $material = [...$route,'weight','weight_price','currency','category_id','departure_on','reached_on','isActive',
            'from_area','to_area','from_address','to_address', 'from_latitude','from_longitude','to_latitude','to_longitude'];
        foreach ($changes as $field=>$value) {
            if ((string)($listing->$field ?? '') === (string)($value ?? '')) continue;
            abort_if($hold && in_array($field,$material,true), 409, 'An accepted deal locks material listing fields.');
            abort_if($obligations->isNotEmpty() && in_array($field,$route,true), 409, 'The listing has retained capacity obligations.');
        }
        $committed = $deals->whereIn('status',['confirmed','completed'])->sum('agreed_kg');
        abort_if(array_key_exists('weight',$changes) && (int)$changes['weight'] < $committed, 409, 'Weight cannot be below committed capacity.');
        // A confirmed sender parcel cannot be enlarged into a second, split shipment.
        abort_if($listing->ad_type === 'Send' && $committed > 0 && array_key_exists('weight',$changes)
            && (int)$changes['weight'] !== (int)$listing->weight, 409, 'A committed sender parcel cannot be resized.');
    }

    public static function guardDelete(int $id): void
    {
        if (!self::available()) return;
        $deals = self::forListing($id)->whereIn('status',['pending','accepted','confirmed'])
            ->orderBy('id')->lockForUpdate()->get();
        abort_if($deals->contains(fn ($d) => in_array($d->status,['pending','confirmed'],true) || self::live($d)),
            409, 'Listings with active requests or deals cannot be deleted.');
    }

    public static function expire(array $ids): void
    {
        DB::table('listing_deals')->where('status','accepted')->where('acceptance_expires_at','<=',now())
            ->where(fn ($q) => $q->whereIn('sender_listing_id',$ids)->orWhereIn('carrier_listing_id',$ids))
            ->update(['status'=>'expired','active_pair'=>null,'updated_at'=>now()]);
    }
}
