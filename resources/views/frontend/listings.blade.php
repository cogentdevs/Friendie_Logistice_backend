@extends('layouts.frontLayout.front-design')
@section('content')

<!--Inner Banner-->
<div class="contactbanner innerbanner">        
    <div class="inner-breadcrumb">
      <div class="container">
        <div class="row align-items-center text-center">
            <div class="col-md-12 col-12 ">
               <h2 class="breadcrumb-title">Listing</h2>
                <nav aria-label="breadcrumb" class="page-breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Listing</li>
                    </ol>
                </nav>                          
            </div>
        </div>
      </div>                    
    </div>                  
</div>  
<!--/Inner Banner-->

<!-- Main Content Section -->
<div class="list-content">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 theiaStickySidebar">
                <div class="listings-sidebar">
                    <div class="card">
                        <h4><img  src="{{ asset('frontend/img/details-icon.svg') }}" alt="details-icon"> Filter</h4>
                        <form method="post" action="{{url('/listing/search/results')}}">
                            {{ csrf_field() }} 
                            <input type="hidden" name="search_from" value="listings">
                            <div class="filter-content looking-input form-group">
                                <select class="form-control select region-select" name="ad_type">
                                    <option selected value="">Type</option>
                                    <option value="Carring">Carring</option>
                                    <option value="Send">Send</option>
                                </select>       
                            </div> 
                            <div class="filter-content form-group">  
                               <select class="form-control select category-select" name="category_id">
                                    <option selected value="">Category</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="filter-content looking-input form-group input-placeholder">
                                <div class="group-img">
                                  <input type="number" class="form-control" name="weight" placeholder="Weight KG?">
                                </div>
                            </div>
                            <div class="filter-content form-group region"> 
                                <select class="form-control select region-select" name="from_city_id">
                                    <option selected value="">From City</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>                  
                            </div>
                            <div class="filter-content looking-input form-group input-placeholder">
                                <div class="group-img">
                                    <input type="text" class="form-control" name="from_area" placeholder="From Area">
                                </div>
                            </div>
                            <div class="filter-content form-group region"> 
                                <select class="form-control select region-select" name="to_city_id">
                                    <option selected value="">To City</option>
                                    @foreach($cities as $city)
                                        <option value="{{ $city->id }}">{{ $city->name }}</option>
                                    @endforeach
                                </select>                  
                            </div> 
                            <div class="filter-content looking-input form-group input-placeholder">
                                <div class="group-img">
                                    <input type="text" class="form-control" name="to_area" placeholder="To Area">
                                </div>
                            </div>
                            <div class="filter-content looking-input form-group input-placeholder">
                                <div class="group-img">
                                    <label class="col-form-label label-heading">Travel On</label>
                                    <input type="date" class="form-control" name="departure_on" placeholder="Departure">
                                </div>
                            </div>                               
                            <div class="filter-content amenities mb-0">
                                <div class="search-btn">
                                    <button class="btn btn-primary" type="submit"> <i class="fa fa-search" aria-hidden="true"></i> Search</button>
                                </div>  
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row sorting-div">
                    <div class="col-lg-4 col-sm-4 align-items-center d-flex">
                        <div class="count-search">
                            <p>Showing <span>{{ $page_listing_count }}</span> of {{ $total_listing_count }} Results</p>
                        </div>
                    </div>
                </div>
                <div class="blog-listview">
                    @foreach($listing as $data)
                        <div class="card">
                            <div class="blog-widget">
                                <div class="blog-img">
                                    <a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">
                                     <img src="{{ asset('frontend/img/listing/'.$data->image ) }}" class="img-fluid" alt="blog-img">
                                    </a>
                                    <div class="fav-item">
                                        <span class="featured-text">{{ $data->ad_type }}</span>
                                        <a href="javascript:void(0)" class="fav-icon">
                                            <i class="feather-heart"></i>
                                        </a>                                        
                                    </div>                                                              
                                </div>
                                <div class="bloglist-content">
                                    <div class="card-body">
                                        <div class="blogfeaturelink">
                                            <div class="blog-features">
                                               <a href="javascript:void(0);"><span> <i class="fa-regular fa-circle-stop"></i> {{ $data->category_name }}</span></a>
                                            </div>                                                                    
                                            <div class="blog-author"> 
                                              <div class="blog-author-img">
                                                <img src="{{ asset('frontend/img/profiles/'.$data->createdByImage ) }}" alt="author">
                                              </div>
                                              <a href="javascript:void(0);">{{ $data->createdBy }}</a>
                                            </div>
                                        </div> 
                                        <h6><a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">{{ $data->title }}</a></h6>                 

                                        <div class="blog-location-details">
                                            <div class="location-info">
                                               <i class="feather-map-pin"></i>From {{ $data->from_country_name }}, {{ $data->from_city_name }}
                                            </div>
                                            <div class="location-info">
                                                <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->departure_on)) }}
                                            </div>
                                        </div>
                                        <div class="blog-location-details">
                                            <div class="location-info">
                                               <i class="feather-map-pin"></i>To {{ $data->to_country_name }}, {{ $data->to_city_name }}
                                            </div>
                                            <div class="location-info">
                                                <i class="fa-regular fa-calendar-days"></i> {{ date("d M, Y", strtotime($data->reached_on)) }}
                                            </div>
                                        </div>

                                        @if($data->show_contact_info == 1)
                                            <div class="blog-location-details">
                                                <div class="location-info">
                                                   <i class="feather-phone-call"></i> {{ $data->createdByPhone }}
                                                </div>
                                            </div>
                                            <div class="blog-location-details">
                                                <div class="location-info">
                                                   <i class="feather-mail"></i> {{ $data->createdByEmail }}
                                                </div>
                                            </div>
                                        @endif
                                        <p class="ratings">
                                            <span>{{ $data->weight_price }} {{ $data->currency }} Per Kg</span><!--  (50) -->
                                        </p>
                                        <div class="amount-details">
                                            <div class="amount">
                                                <span class="validrate">{{ $data->ad_type }} {{ $data->weight }} Kg</span>
                                                <!-- <span>$450</span> -->
                                            </div>
                                            <a href="{{ url('/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}">View details</a>
                                        </div>  
                                    </div>  
                                </div>           
                            </div> 
                        </div>
                    @endforeach
                </div>  
                <!--Pagination--> 
                @if ($listing->hasPages())
                    <div class="listing-pagination">
                        <nav>
                            <ul class="pagination">
                                {{-- Previous Page Link --}}
                                @if ($listing->onFirstPage())
                                    <li class="page-item previtem disabled" aria-disabled="true">
                                        <a class="page-link" href="#"><i class="fas fa-regular fa-arrow-left"></i> Prev</a>
                                    </li>
                                @else
                                    <li class="page-item previtem">
                                        <a class="page-link" href="{{ $listing->previousPageUrl() }}" rel="prev"><i class="fas fa-regular fa-arrow-left"></i> Prev</a>
                                    </li>
                                @endif

                                {{-- Pagination Links --}}
                                @for ($page = 1; $page <= $listing->lastPage(); $page++)
                                    @if ($page == $listing->currentPage())
                                        <li class="page-item active">
                                            <a class="page-link" href="#">{{ $page }} <span class="visually-hidden">(current)</span></a>
                                        </li>
                                    @else
                                        <li class="page-item">
                                            <a class="page-link" href="{{ $listing->url($page) }}">{{ $page }}</a>
                                        </li>
                                    @endif
                                @endfor

                                {{-- Next Page Link --}}
                                @if ($listing->hasMorePages())
                                    <li class="page-item nextlink">
                                        <a class="page-link" href="{{ $listing->nextPageUrl() }}" rel="next">Next <i class="fas fa-regular fa-arrow-right"></i></a>
                                    </li>
                                @else
                                    <li class="page-item nextlink disabled" aria-disabled="true">
                                        <a class="page-link" href="#">Next <i class="fas fa-regular fa-arrow-right"></i></a>
                                    </li>
                                @endif
                            </ul>
                        </nav>
                    </div>
                    @endif


                <!--/Pagination-->                  
            </div>
        </div>
    </div>            
</div>  
<!-- /Main Content Section -->
                                     

@endsection