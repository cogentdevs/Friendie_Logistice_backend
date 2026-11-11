@extends('layouts.frontLayout.front-design')
@section('content')

<style type="text/css">
    .details-main-wrapper .review-list li:nth-child(2) {
        margin-left: 0px !important;
    }
</style>

<!--Inner Banner-->
<div class="contactbanner innerbanner">        
    <div class="inner-breadcrumb">
      <div class="container">
        <div class="row align-items-center text-center">
            <div class="col-md-12 col-12 ">
               <h2 class="breadcrumb-title">Details</h2>
                <nav aria-label="breadcrumb" class="page-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Details</li>
                    </ol>
                </nav>                          
            </div>
        </div>
      </div>                    
    </div>                  
</div>  
<!--/Inner Banner-->

<!--Details Description  Section-->
<section class="details-description">
    <div class="container">
        @if(Session::has('flash_message_success'))
            <div class="alert alert-danger alert-dismissible" style="background-color: #c10037; color: white;">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_success') !!}</strong>
            </div>
        @endif
        @if(Session::has('flash_message_error'))
            <div class="alert alert-danger alert-dismissible" style="background-color: #c10037; color: white;">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_error') !!}</strong>
            </div>
        @endif
        <div class="about-details">
            <div class="about-headings">
                <div class="author-img">
                       <img src="{{ asset('frontend/img/profiles/'.$listing->createdByImage ) }}" alt="authorimg">
                </div>
                <div class="authordetails">
                    <h5>{{ $listing->title }}</h5>
                    <p>{{ $listing->category_name }}</p>
                    <div class="rating">
                        @php
                            $listing_owner_rating = number_format((float)$listing_owner_rating, 1, '.', '');
                            $exp_avg = explode(".", $listing_owner_rating);
                            $exp1 = $exp_avg[0];
                            $exp2 = $exp_avg[1];
                            $cv = 0;
                        @endphp

                        @for($f=1; $f <= 5; $f++)
                            @if($f <= $exp1)
                                <i class="fas fa-star filled"></i>
                            @else
                                @if($exp2 >= 3)
                                    @php $cv++; @endphp

                                    @if($cv == 1)
                                        <i class="fas fa-star-half-alt filled"></i>
                                    @else
                                        <i class="fa-regular fa-star filled"></i>
                                    @endif
                                @else
                                    <i class="fa-regular fa-star filled"></i>
                                @endif
                            @endif
                        @endfor
                        <span class="d-inline-block average-rating"> {{ $listing_owner_rating }} </span>
                    </div>
                </div>
            </div>
            <div class="rate-details">
                <h3>{{ $listing->weight_price }} {{ $listing->currency }} Per Kg</h3>
                <p>{{ $listing->ad_type }} {{ $listing->weight }} Kg</p>
            </div>               
        </div>
        <div class="descriptionlinks">
            <div class="row">
                <div class="col-lg-9">
                    <!-- <ul>
                        <li><a href="javascript:void(0);"><i class="feather-map"></i> Get Directions</a></li>
                        <li><a href="javascript:void(0);"><img src="{{ asset('frontend/img/website.svg') }}" alt="website">Website</a></li>
                        <li><a href="javascript:void(0);"><i class="feather-share-2"></i> share</a></li>
                        <li><a href="javascript:void(0);"><i class="fa-regular fa-comment-dots"></i> Write a review</a></li>
                        <li><a href="javascript:void(0);"><i class="feather-flag"></i> report</a></li>
                        <li><a href="javascript:void(0);"><i class="feather-heart"></i> Save</a></li>
                    </ul> -->
                </div>
                <div class="col-lg-3">
                    <div class="callnow">
                        @if($listing->departure_on > $cur_date)
                            @if(Auth::user())
                                @if($listing->created_by != Auth::user()->id)

                                    @php $room_id = 0; @endphp

                                    @foreach($chk_rooms as $room)
                                        @if($room->from_user_id == Auth::user()->id)

                                            @php 
                                                $room_id = $room->id; 
                                                $room_slug = md5($room_id);
                                            @endphp

                                            <a href="{{ url('/user/messages/'.$listing->id.'/'.str_replace(' ', '-', strtolower($listing->title)).'/'.$room_id.'='.$room_slug) }}">
                                                <i class="feather-mail"></i>  Message Now
                                            </a>

                                        @endif
                                    @endforeach

                                     @if($room_id == 0)
                                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myFirstMessageModal">
                                            <i class="feather-mail"></i>  Message Now
                                        </button>
                                    @endif
                                @else
                                    <a href="{{ url('/user/messages') }}">
                                        <i class="feather-mail"></i>  View Message
                                    </a>
                                @endif
                            @else
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#myLoginModal">
                                    <i class="feather-mail"></i>  Message Now
                                </button>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>      
</section>
<!--/Details Description  Section-->

<!--Details Main  Section-->
<div class="details-main-wrapper listing-section "> 
    <div class="container">
        <div class="row">
            <div class="col-lg-9">                                              
                <div class="card ">
                    <div class="card-header">
                        <span class="bar-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                        </span> 
                        <h4>Description</h4>
                    </div>
                    <div class="card-body">
                        <p>{!! html_entity_decode($listing->description) !!}</p>
                    </div>
                </div>           
                    
                <!--Review  Section-->
                <div  class="card review-sec">
                    <div class="card-header  align-items-center">
                        <i class="fa-regular fa-comment-dots"></i>
                        <h4>Write a Review</h4>
                    </div>
                    <div class="card-body">
                        <div class="review-list">
                            <ul class="">
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

                                        </div>
                                    </li>   
                                @endforeach 

                                @if(Auth::user())
                                    @if(Auth::user()->hasRole('user'))
                                        <li class="review-box feedbackbox mb-0">
                                            <div class="review-details">
                                               <h6>Leave Feedback About This</h6>
                                            </div>
                                            <div class="card-body">
                                                <form enctype="multipart/form-data" method="post" action="{{url('/user/post-review')}}">
                                                    {{ csrf_field() }}
                                                    <input type="hidden" name="post_ads_id" value="{{ $listing->id }}">
                                                    <input type="hidden" name="to_user_id" value="{{ $listing->created_by }}">
                                                    <input type="hidden" name="from_user_id" value="{{ Auth::user()->id }}">
                                                    <input type="hidden" name="rating" id="rating" value="0">
                                                    <div class="rating">
                                                        <p><span> Rating</span> 
                                                            <span class="give-ratting-star">
                                                                <?php for($f=1;$f <= 5;$f++){
                                                                    echo '<i id="rate_'.$f.'" class="fas fa-star giverate"></i>';
                                                                } ?>
                                                            </span>
                                                        </p>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-lg-12 col-md-12">
                                                            <div class="form-group">
                                                                <textarea rows="4" class="form-control" name="review" placeholder="Write a Review*" required></textarea>
                                                             </div>
                                                        </div>
                                                    </div>  
                                                    @if($visitor_reviews_count > 0 || $listing->created_by == Auth::user()->id)
                                                        <div class="submit-section">
                                                            <button class="btn btn-primary submit-btn" disabled type="submit"> Submit Review</button>
                                                        </div>
                                                    @else
                                                        <div class="submit-section">
                                                            <button class="btn btn-primary submit-btn" type="submit"> Submit Review</button>
                                                        </div>
                                                    @endif                                                            
                                                </form>
                                            </div>
                                        </li>
                                    @endif
                                @endif
                                        
                            </ul>
                        </div>
                    </div>
                </div>  
                <!--/Review Section-->  
                    
            </div>
            <div class="col-lg-3 theiaStickySidebar">
                <div class="rightsidebar">
                    <div class="card">
                     <h4><img  src="{{ asset('frontend/img/details-icon.svg') }}" alt="details-icon">Travel Details</h4>
                     <ul>
                        <li>From <span>{{ $listing->from_country_name }},<br> {{ $listing->from_city_name }} @if($listing->from_area != ''),<br> {{ $listing->from_area }} @endif </span></li>
                        <li>Travel On <span>{{ date("d M, Y", strtotime($listing->departure_on)) }}</span></li>
                        <li>To <span>{{ $listing->to_country_name }},<br> {{ $listing->to_city_name }} @if($listing->to_area != ''),<br> {{ $listing->to_area }} @endif</span></li>
                        <li>Reached On <span>{{ date("d M, Y", strtotime($listing->reached_on)) }}</span></li>
                     </ul>
                    </div>
                    @if($listing->show_contact_info == 1)
                        <div class="card">
                            <h4><img src="{{ asset('frontend/img/breifcase.svg') }}" alt=""> Contact Info</h4>
                            <div class="map-details">   
                              <ul class="info-list">
                                <!-- <li><i class="feather-map-pin"></i> 484, Ellis st, San Fransisco,<br>CS 94102, United States</li> -->
                                <li><i class="feather-phone-call"></i> {{ $listing->createdByPhone }}</li>
                                <li><i class="feather-mail"></i> {{ $listing->createdByEmail }}</li>
                              </ul>
                            </div>
                        </div>
                    @endif

                    <div class="card">
                        <h4> <i class="feather-user"></i> Author</h4>
                        <div class="sidebarauthor-details align-items-center">
                           <div class="sideauthor-img">
                             <img src="{{ asset('frontend/img/profiles/'.$listing->createdByImage ) }}" alt="author">
                           </div>
                           <div class="sideauthor-info">
                               <p class="authorname">{{ $listing->createdBy }}</p>
                               <p >Member since {{ date("d M, Y", strtotime($listing->join_date)) }}</p>
                           </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>      
</div>      
<!-- /Details Main Section -->

<!-- The Modal -->
<div class="modal" id="myLoginModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Login</h4>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <!-- Login Form -->
                <form class="form-horizontal mt-3" method="post" action="{{url('/login')}}">
                    {{ csrf_field() }}
                    <input type="hidden" name="from" value="listing">
                    <div class="form-group group-img">
                        <div class="group-img">
                            <i class="feather-mail"></i>
                            <input type="text" class="form-control" name="email" placeholder="Email Address" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="pass-group group-img">
                            <i class="feather-lock"></i>
                            <input type="password" class="form-control pass-input" name="password" placeholder="Password" required>
                            <span class="toggle-password feather-eye"></span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 col-sm-6">
                            <label class="custom_check">
                                <input type="checkbox" name="rememberme" class="rememberme">
                                <span class="checkmark"></span>Remember Me
                            </label>
                        </div>                                  
                        <div class="col-md-6 col-sm-6">
                            <div class="text-md-end">
                                <a class="forgot-link" href="{{ url('/forgot-password') }}">Forgot password?</a>
                            </div>
                        </div>                                  
                    </div>
                    <button class="btn btn-primary w-100 login-btn" type="submit">Sign in</button>
                    <div class="register-link text-center">
                        <p>No account yet? <a class="forgot-link" href="{{ url('/sign-up') }}">Signup</a></p>
                    </div>                           
                </form>
            </div>
        </div>
    </div>
</div>

@if(Auth::user())
    <!-- The First Message Modal -->
    <div class="modal" id="myFirstMessageModal">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Message</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <!-- Modal body -->
                <div class="modal-body">
                    <!-- Login Form -->
                    <form class="form-horizontal mt-3" method="post" action="{{ url('/user/create/room') }}">
                        {{ csrf_field() }}
                        <input type="hidden" name="post_ads_id" value="{{ $listing->id }}">
                        <input type="hidden" name="from_user_id" value="{{ Auth::user()->id }}">
                        <input type="hidden" name="to_user_id" value="{{ $listing->created_by }}">
                        <div class="form-group group-img">
                            <div class="group-img">
                                <i class="feather-mail"></i>
                                <input type="text" class="form-control" name="message" placeholder="Type a message" required>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 login-btn" type="submit">Send</button>                         
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

@endsection