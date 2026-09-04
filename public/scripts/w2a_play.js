function w2a_play(id,type){	
	var request = $.ajax({
	  url: "get-mada-player.htm",
	  method: "POST",
	  data: { id : id ,type : type},
	  dataType: "html",
	  success:function(data){
		  $("#w2a_main_player").html(data);
		  $("#the_main_player").fadeIn();
      }
	});
}
$(document).ready(function(){
	$("#the_main_player .clickable").click(function(){
		$("#the_main_player").fadeOut(350,function(){
			$("#w2a_main_player").html("");
		});
	});
});