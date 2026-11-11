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
            <h5>Meta Tags</h5>
            <div class="list-btn">
                <ul>
                    <li>
                        <a class="btn-filters active" href="{{ url('/admin/view-meta-tags') }}"><i class="fe fe-list"></i> </a>
                    </li>
                    <li>
                        <a class="btn btn-primary" href="{{ url('/admin/add-meta-tags') }}"><i class="fa fa-plus me-2"></i>Add Meta Tags </a>
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
                                <th>Title</th>
                                <th>Keywords</th>
                                <th>Description</th>
                                <th>Slug URL</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 0; ?>
                            @foreach($meta_tags as $data)
                                <tr>
                                    <td>{{ ++$i }}</td>
                                    <td>{{ $data->title }}</td>
                                    <td>{{ $data->keywords }}</td>
                                    <td>{!! html_entity_decode($data->description) !!}</td>
                                    <td>{{ $data->url_slug }}</td>
                                    <td>
                                        <div class="table-actions d-flex">
                                            <a class="delete-table me-2" href="{{ url('/admin/edit-meta-tags/'.$data->id) }}" >
                                               <img src="{{ asset('backend/img/icons/edit.svg') }}" alt="svg">
                                            </a>
                                            <a class="delete-table sa-confirm-delete" href="javascript:void(0);" param-id="{{ $data->id }}" param-route="delete-meta-tag">
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
