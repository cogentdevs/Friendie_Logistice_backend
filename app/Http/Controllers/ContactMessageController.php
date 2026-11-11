<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\ContactMessage;
use Session;
use App\Mail\ContactMessageMail;
use Mail;

class ContactMessageController extends Controller
{
    // Admin Side Functions Start Here

    public function viewContactMessage(){

        $contact_messages = DB::table('contact_messages')->orderBy('id', 'desc')->get();

        return view('admin.contact-message.view_contact_messages')->with(compact('contact_messages'));

    }

    public function deleteContactMessage($id = null){

        ContactMessage::where(['id'=>$id])->delete();

        return redirect()->back()->with('flash_message_success','Contact Message deleted Successfully!');

    }

    // Admin Side Functions End Here
    // ===============================================================================================
    // End User Side Functions Start Here

    public function contact(Request $request){

        if($request->isMethod('post')){

            $data = $request->all();

            $request->validate([
                'g-recaptcha-response' => 'required|captcha'
            ]);

            $contact = new ContactMessage();
            $contact->name = $data['name'];
            $contact->email = $data['email'];
            $contact->phone = $data['phone'];
            $contact->subject = $data['subject'];
            $contact->message = $data['message'];
            $contact->date = date('Y-m-d');
            $contact->save();

            $mail_data = array(
                'name'      =>  $request->name,
                'email'      =>  $request->email,
                'phone'      =>  $request->phone,
                'subject'      =>  $request->subject,
                'message'      =>  $request->message,
            );
            // dd($data);

            Mail::to('wiinworld@gmail.com')->send(new ContactMessageMail($mail_data));

            return redirect('/contact')->with('flash_message_success','Your Message Send Successfully!');

        }

        return view('frontend.contact');

    }
}
