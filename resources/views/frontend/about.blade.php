@extends('layouts.frontLayout.front-design')
@section('content')

<!--Inner Banner-->
<div class="aboutbanner innerbanner">
    <div class="inner-breadcrumb">
        <div class="container">
            <div class="row align-items-center text-center">
                <div class="col-md-12 col-12 ">
                   <h2 class="breadcrumb-title">About Us</h2>
                    <nav aria-label="breadcrumb" class="page-breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                            <li class="breadcrumb-item active" aria-current="page">About us</li>
                        </ol>
                    </nav>                          
                </div>
            </div>
        </div>                  
    </div>                  
</div>  
<!--/Inner Banner-->        

<!--About Content-->
<section class="about-content">
    <div class="container">
        <div class="about-listee">
            <div class="about-img">
                <img src="{{ asset('frontend/img/about.jpg') }}" class="img-fluid" alt="">
            </div>  
            <div class="about-info">
                <h4> <span>About</span> Friendie Logistics</h4>  
                <p>
                    At Friendie Logistics, we believe in the power of connection and the spirit of sharing. Our platform is designed to bring people together, transforming travel into an opportunity for collaboration and support. Our mission is to create a seamless experience for travelers and senders, making parcel delivery simple, cost-effective, and enjoyable.
                </p>
            </div>
        </div>      
    </div>      
</section>
<!--/About Content-->

<!--How It Works-->
<section class="howitworks">
    <div class="container">
      <h3>What We Do</h3>
      <p>
          Friendie Logistics connects travelers looking to lighten their load with people who need to send items from one location to another. Travelers can earn rewards by carrying parcels, offsetting their travel costs while helping others. Whether you're heading out on an adventure or just commuting, you can help someone and gain from the experience.
      </p>
      <h3>Our Vision</h3>
      <p>
          We envision a world where sharing is at the heart of travel. By fostering connections and promoting a sense of community among our users, we aim to create a platform that not only facilitates deliveries but also enriches the lives of everyone involved. We want to empower travelers and senders alike to support each other’s needs and dreams.
      </p>
      <h3>Our Values</h3>
      <ol style="text-align: left !important;">
            <li>
                <strong>Trust</strong>: We prioritize safety and trust among our users by implementing robust verification processes.
            </li>
            <li>
                <strong>Community</strong>: We encourage a friendly, collaborative environment where everyone can contribute and benefit.
            </li>
            <li>
                <strong>Affordability</strong>: Our service provides cost-effective solutions for travelers and senders, ensuring everyone gains from the experience.
            </li>
            <li>
                <strong>Simplicity</strong>: User experience is at the forefront of our design, making our platform easy to navigate and use.
            </li>
      </ol>
      <p>
          Join us in revolutionizing the way we think about parcel delivery. Together, we can make every journey a shared experience that not only meets needs but also builds connections.
      </p>
      <h3>Get Involved</h3>
      <p>
          Ready to get started? Sign up today! Whether you’re a traveler looking to earn while you roam or someone in need of a reliable delivery option, Friendie Logistics is here to help.
      </p>
      <div class="row">
            <div class="col-lg-4 col-md-4 d-flex">
                <div class="howitwork-info">
                    <h5>01</h5>
                    <h6>Create Account</h6>
                </div>                    
            </div>
            <div class="col-lg-4 col-md-4 d-flex">
                <div class="howitwork-info">
                    <h5>02</h5>
                    <h6>Post An Ad</h6>
                </div>                    
            </div>
            <div class="col-lg-4 col-md-4 d-flex">
                <div class="howitwork-info">
                    <h5>03</h5>
                    <h6>Find, Carry Or Send</h6>
                </div>                    
            </div>                      
        </div>
    </div>  
</section>
<!--/How It Works-->

<!--Popular Location-->
<div class="aboutpopular-locations">
    <div class="container">
        <div class="row">
            <div class="col-lg-3 col-md-4">
                <div class="aboutlocation-details usa">
                <div class="aboutloc-img">
                    <img src="{{ asset('frontend/img/locations/sa.webp') }}" alt="">
                </div>
                <a href="#">                                        
                    <div class="aboutlocations-info">
                        <h3>Saudia Arabia</h3>
                        <!-- <span>20+ Ads Posted</span> -->
                    </div> 
                </a>                        
                </div>              
            </div>
            <div class="col-lg-3 col-md-4">
                <div class="aboutlocation-details usa">
                <div class="aboutloc-img">
                    <img src="{{ asset('frontend/img/locations/uae.webp') }}" alt="">
                </div>
                <a href="#">                                        
                    <div class="aboutlocations-info">
                        <h3>UAE</h3>
                        <!-- <span>15+ Ads Posted</span> -->
                    </div> 
                </a>                        
                </div>              
            </div>
            <div class="col-lg-3 col-md-4">
                <div class="aboutlocation-details canada">
                    <div class="aboutloc-img">
                        <img src="{{ asset('frontend/img/locations/oman.webp') }}" alt="">
                    </div>
                    <a href="#">                                                    
                        <div class="aboutlocations-info">
                            <h3>Oman</h3>
                            <!-- <span>25+ Ads Posted</span> -->
                        </div> 
                    </a>                        
                </div>  
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="aboutlocation-details china">
                    <div class="aboutloc-img">
                        <img src="{{ asset('frontend/img/locations/phili.webp') }}" alt="">
                    </div>
                    <a href="#">
                        <div class="aboutlocations-info">
                            <h3>Philippine</h3>
                            <!-- <span>17+ Ads Posted</span> -->
                        </div> 
                    </a>                        
                </div>   
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="aboutlocation-details uk">
                    <div class="aboutloc-img">
                        <img src="{{ asset('frontend/img/locations/beh.webp') }}" alt="">
                    </div>
                    <a href="#">
                        <div class="aboutlocations-info">
                            <h3>Behrain</h3>
                            <!-- <span>12+ Ads Posted</span> -->
                        </div> 
                    </a>                        
                </div>   
            </div>
            <div class="col-lg-6 col-md-6">
                <div class="aboutlocation-details australi-loc">
                    <div class="aboutloc-img">
                        <img src="{{ asset('frontend/img/locations/pak-a.webp') }}" alt="">
                    </div>
                    <a href="#">                                                    
                        <div class="aboutlocations-info">
                            <h3>Pakistan</h3>
                            <!-- <span>25+ Ads Posted</span> -->
                        </div> 
                    </a>                        
                </div>   
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="aboutlocation-details france">
                    <div class="aboutloc-img">
                        <img src="{{ asset('frontend/img/locations/ind.webp') }}" alt="">
                    </div>
                    <a href="#">
                        <div class="aboutlocations-info">
                            <h3>India</h3>
                            <!-- <span>14+ Ads Posted</span> -->
                        </div> 
                    </a>                        
                </div>   
            </div>
        </div>
   </div>
</div>
<!--/Popular location-->

<!-- CTA Section -->
<!-- <section class="section cta-section">
    <div class="container">
        <div class="row align-items-center">
             <div class="col-lg-7">
                  <div class="cta-content">
                      <h3>Earn Cash by <span>Selling</span> <br>
                      or Find Anything you desire                                
                      </h3>
                      <p>There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humo or randomised words which don't look even slightlys</p>
                      <div class="cta-btn">
                         <a href="add-listing.html" class="btn-primary postad-btn">Post Your Ads</a>
                         <a href="#" class="browse-btn">Browse Ads</a>                            
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
</section> -->      
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
<!-- <div class="section partners-section">
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
</div>  -->     
<!-- /Partners  Section --> 

@endsection