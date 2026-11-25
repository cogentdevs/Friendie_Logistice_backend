<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\User;
use App\Models\PostAd;
use App\Models\Country;
use App\Models\Review;
use App\Models\PostAdRoom;
use App\Models\PostAdRoomMessage;
use Session;
use App\Mail\UserForgotPasswrod;
use App\Mail\UserEmailVerification;
use Mail;
use File;
use Illuminate\Support\Facades\Cookie;

class UserController extends Controller
{
    // Admin Side Functions Start Here

    public function viewUser(){

        $users = DB::table('users')->where('id', '!=', 1)->orderBy('id', 'desc')->get();

        return view('admin.users.view_users')->with(compact('users'));

    }

    public function addUser(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            $usercount_email = User::where(['email'=>$data['email']])->count();
            $usercount_phone = User::where(['phone'=>$data['phone']])->count();

            if($usercount_email > 0){

                return redirect()->back()->with('flash_message_error', 'Email Already Exist');

            }elseif($usercount_phone > 0){

                return redirect()->back()->with('flash_message_error', 'Phone Already Exist');

            }else{

                if($request->hasFile('image')){

                    $image_tmp = $request->image;

                    if($image_tmp->isValid()){

                        // create image manager with desired driver
                        $manager = new ImageManager(new Driver());

                        $extension = $image_tmp->getClientOriginalExtension();
                        $filename = 'user_'.rand(1111,9999999).'.'.$extension;
                        $large_image_path = 'frontend/img/profiles/'.$filename;

                        $image = $manager->read($image_tmp);
                        $image->save($large_image_path);

                    }

                }else{
                    $filename = 'avatar.jpg';
                }

                $password = bcrypt($data['password']);

                $user = new User();
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->country_id = $data['country_id'];
                $user->phone = $data['phone'];
                $user->password = $password;
                $user->image = $filename;
                $user->save();
                $user->assignRole('user');

                return redirect('/admin/view-users')->with('flash_message_success','Category Added Successfully!');
            }

        }

        $countries = Country::where(['isActive'=>1])->orderBy('name', 'asc')->get();

        return view('admin.users.add_user')->with(compact('countries'));

    }

    public function editUser(Request $request, $id =null){

        if($request->isMethod('post')){

            $data = $request->all();
            
            $user = User::where(['id'=>$id])->firstOrFail();
            app(\App\Services\CustomerEmailChangeService::class)->validateEmail($user, $request->input('email'));
            // image file delete start
            $image = $user->image;

            $image_path = 'frontend/img/profiles/'.$image;

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if ($image != 'avatar.jpg') {
                    if(File::exists($image_path)){
                        File::delete($image_path);
                    }
                } 

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'user_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'frontend/img/profiles/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->save($large_image_path);

                }

            }else{
                $filename = $data['cur_image'];
            }

            app(\App\Services\CustomerEmailChangeService::class)->update((int) $id, [
                'name'=>$data['name'],
                'email'=>$data['email'],
                'country_id'=>$data['country_id'],
                'phone'=>$data['phone'],
                'image'=>$filename,
                'isActive'=>$data['isActive'],
            ]);

            return redirect('/admin/view-users')->with('flash_message_success','User Updated Successfully!');

        }

        $user = User::where(['id'=>$id])->first();
        $countries = Country::where(['isActive'=>1])->orderBy('name', 'asc')->get();

        return view('admin.users.edit_user')->with(compact('user', 'countries'));

    }

    public function deleteUser($id = null){

        $user = User::where(['id'=>$id])->first();
        $image = $user->image;

        $image_path = 'frontend/img/profiles/'.$image;

        if ($image != 'avatar.jpg') {
            if(File::exists($image_path)){
                File::delete($image_path);
            }
        }

        User::where(['id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','User deleted Successfully!');
        
    }

    // Admin Side Functions End Here
    // ===============================================================================================
    // End User Side Functions Start Here

    public function register(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();

            $request->validate([
                'g-recaptcha-response' => 'required|captcha',
                'email' => 'required|string|email|max:255',
            ]);

            $usercount_email = User::where(['email'=>$data['email']])->count();
            $usercount_phone = User::where(['phone'=>$data['phone']])->count();

            if($usercount_email > 0){

                return redirect()->back()->with('flash_message_error', 'Email Already Exist');

            }elseif($usercount_phone > 0){

                return redirect()->back()->with('flash_message_error', 'Phone Already Exist');

            }else{

                $password = bcrypt($data['password']);

                $user = new User();
                $user->name = $data['name'];
                $user->email = $data['email'];
                $user->country_id = $data['country_id'];
                $user->phone = $data['phone'];
                $user->password = $password;
                $user->image = 'avatar.jpg';
                $user->isActive = 1;
                $user->isVerified = 0;
                $user->save();
                $user->assignRole('user');

                app(\App\Services\EmailActivationService::class)->send($user->id);

                return redirect()->back()->with('flash_message_success','Registration Successfull. We sent you a verification mail on your email also check in Spam.');

            }

        }

        $countries = Country::where(['isActive'=>1])->orderBy('name', 'asc')->get();

        return view('frontend.register')->with(compact('countries'));

    }

    public function activateYourAccount(Request $request, $user_id, #[\SensitiveParameter] $slug){
        return app(\App\Http\Controllers\Auth\EmailActivationController::class)
            ->confirm($request, (string) $user_id, (string) $slug);
    }

    public function login(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();
            
            if(Auth::attempt(['email' => $data['email'], 'password' => $data['password']])){
                
                $users = Auth::User();

                if ($users->isActive == 1 && $users->isVerified == 1) {

                    $user = User::where(['id'=> $users->id])->with('roles')->first();

                    $role_name = $user->roles->first()->name;
                    
                    if($role_name == "user"){

                        // Set cookies if "Remember Me" is checked
                        if (!empty($data['remember'])) {
                            Cookie::queue('email', $data['email'], 60); // 7 days
                            Cookie::queue('password', $data['password'], 60); // 7 days
                            Cookie::queue('remember', 1, 60);
                        } else {
                            Cookie::queue(Cookie::forget('email'));
                            Cookie::queue(Cookie::forget('password'));
                            Cookie::queue(Cookie::forget('remember'));
                        }

                        if ($data['from'] == 'main') {

                            return redirect('user/dashboard')->with('flash_message_success','Login Successfully.');

                        }elseif ($data['from'] == 'listing') {

                            return redirect()->back()->with('flash_message_success','Login Successfully.');

                        }

                    }else{

                        Session::flush();
                        return redirect('/login')->with('flash_message_error','You do not have Rights to Access');
                    }

                } else {

                    Session::flush();
                    return redirect('/login')->with('flash_message_error','You do not verify and activate your email account');

                }
                
            
            }else{

                return redirect('/login')->with('flash_message_error','Invalid Email or Password');

            }

        }

        return view('frontend.login')->with([
            'email' => Cookie::get('email'),
            'password' => Cookie::get('password'),
            'remember' => Cookie::get('remember')
        ]);

    }

    public function logout(){
        Auth::logout();

        // Clear cookies on logout
        Cookie::queue(Cookie::forget('email'));
        Cookie::queue(Cookie::forget('password'));
        Cookie::queue(Cookie::forget('remember'));

        return redirect('/login')->with('flash_message_success', 'Logged out Successfully');
    }

    public function forgot_password_link(Request $request){
        return app(\App\Http\Controllers\Auth\WebPasswordResetController::class)
            ->requestLink($request, 'user');
    }

    public function forgot_reset_password(Request $request, $user_id, #[\SensitiveParameter] $slug){
        return app(\App\Http\Controllers\Auth\WebPasswordResetController::class)
            ->reset($request, (string) $user_id, (string) $slug, 'user');
    }

    public function dashboard(){

        $user_id = Auth::user()->id;

        $count_active_listing = PostAd::where(['created_by' => $user_id, 'isActive'=> 1])->count();
        $count_visitors_reviews = Review::where(['to_user_id' => $user_id])->count();

        $unread_msg_counter =  DB::table('post_ad_room_messages')->where(['read_by' => null, 'to_user_id' => $user_id])->count();

        $visitor_reviews = DB::table('reviews as r')
                            ->join('post_ads as ads','r.post_ads_id','=','ads.id')
                            ->join('users as fu','r.from_user_id','=','fu.id')
                            ->select('r.*', 'fu.name as from_user_name', 'fu.image as from_user_image', 'ads.title as ad_title')
                            ->where(['r.to_user_id' => $user_id])
                            ->orderBy('r.id', 'desc')
                            ->limit(5)
                            ->get();

        return view('frontend.user.dashboard')->with(compact('count_active_listing', 'count_visitors_reviews', 'unread_msg_counter', 'visitor_reviews'));

    }

    public function edit_profile(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            $user_id = Auth::user()->id;

            $user = User::where(['id'=>$user_id])->firstOrFail();
            app(\App\Services\CustomerEmailChangeService::class)->validateEmail($user, $request->input('email'));
            // image file delete start
            $image = $user->image;

            $image_path = 'frontend/img/profiles/'.$image;

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if ($image != 'avatar.jpg') {
                    if(File::exists($image_path)){
                        File::delete($image_path);
                    }
                }

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'user_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'frontend/img/profiles/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->save($large_image_path);

                }

            }else{
                $filename = $data['cur_image'];
            }

            $emailChanged = app(\App\Services\CustomerEmailChangeService::class)->update((int) $user_id, [
                'name'=>$data['name'],
                'email'=>$data['email'],
                'country_id'=>$data['country_id'],
                'phone'=>$data['phone'],
                'image'=>$filename,
            ]);

            if ($emailChanged) {
                Auth::logoutCurrentDevice();
                return redirect('/login')->with('flash_message_success', 'Profile updated. Verify your new email address before signing in.');
            }

            return redirect()->back()->with('flash_message_success','Profile Updated Successfully!');

        }

        $countries = Country::where(['isActive'=>1])->orderBy('name', 'asc')->get();

        return view('frontend.user.profile')->with(compact('countries'));;

    }

    public function change_password(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();
           // dd($request->currentPassword);
            if (!(Hash::check($request->currentPassword, Auth::user()->password))) {
                // The passwords matches
                 return redirect()->back()->with('flash_message_error',' Your current password does not matches with the password you provided. Please try again.  ');
            }

            if(strcmp($request->currentPassword, $request->newPassword) == 0){
                //Current password and new password are same
                  return redirect()->back()->with('flash_message_error',' New Password cannot be same as your current password. Please choose a different password. ');
            }

            if($request->confirmPassword = $request->newPassword){
                //Confirm password and new password are same
                  return redirect()->back()->with('flash_message_error',' New Password and Confirm Password are not same. ');
            }

            // Change Password
            $user = Auth::user();       
            $pass = bcrypt($request->newPassword);
            $result = $user->update(['password'=>$pass]);

            if ($result) {
                return redirect()->back()->with('flash_message_success','Password Change Successfully.');
            }
            else{
                return redirect()->back()->with('flash_message_success','Password Not Changed.');
            }
        }

    }

    public function messagesPostAds(){
        abort_unless(\App\Services\DealContactAccess::customer((int)Auth::id()),403);

        $ads =  \App\Services\DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
                ->join('post_ads as ads','room.post_ads_id','=','ads.id')
                ->join('categories as cat','ads.category_id','=','cat.id')
                ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                ->join('cities as fc','ads.from_city_id','=','fc.id')
                ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                ->join('cities as tc','ads.to_city_id','=','tc.id')
                ->join('users as f_user','room.from_user_id','=','f_user.id')
                ->join('users as t_user','room.to_user_id','=','t_user.id')
                ->select('room.*', 'ads.title', 'ads.image', 'ads.ad_type', 'ads.weight', 'ads.weight_price', 'ads.currency', 'ads.from_area', 'ads.to_area', 'ads.departure_on', 'ads.reached_on', 'ads.isActive', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name', 'f_user.name as fromUserName', 'f_user.image as fromUserImage', 't_user.name as toUserName', 't_user.image as toUserImage', DB::raw('(SELECT COUNT(*) FROM post_ad_room_messages WHERE post_ad_room_messages.post_ad_room_id = room.id AND post_ad_room_messages.read_by IS NULL AND post_ad_room_messages.to_user_id = ' . Auth::user()->id . ') AS unread_message_count'))

                ->groupBy('room.post_ads_id')
                ->orderBy('room.id', 'desc')
                ->paginate(10);
        $paginator = $ads;

        return view('frontend.user.message-posts')->with(compact('ads', 'paginator'));  

    }

    public function messagesPostAdsUserList($post_id)
    {
        \App\Services\DealContactAccess::webListing((int)$post_id,(int)Auth::id());
        $chk_post = DB::table('post_ads')->where(['id' => $post_id])->first();

        // Subquery to fetch the most recent message for each room
        $subQuery = DB::table('post_ad_room_messages as msg')
                    ->select('msg.post_ad_room_id', DB::raw('MAX(msg.created_at) as last_message_time'))
                    ->groupBy('msg.post_ad_room_id');

        // Main query
        if (($chk_post->created_by ?? 0) == Auth::user()->id) {
            $subQuery = DB::table('post_ad_room_messages')
                        ->select('post_ad_room_id', DB::raw('MAX(created_at) as last_message_time'))
                        ->groupBy('post_ad_room_id');

            $rooms = \App\Services\DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
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
            $rooms = \App\Services\DealContactAccess::scopeRooms(DB::table('post_ad_rooms as room'),(int)Auth::id())
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

        return view('frontend.user.messages')->with(compact('rooms', 'post_id'));
    }


    public function bookmarks(){

        return view('frontend.user.bookmarks');

    }

    public function postRateReview(Request $request){

        if($request->isMethod('post')){

            abort_unless(Auth::check(),401);
            $data=$request->validate([
                'deal_id'=>'sometimes|integer|min:1|max:'.PHP_INT_MAX,
                'post_ads_id'=>'required_without:deal_id|integer|min:1|max:2147483647',
                'from_user_id'=>'sometimes|integer|min:1|max:2147483647',
                'to_user_id'=>'sometimes|integer|min:1|max:2147483647',
            ]+\App\Services\Api\DealReviews::rules());
            \App\Services\Api\DealReviews::submit((int)Auth::id(),$data,null,true);
           
            return redirect()->back()->with('flash_message_success','Your Review Post Successfullyl.');
            
        }

    }

    public function reviews(){

        $user_id = Auth::user()->id;

        $count_visitors_reviews = Review::where(['to_user_id' => $user_id])->count();
        $count_your_reviews = Review::where(['from_user_id' => $user_id])->count();

        $visitor_reviews = DB::table('reviews as r')
                            ->join('post_ads as ads','r.post_ads_id','=','ads.id')
                            ->join('users as fu','r.from_user_id','=','fu.id')
                            ->select('r.*', 'fu.name as from_user_name', 'fu.image as from_user_image', 'ads.title as ad_title')
                            ->where(['r.to_user_id' => $user_id])
                            ->get();

        $your_reviews = DB::table('reviews as r')
                        ->join('post_ads as ads','r.post_ads_id','=','ads.id')
                        ->join('users as fu','r.from_user_id','=','fu.id')
                        ->select('r.*', 'fu.name as from_user_name', 'fu.image as from_user_image', 'ads.title as ad_title')
                        ->where(['r.from_user_id' => $user_id])
                        ->get();

        return view('frontend.user.reviews')->with(compact('count_visitors_reviews', 'count_your_reviews', 'visitor_reviews', 'your_reviews'));

    }

}
