@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Reviews</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Reviews</li>
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
		
		<div class="row dashboard-info reviewpage-content">
			<div class="col-lg-6">
			   <div class="card dash-cards">
				    <div class="card-header">
					    <h4>Visitor Review <small>({{ $count_visitors_reviews }})</small></h4>
						<div class="card-dropdown">
						    <!-- <ul>
							    <li class="nav-item dropdown has-arrow logged-item">
									<a href="#" class="dropdown-toggle pageviews-link" data-bs-toggle="dropdown" aria-expanded="false">
										<span>All Listing</span>
									</a>
									<div class="dropdown-menu dropdown-menu-end">
										<a class="dropdown-item" href="javascript:void(0)">Next Week</a>
										<a class="dropdown-item" href="javascript:void(0)">Last Month</a>
										<a class="dropdown-item" href="javascript:void(0)">Next Month</a>
									</div>
								</li>
							</ul> -->
						</div>								
					</div>	
                    <div class="card-body">
					    <ul class="review-list">
					    	@foreach($visitor_reviews as $visitor)
							    <li class="review-box">
									<div class="review-profile">
										<div class="review-img">
											<img src="{{ asset('frontend/img/profiles/'.$visitor->from_user_image ) }}" class="img-fluid" alt="img">
										</div>															
									</div>
									<div class="review-details">
								        <h6>{{ $visitor->from_user_name }}</h6>
										<div class="rating">
	                                        <div class="rating-star">
	                                        	@for($f=1; $f <= 5; $f++)
                                                    @if($f <= $visitor->rating)
                                                        <i class="fas fa-star filled"></i>
                                                    @else
                                                        <i class="fa-regular fa-star filled"></i>
                                                    @endif
                                                @endfor											   
											</div>							        
											<div><i class="fa-sharp fa-solid fa-calendar-days"></i> {{ $visitor->date_time }}</div>
										</div>		
	                                    <p>{{ $visitor->review }}</p>	
	                                    <a href="{{ url('/listing-details/'.$visitor->post_ads_id.'/'.str_replace(' ', '-', strtolower($visitor->ad_title)) ) }}" class="btn btn-view">View Ad</a>
									</div>
								</li>	
							@endforeach
						</ul>								
					</div>							
				</div>						 
			</div>
			<div class="col-lg-6">
				<div class="card dash-cards">
				    <div class="card-header">
					    <h4>Your Review <small>({{ $count_your_reviews }})</small></h4>
					    <div class="card-dropdown">
						    <!-- <ul>
							    <li class="nav-item dropdown has-arrow logged-item">
									<a href="#" class="dropdown-toggle pageviews-link" data-bs-toggle="dropdown" aria-expanded="false">
										<span>All Listing</span>
									</a>
									<div class="dropdown-menu dropdown-menu-end">
										<a class="dropdown-item" href="javascript:void(0)">Next Week</a>
										<a class="dropdown-item" href="javascript:void(0)">Last Month</a>
										<a class="dropdown-item" href="javascript:void(0)">Next Month</a>
									</div>
								</li>
							</ul> -->
						</div>																	
					</div>	
                    <div class="card-body">
				        <ul class="review-list">
				        	@foreach($your_reviews as $your)
							    <li class="review-box">
									<div class="review-profile">
										<div class="review-img">
											<img src="{{ asset('frontend/img/profiles/'.$your->from_user_image ) }}" class="img-fluid" alt="img">
										</div>															
									</div>
									<div class="review-details">
								        <h6>{{ $your->from_user_name }}</h6>
										<div class="rating">
	                                        <div class="rating-star">
											    @for($r=1; $r <= 5; $r++)
	                                                @if($r <= $your->rating)
	                                                    <i class="fas fa-star filled"></i>
	                                                @else
	                                                    <i class="fa-regular fa-star filled"></i>
	                                                @endif
	                                            @endfor	
											</div>							        
											<div><i class="fa-sharp fa-solid fa-calendar-days"></i> {{ $your->date_time }}</div>
										</div>		
	                                    <p>{{ $your->review }}</p>		
	                                    <a href="{{ url('/listing-details/'.$your->post_ads_id.'/'.str_replace(' ', '-', strtolower($your->ad_title)) ) }}" class="btn btn-view">View Ad</a>
									</div>
								</li>	
							@endforeach
						</ul>								
					</div>								
				 </div>							 
			 </div>					
		</div> 
	</div>		
</div>		
<!-- /Profile Content -->

@endsection