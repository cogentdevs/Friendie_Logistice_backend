@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Bookmarks</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Add Bookmarks</li>
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
		
		<div class="bookmarks-content grid-view featured-slider">
		    <div class="row">

			    <div class="col-lg-4 col-md-4 col-sm-6 ">
					<div class="card aos aos-init aos-animate" data-aos="fade-up">
		    			<div class="blog-widget">
							<div class="blog-img">
								<a href="service-details.html">
									<img src="assets/img/list/listgrid-1.jpg" class="img-fluid" alt="blog-img">
								</a>
			    				<div class="fav-item">
									<span class="featured-text">Featured</span>
										<a href="javascript:void(0)" class="fav-icon">
					    					<i class="feather-heart"></i>
				    					</a>										
								</div>	
							</div>
							<div class="bloglist-content">
								<div class="card-body">
									<div class="blogfeaturelink">
									    <div class="grid-author">
										<img  src="assets/img/profiles/avatar-02.jpg" alt="author">	
										</div>
										<div class="blog-features">
										   <a href="javascript:void(0)"><span> <i class="fa-regular fa-circle-stop"></i> Vehicles</span></a>
										</div>																	  
										<div class="blog-author text-end"> 
											<span>  <img src="assets/img/eye.svg" alt="electronics">4000  </span>
										</div>
									</div> 
									<h6><a href="service-details.html">2017 Gulfsteam Ameri-lite</a></h6>																	 
									<div class="blog-location-details">
										<div class="location-info">
										   <i class="feather-map-pin"></i> Los Angeles
										</div>
										<div class="location-info">
											<i class="fa-solid fa-calendar-days"></i> 06 Oct, 2022
										</div>
									</div>
									<div class="amount-details">
										<div class="amount">
											<span class="validrate">$350</span>
											<span>$450</span>
										</div>
										<div class="ratings">
											<span>4.7</span> (50)
										</div>												
									</div>	
								</div>	
							</div>			 
						</div> 
					</div>
				</div>
				
				 <!--Pagination--> 
				<!-- <div class="blog-pagination">
				    <nav>
					    <ul class="pagination">
						    <li class="page-item previtem">
								<a class="page-link" href="#"><i class="fas fa-regular fa-arrow-left"></i> Prev</a>
							</li>
							<li class="justify-content-center pagination-center"> 
								<div class="pagelink">
								    <ul>
										<li class="page-item">
											<a class="page-link" href="#">1</a>
										</li>
										<li class="page-item active">
											<a class="page-link" href="#">2 <span class="visually-hidden">(current)</span></a>
										</li>
										<li class="page-item">
											<a class="page-link" href="#">3</a>
									   </li>
									   <li class="page-item">
											<a class="page-link" href="#">...</a>
									   </li>
									   <li class="page-item">
											<a class="page-link" href="#">14</a>
									   </li>
							       </ul>
							    </div>													
							</li>													
							<li class="page-item nextlink">
								<a class="page-link" href="#">Next <i class="fas fa-regular fa-arrow-right"></i></a>
							</li>
						</ul>
					</nav>
				</div> -->
				<!--/Pagination-->	
				
			</div>						
		</div>
	</div>		
</div>		
<!-- /Profile Content -->

@endsection