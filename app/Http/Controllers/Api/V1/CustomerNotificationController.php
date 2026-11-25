<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Api\DealNotifications;
use App\Support\Api\PublicData;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CustomerNotificationController
{
    public function index(Request $request)
    {
        $input=PublicData::input($request,PublicData::paginationRules(50)+['filter'=>'sometimes|in:all,tasks,unread']);
        $query=DealNotifications::visible((int)$request->user()->id);
        if (($input['filter']??null)==='tasks') DealNotifications::taskFilter($query);
        if (($input['filter']??null)==='unread') $query->whereNull('n.read_at');
        $page=DealNotifications::rows($query)->orderByDesc('n.id')->paginate($input['per_page']??20,['*'],'page',$input['page']??1);
        return ApiResponse::success('Notifications retrieved.',PublicData::page($page,fn($row)=>DealNotifications::data($row)))
            ->header('Cache-Control','no-store, private');
    }

    public function counts(Request $request)
    {
        PublicData::input($request,[]);
        return ApiResponse::success('Notification counts retrieved.',DealNotifications::counts((int)$request->user()->id))
            ->header('Cache-Control','no-store, private');
    }

    public function read(Request $request, string $id)
    {
        $unknown=array_keys($request->all());
        if ($unknown) throw \Illuminate\Validation\ValidationException::withMessages(array_fill_keys($unknown,['This field is not allowed.']));
        foreach (['user:'.$request->user()->id,'ip:'.hash('sha256',(string)$request->ip())] as $scope) {
            $key='deal-notification-read:'.$scope;
            if (RateLimiter::tooManyAttempts($key,120)) throw new HttpException(429,'',null,['Retry-After'=>(string)RateLimiter::availableIn($key)]);
            RateLimiter::hit($key,60);
        }
        $viewer=(int)$request->user()->id;
        $before=DealNotifications::query($viewer)->where('n.id',$id)->first(['n.deal_id']);
        abort_unless($before,404);
        DB::transaction(function () use ($before,$viewer,$id) {
            // Match lifecycle lock order: deal before notification. Read never changes the deal.
            $deal=DB::table('listing_deals')->where('id',$before->deal_id)->lockForUpdate()->first();
            abort_unless($deal && in_array($viewer,[(int)$deal->sender_user_id,(int)$deal->carrier_user_id],true),404);
            $notification=DB::table('deal_notifications')->where('id',$id)->where('deal_id',$deal->id)
                ->where('recipient_user_id',$viewer)->lockForUpdate()->first();
            abort_unless($notification,404);
            if ($notification->read_at===null) DB::table('deal_notifications')->where('id',$id)->update(['read_at'=>now()]);
        },3);
        $row=DealNotifications::rows(DealNotifications::query($viewer)->where('n.id',$id))->first();
        abort_unless($row,404);
        return ApiResponse::success('Notification marked as read.',DealNotifications::data($row))->header('Cache-Control','no-store, private');
    }
}
