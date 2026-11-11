@extends('layouts.frontLayout.front-design')
@section('content')

<style type="text/css">
	#chats {
	    display: flex;
        flex-direction: column-reverse;
	    scroll-behavior: smooth;
	}
</style>

<!-- Breadscrumb Section -->
<div class="breadcrumb-bar">
	<div class="container">
		<div class="row align-items-center text-center">
    		<div class="col-md-12 col-12">
	    	    <h2 class="breadcrumb-title">Messages</h2>
		    	<nav aria-label="breadcrumb" class="page-breadcrumb">
					<ol class="breadcrumb">
						<li class="breadcrumb-item"><a href="{{ url('/') }}">Home</a></li>
						<li class="breadcrumb-item active" aria-current="page">Messages</li>
					</ol>
				</nav>							
			</div>
		</div>
	</div>
</div>
<!-- /Breadscrumb Section -->

<!-- Profile Content -->
<div class="dashboard-content listing-section">		
	<div class="container">
		
		@include('frontend.user.user-profile-bar')
		
		<div class="profile-content">
		     <div class="row dashboard-info chat-window">
			    <div class="col-lg-4 mt-3">
				     <div class="chat-cont-left">
					    <div class="chat-search">
							<div class="form-group">
								<div class="group-img">
									<img src="{{ asset('frontend/img/chatsearch.svg') }}" alt="">
									<input type="text" class="form-control" id="search" name="search" placeholder="Search">
									<input type="hidden" name="post_ad_id" id="post_ad_id" value="{{ $post_id }}">
									<input type="hidden" name="current_url" id="current_url" value="{{ url()->full() }}">
							    </div>
							</div>
						</div>
						<div class="chat-users-list">
							<div class="chat-scroll" id="chat_user_list">
								@foreach($rooms as $room)
									@php 
                                        $room_id = $room->id; 
                                        $room_slug = md5($room_id);

                                        if($room->from_user_id == Auth::user()->id){
                                        	$userName = $room->toUserName; 
                                        	$userImage = $room->toUserImage; 
                                        }elseif($room->to_user_id == Auth::user()->id){
                                        	$userName = $room->fromUserName; 
                                        	$userImage = $room->fromUserImage; 
                                        }

                                        $url = url('/user/messages/' . $room->post_ads_id . '/' . str_replace(' ', '-', strtolower($room->ad_title)) . '/' . $room_id . '=' . $room_slug);

                                    @endphp
									<a href="{{ $url }}" class="media d-flex @if(Request::fullUrl() == $url) active @endif">
										<div class="media-img-wrap flex-shrink-0">
											<div class="avatar">
											<img src="{{ asset('frontend/img/profiles/'.$userImage ) }}" alt="User Image" class="avatar-img rounded-circle">
											</div>
										</div>
										<div class="media-body flex-grow-1">
											@if($room->unread_message_count > 0) 
												<div>
													<div class="user-name"><strong>{{ $userName }}</strong></div>
													<div class="user-last-chat"><strong>{{ $room->message }}</strong></div>
												</div>
												<div>
													<div class="last-chat-time block">{{ $room->message_on }}</div>
													<div class="badge badge-success rounded-pill" style="float: right;">{{ $room->unread_message_count }}</div>
												</div>
											@else
												<div>
													<div class="user-name">{{ $userName }}</div>
													<div class="user-last-chat">@if($room->msg_from_user == Auth::user()->id) You: @endif {{ $room->message }}</div>
												</div>
												<div>
													<div class="last-chat-time block">{{ $room->message_on }}</div>
												</div>
											@endif
										</div>
									</a>
								@endforeach
							</div>
						</div>		 
					</div>					
				</div>
			    <div class="col-lg-8 mt-3">
				    <div class="chat-cont-right">
				    	@if(!empty($room_messages))
						    <div class="chat-header">
							    <a id="back_user_list" href="javascript:void(0)" class="back-user-list">
								   <i class="fa-solid fa-chevron-left"></i>
								</a>
								<div class="media d-flex align-items-center">
									@php 
	                                    if($current_room->from_user_id == Auth::user()->id){
	                                    	$userName = $current_room->toUserName; 
	                                    	$userImage = $current_room->toUserImage; 
	                                    	$toUserID = $current_room->to_user_id;
	                                    }elseif($current_room->to_user_id == Auth::user()->id){
	                                    	$userName = $current_room->fromUserName; 
	                                    	$userImage = $current_room->fromUserImage; 
	                                    	$toUserID = $current_room->from_user_id;
	                                    }
	                                @endphp
									<div class="media-img-wrap flex-shrink-0">
										<div class="avatar">
										<img src="{{ asset('frontend/img/profiles/'.$userImage ) }}" alt="User Image" class="avatar-img rounded-circle">
										</div>
									</div>
									<div class="media-body flex-grow-1">
										<div class="user-name">{{ $userName }}</div>
								    </div>
								</div>									
							</div>
							<div class="chat-body">
								<div class="chat-scroll" id="chats">
									<ul class="list-unstyled">
										@foreach($room_messages as $msg)
											@if($msg->from_user_id == Auth::user()->id)
												<li class="media d-flex sent">
													<div class="media-body flex-grow-1">
														<div class="msg-box">
															<div>
																<p>{{ $msg->message }}</p>
																<ul class="chat-msg-info">
																	<li>
																		<div class="chat-time">
																		    <span>{{ $msg->message_on }}</span>
																		</div>
																	</li>
																</ul>
															</div>
														</div>												
													</div>
												</li>
											@else 
												<li class="media d-flex received">
													<div class="media-body flex-grow-1">
														<div class="msg-box">
															<div>
																<p>{{ $msg->message }}</p>
																<ul class="chat-msg-info">
																	<li>
																		<div class="chat-time">
																		    <span>{{ $msg->message_on }}</span>
																		</div>
																	</li>
																</ul>
															</div>
														</div>	
													</div>
												</li>
											@endif
										@endforeach
									</ul>									
								</div>
							</div> 	
	                        <div class="chat-footer">
								<div class="input-group">
	                                <div class="attach-btn">
	                                	<input type="hidden" name="post_ad_room_id" id="post_ad_room_id" value="{{ $current_room->id }}">
	                                	<input type="hidden" name="from_user_id" id="from_user_id" value="{{ Auth::user()->id }}">
	                                	<input type="hidden" name="to_user_id" id="to_user_id" value="{{ $toUserID }}">										
										<input type="text" class="input-msg-send form-control" name="message" id="message" placeholder="Type something">
									</div>
									<button type="button" class="btn msg-send-btn" id="meg_send"><i class="fas fa-paper-plane"></i></button>
								</div>
							</div>
						@else
							<div class="d-flex justify-content-center align-items-center" style="height: 750px;">
								<img width="50%" src="{{ asset('frontend/img/msg-icon.png' ) }}" alt="msg-icon" class="img-fluid">
							</div>
						@endif

					</div>		
				</div>                       						
			 </div>				
		</div>
	</div>		
</div>		
<!-- /Profile Content -->

@endsection

<script>
    $(document).ready(function() {
        function scrollToBottom() {
            var chatDiv = $('#chats');
            chatDiv.scrollTop(chatDiv.prop("scrollHeight"));
        }
        scrollToBottom(); // Should scroll to the bottom
    });
</script>