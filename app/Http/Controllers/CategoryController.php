<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\Category;
use File;
use Session;

class CategoryController extends Controller
{
    public function viewCategory(){

        $categories = DB::table('categories')->orderBy('id', 'desc')->get();

        return view('admin.category.view_category')->with(compact('categories'));

    }

    public function addCategory(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'category_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'backend/img/category/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->save($large_image_path);

                }

            }

            $Category = new Category();
            $Category->name = $data['name'];
            $Category->image = $filename;
            $Category->save();

            return redirect('/admin/view-categories')->with('flash_message_success','Category Added Successfully!');

        }

        return view('admin.category.add_category');

    }

    public function editCategory(Request $request, $id =null){

        if($request->isMethod('post')){

            $data = $request->all();
            
            $Category = Category::where(['id'=>$id])->first();
            // image file delete start
            $image = $Category->image;

            $image_path = 'backend/img/category/'.$image;

            if($request->hasFile('image')){

                $image_tmp = $request->image;

                if(File::exists($image_path)){
                    File::delete($image_path);
                }

                if($image_tmp->isValid()){

                    // create image manager with desired driver
                    $manager = new ImageManager(new Driver());

                    $extension = $image_tmp->getClientOriginalExtension();
                    $filename = 'category_'.rand(1111,9999999).'.'.$extension;
                    $large_image_path = 'backend/img/category/'.$filename;

                    $image = $manager->read($image_tmp);
                    $image->save($large_image_path);

                }

            }else{
                $filename = $data['cur_image'];
            }

            Category::where(['id'=>$id])->update
            ([
                'name'=>$data['name'],
                'image'=>$filename,
                'isActive'=>$data['isActive'],
            ]);

            return redirect('/admin/view-categories')->with('flash_message_success','Category Updated Successfully!');

        }

        $category = Category::where(['id'=>$id])->first();

        return view('admin.category.edit_category')->with(compact('category'));

    }

    public function deleteCategory($id = null){

        $category = Category::where(['id'=>$id])->first();
        $image = $category->image;

        $image_path = 'backend/img/category/'.$image;

        if(File::exists($image_path)){
            File::delete($image_path);
        }

        Category::where(['id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','Category deleted Successfully!');
        
    }

}
