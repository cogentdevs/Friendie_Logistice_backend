<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use App\Models\User;
use App\Models\Category;
use App\Models\Country;
use App\Models\PostAd;
use Session;
use App\Mail\UserForgotPasswrod;
use Mail;

class AdminController extends Controller
{
    use  Notifiable, HasRoles;

    public function login(Request $request){

        if($request->isMethod('post')){

            $data = $request->input();
            
            if(Auth::attempt(['email'=>$data['email'],'password'=>$data['password']])){
                
                $users = Auth::User();

                $user = User::where(['id'=> $users->id])->with('roles')->first();

                $role_name = $user->roles->first()->name;
                // dd($role_name);
                if($role_name == "admin"){
                    return redirect('admin/dashboard');
                }else{

                    Session::flush();
                    return redirect('/admin')->with('flash_message_error','You do not have Rights to Access');
                }
            
            }else{

                return redirect('/admin')->with('flash_message_error','Invalid Username or Password');

            }

        }

        return view('admin.admin-login');
    
    }

    public function dashboard(){

        $count_users = User::where('id', '!=', 1)->count();
        $count_category = Category::count();
        $count_country = Country::count();
        $count_all_listing = PostAd::count();
        $count_pending_listing = PostAd::where(['isActive'=>0])->count();
        $count_active_listing = PostAd::where(['isActive'=>1])->count();
        $count_active_carring_listing = PostAd::where(['ad_type'=>'Carring', 'isActive'=>1])->count();
        $count_active_send_listing = PostAd::where(['ad_type'=>'Send', 'isActive'=>1])->count();

        return view('admin.dashboard')->with(compact('count_users', 'count_category', 'count_country', 'count_all_listing', 'count_pending_listing', 'count_active_listing', 'count_active_carring_listing', 'count_active_send_listing'));
    }

    public function logout(){
        Session::flush();
        Auth::logout();
        return redirect('/admin')->with('flash_message_success','Logged out Successfully');
    }

    public function change_password(Request $request)
    {
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

            // Change Password
            $user = Auth::user();       
            $pass=bcrypt($request->newPassword);
             $result=$user->update(['password'=>$pass]);

            if ($result) {
                return redirect()->back()->with('flash_message_success','Password Change Successfulyy...!');
            }
            else{
                return redirect()->back()->with('flash_message_success','Password Not Changed...!');
            }
        }

        return view('admin.change-password');   

    }

    public function forgot_password_link(Request $request){
        return app(\App\Http\Controllers\Auth\WebPasswordResetController::class)
            ->requestLink($request, 'admin');
    }

    public function forgot_reset_password(Request $request, $user_id, #[\SensitiveParameter] $slug){
        return app(\App\Http\Controllers\Auth\WebPasswordResetController::class)
            ->reset($request, (string) $user_id, (string) $slug, 'admin');
    }
}
