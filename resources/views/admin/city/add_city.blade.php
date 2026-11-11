@extends('layouts.adminLayout.admin-design')
@section('content')

<div class="page-wrapper">
    <div class="content">
        <div class="row">
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
            <div class="col-lg-12 col-sm-12 m-auto">
                <div class="content-page-header">
                    <h5>Add City</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/add-city') }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Country</label>
                                    <select class="select form-control" required name="country_id" id="country_id" style="width: 100%;">
                                        <option>Select Country</option>
                                        @foreach($countries as $country)
                                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="city_wrapper">
                            <div class="row">
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" class="form-control" name="name[]" placeholder="Name" required>
                                    </div>
                                </div>
                                <div class="col-lg-3">
                                    <div class="form-group">
                                        <label>Short Name</label>
                                        <input type="text" class="form-control" name="short_name[]" placeholder="KHI">
                                    </div>
                                </div>

                                <button type="button" style="margin-top: 27px; width: 70px; height: 42px;" id="add_city_fields" class=" btn btn-submit text-white "> <i class="fa fa-plus" ></i></button>
                            </div>
                        </div>
                        
                        <hr>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn-path">                                    
                            <a href="{{ url('/admin/view-cities')}}" class="btn btn-cancel me-3">Cancel</a>
                            <button type="submit" class="btn btn-submit "> Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

@endsection
