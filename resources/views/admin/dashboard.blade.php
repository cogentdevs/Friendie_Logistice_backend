@extends('layouts.adminLayout.admin-design')
@section('content')

<div class="page-wrapper">
    <div class="content">
        <div class="row">
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service" >
                <div class="card">
                    <div class="card-body">
                        <div class="home-user">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/user.svg') }}" alt="img"></span>
                                    <h6>Users</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters" data-count="{{ $count_users }}">{{ $count_users }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-users') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-provider">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/service.svg') }}" alt="img"></span>
                                    <h6>Categories</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_category }}">{{ $count_category }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-categories') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-service">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/monitor.svg') }}" alt="img"></span>
                                    <h6>Countries</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_country }}">{{ $count_country }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-countries') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-subscription">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/money.svg') }}" alt="img"></span>
                                    <h6>Total Listing</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_all_listing }}">{{ $count_all_listing }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-listing') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-subscription">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/money.svg') }}" alt="img"></span>
                                    <h6>Total Pending Listing</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_pending_listing }}">{{ $count_pending_listing }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-pending-listing') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-subscription">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/money.svg') }}" alt="img"></span>
                                    <h6>Total Active Listing</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_active_listing }}">{{ $count_active_listing }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-listing') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-subscription">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/money.svg') }}" alt="img"></span>
                                    <h6>Total Active Carring Listing</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_active_carring_listing }}">{{ $count_active_carring_listing }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-listing/carring') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-sm-6 col-12 d-flex widget-path widget-service">
                <div class="card">
                    <div class="card-body">
                        <div class="home-user home-subscription">
                            <div class="home-userhead">
                                <div class="home-usercount">
                                    <span><img src="{{ asset('backend/img/icons/money.svg') }}" alt="img"></span>
                                    <h6>Total Active Send Listing</h6>
                                </div>
                            </div>
                            <div class="home-usercontent">
                                <div class="home-usercontents">
                                    <div class="home-usercontentcount">
                                        <img src="{{ asset('backend/img/icons/arrow-up.svg') }}" alt="img" class="me-2">
                                        <span class="counters"  data-count="{{ $count_active_send_listing }}">{{ $count_active_send_listing }}</span>
                                    </div>
                                </div>
                                <div class="homegraph">
                                    <button class="btn-filters"><a href="{{ url('admin/view-listing/send') }}">View All</a></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div> 

@endsection