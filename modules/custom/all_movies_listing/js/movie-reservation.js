var movie_divs = document.querySelectorAll(".movie");
var reserve_movie_button = document.querySelectorAll(".reserve_movie_button");

for(let i = 0; i < movie_divs.length; i++){
  movie_divs[i].addEventListener("click", function(){
    showReserveMovieButton(this.id);
  });
  reserve_movie_button[i].addEventListener("click", function(){
    reserveMovie(this.id);
  });
}

$(document).ready(function(){
  $(".movie").hover(function(){
    $(this).css("background-color", "#e6f0ff");
  }, function(){
    $(this).css("background-color", "white");
  });
});

function showReserveMovieButton(movie) {
  var button = document.getElementById("btn_"+movie);
  if (button.style.display === "none") {
    button.style.display = "block";
  } else {
    button.style.display = "none";
  }
}

function reserveMovie(btn_id){
  alert(btn_id);
}



