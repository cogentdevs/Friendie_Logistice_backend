<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Reset Password</title>

    <!-- Favicons -->
    <link rel="shortcut icon" href="{{ asset('backend/img/favicon.png') }}">

    <!-- Feathericon CSS -->
    <link rel="stylesheet" href="{{ asset('backend/css/feather.css') }}">

    <!-- Select 2 -->
    <link rel="stylesheet" href="{{ asset('backend/css/select2.min.css') }}">

    <!-- Fontawesome CSS -->
    <link rel="stylesheet" href="{{ asset('backend/plugins/fontawesome/css/fontawesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('backend/plugins/fontawesome/css/all.min.css') }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('backend/plugins/bootstrap/css/bootstrap.min.css') }}">

    <!-- Main CSS -->
    <link rel="stylesheet" href="{{ asset('backend/css/style.css') }}">

</head>

<body>
    <div class="main-wrapper wrapper-login">
        <div class="login-pages">
            <div class="container-fluid">
                <div class="row align-items-center justify-content-center">
                    <div class="col-lg-12">
                        <div class="login-logo">
                            <img src="{{ asset('backend/img/logo.png') }}" alt="img" width="300">
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-5 mx-auto">
                        <div class="login-wrap">
                            @if(Session::has('flash_message_success'))
                                <div class="alert alert-danger alert-dismissible" style="background-color: #c10037; color: white;">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    <strong>{!! session('flash_message_success') !!}</strong>
                                </div>
                            @endif
                            @if(Session::has('flash_message_error'))
                                <div class="alert alert-danger alert-dismissible" style="background-color: #c10037; color: white;">
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    <strong>{!! session('flash_message_error') !!}</strong>
                                </div>
                            @endif
                            @if($errors->any())
                                <div class="alert alert-danger" role="alert"><ul>
                                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                                </ul></div>
                            @endif
                            <form class="form-horizontal mt-3" method="post" id="loginform" action="{{ url('/admin/reset-password/'.$user_id.'-'.$slug) }}">
                                {{ csrf_field() }}
                                <div class="login-content">
                                    <div class="login-contenthead text-center">
                                        <h5>Reset Password</h5>
                                        <p>Enter your new password.</p>
                                    </div>
                                    <div class="login-input">
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between">
                                                <label>Reset Password</label>
                                            </div>
                                            <div class="pass-group">
                                                <input type="password" name="newPassword" required class="form-control pass-input" placeholder="********">
                                                <span class="fas toggle-password fa-eye-slash"></span>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between">
                                                <label>Confirm Password</label>
                                            </div>
                                            <div class="pass-group">
                                                <input type="password" name="confirmPassword" required class="form-control pass-input" placeholder="********">
                                                <span class="fas toggle-password fa-eye-slash"></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="login-button">
                                        <button type="submit" class="btn btn-login">Reset</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="{{ asset('backend/js/jquery-3.6.4.min.js') }}"></script>

    <!-- Select 2 JS-->
    <script src="{{ asset('backend/js/select2.min.js') }}"></script>

    <!-- Bootstrap Core JS -->
    <script src="{{ asset('backend/plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>

    <!-- Sweetalert 2 -->
    <script src="{{ asset('backend/plugins/sweetalert/sweetalert2.all.min.js') }}"></script>
    <script src="{{ asset('backend/plugins/sweetalert/sweetalerts.min.js') }}"></script>

    <!-- Custom JS -->
    <script src="{{ asset('backend/js/admin.js') }}"></script>

</body>

</html>
