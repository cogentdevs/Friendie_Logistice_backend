@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Login</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Login</li>
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
				<div class="login-wrap">

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
					
					<div class="login-header">
						<h3>Welcome Back</h3>
						<p>Please Enter your Details</p>								
					</div>
					
					<!-- Login Form -->
					<form class="form-horizontal mt-3" method="post" action="{{url('/login')}}">
                        {{ csrf_field() }}
                        <input type="hidden" name="from" value="main">
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
									<input type="checkbox" name="remember" {{ isset($remember) ? 'checked' : '' }}>
									<!-- <input type="checkbox" name="remember" class="rememberme"> -->
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
						</div> -->								
					</form>
					<!-- /Login Form -->
									
				</div>
			</div>
		</div>
		
	</div>
</div>
<!-- /Login Section -->

@endsection