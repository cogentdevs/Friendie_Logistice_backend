Dear, {{ $data['user_name'] }} <br><br>

Click on the link given below and reset your password on <a href="https://friendielogistics.com">Friendie Logistics</a> account: <br><br>

<a href="{{ $data['reset_url'] }}"><strong>Go To Link</strong></a>
<p>This link expires in {{ config('auth.passwords.users.expire', 60) }} minutes and can be used once.</p>
	

<p>Regards</p>

<p>Friendie Logistics - Team</p>
