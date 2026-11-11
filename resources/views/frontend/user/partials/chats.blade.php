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