@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Activate Your Account</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Activate Your Account</li>
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
						<h3>Activate Your Account</h3>		
					</div>
					
					<!-- Login Form -->
					<form class="form-horizontal mt-3" method="post" action="{{ $activation_url }}">
                        {{ csrf_field() }}
						<button class="btn btn-primary w-100 login-btn" type="submit">Activate</button>									
					</form>		
					<!-- /Login Form -->
									
				</div>
			</div>
		</div>
		
	</div>
</div>
<!-- /Login Section -->

@endsection
