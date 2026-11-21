@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Edit Listing</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Edit Listing</li>
					</ol>
				</nav>							
			</div>
		</div>
	</div>
</div>
<!-- /Breadscrumb Section -->

<!-- Profile Content -->
<div class="dashboard-content listing-section ">		
	<div class="container">
		
		@include('frontend.user.user-profile-bar')
		
		<div class="profile-content">
		    <div class="messages-form"> 
		    	<form class="form-horizontal mt-3" enctype="multipart/form-data" method="post" action="{{url('/user/edit-listing/'.$listing->id )}}">
                    {{ csrf_field() }}
            		<div class="card">
					    <div class="card-header">
						    <h4>Basic information</h4>							
						</div>
	                    <div class="card-body">
	                    	<div class="row">
	                    		<div class="col-lg-6 col-md-6 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label label-heading">Type <span>*</span></label>
									   	<select class="form-control select" name="ad_type" id="ad_type" required>
									   		@if($listing->ad_type == 'Carring')
												<option value="Carring" selected>Carring By You</option>
												<option value="Send">Send By You</option>
											@elseif($listing->ad_type == 'Send')
												<option value="Carring">Carring By You</option>
												<option value="Send" selected>Send By You</option>
											@endif
										</select>														
									</div>
	                    		</div>
	                    		<div class="col-lg-6 col-md-6 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label label-heading">Category <span>*</span></label>
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
									
						    <div class="form-group">
							    <label class="col-form-label label-heading">Listing Title <span>*</span></label>
								<input type="text" class="form-control pass-input" name="title" placeholder="Title" value="{{ $listing->title }}" required>	
							</div>
							<div class="form-group formlast-input">
							    <label class="col-form-label label-heading">Description <span>*</span></label>	
								<textarea rows="2" class="form-control tagline" id="texteditor" name="description" placeholder="">{{ $listing->description }}</textarea>									   
							</div>					
						</div>   							
					</div>	
            		<div class="card">
					    <div class="card-header">
						    <h4>Location information</h4>							
						</div>
	                    <div class="card-body">
						    <label class="col-form-label label-heading">From</label>	
						    <div class="row">
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">Country <span>*</span></label>
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
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">City <span>*</span></label>								    
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
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">Area</label>
										<input type="text" class="form-control pass-input" name="from_area" placeholder="Area" value="{{ $listing->from_area }}">
                                        <label>From Address</label>
                                        <input type="text" class="form-control pass-input" name="from_address" maxlength="255" placeholder="Address" value="{{ old('from_address', $listing->from_address ?? null) }}">
									</div>
	                    		</div>
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">Travel On <span>*</span></label>
										<input type="date" class="form-control pass-input" name="departure_on" value="{{ $listing->departure_on }}" placeholder="Title" required>
									</div>
	                    		</div>
	                    	</div>											    
									
																
							<label class="col-form-label label-heading">To</label>
							<div class="row">
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">Country <span>*</span></label>
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
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">City <span>*</span></label>								    
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
	                    		<div class="col-lg-3 col-md-3 col-sm-12">
	                    			<div class="form-group">
									    <label class="col-form-label">Area</label>
										<input type="text" class="form-control pass-input" name="to_area" placeholder="Area" value="{{ $listing->to_area }}">
                                        <label>To Address</label>
                                        <input type="text" class="form-control pass-input" name="to_address" maxlength="255" placeholder="Address" value="{{ old('to_address', $listing->to_address ?? null) }}">
									</div>
	                    		</div>	
		                    	<div class="col-lg-3 col-md-3 col-sm-12">
									<div class="form-group">
									    <label class="col-form-label">Reach On <span>*</span></label>
										<input type="date" class="form-control pass-input" name="reached_on" value="{{ $listing->reached_on }}" placeholder="Title" required>
									</div>	
								</div>	
	                    	</div>
						</div>
					</div>
					    				
			        <div class="row">
			        	<div class="col-lg-8 col-md-8 col-sm-12">
			        		<div class="card">
							    <div class="card-header">
								    <h4>Weight Information <small>(Carring/Send)</small></h4>							
								</div>
			                    <div class="card-body">
			                    	<div class="row">
			                    		<div class="col-lg-4 col-md-4 col-sm-12">
							        		<div class="form-group">
											    <label class="col-form-label">Weight Limit (<strong>KG</strong>)</label>	
												<input type="number" class="form-control pass-input" name="weight" value="{{ $listing->weight }}" placeholder="Title">
											</div>
							        	</div>
			                    		<div class="col-lg-4 col-md-4 col-sm-12">
							        		<div class="form-group">
											    <label class="col-form-label">Price per (<strong>KG</strong>)</label>								    
												<input type="number" class="form-control pass-input" name="weight_price" value="{{ $listing->weight_price }}" placeholder="Title">	
											</div>
							        	</div>
			                    		<div class="col-lg-4 col-md-4 col-sm-12">
							        		<div class="form-group">
											    <label class="col-form-label">Currency</label>								    
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
			                    	</div>			
								</div>
							</div>	
			        	</div>
			        	<div class="col-lg-4 col-md-4 col-sm-12">
			        		<div class="card">
							    <div class="card-header">
								    <h4>Show Contact Information</h4>							
								</div>
			                    <div class="card-body">
									<div class="form-group">
									    <label class="col-form-label">Show Your Phone and Email to Users <span>*</span></label>	
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
			        	</div>
			        </div>		
			           
			        <div class="row">
			        	<div class="col-lg-8 col-md-8 col-sm-12">   		
			                <div class="card media-section">
							    <div class="card-header">
								    <h4>Media Information </h4>							
								</div>
			                    <div class="card-body">
								    <div class="row">
										<div class="col-lg-6 col-md-6 featured-img1">
										    <h6 class="media-title">Featured Image <span>*</span></h6>
											<div class="media-image ">
												<img src="{{ asset('frontend/img/listing/'.$listing->image ) }}" alt="{{ $listing->title }}">
											</div>
											<div class="settings-upload-btn">
												<input type="file" accept="image/*" name="image" class="hide-input image-upload" id="file">
												<input type="hidden" name="cur_image" value="{{ $listing->image }}">
												<label for="file" class="file-upload">Upload File</label>	
											</div>
										</div>											
									</div>							
								</div>
							</div>
			        	</div>
			        	<div class="col-lg-4 col-md-4 col-sm-12">
			        		<div class="card">
								<div class="card-header">
									<h4>Status</h4>
								</div>
								<div class="card-body">
									<div class="form-group">
									    <label class="col-form-label">The Listing Status is <span>*</span></label>	
										<select class="form-control select" name="isActive" required>
											@if($listing->isActive == 0)
												<option value="0" selected disabled>Pending</option>
											@elseif($listing->isActive == 1)
												<option value="1" selected>Published</option>
												<option value="2">Time Out</option>
											@elseif($listing->isActive == 2)
												<option value="1">Published</option>
												<option value="2" selected>Time Out</option>
											@endif
										</select>									   
									</div>		
								</div>
							</div>
			        	</div>
			        </div>  	

			        <div class="d-flex justify-content-center row">
			        	<div class="col-lg-4 col-md-4 col-sm-12">
			        		<button class="btn btn-primary btn-block" style="width: 100% !important;" type="submit"> Edit</button>
			        	</div>
			        </div>
	                
	            </form>
			</div>
		</div>
	</div>		
</div>		
<!-- /Profile Content -->

@endsection