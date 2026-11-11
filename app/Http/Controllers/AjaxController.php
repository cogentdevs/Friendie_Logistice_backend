<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Category;
use App\Models\Country;
use App\Models\CityController;
use App\Models\Subscribe;

class AjaxController extends Controller
{
    public function viewCountryCityList($country_id){

        $cities = DB::table('cities')->where(['country_id'=>$country_id])->orderBy('name', 'asc')->get();

        $cities_dd = '<option disabled selected>Select</option>';
        foreach ($cities as $data) {
            $cities_dd .='<option value="'.$data->id.'">'.$data->name.'</option>';
        }

        return $cities_dd;

    }

    public function viewCountryCode($country_id){

        $country = DB::table('countries')->where(['id'=>$country_id])->first();

        $code = '('.$country->code.')';

        return $code;

    }

    public function subscribe(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            $subscribes_count = DB::table('subscribes')->where(['email'=>$data['email']])->count();

            if ($subscribes_count == 1) {
                
                $chk = "already";

            } else {

                $subscribe = new Subscribe();
                $subscribe->email = $data['email'];
                $subscribe->save();

                $chk = "done";

            }

            return $chk;

        }

    }
}
