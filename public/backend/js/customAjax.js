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
                window.location.href="/admin/"+deleteFunction+"/"+id;
            }
        })
    }); 

});

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

$(document).ready(function(){

    $('#country_id').on('change', function() { 

        var country_id = $("#country_id").val();
        // console.log(country_id);
        $.ajax({
            url: '/view/country/code/'+country_id,
            success: data => {
                // console.log(data);
                // var citydd = $("#bmobile").val('');
                $('#phone_u').val(data);
            }
        });

    });

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

$(document).ready(function(){
    $('#searchCity').on('keyup', function(){
        var query = $(this).val();

        $.ajax({
            url: '/admin/search-cities/'+query,
            success: function(data){
                $('#cityTable').html(data);
            }
        });
    });
});

$(document).ready(function(){

  var max_fields      = 50; //maximum input boxes allowed
  var wrapper       = $(".city_wrapper"); //Fields wrappeD
  var add_button = $("#add_city_fields"); //Add button ID
  var x = 1; //initlal text box count

  //adding multile field
  $(add_button).click(function(e){ //on add input button click
    e.preventDefault();
      if(x < max_fields){ //max input box allowed
        x++; //text box increment
        $(wrapper).append('<div class="row"><div class="col-lg-3"><div class="form-group"><label>Name</label><input type="text" class="form-control" name="name[]" placeholder="Name" required></div></div><div class="col-lg-3"><div class="form-group"><label>Short Name</label><input type="text" class="form-control" name="short_name[]" placeholder="KHI"></div></div><button type="button" style="margin-top: 27px; width: 70px; height: 42px;" id="delete_city_fields" class=" btn btn-cancel text-white "> <i class="fa fa-minus" ></i></button></div>'); 
      }
  });

  $(wrapper).on("click","#delete_city_fields", function(e){ //user click on remove text
    e.preventDefault(); $(this).parent('div').remove(); x--;
  })


});


