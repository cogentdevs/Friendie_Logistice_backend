<!-- Footer -->
<footer class="footer">
    <div class="container">
        <div class="stay-tuned">
            <h3>Stay Tuned With Us</h3>
            <p>Subscribe to our newletter and never miss our latest news and promotions.</p>
            <div id="msg" style="display: none; background-color: #0000004a; color: white;">
                <div class="alert alert-block alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <strong>Subscribe Successfully.</strong>
                </div>
            </div>
            <div id="msg_error" style="display: none;">
                <div class="alert alert-danger alert-block alert-dismissible">
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    <strong>Already Subscribed.</strong>
                </div>
            </div>
            <form>
                <div class="form-group">
                    <div class="group-img">
                        <i class="feather-mail"></i>
                        <input type="text" class="form-control" name="email" id="email" placeholder="Enter Email Address">
                    </div>                          
                </div>
                <button class="btn btn-primary" type="button" disabled id="btn_subscribe"> Subscribe</button>                           
            </form>                 
        </div>          
    </div>

    <!-- Footer Top -->
    <div class="footer-top aos" data-aos="fade-up">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <!-- Footer Widget -->
                    <div class="footer-widget">
                        <div class="footer-logo">
                            <a href="{{ url('/') }}"><img src="{{ asset('frontend/img/footer-logo.png') }}" alt="logo"></a>
                        </div>
                        <div class="footer-content">
                            <p>At Friendie Logistics, we believe in the power of connection and the spirit of sharing. Our platform is designed to bring people together, transforming travel into an opportunity for collaboration and support.</p>
                        </div>
                        <div class="social-icon">
                            <ul>
                                @if($front_footer_count > 0)
                                    @if(!empty($front_footer->facebook))
                                        <li>
                                            <a href="{{ $front_footer->facebook }}" target="_blank">
                                                <i class="fab fa-facebook-f"></i>
                                            </a>
                                        </li>
                                    @endif
                                    @if(!empty($front_footer->google))
                                        <li>
                                            <a href="{{ $front_footer->google }}" target="_blank">
                                                <i class="fab fa-google"></i>
                                            </a>
                                        </li>
                                    @endif
                                    @if(!empty($front_footer->instagram))
                                        <li>
                                            <a href="{{ $front_footer->instagram }}" target="_blank">
                                                <i class="fab fa-instagram"></i>
                                            </a>
                                        </li>
                                    @endif
                                    @if(!empty($front_footer->linkedin))
                                        <li>
                                            <a href="{{ $front_footer->linkedin }}" target="_blank">
                                                <i class="fab fa-linkedin-in"></i>
                                            </a>
                                        </li>
                                    @endif
                                    @if(!empty($front_footer->twitter))
                                        <li>
                                            <a href="{{ $front_footer->twitter }}" target="_blank">
                                                <i class="fab fa-twitter"></i>
                                            </a>
                                        </li>
                                    @endif
                                    @if(!empty($front_footer->youtube))
                                        <li>
                                            <a href="{{ $front_footer->youtube }}" target="_blank">
                                                <i class="fab fa-youtube-play"></i>
                                            </a>
                                        </li>
                                    @endif
                                @endif
                            </ul>
                        </div>                              
                    </div>
                    <!-- /Footer Widget -->
                </div>
                <div class="col-lg-3 col-md-6">
                    <!-- Footer Widget -->
                    <div class="footer-widget footer-menu">
                        <h2 class="footer-title">About us</h2>
                        <ul>
                            <li>
                                <a href="{{ url('/about') }}">About Us</a>
                            </li>
                            <li>
                                <a href="{{ url('/terms-condition') }}">Terms & Condition</a>
                            </li>
                            <li>
                                <a href="javascript:void(0)">Faq</a>
                            </li>
                            <li>
                                <a href="{{ url('/contact') }}">Contact Us</a>
                            </li>                                   
                        </ul>
                    </div>
                    <!-- /Footer Widget -->
                </div>
                <div class="col-lg-3 col-md-6">
                    <!-- Footer Widget -->
                    <div class="footer-widget footer-menu">
                        <h2 class="footer-title">Top Cities</h2>
                        <ul>
                            <li>
                                <a href="javascript:void(0)">Riyadh</a>
                            </li>
                            <li>
                                <a href="javascript:void(0)">Makkah</a>
                            </li>
                            <li>
                                <a href="javascript:void(0)">Madina</a>
                            </li>
                            <li>
                                <a href="javascript:void(0)">Dammam</a>
                            </li>
                            <li>
                                <a href="javascript:void(0)">Sharjaa</a>
                            </li>                                   
                        </ul>
                    </div>
                    <!-- /Footer Widget -->
                </div>
                <div class="col-lg-3 col-md-6">
                    <!-- Footer Widget -->
                    <div class="footer-widget">
                        <h2 class="footer-title">Communication</h2>
                        <div class="footer-contact-info">
                            @if($front_footer_count > 0)
                                @if(!empty($front_footer->phone_1))
                                    <div class="footer-address">
                                        <img src="{{ asset('frontend/img/call-calling.svg') }}" alt="Callus">
                                        <p>
                                            <span>Call Us</span> <br> 
                                            {{ $front_footer->phone_1 }}
                                            @if(!empty($front_footer->phone_2))
                                                {{ $front_footer->phone_2 }}
                                            @endif
                                        </p>
                                    </div>
                                @endif 

                                @if(!empty($front_footer->email))
                                    <div class="footer-address">
                                        <img src="{{ asset('frontend/img/sms-tracking.svg') }}" alt="Callus">
                                        <p><span>Send Message</span> <br> {{ $front_footer->email }}</p>
                                    </div>    
                                @endif
                            @endif                       
                        </div>                                                              
                    </div>
                    <!-- /Footer Widget -->                         
                </div>
            </div>
            
            <!-- Footer Counter Section-->
            <div class="footercount">
                <div class="row">
                     <div class="col-lg-3 col-md-3">
                                                          
                     </div>
                      <div class="col-lg-3 col-md-3">
                                                            
                     </div> 
                     <div class="col-lg-3 col-md-3">
                                                          
                     </div>
                      <div class="col-lg-3 col-md-3">
                        <div class="vistors-details">
                            <p>We Accept</p>
                            <ul class="d-flex">
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/amex-pay.svg') }}" alt="amex"></a></li>
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/apple-pay.svg') }}" alt="pay"></a></li>
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/gpay.svg') }}" alt="gpay"></a></li>
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/master.svg') }}" alt="paycard"></a></li>
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/phone.svg') }}" alt="spay"></a></li>
                                <li><a href="javascript:void(0)"><img class="img-fluid" src="{{ asset('frontend/img/visa.svg') }}" alt="visa"></a></li>
                            </ul>                                                                       
                        </div>                                   
                     </div>
                </div>
            </div>          
            <!-- /Footer  Counter Section-->    
            
        </div>
    </div>
    <!-- /Footer Top -->
                
    <!-- Footer Bottom -->
    <div class="footer-bottom">
        <div class="container">
            <!-- Copyright -->
            <div class="copyright">
                <div class="row">
                    <div class="col-md-6">
                        <div class="copyright-text">
                            <p class="mb-0">All Copyrights Reserved &copy; <?php echo date('Y')?> - Friendie Logistics. Developed By <a href="https://wiindot.com/" target="_blank">Wiin Dot</a></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <!-- Copyright Menu -->
                        <!-- <div class="copyright-menu">
                            <ul class="policy-menu">
                                <li>
                                    <a href="privacy-policy.html">Privacy </a>
                                </li>
                                <li>
                                    <a href="faq.html">Faq </a>
                                </li>
                                <li>
                                    <a href="terms-condition.html">Terms</a>
                                </li>                                       
                            </ul>
                        </div> -->
                        <!-- /Copyright Menu -->
                    </div>
                </div>
            </div>
            <!-- /Copyright -->
        </div>
    </div>
    <!-- /Footer Bottom -->
    
</footer>
<!-- /Footer -->