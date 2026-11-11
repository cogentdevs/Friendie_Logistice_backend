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
            <h5>Contact Messages</h5>
            <div class="list-btn">
                <ul>
                    <li>
                        <a class="btn-filters active" href="{{ url('/admin/view-contact-messages') }}"><i class="fe fe-list"></i> </a>
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
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Subject</th>
                                <th>Message</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; ?>
                            @foreach($contact_messages as $data)
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $data->name }}</td>
                                    <td>{{ $data->email }}</td>
                                    <td>{{ $data->phone }}</td>
                                    <td>{{ $data->subject }}</td>
                                    <td>{{ $data->message }}</td>
                                    <td>{{ $data->date }}</td>
                                    <td>
                                        <div class="table-actions d-flex">
                                            <a class="delete-table sa-confirm-delete" href="javascript:void(0);" param-id="{{ $data->id }}" param-route="delete-contact-message">
                                                <img src="{{ asset('backend/img/icons/delete.svg') }}" alt="svg">
                                            </a>
                                        </div>
                                    </td>
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
