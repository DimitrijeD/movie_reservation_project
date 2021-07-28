var movie_divs = document.querySelectorAll(".movie");
var reserve_movie_button = document.querySelectorAll(".reserve_movie_button");
var popup = document.querySelectorAll(".popup");
// var btn_final_reserve_movie = document.querySelectorAll(".btn_final_reserve_movie");

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
      btn_for_day.style.display = "none";
    }
  }
  movie_divs[i].addEventListener("click", showReserveMovieButton, false);

  reserve_movie_button[i].addEventListener("click", function(){
    reserveMovie(this.id);
  });
  // next line fixes issue with requirement to click twice on movie div first time page is loaded. Adding css file didnt fix, this did.
  reserve_movie_button[i].style.display = "none";

  popup[i].addEventListener("click", function(){
    showPopup(this.id);
  });
  popup[i].style.display = "none";

}

$(document).ready(function(){
  $(".movie").hover(function(){
    $(this).css("background-color", "#e6f0ff");
  }, function(){
    $(this).css("background-color", "white");
  });
});

function showReserveMovieButton() {
  var button = document.getElementById("btn_"+this.id);
  if (button.style.display === "none") {
    button.style.display = "block";
  } /*else {
    button.style.display = "none";
  }*/
}

function reserveMovie(btn_id){
  var split_id = btn_id.split("_");
  showPopup('popup_'+split_id[1]);
}

function showPopup(popup_id) {
  var popup = document.getElementById(popup_id);
  if (popup.style.display === "none") {
    popup.style.display = "block";
  } /*else {
    popup.style.display = "none";
  }*/
}

function showButtonForMovieByDay(id_day){
  let split_id = id_day.split("_");
  var btn_final_reserve_movie = document.getElementById("btn_"+split_id[0]+"_"+split_id[1]);
  if (btn_final_reserve_movie.style.display === "none") {
    btn_final_reserve_movie.style.display = "block";
  }
}

function finalReserveMovie(wat){
  console.log('from clicked final button which than finaly reserves a movie for that day');
}
