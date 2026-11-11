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
                    <h5>Edit Listing</h5>
                </div>
                <form enctype="multipart/form-data" class="form-horizontal" method="post" action="{{ url('/admin/edit-listing/'.$listing->id) }}" > 
                    {{ csrf_field() }}
                    <div class="form-body">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Type <span>*</span></label>
                                            <select class="form-control select" name="ad_type" id="ad_type" required>
                                                @if($listing->ad_type == 'Carring')
                                                    <option value="Carring" selected>Carring By You</option>
                                                    <option value="Ssend">Send By You</option>
                                                @elseif($listing->ad_type == 'Send')
                                                    <option value="Carring">Carring By You</option>
                                                    <option value="Send" selected>Send By You</option>
                                                @endif
                                            </select>                                                       
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label >Category <span>*</span></label>
                                            <select class="form-control select" name="category_id" id="country_id" required>
                                                <option selected disabled>Select</option>
                                                @foreach($categories as $category)
                                                    @if($category->id == $listing->category_id)
                                                        <option value="{{ $category->id }}" selected>{{ $category->name }}</option>
                                                    @else)
                                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                                       
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>Title <span>*</span></label>
                                            <input type="text" class="form-control" name="title" placeholder="listing Name" value="{{ $listing->title }}" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>Description <span>*</span></label>  
                                            <textarea rows="2" class="form-control tagline" id="texteditor" name="description" placeholder="">{{ $listing->description }}</textarea>                                       
                                        </div>  
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Is Active</label>
                                            <ul class="custom-radiosbtn">
                                                @if($listing->isActive == 0)
                                                    <li>
                                                        <label class="radiossets">
                                                            Pending
                                                            <input type="radio" checked="checked" value="0" name="isActive">
                                                            <span class="checkmark-radio"></span>
                                                        </label>
                                                    </li>
                                                    <li>
                                                        <label class="radiossets">
                                                            Active
                                                            <input type="radio" value="1" name="isActive">
                                                            <span class="checkmark-radio"></span>
                                                        </label>
                                                    </li>
                                                    <li>
                                                        <label class="radiossets">
                                                            Not Active
                                                            <input type="radio" value="2" name="isActive">
                                                            <span class="checkmark-radio"></span>
                                                        </label>
                                                    </li>
                                                @elseif($listing->isActive == 1)
                                                    <li>
                                                        <label class="radiossets">
                                                            Active
                                                            <input type="radio" value="1" name="isActive">
                                                            <span class="checkmark-radio"></span>
                                                        </label>
                                                    </li>
                                                    <li>
                                                        <label class="radiossets">
                                                            Not Active
                                                            <input type="radio" value="2" name="isActive">
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
                                                            Not Active
                                                            <input type="radio" checked="checked" value="2" name="isActive">
                                                            <span class="checkmark-radio"></span>
                                                        </label>
                                                    </li>
                                                @endif
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">  
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>From Country <span>*</span></label>
                                            <select class="form-control select" name="from_country_id" id="from_country_id" required>
                                                <option selected disabled>Select</option>
                                                @foreach($countries as $country)
                                                    @if($country->id == $listing->from_country_id)
                                                        <option value="{{ $country->id }}" selected>{{ $country->name }}</option>
                                                    @else)
                                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                      
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>From City <span>*</span></label>                                   
                                            <select class="form-control select" name="from_city_id" id="from_city_id" required>
                                                <option selected disabled>Select</option>
                                                @foreach($from_city_list as $city)
                                                    @if($city->id == $listing->from_city_id)
                                                        <option value="{{ $city->id }}" selected>{{ $city->name }}</option>
                                                    @else)
                                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                      
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>From Area</label>
                                            <input type="text" class="form-control pass-input" name="from_area" value="{{ $listing->from_area }}" placeholder="Area">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Travel On <span>*</span></label>
                                            <input type="date" class="form-control pass-input" name="departure_on" value="{{ $listing->departure_on }}" placeholder="Title" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>To Country <span>*</span></label>
                                            <select class="form-control select" name="to_country_id" id="to_country_id" required>
                                                <option selected disabled>Select</option>
                                                @foreach($countries as $country)
                                                    @if($country->id == $listing->to_country_id)
                                                        <option value="{{ $country->id }}" selected>{{ $country->name }}</option>
                                                    @else)
                                                        <option value="{{ $country->id }}">{{ $country->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                      
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>To City <span>*</span></label>                                   
                                            <select class="form-control select" name="to_city_id" id="to_city_id" required>
                                                <option selected disabled>Select</option>
                                                @foreach($to_city_list as $city)
                                                    @if($city->id == $listing->to_city_id)
                                                        <option value="{{ $city->id }}" selected>{{ $city->name }}</option>
                                                    @else)
                                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                      
                                        </div>
                                    </div>    
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>To Area</label>
                                            <input type="text" class="form-control pass-input" name="to_area" value="{{ $listing->to_area }}" placeholder="Area">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Reach On <span>*</span></label>
                                            <input type="date" class="form-control pass-input" name="reached_on" value="{{ $listing->reached_on }}" placeholder="Title" required>
                                        </div>  
                                    </div>  
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Weight Limit (<strong>KG</strong>)</label>    
                                            <input type="number" class="form-control pass-input" name="weight" value="{{ $listing->weight }}" placeholder="Title">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Price per (<strong>KG</strong>)</label>                                   
                                            <input type="number" class="form-control pass-input" name="weight_price" value="{{ $listing->weight_price }}" placeholder="Title">  
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Currency</label>                                  
                                            <select class="form-control select" name="currency" id="currency" required>
                                                <option selected disabled>Select</option>
                                                @foreach($currencies as $country)
                                                    @if($country->currency_name == $listing->currency)
                                                        <option value="{{ $country->currency_name }}" selected>{{ $country->currency_name }}</option>
                                                    @else)
                                                        <option value="{{ $country->currency_name }}">{{ $country->currency_name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>                                     
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Show Contact to Users <span>*</span></label> 
                                            <select class="form-control select" name="show_contact_info" required>
                                                @if($listing->show_contact_info == 0)
                                                    <option value="0" selected>No</option>
                                                    <option value="1">Yes</option>
                                                @elseif($listing->show_contact_info == 1)
                                                    <option value="0">No</option>
                                                    <option value="1" selected>Yes</option>
                                                @endif
                                            </select>                                      
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="upload-div mb-0">
                                            <div class="upload-sets">
                                                <div class="upload-sets-btn form-group">
                                                    <label>Image <span>*</span></label>
                                                    <input type="file" id="imgInp" class="form-control" name="image">
                                                    <input type="hidden" name="cur_image" value="{{ $listing->image }}">
                                                    <a href="javascript:void(0);" class="btn btn-upload">Upload File</a>
                                                </div>
                                            </div>
                                            <div class="upload-img">
                                                <div class="upload-imgset"> 
                                                    <img src="{{ asset('frontend/img/listing/'.$listing->image) }}" alt="img" id="blah">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <hr>
                    </div>
                    <div class="col-lg-12">
                        <div class="btn-path">                                    
                            <a href="{{ url('/admin/view-listing')}}" class="btn btn-cancel me-3">Cancel</a>
                            <button type="submit" class="btn btn-submit "> Edit</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div> 

@endsection
