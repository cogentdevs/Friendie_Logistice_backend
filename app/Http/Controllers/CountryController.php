<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\Country;
use App\Models\City;
use File;
use Session;

class CountryController extends Controller
{
    public function viewCountry(){

        $countries = DB::table('countries')->orderBy('id', 'desc')->get();

        return view('admin.country.view_country')->with(compact('countries'));

    }

    public function addCountry(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'country_flag_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'backend/img/flags/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->resize(200, 120)->save($large_image_path);

                }

            }

            $country = new Country();
            $country->name = $data['name'];
            $country->short_name = $data['short_name'];
            $country->currency_name = $data['currency_name'];
            $country->code = $data['code'];
            $country->image = $filename;
            $country->save();

            return redirect('/admin/view-countries')->with('flash_message_success','Country Added Successfully!');

        }

        return view('admin.country.add_country');

    }

    public function editCountry(Request $request, $id =null){

        if($request->isMethod('post')){

            $data = $request->all();

            $country = Country::where(['id'=>$id])->first();
            // image file delete start
            $image = $country->image;

            $image_path = 'backend/img/flags/'.$image;

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if(File::exists($image_path)){
                    File::delete($image_path);
                }

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'country_flag_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'backend/img/flags/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->resize(200, 120)->save($large_image_path);

                }

            }else{
                $filename = $data['cur_image'];
            }

            Country::where(['id'=>$id])->update
            ([
                'name'=>$data['name'],
                'short_name'=>$data['short_name'],
                'currency_name'=>$data['currency_name'],
                'code'=>$data['code'],
                'image'=>$filename,
                'isActive'=>$data['isActive'],
            ]);

            return redirect('/admin/view-countries')->with('flash_message_success','Country Updated Successfully!');

        }

        $country = Country::where(['id'=>$id])->first();

        return view('admin.country.edit_country')->with(compact('country'));

    }

    public function deleteCountry($id = null){

        $country = Country::where(['id'=>$id])->first();
        $image = $country->image;

        $image_path = 'backend/img/flags/'.$image;

        if(File::exists($image_path)){
            File::delete($image_path);
        }

        Country::where(['id'=>$id])->delete();
        City::where(['country_id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','Country deleted Successfully!');

    }

}
