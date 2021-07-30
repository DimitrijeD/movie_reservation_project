var movie_divs = document.querySelectorAll(".movie");
var reserve_movie_button = document.querySelectorAll(".reserve_movie_button");
var popup = document.querySelectorAll(".popup");

class EventHelper{
  //fixes issues with requirement to click twice on movie div first time page is loaded. Adding css file didnt fix, this did.
  static add_display_none(){
    return "none";
  }
}

// Attach event listeners
for(let i = 0; i < movie_divs.length; i++){
  let availability_by_day = movie_divs[i].querySelectorAll(".availability_by_day");

  if(availability_by_day){
    for(let j = 0; j < availability_by_day.length; j++){
      availability_by_day[j].addEventListener("click", function(){
        showButtonForMovieByDay(this.id);
      });

      let id_of_day = availability_by_day[j].id;
      let split_id = id_of_day.split("_");
      let btn_for_day = document.getElementById("btn_"+split_id[0]+"_"+split_id[1]);

      btn_for_day.addEventListener("click", function(){
        finalReserveMovie(this.id);
      });
      btn_for_day.style.display = EventHelper.add_display_none();
    }
  }
  movie_divs[i].addEventListener("click", showReserveMovieButton, false);

  reserve_movie_button[i].addEventListener("click", function(){
    reserveMovie(this.id);
  });
  reserve_movie_button[i].style.display = EventHelper.add_display_none();

  popup[i].addEventListener("click", function(){
    showPopup(this.id);
  });
  popup[i].style.display = EventHelper.add_display_none();
}

$(document).ready(function(){
  $(".movie").hover(function(){
    $(this).css("background-color", "#e6f0ff");
  }, function(){
    $(this).css("background-color", "white");
  });
});

function showReserveMovieButton(){
  var button = document.getElementById("btn_"+this.id);
  if (button.style.display === "none") {
    button.style.display = "block";
  }
}

function reserveMovie(btn_id){
  var split_id = btn_id.split("_");
  showPopup('popup_'+split_id[1]);
}

function showPopup(popup_id){
  var popup = document.getElementById(popup_id);
  if (popup.style.display === "none") {
    popup.style.display = "block";
  }
}

function showButtonForMovieByDay(id_day){
  let split_id = id_day.split("_");
  var btn_final_reserve_movie = document.getElementById("btn_"+split_id[0]+"_"+split_id[1]);
  if (btn_final_reserve_movie.style.display === "none"){
    btn_final_reserve_movie.style.display = "block";
  }
}

function finalReserveMovie(final_btn_id){
  // final_btn_id = 'btn_4_tuesday'
  // 'btn_' + movie_id + "_" + day_for_which_movie_is_available
}

function validate_customer_form(){
  let customer_name = document.getElementById("customer_name").value;
  let errors = [];
  if (customer_name === "") {

    errors.push("Name must be filled out.");
  }
  if (customer_name[0] !== customer_name[0].toUpperCase()){
    errors.push("First letter must be capital!");
  }
  for(let i = 0; i < customer_name.length; i++){
    if( !isNaN(customer_name[i]) ){
      errors.push("Name can't contain numbers.");
      break;
    }
  }
  if(errors.length !== 0){
    var all_errors = "";
    for(let j = 0; j < errors.length; j++){
      all_errors = all_errors + errors[j] + '\n';
    }
    alert(all_errors);
  }
  set_all_inputs_for_customer_name(customer_name);
  return customer_name;
}

function set_all_inputs_for_customer_name(customer_name_valid){
  var hidden_c_name_inputs = document.querySelectorAll(".customer_name");
  for(let i = 0; i < hidden_c_name_inputs.length; i++){
    hidden_c_name_inputs[i].value = customer_name_valid;
  }
}
