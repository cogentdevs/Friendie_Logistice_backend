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
	<a href="{{ $url }}" class="media d-flex">
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