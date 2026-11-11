@extends('layouts.adminLayout.admin-design')
@section('content')

<div class="page-wrapper page-settings">
    <div class="content">
        @if(Session::has('flash_message_error'))
            <div class="alert alert-danger alert-block alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_error') !!}</strong>
            </div>
        @endif
        @if(Session::has('flash_message_success'))
            <div class="alert alert-success alert-block alert-dismissible">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <strong>{!! session('flash_message_success') !!}</strong>
            </div>
        @endif
        <div class="content-page-header content-page-headersplit mb-0">
            <h5>Listing</h5>
            <div class="list-btn">
                <ul>
                    <li>
                        <a class="btn-filters active" href="{{ url('/admin/view-listing') }}"><i class="fe fe-list"></i> </a>
                    </li>
                </ul>
            </div>
        </div>
        <div class="row">
            <div class="col-12 ">
                <div class="table-resposnive table-div">
                    <table class="table datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Image</th>
                                <th>Detail</th>
                                <th>Contact</th>
                                <th>Featured</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; ?>
                            @foreach($listing as $data)
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>
                                        <div class="table-imgname">
                                            <img class="me-2" src="{{ asset('frontend/img/listing/'.$data->image ) }}" alt="{{ $data->title }}">
                                        </div>
                                    </td>
                                    <td>
                                        <h6>
                                            <a href="javascript:void(0)">{{ $data->title }}</a>
                                        </h6>
                                        <div class="listingtable-rate">
                                            <span class="fixed-amt">{{ $data->category_name }}</span><br>
                                            <a href="javascript:void(0)" class="cat-icon">
                                                {{ $data->weight }} KG is {{ $data->ad_type }} By <strong>{{ $data->createdBy }}</strong> On Price {{ $data->weight_price }} {{ $data->currency }}
                                            </a> <br>
                                            <span class="fixed-amt">{{ $data->from_city_name }}
                                                @if($data->from_area != '')
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
                                        <p>{!! mb_strimwidth(html_entity_decode($data->description),0,100,'...') !!}</p>
                                    </td>
                                    <td>
                                        @if($data->show_contact_info == 1)
                                            Show
                                        @else
                                            Not Show
                                        @endif
                                    </td>
                                    <td>
                                        @if($data->isFeatured == 1)
                                            Yes <a href="{{ url('/admin/unmark-featured/listing/'.$data->id) }}" style="display: inline; padding: 2px 10px;" class="btn btn-cancel">Unmark</a>
                                        @else
                                            No 
                                            <a href="{{ url('/admin/mark-featured/listing/'.$data->id) }}" style="display: inline; padding: 2px 10px;" class="btn btn-submit">Mark</a>
                                        @endif
                                    </td>
                                    <td>
                                        @if($data->isActive == 0)
                                            Pending
                                        @elseif($data->isActive == 1)
                                            Active
                                        @else
                                            Not Active
                                        @endif
                                    </td>
                                    <td>
                                        <div class="table-actions d-flex">
                                            @if($data->isActive == 0)
                                                <a class="btn btn-primary me-2" href="{{ url('/admin/mark-published/listing/'.$data->id) }}" >Active</a>
                                            @endif
                                            <a class="delete-table me-2" href="{{ url('/admin/edit-listing/'.$data->id) }}" >
                                               <img src="{{ asset('backend/img/icons/edit.svg') }}" alt="svg">
                                            </a>
                                            <a class="delete-table sa-confirm-delete" href="javascript:void(0);" param-id="{{ $data->id }}" param-route="delete-listing">
                                                <img src="{{ asset('backend/img/icons/delete.svg') }}" alt="svg">
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach                            
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div> 

@endsection
