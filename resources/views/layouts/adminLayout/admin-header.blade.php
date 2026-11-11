<!-- Header -->
<div class="header">
    <div class="header-left"> 
        <a href="{{ url('/admin/dashboard') }}" class="logo">
            <img src="{{ asset('backend/img/logo.png') }}" alt="Logo">
        </a>
        <a href="{{ url('/admin/dashboard') }}" class=" logo-small">
            <img src="{{ asset('backend/img/logo.png') }}" alt="Logo">
        </a>
    </div>
    <a class="mobile_btn" id="mobile_btn" href="javascript:void(0);">
        <i class="fas fa-align-left"></i>
    </a>
    <div class="header-split">
        <div class="page-headers">
            <!-- <div class="search-bar">
                <span><i class="fe fe-search"></i></span>
                <input type="text" placeholder="Search" class="form-control">
            </div> -->
        </div>
        <ul class="nav user-menu">
            <!-- Notifications -->
            <li class="nav-item">
                <a href="{{ url('/') }}" class="viewsite" target="_blank"><i class="fe fe-globe me-2"></i>View Site</a>
            </li>
            <li class="nav-item dropdown has-arrow dropdown-heads flag-nav">
                <a class="nav-link" data-bs-toggle="dropdown" href="javascript:void(0);" role="button">
                    <img src="{{ asset('backend/img/flags/us1.png') }}" alt="" height="20"> 
                </a>
                <div class="dropdown-menu dropdown-menu-right">
                    <a href="javascript:void(0);" class="dropdown-item">
                        <img src="{{ asset('backend/img/flags/us.png') }}" class="me-2" alt="" height="16"> English
                    </a>
                </div>
            </li>
            <!-- <li class="nav-item dropdown has-arrow dropdown-heads ">
                <a href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class="fe fe-bell"></i>
                </a>
                <div class="dropdown-menu notifications">
                    <div class="topnav-dropdown-header">
                        <span class="notification-title">Notifications</span>
                        <a href="javascript:void(0)" class="clear-noti"> Clear All </a>
                    </div>
                    <div class="noti-content">
                        <ul class="notification-list">
                            <li class="notification-message">
                                <a href="notifications.html">
                                    <div class="media d-flex">
                                        <span class="avatar avatar-sm flex-shrink-0">
                                            <img class="avatar-img rounded-circle" alt="" src="{{ asset('backend/img/provider/provider-01.jpg') }}">
                                        </span>
                                        <div class="media-body flex-grow-1">
                                            <p class="noti-details">
                                                <span class="noti-title">Matthew Garcia have been subscribed</span>
                                            </p>
                                            <p class="noti-time">
                                                <span class="notification-time">15 Sep 2020 10:20 PM</span>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                                <button type="submit" class="btn btn-accept">Accept</button><button type="submit" class="btn btn-decline">Decline</button>
                            </li>
                            <li class="notification-message">
                                <a href="notifications.html">
                                    <div class="media d-flex">
                                        <span class="avatar avatar-sm flex-shrink-0">
                                            <img class="avatar-img rounded-circle" alt="" src="{{ asset('backend/img/provider/provider-02.jpg') }}">
                                        </span>
                                        <div class="media-body flex-grow-1">
                                            <p class="noti-details">
                                                <span class="noti-title">New User Registered “James Hardin”</span>
                                            </p>
                                            <p class="noti-time">
                                                <span class="notification-time">15 Sep 2020 10:20 PM</span>
                                            </p>
                                        </div>
                                    </div>
                                </a>
                                <button type="submit" class="btn btn-accept">Accept</button><span><button type="submit" class="btn btn-decline">Decline</button></span>
                            </li>
                            <li class="notification-message">
                                <a href="notifications.html">
                                    <div class="media d-flex">
                                        <span class="avatar avatar-sm flex-shrink-0">
                                            <img class="avatar-img rounded-circle" alt="" src="{{ asset('backend/img/provider/provider-03.jpg') }}">
                                        </span>
                                        <div class="media-body flex-grow-1">
                                            <p class="noti-details">
                                                <span class="noti-title">New Service added  “Plumbing”</span>
                                            </p>
                                            <p class="noti-time">
                                                <span class="notification-time">15 Sep 2020 10:20 PM</span>
                                            </p>
                                           
                                        </div>
                                    </div>
                                </a>
                                <button type="submit" class="btn btn-decline-accept">Accept</button>
                            </li>
                        </ul>
                    </div>
                    <div class="topnav-dropdown-footer">
                        <a href="notifications.html">View all Notifications</a>
                    </div>
                </div>
            </li> -->
            <!-- <li class="nav-item  has-arrow dropdown-heads ">
                <a href="javascript:void(0);" class="win-maximize">
                    <i class="fe fe-maximize" ></i>
                </a>
            </li> -->
            
            <!-- User Menu -->
            <li class="nav-item dropdown">
                <a href="javascript:void(0)" class="user-link  nav-link" data-bs-toggle="dropdown">
                    <span class="user-img">
                        <img class="rounded-circle" src="{{ asset('backend/img/user.jpg') }}" width="40" alt="Admin">
                        <span class="animate-circle"></span>
                    </span>
                    <span class="user-content">
                        <span class="user-name">{{ Auth::user()->name }}</span>
                        <span class="user-details">Admin</span>
                    </span>
                </a>
                <div class="dropdown-menu menu-drop-user">
                    <div class="profilemenu ">
                        <div class="user-detials">
                            <a href="">
                                <span class="profile-image">
                                    <img src="{{ asset('backend/img/user.jpg') }}" alt="img" class="profilesidebar">
                                </span>
                                <span class="profile-content">
                                    <span>{{ Auth::user()->name }}</span>
                                    <span>{{ Auth::user()->email }}</span>
                                </span>
                            </a>
                        </div>
                        <div class="subscription-menu">
                            <ul>
                                <li>
                                    <a href="{{ url('/admin/change-password') }}" >Change Password</a>
                                </li>
                            </ul>
                        </div>
                        <div class="subscription-logout">
                            <a href="{{ url('/admin/logout') }}">Log Out</a>
                        </div>
                    </div>
                </div>
            </li>
            <!-- /User Menu -->
        </ul>
    </div>
    
</div>
<!-- /Header -->
