@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Dashboard</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Dashboard</li>
					</ol>
				</nav>							
			</div>
		</div>
	</div>
</div>
<!-- /Breadscrumb Section -->

<!-- Dashboard Content -->
<div class="dashboard-content listing-section">		
	<div class="container">

		@include('frontend.user.user-profile-bar')

		<div class="dashboard-details">
		    <div class="row">
			    <div class="col-lg-4 col-md-4">
				    <div class="card dash-cards">
				    	<a href="{{ url('user/my-listing') }}">
						     <div class="card-body">
								<div class="dash-top-content">
									<div class="dashcard-img">
										<img src="{{ asset('frontend/img/icons/verified.svg') }}" class="img-fluid" alt="">
									</div>
								</div>
								<div class="dash-widget-info">
									<h6>Active Listing</h6>
									<h3 class="counter">{{ $count_active_listing }}</h3>
								</div>
							</div>	
						</a>								
					</div>
				</div>
				 <div class="col-lg-4 col-md-4">
				    <div class="card dash-cards">
				    	<a href="{{ url('user/reviews') }}">
						    <div class="card-body">
								<div class="dash-top-content">
									<div class="dashcard-img">
										<img src="{{ asset('frontend/img/icons/rating.svg') }}" class="img-fluid" alt="">
									</div>
								</div>
								<div class="dash-widget-info">
									<h6>Total Reviews</h6>
									<h3>{{ $count_visitors_reviews }}</h3>
								</div>
							</div>	
						</a>								
					</div>
				</div>
				 <div class="col-lg-4 col-md-4">
				    <div class="card dash-cards">
				    	<a href="{{ url('user/messages') }}">
						     <div class="card-body">
								<div class="dash-top-content">
									<div class="dashcard-img">
										<img src="{{ asset('frontend/img/icons/chat.svg') }}" class="img-fluid" alt="">
									</div>
								</div>
								<div class="dash-widget-info">
									<h6>New Messages</h6>
									<h3>{{ $unread_msg_counter }}</h3>
								</div>
							</div>	
						</a>								
					</div>
				</div>
				 <!-- <div class="col-lg-3 col-md-3">
				    <div class="card dash-cards">
					     <div class="card-body">
							<div class="dash-top-content">
								<div class="dashcard-img">
									<img src="{{ asset('frontend/img/icons/bookmark.svg') }}" class="img-fluid" alt="">
								</div>
							</div>
							<div class="dash-widget-info">
								<h6>Bookmark</h6>
								<h3>30</h3>
							</div>
						</div>									
					</div>
				</div> -->
			</div>	
            <div class="row dashboard-info">
			     <!-- <div class="col-lg-6 d-flex">
				    <div class="card dash-cards w-100">
					    <div class="card-header">
						    <h4>Page Views</h4>
							<div class="card-dropdown">
							    <ul>
								    <li class="nav-item dropdown has-arrow logged-item">
										<a href="#" class="dropdown-toggle pageviews-link" data-bs-toggle="dropdown" aria-expanded="false">
											<span>This week</span>
										</a>
										<div class="dropdown-menu dropdown-menu-end">
											<a class="dropdown-item" href="javascript:void();">Next Week</a>
											<a class="dropdown-item" href="javascript:void()">Last Month</a>
											<a class="dropdown-item" href="javascript:void()">Next Month</a>
										</div>
									</li>
								</ul>
							</div>								
						</div>	
                        <div class="card-body">
						    <div id="review-chart"></div>								
						</div>							
					</div>						 
				 </div> -->
				 <div class="col-lg-12 d-flex">
				    <div class="card dash-cards w-100">
					    <div class="card-header">
						    <h4>Visitor Review</h4>
							<div class="card-dropdown">
							    <ul>
								    <!-- <li class="nav-item dropdown has-arrow logged-item">
										<a href="#" class="dropdown-toggle pageviews-link" data-bs-toggle="dropdown" aria-expanded="false">
											<span>All Listing</span>
										</a>
										<div class="dropdown-menu dropdown-menu-end">
											<a class="dropdown-item" href="javascript:void(0)">Next Week</a>
											<a class="dropdown-item" href="javascript:void(0)">Last Month</a>
											<a class="dropdown-item" href="javascript:void(0)">Next Month</a>
										</div>
									</li> -->
								</ul>
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
			</div> 					
		</div>				
	</div>				
</div>					
<!-- /Dashboard Content -->	

@endsection