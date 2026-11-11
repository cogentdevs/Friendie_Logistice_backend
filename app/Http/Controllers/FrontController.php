<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Country;
use App\Models\City;
use App\Models\Category;
use App\Models\PostAd;
use App\Models\User;
use App\Models\Review;
use App\Models\PostAdRoom;
use Session;

class FrontController extends Controller
{
    public function index(){

        $categories = DB::table('categories')
                    ->leftJoin('post_ads', 'categories.id', '=', 'post_ads.category_id')
                    ->select('categories.id', 'categories.name', 'categories.image', DB::raw('COUNT(post_ads.id) as ads_count'))
                    ->where(['categories.isActive' => 1])
                    ->groupBy('categories.id', 'categories.name', 'categories.image')
                    ->orderBy('categories.name', 'asc')
                    ->get();

        $cities = DB::table('cities')->where(['isActive' => 1])->orderBy('name', 'asc')->get();

        $feature_listing = DB::table('post_ads as ads')
                            ->join('users as u','ads.created_by','=','u.id')
                            ->join('categories as cat','ads.category_id','=','cat.id')
                            ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                            ->join('cities as fc','ads.from_city_id','=','fc.id')
                            ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                            ->join('cities as tc','ads.to_city_id','=','tc.id')
                            ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'cat.name as category_name', 'fcon.short_name as from_country_name', 'fc.name as from_city_name', 'tcon.short_name as to_country_name', 'tc.name as to_city_name')
                            ->where(['ads.isActive' => 1, 'ads.isFeatured' => 1])
                            ->where('ads.departure_on', '>', date('Y-m-d'))
                            ->orderBy('ads.id', 'desc')
                            ->get();

        $count_feature_listing = DB::table('post_ads as ads')
                                ->where(['ads.isActive' => 1, 'ads.isFeatured' => 1])
                                ->where('ads.departure_on', '>', date('Y-m-d'))
                                ->orderBy('ads.id', 'desc')
                                ->count();

        $listing = DB::table('post_ads as ads')
                    ->join('users as u','ads.created_by','=','u.id')
                    ->join('categories as cat','ads.category_id','=','cat.id')
                    ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                    ->join('cities as fc','ads.from_city_id','=','fc.id')
                    ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                    ->join('cities as tc','ads.to_city_id','=','tc.id')
                    ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'cat.name as category_name', 'fcon.short_name as from_country_name', 'fc.name as from_city_name', 'tcon.short_name as to_country_name', 'tc.name as to_city_name')
                    ->where(['ads.isActive' => 1])
                    ->orWhere(['ads.isActive' => 2])
                    // ->where('ads.departure_on', '>', date('Y-m-d'))
                    ->orderBy('ads.id', 'desc')
                    ->limit(8)
                    ->get();

        return view('frontend.index')->with(compact('categories', 'listing', 'feature_listing', 'count_feature_listing', 'cities'));

    }

    public function about(){

        return view('frontend.about');

    }

    public function termsAndCondition(){

        return view('frontend.terms-condition');

    }

    public function searchListings(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();
            // dd($data);
            $categories = DB::table('categories')->where(['isActive' => 1])->orderBy('name', 'asc')->get();

            $cities = DB::table('cities')->where(['isActive' => 1])->orderBy('name', 'asc')->get();

            if ($data['search_from'] == 'home') {

                $listing = DB::table('post_ads as ads')
                            ->join('users as u','ads.created_by','=','u.id')
                            ->join('categories as cat','ads.category_id','=','cat.id')
                            ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                            ->join('cities as fc','ads.from_city_id','=','fc.id')
                            ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                            ->join('cities as tc','ads.to_city_id','=','tc.id')
                            ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'u.created_at as join_date', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                            ->where([
                                    ['ads.ad_type','=',$data['ad_type'], 'OR', $data['ad_type'], 'IS NULL'],
                                    ['ads.category_id','=',$data['category_id'], 'OR', $data['category_id'], 'IS NULL'],
                                    ['ads.weight','=',$data['weight'], 'OR', $data['weight'], 'IS NULL'],
                                    ['ads.from_city_id','=',$data['from_city_id'], 'OR', $data['from_city_id'], 'IS NULL'],
                                    ['ads.to_city_id','=',$data['to_city_id'], 'OR', $data['to_city_id'], 'IS NULL'],
                                    ['ads.departure_on','=',$data['departure_on'], 'OR', $data['departure_on'], 'IS NULL'],
                                  ])
                            ->where(['ads.isActive' => 1])
                            ->orWhere(['ads.isActive' => 2])
                            ->orderBy('ads.id', 'desc')
                            ->paginate(10);
                $paginator = $listing;

            } elseif ($data['search_from'] == 'listings') {

                $listing = DB::table('post_ads as ads')
                            ->join('users as u','ads.created_by','=','u.id')
                            ->join('categories as cat','ads.category_id','=','cat.id')
                            ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                            ->join('cities as fc','ads.from_city_id','=','fc.id')
                            ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                            ->join('cities as tc','ads.to_city_id','=','tc.id')
                            ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'u.created_at as join_date', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                            ->where([
                                    ['ads.ad_type','=',$data['ad_type'], 'OR', $data['ad_type'], 'IS NULL'],
                                    ['ads.category_id','=',$data['category_id'], 'OR', $data['category_id'], 'IS NULL'],
                                    ['ads.weight','=',$data['weight'], 'OR', $data['weight'], 'IS NULL'],
                                    ['ads.from_city_id','=',$data['from_city_id'], 'OR', $data['from_city_id'], 'IS NULL'],
                                    ['ads.from_area','LIKE','%'.$data['from_area'].'%', 'OR', $data['from_area'], 'IS NULL'],
                                    ['ads.to_city_id','=',$data['to_city_id'], 'OR', $data['to_city_id'], 'IS NULL'],
                                    ['ads.to_area','LIKE','%'.$data['to_area'].'%', 'OR', $data['to_area'], 'IS NULL'],
                                    ['ads.departure_on','=',$data['departure_on'], 'OR', $data['departure_on'], 'IS NULL'],
                                  ])
                            ->where(['ads.isActive' => 1])
                            ->orWhere(['ads.isActive' => 2])
                            ->orderBy('ads.id', 'desc')
                            ->paginate(10);
                $paginator = $listing;

            }      

            $page_listing_count = $listing->count();
            $total_listing_count = $listing->total();       

            return view('frontend.listings')->with(compact('categories', 'listing', 'paginator', 'cities', 'page_listing_count', 'total_listing_count'));

        }

    }

    public function listings($id = null) {
        
        $categories = DB::table('categories')->where(['isActive' => 1])->orderBy('name', 'asc')->get();
        $cities = DB::table('cities')->where(['isActive' => 1])->orderBy('name', 'asc')->get();

        $query = DB::table('post_ads as ads')
                    ->join('users as u', 'ads.created_by', '=', 'u.id')
                    ->join('categories as cat', 'ads.category_id', '=', 'cat.id')
                    ->join('countries as fcon', 'ads.from_country_id', '=', 'fcon.id')
                    ->join('cities as fc', 'ads.from_city_id', '=', 'fc.id')
                    ->join('countries as tcon', 'ads.to_country_id', '=', 'tcon.id')
                    ->join('cities as tc', 'ads.to_city_id', '=', 'tc.id')
                    ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'u.created_at as join_date', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                    ->where(['ads.isActive' => 1])
                    ->orWhere(['ads.isActive' => 2]);
                    // ->where('ads.departure_on', '>', date('Y-m-d'));

        // If category ID is provided, filter by category
        if ($id) {
            $query->where('ads.category_id', $id);
        }

        $listing = $query->orderBy('ads.id', 'desc')->paginate(10);

        $paginator = $listing;
        $page_listing_count = $listing->count();
        $total_listing_count = $listing->total();

        return view('frontend.listings')->with(compact('categories', 'listing', 'paginator', 'cities', 'page_listing_count', 'total_listing_count'));

    }

    public function listing_details($id){

        $listing = DB::table('post_ads as ads')
                    ->join('users as u','ads.created_by','=','u.id')
                    ->join('categories as cat','ads.category_id','=','cat.id')
                    ->join('countries as fcon','ads.from_country_id','=','fcon.id')
                    ->join('cities as fc','ads.from_city_id','=','fc.id')
                    ->join('countries as tcon','ads.to_country_id','=','tcon.id')
                    ->join('cities as tc','ads.to_city_id','=','tc.id')
                    ->select('ads.*', 'u.name as createdBy', 'u.email as createdByEmail', 'u.phone as createdByPhone', 'u.image as createdByImage', 'u.created_at as join_date', 'cat.name as category_name', 'fcon.name as from_country_name', 'fc.name as from_city_name', 'tcon.name as to_country_name', 'tc.name as to_city_name')
                    ->where(['ads.id' => $id])
                    ->orderBy('ads.id', 'desc')
                    ->first();

        $cur_date = date('Y-m-d');

        $chk_rooms = PostAdRoom::where(['post_ads_id' => $listing->id, 'to_user_id'=>$listing->created_by])->get();        

        $listing_owner_rating = round(DB::table('reviews')->where('to_user_id', $listing->created_by)->avg('rating'), 1);
        // dd($listing_owner_rating);

        $visitor_reviews = DB::table('reviews as r')
                            ->join('post_ads as ads','r.post_ads_id','=','ads.id')
                            ->join('users as fu','r.from_user_id','=','fu.id')
                            ->select('r.*', 'fu.name as from_user_name', 'fu.image as from_user_image', 'ads.title as ad_title')
                            ->where(['r.post_ads_id' => $listing->id])
                            ->get();
        if (!empty(Auth::user())) {
            $visitor_reviews_count = DB::table('reviews as r')
                            ->where(['r.post_ads_id' => $listing->id, 'r.from_user_id' => Auth::user()->id])
                            ->count();
        }else{
            $visitor_reviews_count =  0;
        }

        return view('frontend.listing-details')->with(compact('listing', 'cur_date', 'chk_rooms', 'visitor_reviews', 'listing_owner_rating', 'visitor_reviews_count'));

    }

}
