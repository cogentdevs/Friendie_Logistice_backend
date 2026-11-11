@extends('layouts.frontLayout.front-design')
@section('content')

<style type="text/css">
    .label-heading{
        color: black !important;
    }
</style>

<!-- Banner Section -->
<section class="banner-section">    
     <div class="banner-circle">
        <img src="{{ asset('frontend/img/bannerbg.png') }}" class="img-fluid" alt="bannercircle">
    </div>      
    <div class="container">
        <div class="home-banner">       
            <div class="row align-items-center">                        
                <div class="col-lg-7">
                    <div class="section-search aos" data-aos="fade-up">
                        <p class="explore-text"> <span>Explore top-rated attractions</span></p>
                        <img src="{{ asset('frontend/img/arrow-banner.png') }}" class="arrow-img" alt="arrow">
                        <h1>Let us help you <br>
                        <span>Find, Send</span> Or Receive</h1>
                        <p>Countrys most loved and trusted classified ad listing website.</p>
                        <!-- <div class="search-box">
                            <form class="d-flex" method="post" action="{{url('/listing/search/results')}}">
                                {{ csrf_field() }} 
                                <input type="hidden" name="search_from" value="home">
                                <div class="search-input line">                                         
                                    <div class="form-group">
                                        <select class="form-control select category-select" name="category_id" required>                        
                                            <option selected disabled>Category</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="search-input">                                          
                                    <div class="form-group">                                                
                                        <div class="group-img">
                                            <select class="form-control select region-select" name="to_city_id" required>
                                                <option selected disabled>Destination</option>
                                                @foreach($cities as $city)
                                                    <option value="{{ $city->id }}">{{ $city->name }}</option>
                                                @endforeach
                                            </select> 
                                        </div>
                                    </div>
                                </div>
                                <div class="search-btn">
                                    <button class="btn btn-primary" type="submit"> <i class="fa fa-search" aria-hidden="true"></i> Search</button>
                                </div>
                            </form>
                        </div> -->
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="banner-imgs" style="position: relative !important; /*box-shadow: 0 0 15px #b1abab;*/">
                          <img src="{{ asset('frontend/img/final.png') }}" class="img-fluid" alt="bannerimage">                          
                    </div>
                </div>
            </div>
        </div>  
    </div>
    <img src="{{ asset('frontend/img/bannerellipse.png') }}" class="img-fluid banner-elipse" alt="arrow">
    <img src="{{ asset('frontend/img/banner-arrow.png') }}" class="img-fluid bannerleftarrow" alt="arrow">
</section>
<!-- /Banner Section -->

<!-- Main Content Section -->
<div class="list-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-12 theiaStickySidebar">
                <div class="listings-sidebar">
                    <div class="card">
                        <h4><img  src="{{ asset('frontend/img/details-icon.svg') }}" alt="details-icon"> Search</h4>
                        <form method="post" action="{{url('/listing/search/results')}}">
                            {{ csrf_field() }} 
                            <input type="hidden" name="search_from" value="home">
                            <div class="row">
                                <div class="col-md-2">
                                    <div class="filter-content looking-input form-group">
                                        <label class="col-form-label label-heading">Type</label>
                                        <select class="form-control select region-select" name="ad_type">
                                            <option selected value="">Select</option>
                                            <option value="Carring">Carring</option>
                                            <option value="Send">Send</option>
                                        </select>       
                                    </div> 
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-content form-group">  
                                        <label class="col-form-label label-heading">Category</label>
                                       <select class="form-control select category-select" name="category_id">
                                            <option selected value="">Select</option>
                                            @foreach($categories as $category)
                                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-content looking-input form-group input-placeholder">
                                        <div class="group-img">
                                            <label class="col-form-label label-heading">Weight</label>
                                            <input type="number" class="form-control" name="weight" placeholder="Weight KG?">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-content form-group region"> 
                                        <label class="col-form-label label-heading">From</label>
                                        <select class="form-control select region-select" name="from_city_id">
                                            <option selected value="">Select</option>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                                            @endforeach
                                        </select>                  
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-content form-group region"> 
                                        <label class="col-form-label label-heading">Destination</label>
                                        <select class="form-control select region-select" name="to_city_id">
                                            <option selected value="">To</option>
                                            @foreach($cities as $city)
                                                <option value="{{ $city->id }}">{{ $city->name }}</option>
                                            @endforeach
                                        </select>                  
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="filter-content looking-input form-group input-placeholder">
                                        <div class="group-img">
                                            <label class="col-form-label label-heading">Travel On</label>
                                            <input type="date" class="form-control" name="departure_on" placeholder="Departure">
                                        </div>
                                    </div> 
                                </div>
                            </div>                        
                            <div class="filter-content amenities mb-0">
                                <div class="search-btn">
                                    <button class="btn btn-primary" type="submit"> <i class="fa fa-search" aria-hidden="true"></i> Search</button>
                                </div>  
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Category Section -->
<section class="category-section">
    <div class="container">
       <div class="section-heading">
         <div class="row align-items-center">
                <div class="col-md-6 aos aos-init aos-animate" data-aos="fade-up">
                    <h2>Our  <span class="title-left">Cat</span>egories</h2>
                    <p>Carry and Send anything</p>
                </div>
                <div class="col-md-6 text-md-end aos aos-init aos-animate" data-aos="fade-up">
                    <!-- <a href="categories.html" class="btn  btn-view">View All</a> -->
                </div>      
         </div>              
       </div>   
        <div class="row">
            @foreach($categories as $data)
                <div class="col-lg-2 col-md-3 col-sm-6">
                   <a href="{{ url('/listing/category/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->name)) ) }}" class="category-links">
                      <h5>{{ $data->name }}</h5>
                      <span>{{ $data->ads_count }} Ads</span>
                      <img src="{{ asset('backend/img/category/'.$data->image) }}" width="100%" alt="icons">
                   </a> 
                </div>
            @endforeach
        </div>             
    </div>      
</section>  
<!-- /Category Section -->

<!-- Featured ADS Section -->
@if($count_feature_listing > 0)
    <section class="featured-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 aos aos-init aos-animate" data-aos="fade-up">
                    <div class="section-heading">
                        <h2>Featu<span class="title-right">red</span> Ads</h2>
                        <p>Checkout these latest coo ads from our members</p>
                    </div>                      
                </div>
                <div class="col-md-6 text-md-end aos" data-aos="fade-up">
                       <div class="owl-nav mynav2"></div>
                </div>                  
            </div>
            <div class="row">
                <div class="col-md-12">                     
                    <div class="owl-carousel featured-slider grid-view">   
                        @foreach($feature_listing as $data)                 
                            <div class="card aos" data-aos="fade-up">
                                <div class="blog-widget">
                                    <div class="blog-img">
                                        <a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">
                                            <img src="{{ asset('frontend/img/listing/'.$data->image ) }}" class="img-fluid" alt="blog-img">
                                        </a>
                                        <div class="fav-item">
                                            <span class="featured-text">{{ $data->ad_type }}</span>
                                            <a href="javascript:void(0)" class="fav-icon">
                                                <i class="feather-heart"></i>
                                            </a>                                        
                                        </div>  
                                    </div>
                                    <div class="bloglist-content">
                                        <div class="card-body">
                                            <div class="blogfeaturelink">
                                                <div class="grid-author">
                                                    <img  src="{{ asset('frontend/img/profiles/'.$data->createdByImage ) }}" alt="author">
                                                </div>
                                                <div class="blog-features">
                                                   <a href="javascript:void(0)"><span> <i class="fa-regular fa-circle-stop"></i> {{ $data->category_name }}</span></a>
                                                </div>                                                                    
                                                <!-- <div class="blog-author text-end"> 
                                                    <span>  <i class="feather-eye"></i>4000 </span>
                                                </div> -->
                                            </div> 
                                            <h6><a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">{{ $data->title }}</a></h6>                                                                    
                                            <div class="blog-location-details">
                                                <div class="location-info">
                                                   <i class="feather-map-pin"></i>From {{ $data->from_country_name }}, {{ $data->from_city_name }}
                                                </div>
                                                <div class="location-info">
                                                    <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->departure_on)) }}
                                                </div>
                                            </div>
                                            <div class="blog-location-details">
                                                <div class="location-info">
                                                   <i class="feather-map-pin"></i>To {{ $data->to_country_name }}, {{ $data->to_city_name }}
                                                </div>
                                                <div class="location-info">
                                                    <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->reached_on)) }}
                                                </div>
                                            </div>
                                            <div class="amount-details">
                                                <div class="amount">
                                                    <span class="validrate">{{ $data->ad_type }} {{ $data->weight }} Kg</span>
                                                    <!-- <span>$450</span> -->
                                                </div>
                                                <div class="ratings">
                                                    <span>{{ $data->weight_price }} {{ $data->currency }} / Kg</span><!--  (50) -->
                                                </div>                                              
                                            </div>  
                                        </div>  
                                    </div>           
                                </div> 
                            </div>
                        @endforeach
                    </div>                          
                </div>
            </div>
        </div>
    </section>    
@endif  
<!-- /Featured ADS Section -->

<!-- Popular Location Section -->
<section class="popular-locations"> 
    <div class="popular-circleimg">
         <img class="img-fluid" src="{{ asset('frontend/img/popular-img.png') }}" alt="Popular-sections">
    </div>
    <div class="container">     
         <div class="section-heading">
            <h2>Popular <span>Loc</span>ations</h2>
            <p>Start by selecting your favuorite locations</p>
        </div>
        <div class="location-details d-flex">   
            <div class="row">               
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img class="img-fluid" src="{{ asset('frontend/img/locations/sa.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                      <a href="">Saudia Arabia</a>
                      <!-- <p>20+ Ads Posted</p> -->
                      <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                </div>
             </div> 
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img class="img-fluid" src="{{ asset('frontend/img/locations/uae.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                      <a href="">UAE</a>
                      <!-- <p>20+ Ads Posted</p> -->
                      <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                </div>
             </div> 
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img class="img-fluid" src="{{ asset('frontend/img/locations/phili.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                      <a href="">Philippine</a>
                      <!-- <p>20+ Ads Posted</p> -->
                      <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                </div>
             </div>             
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img  class="img-fluid" src="{{ asset('frontend/img/locations/beh.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                      <a href="">Behrain</a>
                      <!-- <p>20+ Ads Posted</p> -->
                      <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                 </div>
             </div>                
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img  class="img-fluid" src="{{ asset('frontend/img/locations/pak.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                      <a href="">Pakistan</a>
                      <!-- <p>20+ Ads Posted</p> -->
                      <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                </div>
             </div>
             <div class="location-info col-lg-4 col-md-6">
                <div class="location-info-details d-flex align-items-center">
                  <div class="location-img">
                     <a href=""><img class="img-fluid" src="{{ asset('frontend/img/locations/ind.webp') }}" alt="locations"></a>
                  </div>
                  <div class="location-content">
                    <a href="">India</a>
                    <!-- <p>20+ Ads Posted</p> -->
                    <!-- <a href="" class="view-detailsbtn">View Details</a> -->
                  </div>
                </div>                           
             </div>              
            </div>               
        </div>
         <div class="align-items-center"><a href="{{ url('/listing') }}" class="browse-btn">Browse Ads</a></div>
    </div>
</section>      
<!-- /Popular Locations Section -->

<!-- Latest Ads Section -->
 <section class="latestad-section grid-view featured-slider">   
    <div class="container">     
        <div class="section-heading">
            <div class="row align-items-center">
                <div class="col-md-6 aos aos-init aos-animate" data-aos="fade-up">
                    <h2>Lat<span class="title-right">est</span> Ads</h2>
                    <p>checkout these latest cool ads from our members</p>
                </div>
                <div class="col-md-6 text-md-end aos aos-init aos-animate" data-aos="fade-up">
                    <a href="{{ url('/listing') }}" class="btn  btn-view">View All</a>
                </div>      
            </div>
        </div>
        <div class="lateestads-content">
            <div class="row">
                @foreach($listing as $data)
                    <div class="col-lg-3 col-md-4 col-sm-6 d-flex">
                        <div class="card aos flex-fill" data-aos="fade-up">
                            <div class="blog-widget">
                                <div class="blog-img">
                                    <a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">
                                        <img src="{{ asset('frontend/img/listing/'.$data->image ) }}" class="img-fluid" alt="blog-img">
                                    </a>
                                    <div class="fav-item">
                                        <span class="featured-text">{{ $data->ad_type }}</span>
                                        <a href="javascript:void(0)" class="fav-icon">
                                            <i class="feather-heart"></i>
                                        </a>                                        
                                    </div>  
                                </div>
                                <div class="bloglist-content">
                                    <div class="card-body">
                                        <div class="blogfeaturelink">
                                            <div class="grid-author">
                                                <img  src="{{ asset('frontend/img/profiles/'.$data->createdByImage ) }}" alt="author">
                                            </div>
                                            <div class="blog-features">
                                               <a href="javascript:void(0)"><span> <i class="fa-regular fa-circle-stop"></i> {{ $data->category_name }}</span></a>
                                            </div>                                                                    
                                            <!-- <div class="blog-author text-end"> 
                                                <span>  <i class="feather-eye"></i>4000 </span>
                                            </div> -->
                                        </div> 
                                        <h6><a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">{{ $data->title }}</a></h6>                                                                    
                                        <div class="blog-location-details">
                                            <div class="location-info">
                                               <i class="feather-map-pin"></i>From {{ $data->from_country_name }}, {{ $data->from_city_name }}
                                            </div>
                                            <div class="location-info">
                                                <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->departure_on)) }}
                                            </div>
                                        </div>
                                        <div class="blog-location-details">
                                            <div class="location-info">
                                               <i class="feather-map-pin"></i>To {{ $data->to_country_name }}, {{ $data->to_city_name }}
                                            </div>
                                            <div class="location-info">
                                                <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->reached_on)) }}
                                            </div>
                                        </div>
                                        <div class="amount-details">
                                            <div class="amount">
                                                <span class="validrate">{{ $data->ad_type }} {{ $data->weight }} Kg</span>
                                                <!-- <span>$450</span> -->
                                            </div>
                                            <div class="ratings">
                                                <span>{{ $data->weight_price }} {{ $data->currency }} / Kg</span><!--  (50) -->
                                            </div>                                              
                                        </div>  
                                    </div>  
                                </div>           
                            </div> 
                        </div>
                    </div>
                @endforeach
            </div>                      
        </div>                      
    </div>
</section>  
<!-- /Latest Ads Section -->

<!-- CTA Section -->
<section class="cta-section">
    <div class="container">
        <div class="row align-items-center">
             <div class="col-lg-7">
                  <div class="cta-content">
                      <h3>Earn Cash by <span>Carrying</span> <br>
                      goods                               
                      </h3>
                      <p>At Friendie Logistics, we believe in the power of connection and the spirit of sharing. Our platform is designed to bring people together, transforming travel into an opportunity for collaboration and support.</p>
                      <div class="cta-btn">
                        @if(Auth::user())
                            @if(Auth::user()->hasRole('user'))
                                <a href="{{ url('/user/add-listing') }}" class="btn-primary postad-btn">Post Your Ads</a>
                            @endif
                        @else
                            <a href="{{ url('/login') }}" class="btn-primary postad-btn">Post Your Ads</a>
                        @endif
                         <a href="{{ url('/listing') }}" class="browse-btn">Browse Ads</a>                            
                      </div>
                  </div>
             </div>
             <div class="col-lg-5">
                   <div class="cta-img">
                        <img src="{{ asset('frontend/img/cta-img.png') }}" class="img-fluid" alt="CTA">
                   </div>
             </div>
        </div>
    </div>
</section>      
<!-- /CTA Section -->

<!-- Client Testimonilas Section -->
<section class="testimonials-section">    
    <div class="row">
        <div class="col-lg-5">          
           <div class="testimonial-heading d-flex">
               <h4> Client <br> Testimonials</h4>
               <img src="{{ asset('frontend/img/quotes.png') }}" alt="quotes">
           </div>
        </div>
        <div class="col-lg-7">          
           <div class="rightimg"></div>
        </div>
    </div>
    <div class="container">
        <div class="row">
            <div class="testimonials-slidersection">
                <div class="owl-nav mynav1"></div>
                <div class="owl-carousel testi-slider">
                    <div class="testimonial-info">
                        <div class="testimonialslider-heading d-flex">
                            <div class="testi-img">
                                <img src="{{ asset('frontend/img/testimonial-1.jpg') }}" class="img-fluid" alt="testi-img">
                             </div>
                             <div class="testi-author">
                                  <h6>Alex P.</h6>
                                  <p>Frequent Traveler</p>
                             </div>
                            
                        </div>
                         <div class="testimonialslider-content">
                             <p>This platform has completely changed my travel experience. I used to have unused luggage space, but now I earn extra cash on every trip. It's easy, efficient, and a win-win for everyone!</p>
                        </div>
                    </div>
                     <div class="testimonial-info">
                        <div class="testimonialslider-heading d-flex">
                            <div class="testi-img">                                         
                                <img src="{{ asset('frontend/img/testimonial-2.jpg') }}" class="img-fluid" alt="testi-img">
                            </div>
                             <div class="testi-author">
                                  <h6>Sarah K.</h6>
                                  <p>Travel Enthusiast</p>
                             </div>                                         
                        </div>
                        <div class="testimonialslider-content">
                              <p>I was traveling internationally and decided to try this platform. Not only did I make some extra money, but I also met wonderful people. The process was seamless from start to finish!</p>
                        </div>
                    </div>
                    <div class="testimonial-info">
                        <div class="testimonialslider-heading d-flex">
                             <div class="testi-img">
                                  <img src="{{ asset('frontend/img/testimonial-2.jpg') }}" class="img-fluid" alt="testi-img">
                             </div>
                             <div class="testi-author">
                                  <h6>Emma R.</h6>
                                  <p>Small Business Owner</p>
                             </div>                                         
                        </div>
                        <div class="testimonialslider-content">
                              <p>Sending a package through this platform was so much cheaper than traditional couriers. My parcel arrived on time, and I was able to track it directly with the traveler!</p>
                        </div>
                    </div>
                     <div class="testimonial-info">
                        <div class="testimonialslider-heading d-flex">
                             <div class="testi-img">
                                 <img src="{{ asset('frontend/img/testimonial-2.jpg') }}"  class="img-fluid" alt="testi-img">
                             </div>
                             <div class="testi-author">
                                  <h6>Maya L.</h6>
                                  <p>Satisfied Sender</p>
                             </div>                                         
                        </div>
                        <div class="testimonialslider-content">
                              <p>I urgently needed to send a gift to my friend in another city, and this platform made it possible. The traveler was friendly and professional, and everything went smoothly!</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>         
</section>      
<!-- /Client Testimonilas Section -->

<!-- Partners Section -->
<!-- <div class="partners-section">
    <div class="container">
         <p class="partners-heading">Over 5,26,000+ Sponsers being contact with us</p>  
         <ul class="owl-carousel partnerslist d-flex">
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-1.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-2.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-3.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-4.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-5.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-6.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-1.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-2.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-3.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-4.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-5.svg') }}" alt="partners"></a></li>
            <li><a href="javascript:void(0);"><img class="img-fluid" src="{{ asset('frontend/img/partners/partners-6.svg') }}" alt="partners"></a></li>
         </ul>
    </div>
</div>   -->    
<!-- /Partners  Section -->

<!-- Pricing Plan Section -->
<!-- <section class="pricingplan-section">
        <div class="section-heading">
                <div class="container">             
                     <div class="row text-center">
                            <h2>Our Pricing <span>Pla</span>n</h2>
                            <p>checkout these latest cool ads from our members</p>
                     </div>                                  
                </div>   
        </div>
        <div class="container">
            <div class="row">
                <div class="col-lg-3 d-flex col-md-6">
                    <div class="price-card flex-fill">
                        <div class="price-head">
                            <div class="price-level">
                                <h6>Intro</h6>
                            </div>
                            <h4>$10 <span>/ month</span></h4>                               
                        </div>  
                        <div class="price-body">
                            <p>For most business that want to optimize web queries</p>
                            <ul>
                                <li class="active">Basic listing submission</li>
                                <li class="active">One Listing</li>
                                <li class="active">30 days Availabilty</li>
                                <li class="inactive">Limited Support</li>
                                <li class="inactive">Edit your listing</li>
                            </ul>
                            <div>
                                <a href="login.html" class="btn viewdetails-btn">View details</a>
                            </div>                          
                        </div>                          
                    </div>
                </div>
                <div class="col-lg-3 d-flex col-md-6">
                    <div class="price-card flex-fill">
                        <div class="price-head">
                            <div class="price-level">
                                <h6>Basic</h6>
                            </div>
                            <h4>$25 <span>/ month</span></h4>                               
                        </div>  
                        <div class="price-body">
                            <p>For most business that want to optimize web queries</p>
                            <ul>
                                <li class="active">Basic listing submission</li>
                                <li class="active">One Listing</li>
                                <li class="active">30 days Availabilty</li>
                                <li class="inactive">Limited Support</li>
                                <li class="inactive">Edit your listing</li>
                            </ul>
                            <div>
                                <a href="login.html" class="btn viewdetails-btn">View details</a>
                            </div>                          
                        </div>                          
                    </div>  
                </div>
                <div class="col-lg-3 d-flex col-md-6">
                    <div class="price-card flex-fill">
                        <div class="price-head">
                            <div class="price-level">
                                <h6>Popular</h6>
                            </div>
                            <h4>$50 <span>/ month</span></h4>                                   
                        </div>  
                        <div class="price-body">
                            <p>For most business that want to optimize web queries</p>
                            <ul>
                                <li class="active">Basic listing submission</li>
                                <li class="active">One Listing</li>
                                <li class="active">30 days Availabilty</li>
                                <li class="inactive">Limited Support</li>
                                <li class="inactive">Edit your listing</li>
                            </ul>
                            <div>
                                <a href="login.html" class="btn viewdetails-btn">View details</a>
                            </div>                          
                        </div>                          
                    </div>
                </div>
                <div class="col-lg-3 d-flex col-md-6">
                     <div class="price-card flex-fill">
                        <div class="price-head">
                            <div class="price-level">
                                <h6>Enterprise</h6>
                            </div>
                            <h4>$100 <span>/ month</span></h4>                                  
                        </div>  
                        <div class="price-body">
                            <p>For most business that want to optimize web queries</p>
                            <ul>
                                <li class="active">Basic listing submission</li>
                                <li class="active">One Listing</li>
                                <li class="active">30 days Availabilty</li>
                                <li class="inactive">Limited Support</li>
                                <li class="inactive">Edit your listing</li>
                            </ul>
                            <div>
                                <a href="login.html" class="btn viewdetails-btn">View details</a>
                            </div>                          
                        </div>                          
                    </div>
                </div>
            </div>
        </div>
</section> -->
<!-- /Pricing Plan Section -->

<!-- Blog  Section -->
<!-- <section class="blog-section">
    <div class="section-heading">
            <div class="container">             
                <div class="row align-items-center">
                    <div class="col-md-6 aos aos-init aos-animate" data-aos="fade-up">
                        <h2>Lat<span class="title-right">est</span> Blog</h2>
                        <p>people are giving these goods for free so check them out</p>
                    </div>
                    <div class="col-md-6 text-md-end aos aos-init aos-animate" data-aos="fade-up">
                            <a href="blog-grid.html" class="btn  btn-view">View All</a>
                    </div>      
                </div>  
            </div>  
    </div>
    <div class="container">                                     
        <div class="row">
            <div class="col-lg-4 col-md-4 d-flex">
                    <div class="blog grid-blog">
                        <div class="blog-image">
                            <a href="blog-details.html"><img class="img-fluid" src="{{ asset('frontend/img/blog/blog-1.jpg') }}" alt="Post Image"></a>
                        </div>
                        <div class="blog-content">
                            <p class="blog-category">
                               <a href="javascript:void(0);"><span>Health</span></a><a href="javascript:void(0);"><span>Care</span></a>
                            </p>
                            <ul class="entry-meta meta-item">
                                <li>
                                    <div class="post-author">
                                        <div class="post-author-img">
                                            <img src="{{ asset('frontend/img/profiles/avatar-14.jpg') }}" alt="Post Author">
                                        </div>
                                        <a href="javascript:void(0);" class="mb-0"> <span> Amara </span></a>
                                    </div>
                                </li>
                                <li class="date-icon"><i class="fa-solid fa-calendar-days"></i> October 4, 2023</li>
                            </ul>
                            <h3 class="blog-title"><a href="blog-details.html">The Best Spa Saloons for your relaxations?</a></h3>
                            <p class="blog-description">Lorem ipsum dolor sit amet, consectetur em adipiscing elit, sed do eiusmod tempor. Lorem ipsum dolor sit amet, consectetur em adipiscing elit,</p>
                            <p class="viewlink"><a href="blog-details.html">View Details <i class="feather-arrow-right"></i></a></p>
                        </div>
                    </div>
            </div>
            <div class="col-lg-4 col-md-4 d-flex">
                    <div class="blog grid-blog">
                        <div class="blog-image">
                                <a href="blog-details.html"><img class="img-fluid" src="{{ asset('frontend/img/blog/blog-2.jpg') }}" alt="Post Image"></a>
                        </div>
                        <div class="blog-content">
                            <p class="blog-category">
                               <a href="javascript:void(0);"><span>Health</span></a><a href="javascript:void(0);"><span>Care</span></a>
                            </p>
                            <ul class="entry-meta meta-item">
                                <li>
                                    <div class="post-author">
                                        <div class="post-author-img">
                                            <img src="{{ asset('frontend/img/profiles/avatar-12.jpg') }}" alt="Post Author">
                                        </div>
                                        <a href="javascript:void(0);" class="mb-0"><span> Darryl </span></a>
                                    </div>
                                </li>
                                <li class="date-icon"><i class="fa-solid fa-calendar-days"></i> October 6, 2023</li>
                            </ul>
                            <h3 class="blog-title"><a href="blog-details.html">Three Powerful Tricks For Online Advertising</a></h3>
                            <p class="blog-description">Lorem ipsum dolor sit amet, consectetur em adipiscing elit, sed do eiusmod tempor. Lorem ipsum dolor sit amet, consectetur em adipiscing elit,</p>
                            <p class="viewlink"><a href="blog-details.html">View Details <i class="feather-arrow-right"></i></a></p>
                        </div>
                    </div>
            </div>
            <div class="col-lg-4 col-md-4 d-flex">
                 <div class="blog grid-blog">
                    <div class="blog-image">
                        <a href="blog-details.html"><img class="img-fluid" src="{{ asset('frontend/img/blog/blog-3.jpg') }}" alt="Post Image"></a>
                    </div>
                    <div class="blog-content">
                        <p class="blog-category">
                           <a href="javascript:void(0);"><span>Health</span></a><a href="javascript:void(0);"><span>Care</span></a>
                        </p>
                        <ul class="entry-meta meta-item">
                            <li>
                                <div class="post-author">
                                    <div class="post-author-img">
                                        <img src="{{ asset('frontend/img/profiles/avatar-13.jpg') }}" alt="Post Author">
                                    </div>
                                    <a href="javascript:void(0);" class="mb-0"> <span> Mary </span></a>
                                </div>
                            </li>
                            <li class="date-icon"><i class="fa-solid fa-calendar-days"></i> October 10, 2023</li>
                        </ul>
                        <h3 class="blog-title"><a href="blog-details.html">Competitive Analysis for Enterprerneurs in 20222</a></h3>
                        <p class="blog-description">Lorem ipsum dolor sit amet, consectetur em adipiscing elit, sed do eiusmod tempor. Lorem ipsum dolor sit amet, consectetur em adipiscing elit,</p>
                        <p class="viewlink"><a href="blog-details.html">View Details  <i class="feather-arrow-right"></i></a></p>
                    </div>
                 </div>
            </div>
        </div>                                  
    </div>   
</section> -->
<!-- /Blog  Section -->

@endsection
