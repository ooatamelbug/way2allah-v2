$(document).ready(function(){
	$('#commentsModal, #sendFriendModal').on('show.bs.modal', function () {
		$(this).find('.modal-error').hide();
	});

	$(".send-comment-btn").click(function (){
		$("#comments_form").show();
		$("#commentsModal .modal-error").hide();
		$(".sending-result").remove();
		$("#send_comment").show();
		$("#send_comment").removeAttr("disabled");
		$("#send_comment").html(' ارسل التعليق ');
		$("#user_nickname").val("");
		$("#user_comment").val("");
	});
	$("#send_comment").click(function (){		
		var user_nickname = $("#user_nickname").val();
		var user_comment = $("#user_comment").val();
		if(!user_nickname){
			$("#user_nickname_error").show();
			return false;
		}
		if(!user_comment){
			$("#user_comment_error").show();
			return false;
		}
		var msg = '';
		$("#send_comment").attr("disabled","disabled");
		$("#send_comment").html('جاري الارسال');
		// Laravel (decision-log #105): the canonical comment endpoint (anasheed.item.store-comment, rendered into the
		// form's data-comment-url) and the form's CSRF token; Gen 2's own comment URL is not routed in Laravel.
		var request = $.ajax({
		  url: $("#comments_form").data("comment-url"),
		  method: "POST",
		  data: { user_nickname : user_nickname, user_comment:user_comment, _token: $("#comments_form input[name='_token']").val()},
		  dataType: "html",
		  success:function(data){
			  if(data == 1){
				  msg = '<div class="sending-result alert alert-success">شكرا لك ، تم اضافة التعليق بنجاح وسوف يتم نشره بعد مراجعته من قبل الادارة</div>';
			  }else if(data == -1){
				  msg = '<div class="sending-result alert alert-danger">عفوا ، حدث خطأ اثناء اضافة التعليق من فضلك حاول مرة اخرى</div>';
			  }else if(data == 2){
				  msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك ادخال اسمك المستعار</div>';
			  }else if(data == 3){
				  msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك ادخال التعليق</div>';
			  }
			  $("#comments_form").hide();
			  $("#modal-comment-body").append(msg);
			  $("#send_comment").hide();
		  }
		});
	});
	$("#user_nickname").keyup(function (){
		$("#user_nickname_error").hide();
	});
	$("#user_comment").keyup(function (){
		$("#user_comment_error").hide();
	});
	/*==================================*/
	/*                    Send Friend                       */
	/*==================================*/
	$(".send-friend-btn").click(function (){
		$("#sendFriend_form").show();
		$("#sendFriendModal .modal-error").hide();
		$(".sending-result").remove();
		$("#send_friend").show();
		$("#send_friend").removeAttr("disabled");
		$("#send_friend").html(' ارسل لصديقك ');
		$("#your_name").val("");
		$("#your_email").val("");
		$("#friend_name").val("");
		$("#friend_email").val("");
	});
	
	$("#your_name").keyup(function (){
		$("#your_name_error").hide();
	});
	$("#your_email").keyup(function (){
		$("#your_email_error").hide();
	});
	$("#friend_name").keyup(function (){
		$("#friend_name_error").hide();
	});
	$("#friend_email").keyup(function (){
		$("#friend_email_error").hide();
	});
});
$("#send_friend").click(function (){
	anasheed_send_friend();
});
function validateEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(email);
}
function anasheed_send_friend(){
	var your_name = $("#your_name").val();
	var your_email = $("#your_email").val();
	var friend_name = $("#friend_name").val();
	var friend_email = $("#friend_email").val();
	if(!your_name){
		$("#your_name_error").show();
		return false;
	}
	if(!your_email){
		$("#your_email_error").show();
		return false;
	}
	if(!validateEmail(your_email)){
		$("#your_email_error").show();
		return false;
	}	
	if(!friend_name){
		$("#friend_name_error").show();
		return false;
	}
	if(!friend_email){
		$("#friend_email_error").show();
		return false;
	}
	if(!validateEmail(friend_email)){
		$("#friend_email_error").show();
		return false;
	}
	var msg = '';
	$("#send_friend").attr("disabled","disabled");
	$("#send_friend").html('جاري الارسال');
	// Laravel (decision-log #106): the same send-friend-anasheed-{id}.htm route, rendered into the form's
	// data-send-url, and the form's CSRF token (Laravel CSRF applies to this POST).
	var request = $.ajax({
	  url: $("#sendFriend_form").data("send-url"),
	  method: "POST",
	  data: { your_name : your_name, your_email:your_email, friend_name:friend_name, friend_email:friend_email, _token: $("#sendFriend_form input[name='_token']").val()},
	  dataType: "html",
	  success:function(data){
		  if(data == 1){
			  msg = '<div class="sending-result alert alert-success">شكرا لك ، تم ارسال المادة الى صديقك بنجاح</div>';
		  }else if(data == -1){
			  msg = '<div class="sending-result alert alert-danger">عفوا ، حدث خطأ اثناء ارسال المادة من فضلك حاول مرة اخرى</div>';
		  }else if(data == 2){
			  msg = '<div class="sending-result alert alert-danger">عفوا ، يجب عليك اكمال البيانات المطلوبة</div>';
		  }
		  $("#sendFriend_form").hide();
		  $("#modal-sendFriend-body").append(msg);
		  $("#send_friend").hide();
	  },
	  // #106 (2026-11-08): rate limit (429) or any other HTTP error — never left on «جاري الارسال».
	  error:function(xhr){
		  if(xhr.status === 429){
			  msg = '<div class="sending-result alert alert-danger">عفوا ، تم تجاوز الحد المسموح لإرسال المواد. من فضلك حاول مرة اخرى لاحقا</div>';
		  }else{
			  msg = '<div class="sending-result alert alert-danger">عفوا ، حدث خطأ اثناء ارسال المادة من فضلك حاول مرة اخرى</div>';
		  }
		  $("#sendFriend_form").hide();
		  $("#modal-sendFriend-body").append(msg);
		  $("#send_friend").hide();
	  }
	});
}