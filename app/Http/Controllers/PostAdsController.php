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
use App\Models\Country;
use App\Models\City;
use App\Models\Category;
use App\Models\PostAd;
use Session;

class PostAdsController extends Controller
{
    public function viewPostAds($ad_type = null){

        $user_id = Auth::user()->id;

        if ($user_id == 1 || $user_id == '1') {

            $query = DB::table('post_ads as ads')
                    ->join('users as u','ads.created_by','=','u.id')
                    ->join('categories as cat','ads.category_id','=','cat.id')
                    ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                    ->join('cities as fc','ads.from_city_id','=','fc.id')
                    ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                    ->join('cities as tc','ads.to_city_id','=','tc.id')
                    ->select('ads.*', 'u.name as createdBy', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name');

            if ($ad_type) {
                $query->where('ads.ad_type', 'LIKE', '%'.$ad_type.'%');
            }

            $listing = $query->orderBy('ads.id', 'desc')->get();

            return view('admin.listing.view_listing')->with(compact('listing'));

        }elseif ($user_id > 1 || $user_id > '1') {

            $query = DB::table('post_ads as ads')
                          ->join('categories as cat','ads.category_id','=','cat.id')
                          ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                          ->join('cities as fc','ads.from_city_id','=','fc.id')
                          ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                          ->join('cities as tc','ads.to_city_id','=','tc.id')
                          ->select('ads.*', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                          ->where(['ads.created_by' => $user_id]);

            if ($ad_type) {
                $query->where('ads.ad_type', 'LIKE', '%'.$ad_type.'%');
            }

            $my_listings = $query->orderBy('ads.id', 'desc')->paginate(10);
            $paginator = $my_listings;

            return view('frontend.user.my-listing')->with(compact('my_listings', 'paginator'));

        }

    }

    public function viewPendingPostAds(){

        $listing = DB::table('post_ads as ads')
                ->join('users as u','ads.created_by','=','u.id')
                ->join('categories as cat','ads.category_id','=','cat.id')
                ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                ->join('cities as fc','ads.from_city_id','=','fc.id')
                ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                ->join('cities as tc','ads.to_city_id','=','tc.id')
                ->select('ads.*', 'u.name as createdBy', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                ->where(['ads.isActive'=>0])
                ->orderBy('ads.id', 'desc')
                ->get();

        return view('admin.listing.view_listing')->with(compact('listing'));

    }

    public function markFeaturedPostAds($id){

        PostAd::where(['id'=>$id])->update
        ([
            'isFeatured'=> 1
        ]);

        return redirect()->back()->with('flash_message_success','Listing Mark As Featured Successfully!');

    }

    public function unmarkFeaturedPostAds($id){

        PostAd::where(['id'=>$id])->update
        ([
            'isFeatured'=> 0
        ]);

        return redirect()->back()->with('flash_message_success','Listing Unark As Featured Successfully!');

    }

    public function markPublishedPostAds($id){

        PostAd::where(['id'=>$id])->update
        ([
            'isActive'=> 1
        ]);

        return redirect('/admin/view-listing')->with('flash_message_success','Listing Published Successfully!');

    }

    public function addPostAds(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();
            // dd($data);

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'listing_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'frontend/img/listing/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->resize(420,340)->save($large_image_path);

                }

            }else{
                $filename = 'listing.png';
            }

            $post_ads = new PostAd();
            $post_ads->category_id = $data['category_id'];
            $post_ads->ad_type = $data['ad_type'];
            $post_ads->title = $data['title'];
            $post_ads->image = $filename;
            $post_ads->description = $data['description'];
            $post_ads->weight = $data['weight'];
            $post_ads->weight_price = $data['weight_price'];
            $post_ads->currency = $data['currency'];
            $post_ads->from_country_id = $data['from_country_id'];
            $post_ads->from_city_id = $data['from_city_id'];
            $post_ads->from_area = $data['from_area'];
            $post_ads->to_country_id = $data['to_country_id'];
            $post_ads->to_city_id = $data['to_city_id'];
            $post_ads->to_area = $data['to_area'];
            $post_ads->departure_on = $data['departure_on'];
            $post_ads->reached_on = $data['reached_on'];
            $post_ads->show_contact_info = $data['show_contact_info'];
            $post_ads->created_by = Auth::user()->id;
            $post_ads->save();

            return redirect('/user/my-listing')->with('flash_message_success','Listing Added Successfully!');

        }

        $categories = DB::table('categories')->orderBy('name', 'asc')->get();
        $countries = DB::table('countries')->orderBy('name', 'asc')->get();
        $currencies = DB::table('countries')->groupBy('currency_name')->orderBy('currency_name', 'asc')->get('currency_name');

        return view('frontend.user.add-listing')->with(compact('categories', 'countries', 'currencies'));

    }

    public function editPostAds(Request $request, $id){

        $user_id = Auth::user()->id;

        if($request->isMethod('post')){

            $data = $request->all();

            $post_ad = PostAd::where(['id'=>$id])->first();
            // image file delete start
            $image = $post_ad->image;

            $image_path = 'frontend/img/listing/'.$image;

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if ($image != 'listing.png') {
                    if(File::exists($image_path)){
                        File::delete($image_path);
                    }
                }

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'listing_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'frontend/img/listing/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->resize(420,340)->save($large_image_path);

                }

            }else{
                $filename = $data['cur_image'];
            }

            PostAd::where(['id'=>$id])->update
            ([
                'category_id'=>$data['category_id'],
                'ad_type'=>$data['ad_type'],
                'title'=>$data['title'],
                'image'=>$filename,
                'description'=>$data['description'],
                'weight'=>$data['weight'],
                'weight_price'=>$data['weight_price'],
                'currency'=>$data['currency'],
                'from_country_id'=>$data['from_country_id'],
                'from_city_id'=>$data['from_city_id'],
                'from_area'=>$data['from_area'],
                'to_country_id'=>$data['to_country_id'],
                'to_city_id'=>$data['to_city_id'],
                'to_area'=>$data['to_area'],
                'departure_on'=>$data['departure_on'],
                'reached_on'=>$data['reached_on'],
                'show_contact_info'=>$data['show_contact_info'],
                'isActive'=>$data['isActive'],
            ]);

            if ($user_id == 1 || $user_id == '1') {

                return redirect('/admin/view-listing')->with('flash_message_success','Listing Updated Successfully!');
                
            }elseif ($user_id > 1 || $user_id > '1') {

                return redirect('/user/my-listing')->with('flash_message_success','Listing Updated Successfully!');

            }
            
        }

        $listing = DB::table('post_ads')->where(['id' => $id])->first();

        $categories = DB::table('categories')->orderBy('name', 'asc')->get();
        $countries = DB::table('countries')->orderBy('name', 'asc')->get();
        $currencies = DB::table('countries')->groupBy('currency_name')->orderBy('currency_name', 'asc')->get('currency_name');

        $from_city_list = DB::table('cities')->where(['country_id' => $listing->from_country_id])->get();
        $to_city_list = DB::table('cities')->where(['country_id' => $listing->to_country_id])->get();

        if ($user_id == 1 || $user_id == '1') {

            return view('admin.listing.edit_listing')->with(compact('listing', 'categories', 'countries', 'from_city_list', 'to_city_list', 'currencies'));
            
        }elseif ($user_id > 1 || $user_id > '1') {

            return view('frontend.user.edit-listing')->with(compact('listing', 'categories', 'countries', 'from_city_list', 'to_city_list', 'currencies'));

        }

    }

    public function deletePostAds($id = null){

        $post_ad = PostAd::where(['id'=>$id])->first();
        // image file delete start
        $image = $post_ad->image;

        if ($image != 'listing.png') {
            if(File::exists($image_path)){
                File::delete($image_path);
            }
        }

        PostAd::where(['id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','Listing Deleted Successfully!');
        
    }
}
