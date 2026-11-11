<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0">
    <title>Admin</title>

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
                            <img src="{{ asset('backend/img/logo.png') }}" alt="img">
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
                            <form class="form-horizontal mt-3" method="post" id="loginform" action="{{url('/admin')}}">
                                {{ csrf_field() }}
                                <div class="login-content">
                                    <div class="login-contenthead text-center">
                                        <h5>Login</h5>
                                    </div>
                                    <div class="login-input">
                                        <div class="form-group">
                                            <label>E-mail</label>
                                            <input type="email" required name="email" class="form-control" placeholder="example@email.com">
                                        </div>
                                        <div class="form-group">
                                            <div class="d-flex justify-content-between">
                                                <label>Password</label>
                                            </div>
                                            <div class="pass-group">
                                                <input type="password" name="password" required class="form-control pass-input" placeholder="********">
                                                <span class="fas toggle-password fa-eye-slash"></span>
                                            </div>
                                        </div>
                                        <div class="filter-checkbox m-0">
                                            <ul class="d-flex justify-content-between">
                                                <li>
                                                    <label class="checkboxs">
                                                        <input type="checkbox">
                                                        <span><i></i></span>
                                                        <b class="check-content">Remember Me</b>
                                                    </label>
                                                </li>
                                                <li>
                                                    <a class="forgot-link" href="{{ url('/admin/forgot-password') }}">Forgot password?</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="login-button">
                                        <button type="submit" class="btn btn-login">Sign In</button>
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