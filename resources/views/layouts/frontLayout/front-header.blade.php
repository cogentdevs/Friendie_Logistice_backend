<!-- Header -->
<header class="header">
    <div class="container">
        <nav class="navbar navbar-expand-lg header-nav">
            <div class="navbar-header">
                <a id="mobile_btn" href="javascript:void(0);">
                    <span class="bar-icon">
                        <span></span>
                        <span></span>
                        <span></span>
                    </span>
                </a>
                <a href="{{ url('/') }}" class="navbar-brand logo">
                    <img src="{{ asset('backend/img/logo.png') }}" class="img-fluid" alt="Logo">
                </a>                        
            </div>
            <div class="main-menu-wrapper">
                <div class="menu-header">
                    <a href="{{ url('/') }}" class="menu-logo">
                        <img src="{{ asset('backend/img/logo.png') }}" class="img-fluid" alt="Logo">
                    </a>
                    <a id="menu_close" class="menu-close" href="javascript:void(0);"> <i class="fas fa-times"></i></a>
                </div>
                <ul class="main-nav">
                    <li class="@if(Request::path() == '/') active @endif">
                        <a href="{{ url('/') }}">Home</a>
                    </li>
                    <li class="@if(Request::path() == '/listing') active @endif">
                        <a href="{{ url('/listing') }}">Listings</a>
                    </li>
                    <li class="@if(Request::path() == '/about') active @endif">
                        <a href="{{ url('/about') }}">About Us</a>
                    </li>
                    <li class="@if(Request::path() == '/contact') active @endif">
                        <a href="{{ url('/contact') }}">Contact</a>
                    </li>
                    @if(Auth::user())
                        @if(Auth::user()->hasRole('user'))
                            <li class="login-link">
                                <a href="{{ url('/user/add-listing') }}"><i class="fa-solid fa-plus"></i>Add Listing</a>
                            </li>

                            <li class="login-link">
                                <a href="#">
                                    <img src="{{ asset('frontend/img/profiles/'.Auth::user()->image ) }}" class="rounded-circle" width="15%" height="35" alt="">
                                    <span>{{ Auth::user()->name }}</span>
                                </a>
                            </li>
                            <li class="login-link">
                                <a href="{{ url('/user/dashboard') }}">Dashboard</a>
                            </li>
                            <li class="login-link">
                                <a href="{{ url('/user/profile') }}">Profile Settings</a>
                            </li>
                            <li class="login-link">
                                <a href="{{ url('/user/logout') }}">Logout</a>
                            </li> 
                        @endif
                    @else
                        <li class="login-link @if(Request::path() == '/sign-up') active @endif">
                            <a href="{{ url('/sign-up') }}">Sign Up</a>
                        </li>
                        <li class="login-link @if(Request::path() == '/login') active @endif">
                            <a href="{{ url('/login') }}"> Sign In</a>
                        </li>

                        <li class="login-link">
                            <a href="{{ url('/login') }}"><i class="fa-solid fa-plus"></i>Add Listing</a>
                        </li>
                    @endif
                </ul>
            </div>
            <ul class="nav header-navbar-rht">
                @if(Auth::user())
                    @if(Auth::user()->hasRole('user'))
                        <li class="nav-item">
                            <a class="nav-link add-listing" href="{{ url('/user/add-listing') }}"><i class="fa-solid fa-plus"></i>Add Listing</a>
                        </li>

                        <li class="nav-item dropdown has-arrow logged-item">
                            <a href="#" class="dropdown-toggle profile-userlink" data-bs-toggle="dropdown" aria-expanded="false">
                                <img src="{{ asset('frontend/img/profiles/'.Auth::user()->image ) }}" alt="">
                                <span>{{ Auth::user()->name }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ url('/user/dashboard') }}">Dashboard</a>
                                <a class="dropdown-item" href="{{ url('/user/profile') }}">Profile Settings</a>
                                <a class="dropdown-item" href="{{ url('/user/logout') }}">Logout</a>
                            </div>
                        </li> 
                    @endif
                @else
                    <li class="nav-item @if(Request::path() == '/sign-up') active @endif">
                        <a class="nav-link header-reg" href="{{ url('/sign-up') }}">Sign Up</a>
                    </li>
                    <li class="nav-item @if(Request::path() == '/login') active @endif">
                        <a class="nav-link header-login" href="{{ url('/login') }}"> Sign In</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link add-listing" href="{{ url('/login') }}"><i class="fa-solid fa-plus"></i>Add Listing</a>
                    </li>
                @endif
            </ul>
        </nav>
    </div>
</header>
<!-- /Header -->