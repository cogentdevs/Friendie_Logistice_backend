@extends('layouts.frontLayout.front-design')
@section('content')
<div class="login-content"><div class="container"><div class="row">
    <div class="col-md-6 col-lg-5 mx-auto"><div class="login-wrap">
        <h3>Request an activation link</h3>
        <p>Links expire after 24 hours. Disabled accounts require support review.</p>
        @foreach(['flash_message_success', 'flash_message_error'] as $message)
            @if(session()->has($message))<div class="alert alert-info">{{ session($message) }}</div>@endif
        @endforeach
        <form method="post" action="{{ url('/resend-activation') }}">
            @csrf
            <label for="activation-email">Email address</label>
            <input id="activation-email" type="email" name="email" required maxlength="255" class="form-control" autocomplete="email">
            <button type="submit" class="btn btn-primary mt-3">Send activation link</button>
        </form>
        <a href="{{ url('/login') }}">Back to sign in</a>
    </div></div>
</div></div></div>
@endsection
