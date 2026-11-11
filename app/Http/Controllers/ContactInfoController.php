<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\ContactInfo;

class ContactInfoController extends Controller
{
    public function viewContactInfo()
    {
        $contact_infos = DB::table('contact_infos')->first();

        $contact_infos_count = DB::table('contact_infos')->count();

        return view('admin.contact-info.view_contact_info')->with(compact('contact_infos', 'contact_infos_count'));
    }

    public function addContactInfo(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            $ContactInfo = new ContactInfo();
            $ContactInfo->email = $data['email'];
            $ContactInfo->phone_1 = $data['phone_1'];
            $ContactInfo->phone_2 = $data['phone_2'];
            $ContactInfo->address = $data['address'];
            $ContactInfo->google = $data['google'];
            $ContactInfo->linkedin = $data['linkedin'];
            $ContactInfo->twitter = $data['twitter'];
            $ContactInfo->facebook = $data['facebook'];
            $ContactInfo->instagram = $data['instagram'];
            $ContactInfo->youtube = $data['youtube'];
            $ContactInfo->save();

            return redirect('/admin/view-contact-info-item')->with('flash_message_success','ContactInfo Items Added Successfully!');

        }

        return view('admin.contact-info.add_contact_info');

    }

    public function editContactInfo(Request $request, $column_name, $id)
    {

        if($request->isMethod('post')){

            $data = $request->all();

            $item = '';            

            if ($column_name == 'address') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'address'=>$data['address']
                ]);

                $item = 'Address';
                
            } elseif ($column_name == 'phone_1') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'phone_1'=>$data['phone_1']
                ]);

                $item = 'Phone 1';
                
            } elseif ($column_name == 'phone_2') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'phone_2'=>$data['phone_2']
                ]);

                $item = 'Phone 2';
                
            } elseif ($column_name == 'email') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'email'=>$data['email']
                ]);

                $item = 'E-mail';
                
            } elseif ($column_name == 'google-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'google'=>$data['google']
                ]);

                $item = 'Google Link';
                
            } elseif ($column_name == 'linkedin-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'linkedin'=>$data['linkedin']
                ]);

                $item = 'Linkedin Link';
                
            } elseif ($column_name == 'twitter-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'twitter'=>$data['twitter']
                ]);

                $item = 'Twitter Link';
                
            } elseif ($column_name == 'facebook-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'facebook'=>$data['facebook']
                ]);

                $item = 'Facebook Link';
                
            } elseif ($column_name == 'instagram-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'instagram'=>$data['instagram']
                ]);

                $item = 'Instagram Link';
                
            } elseif ($column_name == 'youtube-link') {

                ContactInfo::where(['id'=>$id])->update
                ([
                    'youtube'=>$data['youtube']
                ]);

                $item = 'Youtube Link';
                
            }

            return redirect('/admin/view-contact-info-item')->with('flash_message_success', $item.' Updated Successfully!');
        }

        $contact_info =ContactInfo::where(['id'=>$id])->first();

        return view('admin.contact-info.edit_contact_info')->with(compact('contact_info', 'column_name'));

    }

    public function deleteContactInfo($column_name, $id = null)
    {

        $item = '';            

        if ($column_name == 'address') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'address'=>''
            ]);

            $item = 'Address';
            
        } elseif ($column_name == 'phone_1') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'phone_1'=>''
            ]);

            $item = 'Phone 1';
            
        } elseif ($column_name == 'phone_2') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'phone_2'=>''
            ]);

            $item = 'Phone 2';
            
        } elseif ($column_name == 'email') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'email'=>''
            ]);

            $item = 'E-mail';
            
        } elseif ($column_name == 'google-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'google'=>''
            ]);

            $item = 'Google Link';
            
        } elseif ($column_name == 'linkedin-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'linkedin'=>''
            ]);

            $item = 'Linkedin Link';
            
        } elseif ($column_name == 'twitter-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'twitter'=>''
            ]);

            $item = 'Twitter Link';
            
        } elseif ($column_name == 'facebook-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'facebook'=>''
            ]);

            $item = 'Facebook Link';
            
        } elseif ($column_name == 'instagram-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'instagram'=>''
            ]);

            $item = 'Instagram Link';
            
        } elseif ($column_name == 'youtube-link') {

            ContactInfo::where(['id'=>$id])->update
            ([
                'youtube'=>''
            ]);

            $item = 'Youtube Link';
            
        }

        return redirect('/admin/view-contact-info-item')->with('flash_message_success', $item.' Deleted Successfully!');

    }
}
