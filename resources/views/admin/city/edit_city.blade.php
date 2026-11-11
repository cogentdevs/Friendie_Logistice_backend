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
                    <h5>Edit City</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/edit-city/'.$city->id) }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Country</label>
                                    <select class="select form-control" required name="country_id" id="country_id">
                                        <option>Select Country</option>
                                        @foreach($countries as $country)
                                            @if($country->id == $city->country_id)
                                                <option value="{{ $country->id }}" selected>{{ $country->name }}</option>
                                            @else
                                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="Name" value="{{ $city->name }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Short Name</label>
                                    <input type="text" class="form-control" name="short_name" placeholder="KHI" value="{{ $city->short_name }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Is Active</label>
                                    <ul class="custom-radiosbtn">
                                        @if($city->isActive == 1)
                                            <li>
                                                <label class="radiossets">
                                                    Yes
                                                    <input type="radio" checked="checked" value="1" name="isActive">
                                                    <span class="checkmark-radio"></span>
                                                </label>
                                            </li>
                                            <li>
                                                <label class="radiossets">
                                                    No
                                                    <input type="radio" value="0" name="isActive">
                                                    <span class="checkmark-radio"></span>
                                                </label>
                                            </li>
                                        @else
                                            <li>
                                                <label class="radiossets">
                                                    Yes
                                                    <input type="radio" value="1" name="isActive">
                                                    <span class="checkmark-radio"></span>
                                                </label>
                                            </li>
                                            <li>
                                                <label class="radiossets">
                                                    No
                                                    <input type="radio" checked="checked" value="0" name="isActive">
                                                    <span class="checkmark-radio"></span>
                                                </label>
                                            </li>
                                        @endif
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <hr>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn-path">                                    
                            <a href="{{ url('/admin/view-countries')}}" class="btn btn-cancel me-3">Cancel</a>
                            <button type="submit" class="btn btn-submit "> Edit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

@endsection
