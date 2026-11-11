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
                    <h5>Edit Country</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/edit-country/'.$country->id) }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="Name" value="{{ $country->name }}" required>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Short Name</label>
                                    <input type="text" class="form-control" name="short_name" placeholder="PAK" value="{{ $country->short_name }}" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <input type="text" class="form-control" name="currency_name" placeholder="PKR" value="{{ $country->currency_name }}" required>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="form-group">
                                    <label>Code</label>
                                    <input type="text" class="form-control" name="code" value="{{ $country->code }}" placeholder="+65" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="upload-div mb-0">
                                    <div class="upload-sets">
                                        <div class="upload-sets-btn form-group">
                                            <label>Image</label>
                                            <input type="file" id="imgInp" class="form-control" name="image">
                                            <input type="hidden" name="cur_image" value="{{ $country->image }}">
                                            <a href="javascript:void(0);" class="btn btn-upload">Upload File</a>
                                        </div>
                                    </div>
                                    <div class="upload-img">
                                        <div class="upload-imgset"> 
                                            <img src="{{ asset('backend/img/flags/'.$country->image) }}" alt="img" id="blah">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Is Active</label>
                                    <ul class="custom-radiosbtn">
                                        @if($country->isActive == 1)
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
