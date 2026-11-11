@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Forgot Password</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Forgot Password</li>
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
				<div class="login-wrap password-form">

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
						<h3>Reset Password</h3>
						<p>Enter your new password.</p>								
					</div>
					
					<!-- Login Form -->
					<form class="form-horizontal mt-3" method="post" action="{{ url('/reset-password/'.$user_id.'-'.$slug) }}">
                        {{ csrf_field() }}
						<div class="form-group">
							<div class="pass-group group-img">
							    <i class="feather-lock"></i>
								<input type="password" class="form-control pass-input" name="newPassword" placeholder="New Password">
								<span class="toggle-password feather-eye"></span>
							</div>
						</div>
						<div class="form-group">
							<div class="pass-group group-img">
							    <i class="feather-lock"></i>
								<input type="password" class="form-control pass-input" name="confirmPassword" placeholder="Confirm Password">
								<span class="toggle-password feather-eye"></span>
							</div>
						</div>
						<button class="btn btn-primary w-100 login-btn" type="submit">Reset</button>									
					</form>		
					<!-- /Login Form -->
									
				</div>
			</div>
		</div>
		
	</div>
</div>
<!-- /Login Section -->

@endsection