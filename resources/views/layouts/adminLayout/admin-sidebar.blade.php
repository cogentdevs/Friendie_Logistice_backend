<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <a href="{{ url('/admin/dashboard') }}">
                <img src="{{ asset('backend/img/logo.png') }}" class="img-fluid logo" alt="Logo">
            </a>
            <a href="{{ url('/admin/dashboard') }}">
                <img src="{{ asset('backend/img/logo.png') }}" class="img-fluid logo-small" alt="Logo">
            </a>
        </div>
    </div>
    
    <div class="sidebar-inner slimscroll">
        <div id="sidebar-menu" class="sidebar-menu">
            <ul>
                <li class="menu-title m-0">
                    <h6>Home</h6>
                </li>
                <li>
                    <a href="{{ url('/admin/dashboard') }}" class="active"><i class="fe fe-grid"></i> <span>Dashboard</span></a>
                </li>
                <li class="menu-title">
                    <h6>Listing</h6>
                </li>
                <li>
                    <a href="{{ url('/admin/view-listing') }}" ><i class="fe fe-briefcase"></i> 
                        <span>Listings</span>
                    </a>
                </li>
                <li>
                    <a href="{{ url('/admin/view-categories') }}"><i class="fe fe-file-text"></i> 
                        <span>Categories</span>
                    </a>
                </li>
                <li class="menu-title">
                    <h6>Others</h6>
                </li>
                <li>
                    <a href=""><i class="fe fe-message-square"></i> <span>Chat</span></a>
                </li>
                <li>
                    <a href="{{ url('/admin/view-subscribers') }}"><i class="fe fe-bell"></i> <span>Subscribers</span></a>
                </li>
                <li class="menu-title">
                    <h6>Locations</h6>
                </li>
                <li class="submenu">
                    <a href="javascript:void(0);"><i class="fe fe-map-pin"></i> 
                        <span>Location</span>
                        <span class="menu-arrow"><i class="fe fe-chevron-right"></i></span>
                    </a>
                    <ul>
                        <li>
                            <a href="{{ url('/admin/view-countries') }}">Countries</a>
                        </li>
                        <li>
                            <a href="{{ url('/admin/view-cities') }}">Cities</a>
                        </li>
                    </ul>
                </li>
                <li class="menu-title">
                    <h6>User Management</h6>
                </li>
                <li>
                    <a href="{{ url('/admin/view-users') }}"><i class="fe fe-user"></i> 
                        <span> Users</span>
                    </a>
                </li>
                <li class="menu-title">
                    <h6>Support</h6>
                </li>
                <li>
                    <a href="{{ url('/admin/view-contact-messages') }}"><i class="fe fe-message-square"></i> <span>Contact Messages</span></a>
                </li>
                <li class="menu-title">
                    <h6>Settings</h6>
                </li>
                <li>
                    <a href="{{ url('/admin/view-meta-tags') }}" ><i class="fe fe-settings"></i> <span>Meta Tags</span></a>
                </li>
                <li>
                    <a href="{{ url('/admin/view-contact-info-item') }}" ><i class="fe fe-settings"></i> <span>Contact Info</span></a>
                </li>
                <li>
                    <a href="{{ url('/admin/change-password') }}" ><i class="fe fe-settings"></i> <span>Change Password</span></a>
                </li>
                <li>
                    <a href="{{ url('/admin/logout') }}"><i class="fe fe-log-out"></i> <span>Logout</span></a>
                </li>
            </ul>
        </div>
    </div>
</div>
<!-- /Sidebar -->   
