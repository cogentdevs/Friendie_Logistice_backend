@extends('layouts.frontLayout.front-design')
@section('content')

<style type="text/css">
    /* Set the width of the intl-tel-input container to 100% */
.iti {
    width: 100% !important;
}

/* Set the width of the input itself to 100% and adjust padding/margins */
.iti .form-control,
.iti input[type="tel"] {
    width: 100% !important;
    padding: 8px; /* Adjust padding to match your theme */
    border-radius: 4px; /* Adjust for rounded corners, if needed */
    border: 1px solid #ced4da; /* Add your border style */
}

/* Adjust the flag dropdown icon styling */
.iti__flag-container {
    padding: 0 8px; /* Adjust padding to align with the design */
    background-color: #f8f9fa; /* Optional: Set background color for consistency */
    border-radius: 4px 0 0 4px;
    border-right: 1px solid #ced4da;
}

</style>

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
    <div class="container">
        <div class="row align-items-center text-center">
            <div class="col-md-12 col-12">
                <h2 class="breadcrumb-title">Create an Account</h2>
                <nav aria-label="breadcrumb" class="page-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Register</li>
                    </ol>
                </nav>                          
            </div>
        </div>
    </div>
</div>
<!-- /Breadscrumb Section -->

<!-- Login Section -->      
<div class="login-content">
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-lg-5 mx-auto">
                <div class="login-wrap register-form">

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
                    @if ($errors->has('g-recaptcha-response'))
                        <div class="alert alert-danger alert-dismissible" style="background-color: #c10037; color: white;">
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            <strong>{{ $errors->first('g-recaptcha-response') }}</strong>
                        </div>
                    @endif
                    
                    <div class="login-header">
                        <h3>Create an Account</h3>
                        <p>Lets start with <span>Friendie Logistics</span></p>                              
                    </div>
                    
                    <!-- Login Form -->
                    <form class="form-horizontal mt-3" method="post" action="{{url('/sign-up')}}">
                        {{ csrf_field() }}
                        <div class="form-group group-img">
                            <div class="group-img">
                                <i class="feather-user"></i>
                                <input type="text" class="form-control" name="name" placeholder="Full Name" required>
                            </div>
                        </div>
                        <div class="form-group group-img">
                            <div class="group-img">
                                <i class="feather-mail"></i>
                                <input type="text" class="form-control" name="email" placeholder="Email Address" required>
                            </div>
                        </div>

                        <div class="form-group group-img">
                            <div class="group-img">
                                <select class="form-control select" name="country_id" id="country_id" required>
                                    <option selected disabled>Select Country</option>
                                    @foreach($countries as $data)
                                        <option value="{{ $data->id }}" data-code="{{ $data->code }}">{{ $data->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="form-group group-img">
                            <div class="group-img">
                                <i class="feather-phone-call"></i>
                                <input type="tel" class="form-control" name="phone" id="phone" style="width: 100% !important; padding: 8px !important; border: 1px solid #ced4da !important;" placeholder="Phone Number" required>
                                <small id="phone-error" class="text-danger" style="display:none;">Invalid phone number format.</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="pass-group group-img">
                                <i class="feather-lock"></i>
                                <input type="password" class="form-control pass-input" name="password" placeholder="Password" required>
                                <span class="toggle-password feather-eye"></span>
                            </div>
                        </div>

                        <div class="form-group">
                            {!! NoCaptcha::renderJs() !!}
                            {!! NoCaptcha::display() !!}
                        </div>
                        
                        <button class="btn btn-primary w-100 login-btn" type="submit">Create Account</button>
                        <div class="register-link text-center">
                            <p>Already have an account? <a class="forgot-link" href="{{ url('/login') }}">Sign In</a></p>
                        </div>
                        <!-- <div class="login-or">
                            <span class="or-line"></span>
                            <span class="span-or">Sign in with Social Media Accounts</span>
                        </div>                  
                        <div class="social-login">
                            <a href="#" class="btn btn-apple w-100"><img src="{{ asset('frontend/img/apple.svg') }}" class="me-1" alt="img">Sign in with Apple</a>
                        </div>
                        <div class="social-login">
                            <a href="#" class="btn btn-google w-100"><img src="{{ asset('frontend/img/google.svg') }}" class="me-1" alt="img">Sign in with Google</a>
                        </div>
                        <div class="social-login">
                            <a href="#" class="btn btn-facebook w-100 mb-0"><img src="{{ asset('frontend/img/facebook.svg') }}" class="me-2" alt="img">Continue with Facebook</a>
                        </div>  -->                             
                    </form>
                    <!-- /Login Form -->
                                    
                </div>
            </div>
        </div>
        
    </div>
</div>
<!-- /Login Section -->

@endsection

<!-- Include Intl-Tel-Input styles and scripts if not using npm -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const phoneInputField = document.getElementById("phone");
        const phoneError = document.getElementById("phone-error");

        // Initialize intl-tel-input with options
        const iti = window.intlTelInput(phoneInputField, {
            initialCountry: "auto",
            separateDialCode: true,
            geoIpLookup: function (callback) {
                fetch('https://ipinfo.io/json?token=<YOUR_TOKEN>')
                    .then(response => response.json())
                    .then(data => callback(data.country))
                    .catch(() => callback("us"));
            },
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js",
        });

        phoneInputField.addEventListener("keyup", function () {
            if (iti.isValidNumber()) {
                phoneError.style.display = "none";
            } else {
                phoneError.style.display = "block";
            }
        });

        // Update phone number validation based on selected country
        document.getElementById('country_id').addEventListener('change', function () {
            const selectedCountryCode = this.options[this.selectedIndex].getAttribute('data-code');
            iti.setCountry(selectedCountryCode);
        });
    });
</script>