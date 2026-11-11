<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Http\View\Composers\ProfileComposer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\PostAd;
use Session;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        view()->composer('*', function($view){

            $listing_get = DB::table('post_ads')->get();
            $cur_date = date('Y-m-d');
            foreach ($listing_get as $ads) {
                if ($ads->departure_on < $cur_date) {
                    $ads_id = $ads->id;
                    PostAd::where(['id'=>$ads_id])->update
                    ([
                        'isActive'=>2,
                    ]);
                }
            }

            $front_footer_count = DB::table('contact_infos')->count();
            $front_footer = DB::table('contact_infos')->first();

            $meta_tags = DB::table('meta_tags')->get();
            $home_meta_tags = DB::table('meta_tags')->where(['url_slug'=>'/'])->first();
            $about_meta_tags = DB::table('meta_tags')->where(['url_slug'=>'about'])->first();
            $contact_meta_tags = DB::table('meta_tags')->where(['url_slug'=>'contact'])->first();

            $view
                ->with('front_footer_count', $front_footer_count)
                ->with('front_footer', $front_footer)
                ->with('meta_tags', $meta_tags)
                ->with('home_meta_tags', $home_meta_tags)
                ->with('about_meta_tags', $about_meta_tags)
                ->with('contact_meta_tags', $contact_meta_tags);
            
        });
    }
}
