<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\Subscribe;

class ExportSubscribers implements FromCollection,WithHeadings,ShouldAutoSize
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $subscribers = DB::table('subscribes')->select('email', 'created_at')->orderBy('id', 'desc')->get();

        return $subscribers;
    }

    public function headings(): array
    {
        return [
            'E-mail',
            'Subscribe On',
        ];
    }
}
