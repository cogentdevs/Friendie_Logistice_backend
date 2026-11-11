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
            <h5>Cities</h5>
            <div class="list-btn">
                <ul>
                    <li>
                        <a class="btn-filters active" href="{{ url('/admin/view-cities') }}"><i class="fe fe-list"></i> </a>
                    </li>
                    <li>
                        <a class="btn btn-primary" href="{{ url('/admin/add-city') }}"><i class="fa fa-plus me-2"></i>Add City </a>
                    </li>
                </ul>
            </div>
        </div>
        <!-- Search Input -->
        <div class="row">
            <div class="col-md-4">
                <input type="text" id="searchCity" class="form-control" placeholder="Search City...">
            </div>
        </div>
        <div class="row">
            <div class="col-12 ">
                <div class="table-resposnive table-div">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Country</th>
                                <th>City</th>
                                <th>Short Name</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="cityTable">
                            <?php $i = 0; ?>
                            @foreach($cities as $data)
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $data->countryName }}</td>
                                    <td>{{ $data->name }}</td>
                                    <td>{{ $data->short_name }}</td>
                                    <td>
                                        @if($data->isActive == 1)
                                            Active
                                        @else
                                            Not Active
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions d-flex">
                                            <a class="delete-table me-2" href="{{ url('/admin/edit-city/'.$data->id) }}" >
                                               <img src="{{ asset('backend/img/icons/edit.svg') }}" alt="svg">
                                            </a>
                                            <a class="delete-table sa-confirm-delete" href="javascript:void(0);" param-id="{{ $data->id }}" param-route="delete-city">
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
