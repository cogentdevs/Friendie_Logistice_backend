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
                    <h5>Add Meta Tags</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/add-meta-tags') }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Title</label>
                                    <select class="select" required name="title">
                                        <option selected="" disabled >Select</option>
                                        <option value="Friendie Logistics" >Home</option>
                                        <option value="Listing" >Listing</option>
                                        <option value="About" >About</option>
                                        <option value="Contact" >Contact</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Keywords</label>
                                    <input type="text" id="keywords" name="keywords" class="form-control" placeholder="webapp, app, php, laravel, mysql," required="">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="control-label">Description</label>
                                    <textarea id="texteditor" name="description" class="form-control"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn-path">                                    
                            <a href="{{ url('/admin/view-meta-tags')}}" class="btn btn-cancel me-3">Cancel</a>
                            <button type="submit" class="btn btn-submit "> Add</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

@endsection
