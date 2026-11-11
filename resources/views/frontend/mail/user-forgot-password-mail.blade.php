Dear, {{ $data['user_name'] }} <br><br>

Click on the link given below and reset your password on <a href="https://friendielogistics.com">Friendie Logistics</a> account: <br><br>

@if($data['user_id'] == 1)
	<!-- <a href="http://localhost:8000/admin/reset-password/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a> -->
	<a href="https://friendielogistics.com/admin/reset-password/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a>
@else
	<!-- <a href="http://localhost:8000/reset-password/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a> -->
	<a href="https://friendielogistics.com/reset-password/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a>
@endif
	

<p>Regards</p>

<p>Friendie Logistics - Team</p>