<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\Country;
use App\Models\City;
use Session;

class CityController extends Controller
{
    public function viewCity(){

        $cities = DB::table('cities as ci')
                  ->join('countries as c','ci.country_id','=','c.id')
                  ->select('ci.*', 'c.name as countryName')
                  ->orderBy('ci.id', 'desc')
                  ->get();

        return view('admin.city.view_city')->with(compact('cities'));

    }

    public function addCity(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            for ($i=0; $i < count($data['name']); $i++) { 
                $City = new City();
                $City->country_id = $data['country_id'];
                $City->name = $data['name'][$i];
                $City->short_name = $data['short_name'][$i];
                $City->save();
            }

            return redirect('/admin/view-cities')->with('flash_message_success','City Added Successfully!');

        }

        $countries = DB::table('countries')->orderBy('name', 'asc')->get();

        return view('admin.city.add_city')->with(compact('countries'));

    }

    public function editCity(Request $request, $id =null){

        if($request->isMethod('post')){

            $data = $request->all();

            City::where(['id'=>$id])->update
            ([
                'name'=>$data['name'],
                'short_name'=>$data['short_name'],
                'isActive'=>$data['isActive'],
            ]);

            return redirect('/admin/view-cities')->with('flash_message_success','City Updated Successfully!');

        }

        $countries = DB::table('countries')->orderBy('name', 'asc')->get();

        $city = DB::table('cities as ci')->where(['ci.id' => $id])->first();

        return view('admin.city.edit_city')->with(compact('city', 'countries'));

    }

    public function deleteCity($id = null){

        City::where(['id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','City deleted Successfully!');

    }

    public function searchCities($name)
    {

        $cities = DB::table('cities as ci')
                  ->join('countries as c','ci.country_id','=','c.id')
                  ->select('ci.*', 'c.name as countryName')
                  ->orderBy('ci.id', 'desc')
                  ->where('ci.name', 'LIKE', '%'.$name.'%')
                  ->get();

        $output = '';
        $i = 0;
        foreach ($cities as $data) {
            $i++;
            $output .= '<tr>
                <td>' . $i . '</td>
                <td>' . $data->countryName . '</td>
                <td>' . $data->name . '</td>
                <td>' . $data->short_name . '</td>
                <td>' . ($data->isActive == 1 ? 'Active' : 'Not Active') . '</td>
                <td>
                    <div class="table-actions d-flex">
                        <a class="delete-table me-2" href="' . url('/admin/edit-city/' . $data->id) . '">
                            <img src="' . asset('backend/img/icons/edit.svg') . '" alt="svg">
                        </a>
                        <a class="delete-table sa-confirm-delete" href="javascript:void(0);" param-id="' . $data->id . '" param-route="delete-city">
                            <img src="' . asset('backend/img/icons/delete.svg') . '" alt="svg">
                        </a>
                    </div>
                </td>
            </tr>';
        }

        return response()->json($output);
    }

}
