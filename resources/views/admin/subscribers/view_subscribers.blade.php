@extends('layouts.adminLayout.admin-design')
@section('content')

<div class="page-wrapper page-settings">
    <div class="content">
        @if(Session::has('flash_message_error'))
            <div class="alert alert-danger alert-block alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_error') !!}</strong>
            </div>
        @endif
        @if(Session::has('flash_message_success'))
            <div class="alert alert-success alert-block alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_success') !!}</strong>
            </div>
        @endif
        <div class="content-page-header content-page-headersplit mb-0">
            <h5>Countries</h5>
            <div class="list-btn">
                <ul>
                    <li>
                        <a class="btn-filters active" href="{{ url('/admin/view-subscribers') }}"><i class="fe fe-list"></i> </a>
                    </li>
                    <li>
                        <a class="btn btn-primary" href="{{ url('/admin/export-subscribers/xlsx') }}"><i class="fa fa-download me-2"></i>Export In Excel</a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="row">
            <div class="col-12 ">
                <div class="table-resposnive table-div">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>E-mail</th>
                                <th>Subscribe On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; ?>
                            @foreach($subscribes as $data)
                            <?php
                                $date= $data->created_at;
                                $formated_date = strtotime($date);   
                                $created_at = date("d-M-Y H:i A", $formated_date);
                            ?>
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $data->email }}</td>
                                    <td>{{ $created_at }}</td>
                                </tr>
                            @endforeach                         
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div> 

@endsection
