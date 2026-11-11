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

<div class="">			        
	<ul class="dashborad-menus">
		<li class="@if(Request::path() == 'user/dashboard') active @endif">
			<a href="{{ url('/user/dashboard') }}">
				<i class="feather-grid"></i> <span>Dashboard</span>
			</a>
		</li>
		<li class="@if(Request::path() == 'user/profile') active @endif">
			<a href="{{ url('/user/profile') }}">
				<i class="fa-solid fa-user"></i> <span>Profile</span>
			</a>
		</li>
		<li class="@if(Request::path() == 'user/my-listing') active @endif">
			<a href="{{ url('/user/my-listing') }}">
				<i class="feather-list"></i> <span>My Listing</span>
			</a>
		</li>
		<!-- <li class="@if(Request::path() == 'user/bookmarks') active @endif">
			<a href="{{ url('/user/bookmarks') }}">
				<i class="fas fa-solid fa-heart"></i> <span>Bookmarks</span>
			</a>
		</li> -->
		<li class="@if(Request::is('user/messages*')) active @endif">
			<a href="{{ url('/user/messages') }}">
				<i class="fa-solid fa-comment-dots"></i> <span>Messages</span>
			</a>
		</li>
		<li class="@if(Request::path() == 'user/reviews') active @endif">
			<a href="{{ url('/user/reviews') }}">
				<i class="fas fa-solid fa-star"></i> <span>Reviews</span>
			</a>
		</li>
		<li>
			<a href="{{ url('/user/logout') }}">
				<i class="fas fa-light fa-circle-arrow-left"></i> <span>Logout</span>
			</a>
		</li>
	</ul>								
</div>