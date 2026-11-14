@extends('layouts.frontLayout.front-design')
@section('content')

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">My Listing</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">My Listing</li>
					</ol>
				</nav>							
			</div>
		</div>
	</div>
</div>
<!-- /Breadscrumb Section -->

<!-- Dashboard Content -->
<div class="dashboard-content listing-section ">		
	<div class="container">
		
		@include('frontend.user.user-profile-bar')
		
		<div class="dash-listingcontent dashboard-info">
		    <div class="dash-cards card">
			    <div class="card-header">
				    <h4>My Listings</h4>
				    <a class="nav-link add-listing" href="{{ url('/user/add-listing') }}"><span><i class="fa-solid fa-plus"></i></span>Add Listing</a>									
				</div>
                <div class="card-body">
					<!-- <div class="listing-search">
						<div class="filter-content form-group">
							<div class="group-img">
							  <input type="text" class="form-control" placeholder="Search...">
							  <i class="feather-search"></i>
							</div>
						</div>
						<div class="sorting-div">
							 <div class="sortbyset">
								<span class="sortbytitle">Sort by</span>
								<div class="sorting-select">
									<select class="form-control select">
										<option>Newest</option>
										<option>Newest</option>
										<option>Oldest</option>
									</select>
								</div>
							</div>						
						</div>																
					</div> -->
                    <div class="table-responsive">
						<table class="listing-table datatable" id="listdata-table" >
                           	<thead>								    
								<tr>
								    <th class="no-sort">S.No</th>	
								    <th class="no-sort">Image</th>										 
								    <th class="no-sort">Details</th>										 
								    <th class="no-sort">Status</th>										 
								    <th class="no-sort">Action</th>										 
								 </tr>	
                            </thead>
                            <tbody>	
                            	<?php $i = 0; ?>
                            	@foreach($my_listings as $data)
	                            	<tr>
	                            		<td>{{ ++$i }}</td>
								     	<td>
									    	<div class="listingtable-img"> 
									    		<a href="javascript:void(0)">
									    			<img class="img-fluid avatar-img" width="100%" src="{{ asset('frontend/img/listing/'.$data->image ) }}" alt="{{ $data->title }}">
									    		</a>
									    	</div>
									 	</td>
								     	<td>
									     	<h6>
									     		<a href="javascript:void(0)">{{ $data->title }}</a>
									     	</h6>
									     	<div class="listingtable-rate">
									     		<span class="fixed-amt">{{ $data->category_name }}</span><br>
									     		<a href="javascript:void(0)" class="cat-icon">
									     			{{ $data->weight }} KG is {{ $data->ad_type }} By You On Price {{ $data->weight_price }} {{ $data->currency }}
										     	</a> <br>
									     		<span class="fixed-amt">{{ $data->from_city_name }}						     			@if($data->from_area != '')
                                                    	, {{ $data->from_area }}
	                                                @endif   
	                                                On {{ $data->departure_on }}</span>
									     		<span class="discount-amt"><i class="fa fa-fighter-jet"></i></span>
									     		<span class="fixed-amt">{{ $data->to_city_name }}
									     			@if($data->to_area != '')
                                                    	, {{ $data->to_area }}
	                                                @endif   
	                                                 On {{ $data->reached_on }}</span>
									     	</div>
                                    <p>{{ \App\Support\ListingDescription::preview($data->description, 150) }}</p>
									 	</td>
								     	<td>
								     		<span class="status-text">
								     			@if($data->isActive == 0)
		                                            Pending
								     			@elseif($data->isActive == 1)
		                                            Published
		                                        @else
		                                            Time Out
		                                        @endif
									     	</span>
								     	</td>
								     	<td>
								    		<div class="action">
								    			
												<!-- <a href="{{ url('/user/listing-details/'.$data->id.'/'.str_replace(' ', '-', strtolower($data->title)) ) }}" class="action-btn btn-view">
													<i class="feather-eye"></i>
												</a> -->
										    	<a href="{{ url('/user/edit-listing/'.$data->id) }}" class="action-btn btn-edit">
										    		<i class="feather-edit-3"></i>
										    	</a>
										    	<a href="javascript:void(0)" class="action-btn btn-trash sa-confirm-delete" param-id="{{ $data->id }}" param-route="delete-listing">
										    		<i class="feather-trash-2"></i>
										    	</a>
									    	</div>
									 	</td>
	                             	</tr>   
	                            @endforeach
                            </tbody>									 
					    </table>				
					</div>
                    @if ($paginator->hasPages())
						<div class="blog-pagination">
						    <nav>
						        <ul class="pagination">
						            {{-- Previous Page Link --}}
						            @if ($paginator->onFirstPage())
						                <li class="page-item previtem disabled" aria-disabled="true">
						                    <a class="page-link" href="#"><i class="fas fa-regular fa-arrow-left"></i> Prev</a>
						                </li>
						            @else
						                <li class="page-item previtem">
						                    <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fas fa-regular fa-arrow-left"></i> Prev</a>
						                </li>
						            @endif

						            {{-- Pagination Links --}}
	                                @for ($page = 1; $page <= $paginator->lastPage(); $page++)
	                                    @if ($page == $paginator->currentPage())
	                                        <li class="page-item active">
	                                            <a class="page-link" href="#">{{ $page }} <span class="visually-hidden">(current)</span></a>
	                                        </li>
	                                    @else
	                                        <li class="page-item">
	                                            <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
	                                        </li>
	                                    @endif
	                                @endfor

						            {{-- Next Page Link --}}
						            @if ($paginator->hasMorePages())
						                <li class="page-item nextlink">
						                    <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next <i class="fas fa-regular fa-arrow-right"></i></a>
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
										
			    </div>							
			</div>                  							
		</div>							
	</div>				
</div>			
<!-- /Dashboard Content -->

@endsection