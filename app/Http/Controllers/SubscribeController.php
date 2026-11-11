<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Subscribe;
use App\Exports\ExportSubscribers;
use Maatwebsite\Excel\Facades\Excel;

class SubscribeController extends Controller
{
    public function viewSubscribers(){

        $subscribes = DB::table('subscribes')->orderBy('.id', 'desc')->get();

        return view('admin.subscribers.view_subscribers')->with(compact('subscribes'));

    }

    public function exportSubscribers() {

        return Excel::download(new ExportSubscribers, 'Subscribers.xlsx');

    }
}
