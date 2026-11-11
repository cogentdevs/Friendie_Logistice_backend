$(document).ready(function(){

    // from city klist according to country
    $(document).on('change', '#from_country_id', function() {

        var from_country_id = $("#from_country_id").val();

        $.ajax({
            url: '/view/city-list/'+from_country_id,
            success: data => {
                console.log(data);
                $("#from_city_id").html('');
                $("#from_city_id").append(data);
                
            }
        });
        
    });

});

$(document).ready(function(){

    // from city klist according to country
    $(document).on('change', '#to_country_id', function() {

        var to_country_id = $("#to_country_id").val();

        $.ajax({
            url: '/view/city-list/'+to_country_id,
            success: data => {
                console.log(data);
                $("#to_city_id").html('');
                $("#to_city_id").append(data);
                
            }
        });
        
    });

}); 

// sweet alert
$(document).ready(function(){

    // admin panel delete function popups
    $(document).on('click', '.sa-confirm-delete', function() {
        var id = $(this).attr('param-id');
        var deleteFunction = $(this).attr('param-route');
        console.log(id);
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!',
        }).then((result) => {

            if (result.value) {
                window.location.href="/user/"+deleteFunction+"/"+id;
            }
        })
    }); 

});

$(document).ready(function(){

    $('#country_id').on('change', function() { 

        var country_id = $("#country_id").val();
        // console.log(country_id);
        $.ajax({
            url: '/view/country/code/'+country_id,
            success: data => {
                // console.log(data);
                // var citydd = $("#bmobile").val('');
                // $('#phone').val(data);
            }
        });

    });

});

$(document).ready(function(){

    $('#email').on('change keyup blur', function() { 

        var email = $("#email").val();

        if (email == '') {

            $("#btn_subscribe").attr("disabled", true);

        } else {

            $("#btn_subscribe").attr("disabled", false);

        }
            
    }); 

}); 

$(document).ready(function(){

    // find currency rate page
    $(document).on('click', '#btn_subscribe', function() {

        var email = $("#email").val();

        $.ajax({
            type: 'POST',
            url: '/subscribe',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {email : email},
            success: data => {
                console.log(data);
                if (data == 'already') {

                    $("#msg").css("display", "none");
                    $("#msg_error").css("display", "block");

                } else if (data == 'done') {

                    $("#msg").css("display", "block");
                    $("#msg_error").css("display", "none");

                }
                
            }
        });
        
    });

}); 

$(document).on('click',".giverate",function () {
    var html = '';
    $(".give-ratting-star").html("");

    var id = $(this).attr('id');
    var split_data = id.split("_");

    for(var i=1; i<=5; i++){
        if(i <= split_data[1]){
            html+='<i id="rate_'+i+'" class="fas fa-star filled giverate"></i>';
        }else{
            html+='<i id="rate_'+i+'" class="fas fa-star giverate"></i>';
        }
    }

    $(".give-ratting-star").html(html);
    $("#rating").val(split_data[1]);
});

$(document).ready(function(){

    $("#country_id").select2();
    $("#category_id").select2();        
    $("#ad_type").select2();        
    $("#from_country_id").select2();        
    $("#to_country_id").select2();        
    $("#from_city_id").select2();        
    $("#to_city_id").select2();        
    $("#currency").select2();        

});

// scroll to bottom
function scrollToBottom() {
    var chatDiv = $('#chats');
    chatDiv.scrollTop(chatDiv.prop("scrollHeight"));
}

 // Function to scroll #caht_user_list to the top
function scrollToTop() {
    var userListDiv = $('#chat_user_list');
    userListDiv.scrollTop(0); // Scroll to the top
}

// Function to reload the chat user list and highlight the active user
function reloadChatUserList() {

    var postAdId = $("#post_ad_id").val();
    var currentUrl = $("#current_url").val();

    $("#chat_user_list").load(`/user/get-chat-user-list/${postAdId}`, function () {
        // Add 'active' class to the appropriate link
        $("#chat_user_list a").each(function () {
            if ($(this).attr("href") === currentUrl) {
                $(this).addClass("active");
            } else {
                $(this).removeClass("active");
            }
        });

        scrollToTop();
    });
}

// msg send
$(document).ready(function(){

    $(document).on('click', '#meg_send', function() {

        var post_ad_room_id = $("#post_ad_room_id").val();
        var from_user_id = $("#from_user_id").val();
        var to_user_id = $("#to_user_id").val();
        var message = $("#message").val();

        $.ajax({
            type: 'POST',
            url: '/user/msg/send',
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
            data: {post_ad_room_id, from_user_id, to_user_id, message},
            success: data => {
                console.log(data);
                if (data == 'send') {

                    // Reload the chat user list
                    reloadChatUserList();

                    $("#chats").load(`/user/get-chats/${post_ad_room_id}`, function() {
                        scrollToBottom();
                    });
                    $("#message").val('');

                } 
                
            }
        });
        
    });

    // Trigger send message on "Enter" key
    $(document).on('keydown', '#message', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            $('#meg_send').click();
        }
    });

    // Initial scroll behavior on page load
    scrollToBottom();
    scrollToTop();

}); 

// search
$(document).ready(function(){

    $(document).on('keyup', '#search', function(e) {

        var search = $("#search").val();
        var postAdId = $("#post_ad_id").val();
        console.log(search);

        $("#chat_user_list").load(`/user/get-search-users/${postAdId}/${search}`, function () {

            scrollToTop();
        });
        

    });    

});

