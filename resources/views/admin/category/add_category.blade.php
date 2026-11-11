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
                    <h5>Add Category</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/add-category') }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Category Name</label>
                                    <input type="text" class="form-control" name="name" placeholder="Category Name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="upload-div mb-0">
                                    <div class="upload-sets">
                                        <div class="upload-sets-btn form-group">
                                            <label>Image</label>
                                            <input type="file" id="imgInp" class="form-control" name="image" required>
                                            <a href="javascript:void(0);" class="btn btn-upload">Upload File</a>
                                        </div>
                                    </div>
                                    <div class="upload-img">
                                        <div class="upload-imgset"> 
                                            <img src="{{ asset('backend/img/icons/gallery.svg') }}" alt="img" id="blah">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn-path">                                    
                            <a href="{{ url('/admin/view-categories')}}" class="btn btn-cancel me-3">Cancel</a>
                            <button type="submit" class="btn btn-submit "> Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

@endsection
