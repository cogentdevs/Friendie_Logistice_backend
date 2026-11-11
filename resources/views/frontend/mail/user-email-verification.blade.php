Dear, {{ $data['user_name'] }} <br><br>

Click on the link given below and Activate your account on <a href="https://friendielogistics.com">Friendie Logistics</a><br><br>

<!-- <a href="http://localhost:8000/activate-your-account/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a> -->
<a href="https://friendielogistics.com/activate-your-account/{{ $data['user_id'] }}-{{ bin2hex(random_bytes(10)) }}"><strong>Go To Link</strong></a>
	

<p>Regards</p>

<p>Friendie Logistics - Team</p>