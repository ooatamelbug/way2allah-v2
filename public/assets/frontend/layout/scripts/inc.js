function show_video(a){
	var iid = "khotab-play-"+ a +".htm";
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});
}

function show_mirror_video(id,khid){
	var iid = "play-mirror-"+khid+"-"+id+".htm";
	//alert(iid);
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});
}

function show_ansh_mirror_video(id,Ansh_id){
	var iid ="play-ansh-mirror-"+Ansh_id+"-"+id+".htm";
	//alert(iid);
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});
}

/////////////////////////////////////////////////////////////////////////////////////////////////
function advanced_search_khotab(p){
		
	$('#kh_ajax_results').html('<center><img src="loading.gif"></center>');	
	var kh_title_h =$("#kh_title_h").val();
	var kh_author_name_h =$("#kh_author_name_h").val();
	var kh_channel_h  =$("#kh_channel_h").val();
	var kh_from_h =$("#kh_from_h").val();
	var kh_to_h =$("#kh_to_h").val();
	$.ajax({
			url: "advanced_search.htm",
			type:"POST",
			data: {kh_title: kh_title_h, kh_author_name: kh_author_name_h, kh_channel: kh_channel_h, kh_from: kh_from_h, kh_to: kh_to_h, kh_search:"Search", page: p, mode:"ajax_khotab"},
			dataType: "html",
			success:function(data){
				$('#kh_ajax_results').html(data);
			}
		});
}

function advanced_search_series(p){
		
	$('#ser_ajax_results').html('<center><img src="loading.gif"></center>');	
	var kh_title_h =$("#kh_title_h").val();
	var kh_author_name_h =$("#kh_author_name_h").val();
	var kh_channel_h  =$("#kh_channel_h").val();
	var kh_from_h =$("#kh_from_h").val();
	var kh_to_h =$("#kh_to_h").val();
	$.ajax({
			url: "advanced_search.htm",
			type:"POST",
			data: {kh_title: kh_title_h, kh_author_name: kh_author_name_h, kh_channel: kh_channel_h, kh_from: kh_from_h, kh_to: kh_to_h, kh_search:"Search", page: p, mode:"ajax_series"},
			dataType: "html",
			success:function(data){
				$('#ser_ajax_results').html(data);
			}
		});
}
/////////////////////// NEW SEARCH AJAX ///////////////////////
function new_advanced_search_mawad(p){		
	$('#kh_ajax_results').html('<center><img src="loading.gif"></center>');	
	var kh_title_h =$("#kh_title_h").val();
	var kh_dept_h =$("#kh_dept_h").val();
	var kh_author_name_h =$("#kh_author_name_h").val();
	var kh_channel_h  =$("#kh_channel_h").val();
	var kh_from_h =$("#kh_from_h").val();
	var kh_to_h =$("#kh_to_h").val();
	$.ajax({
			url: "search.htm",
			type:"POST",
			async :false,
			data: {kh_title: kh_title_h, kh_author_name: kh_author_name_h, kh_channel: kh_channel_h, kh_from: kh_from_h, kh_to: kh_to_h, kh_search:"Search", page: p, mode:"ajax_mawad",kh_dept:kh_dept_h},
			dataType: "html",
			success:function(data){
				console.log(data);
				$('#kh_ajax_results').html(data);
				$(".no_ajax_res").remove();
			}
		});
}

function new_advanced_search_series(p){		
	$('#ser_ajax_results').html('<center><img src="loading.gif"></center>');	
	var kh_title_h =$("#kh_title_h").val();
	var kh_dept_h =$("#kh_dept_h").val();
	var kh_author_name_h =$("#kh_author_name_h").val();
	var kh_channel_h  =$("#kh_channel_h").val();
	var kh_from_h =$("#kh_from_h").val();
	var kh_to_h =$("#kh_to_h").val();
	$.ajax({
			url: "search.htm",
			type:"POST",
			async :false,
			data: {kh_title: kh_title_h, kh_author_name: kh_author_name_h, kh_channel: kh_channel_h, kh_from: kh_from_h, kh_to: kh_to_h, kh_search:"Search", page: p, mode:"ajax_series",kh_dept:kh_dept_h},
			dataType: "html",
			success:function(data){
				console.log(data);
				$('#ser_ajax_results').html(data);
				$(".no_ajax_res").remove();
			}
		});
}
function load_search_results(module){
	/*$('#kh_ajax_results').html('<center><img src="loading.gif"></center>');*/
	/*setCookie2("available_60","60 second you should wait",-1);*/
	if(check_availability('1')){
		$(".w2a_search_error").remove();
		$('#kh_ajax_results').html('');
		$('#ser_ajax_results').html('<center><img src="loading.gif"></center>');	
		$("#kh_dept_h").val(module);
		$("#kh_dept,#w2a_kh_dept").val(module);
		jQuery(".results_tab").removeClass("current_tab");
		jQuery("#tab_"+module).addClass("current_tab");
		setTimeout(function () {
			new_advanced_search_series(1);
			new_advanced_search_mawad(1);        
		}, 2000);	
		$(".no_ajax_res").remove();
		//setCookie("available_60","60 second you should wait",1);	
	}
}
function setCookie(c_name,value,exmins){
	var exdate=new Date();
	exdate_time = exdate.getTime();
	var exmins=new Date(exdate_time+ ((exmins*60) - 7200)); // - 7200 because Egypt is GMT+2
	/*var exdate=new Date();
	exdate.setDate(exdate.getMinutes() + exmins);*/
	/*var c_value=escape(value) + ((exmins==null) ? "" : "; expires="+(Date().getTime()+(exmins*60)));
	var c_value=escape(value) + ((exmins==null) ? "" : "; expires="+exdate.toUTCString());*/
	var c_value=escape(value) + ((exmins==null) ? "" : "; expires="+exmins);
	console.log(c_name + "=" + c_value+"; path=/");
	document.cookie=c_name + "=" + c_value+"; path=/";
}
/*function setCookie2(c_name,value,exdays){
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
	document.cookie=c_name + "=" + c_value+"; path=/";
}*/
////////////////////////////////////////////////////////////////////////////////////////////////

/*start khotab items*/
function show_khotab(a, mytype){
	var iid = "khotab-play-"+ a +".htm";
	if(mytype==1){
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});		
	}else{		
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#play_khotab_by_player').html(data);
				$( "#play_khotab_by_player" ).dialog( "open" );
			}
		});	
	}
}


function show_mirror(id, khid, mytype){
	var iid = "play-mirror-"+khid+"-"+id+".htm";
	if(mytype==1){
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});		
	}else{		
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#play_khotab_by_player').html(data);
				$( "#play_khotab_by_player" ).dialog( "open" );
			}
		});
	}
}


function show_video_anash(a, mytype){
	var iid = "var-play-"+ a +".htm";
	if(mytype==1){
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});		
	}else{		
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#play_khotab_by_player').html(data);
				$( "#play_khotab_by_player" ).dialog( "open" );
			}
		});	
	}		
}



function show_ansh_mirror(id,Ansh_id, mytype){
	var iid ="play-ansh-mirror-"+Ansh_id+"-"+id+".htm";
	if(mytype==1){
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});		
	}else{		
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#play_khotab_by_player').html(data);
				$( "#play_khotab_by_player" ).dialog( "open" );
			}
		});	
	}		
}

function show_fatwa(id, mytype){
	var iid ="fatawa-play-"+id+".htm";
	if(mytype==1){
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo'+id).dialog({
					autoOpen: false,
					modal: true,
					width: 670,
					dialogClass:'mybg', 
					dragable:false,
					resizable:false,
					show: "blind"
				});
				$('#shofvideo'+id).html(data);
				$("#shofvideo"+id).dialog( "open" );
			}
		});		
	}else{
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#play_khotab_by_player'+id).dialog({
					autoOpen: false,
					modal: true,
					width: 670,
					dialogClass:'mybg', 
					dragable:false,
					resizable:false,
					show: "blind"
				});
				$('#play_khotab_by_player'+id).html(data);
				$('#play_khotab_by_player'+id).dialog( "open" );
			}
		});	
	}		
}

function show_Tlawa(a){
	var iid = "recite-play-"+ a +".htm";
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				$('#shofvideo').html(data);
				$( "#shofvideo" ).dialog( "open" );
			}
		});
}

/*
function show_fatwa(a){
	var iid = "fatawa-play-"+ a +".htm";
	$.ajax({
			url:iid,
			type:"GET",
			success:function(data){
				jQuery( "#shofvideo"+a ).dialog({
					autoOpen: false,
					modal: true,
					width: 670,
					dialogClass:'mybg', 
					dragable:false,
					resizable:false,
					show: "blind"
				});
				$('#shofvideo'+a).html(data);
				$('#shofvideo'+a).dialog( "open" );
			}
		});
}
*/


function advfile(a){
	var gid = a;
	$.ajax({
			url:"modules.php?name=Khotab&op=advfile",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#fileadss').html(data);
				$( "#fileadss" ).dialog( "open" );
			}
		});
}
//==================================================================
function show_telawa_by_player(url)
{
//	alert("X");
	$("#play_telawa_by_player" ).dialog( "open" );
	AudioPlayer.embed("play_telawa_by_player", {soundFile: url});
	//$("#play_telawa_by_player" ).dialog( "open" );		   
}
function show_khotab_by_player(url)
{
	//alert(url);
	$("#play_telawa_by_player" ).dialog( "open" );
	AudioPlayer.embed("play_telawa_by_player", {soundFile: url});
}
//==================================================================


function add_comment(a){

	var gid = a;
	$.ajax({
			url:"modules.php?name=Khotab&file=Comments&op=Reply",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#scomment').html(data);
				$( "#scomment" ).dialog( "open" );
			}
		});
}
function friend(a){

	var gid = a;
	$.ajax({
			url:"modules.php?name=Khotab&file=friend&op=FriendSend",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#sfriend').html(data);
				$( "#sfriend").dialog( "open" );
			}
		});
}

function send_fatwa_to_friend(a){

	var gid=a;
	$.ajax({
			url:"fatawa-friend-"+gid+".htm",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#sfriend_'+gid).html(data);
				$( "#sfriend_"+gid ).dialog("open");
			}
		});
}

function send_email(form){

	//var gid = a;
		var gid = form.id.value;
	var yname=form.yname.value;
	var ymail=form.ymail.value;
	var fname=form.fname.value;
	var fmail=form.fmail.value;
	//var alr=gid+"<br>"+yname+"<br>"+ymail+"<br>"+fname+"<br>"+fmail;
	
	//alert(alr);
	$.ajax({
			url:"fatawa-friend-sendemail-"+gid+".htm",
			data:"id="+gid+"&yname="+yname+"&ymail="+ymail+"&fname="+fname+"&fmail="+fmail,
			type:"POST",
			success:function(data){
				//alert(data);
				//$('#sfriend').html(data);
				//$( "#sfriend" ).dialog( "open" );
				$( "#sfriend_"+gid ).dialog( "close" );
				$('#mailsent_'+gid).html(data);
				$( "#mailsent_"+gid ).dialog("open");
			}
		});
}
/*start anasheed items*/


function add_comment_anash(a){

	var gid = a;
	$.ajax({
			url:"modules.php?name=Anasheed&file=Comments&op=Reply",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#scomment').html(data);
				$( "#scomment" ).dialog( "open" );
			}
		});
}
//======================================================
function show_report_for_bad_ads_dialog()
{
	$("#report_for_bad_ads").dialog( "open" );
}
//======================================================
function friend_anash(a){

	var gid = a;
	$.ajax({
			url:"modules.php?name=Anasheed&file=friend&op=FriendSend",
			data:"id="+gid,
			type:"POST",
			success:function(data){
				$('#sfriend').html(data);
				$( "#sfriend" ).dialog( "open" );
			}
		});
}
//======================================================
function load_sub_groups(current_group_id)
{
	//alert("Selected group id is "+current_group_id);
	$.ajax({
			url:"admin.php?op=load_sub_categories&current_g_id="+current_group_id,
			data:"current_g_id="+current_group_id,
			type:"POST",
			success:function(data){
				//alert(data);
				$("#sub_group_id").empty();
				$("#sub_group_id").append(data);
			}
		});
}
function get_filesize(u){
	u = u.value;
	$( "#media_size" ).attr('readonly','readonly');
	$( "#loading0" ).css('display','inline-block');
	$.ajax({
			url:"admin.php?op=getfilesize&url_file="+u,
			type:"GET",
			success:function(data){
				try{
				realsize = data.split('MB');realsize = realsize[0];
				realsize /= 1048576;
				realsize = ""+realsize;
				realsize = realsize.split('.');
				realsize = realsize[0]+'.'+realsize[1][0]+realsize[1][1];
				$( "#media_size" ).val( realsize);
				$( "#media_size" ).removeAttr('readonly');
				$("#loading0").css('display','none');
				}catch(e){
					$( "#media_size" ).removeAttr('readonly');
					$("#loading0").css('display','none');
				}
			},
			error:function(){
				$( "#media_size" ).removeAttr('readonly');
				$("#loading0").css('display','none');
			},
			complete:function(){
				$( "#media_size" ).removeAttr('readonly');
				$("#loading0").hide('display','none');
			}
		});
}

//======================================================
function hidedialog()
{
	$( "#mailsent" ).dialog( "close" );
	$( ".mailsent" ).dialog( "close" );
}
	
function increaseclick(valx)
{
	/*alert(valx);*/
	$.ajax({

			url:"ads_click.php",
			data:"ads_id="+valx,
			type:"POST"
		});
}
	
function apply_filter(form,all_or_cond)
{
	//alert("function called");
	var cat_id=form.all_cats.value;
	var media_name="";//form.name.value;
	var auther_id=form.all_authers.value;
	//alert(cat_id);
	if(all_or_cond == 0)
	{
		var cat_id=0;
		var media_name="";
		var auther_id=0;	
	}
	$.ajax({
			//
			url:"get_cats_with_conditions_ajax.php",
			data:"cat_id="+cat_id+" &media_name="+media_name+"&auther_id="+auther_id,
			type:"POST",
			success:function(data){
				//alert(data);
				document.getElementById('all_banners').innerHTML = "";
				document.getElementById('all_banners').innerHTML = data;//"<option value='0'>no khotab</option>";
				//$('#all_banners').append(data);
				
			}
		});
}
			function w2a_show(items){
				items.slideDown();
				return items;
			}
			function w2a_hide(items){
				items.hide();
				return items;
			}
			
	$(document).ready(function(){

	var col=0;
	  var coll=getCookie("col");
	  if (coll!=null && coll==63)
	  {
	   col = 1;
	  }

	if(col == 0){
		$("#headcol2").show();
		$('#headcol').addClass("kkk");
	} else {
		$('#headcol').addClass("jjj");
		$("#headcol2").hide();
	}
			
	
	function setCookie(c_name,value,exdays)
	{
	var exdate=new Date();
	exdate.setDate(exdate.getDate() + exdays);
	var c_value=escape(value) + ((exdays==null) ? "" : "; expires="+exdate.toUTCString());
	document.cookie=c_name + "=" + c_value;
	}
	
	
	function getCookie(c_name)
	{
	var i,x,y,ARRcookies=document.cookie.split(";");
	for (i=0;i<ARRcookies.length;i++)
	{
	  x=ARRcookies[i].substr(0,ARRcookies[i].indexOf("="));
	  y=ARRcookies[i].substr(ARRcookies[i].indexOf("=")+1);
	  x=x.replace(/^\s+|\s+$/g,"");
	  if (x==c_name)
		{
		return unescape(y);
		}
	  }
	}
			
	$('#headcol').click(function(){

			
	if ($("#headcol2").is(":hidden")) {
	$("#headcol2").slideDown();
	$('#headcol').removeClass("jjj");
	$('#headcol').addClass("kkk");
	setCookie("col",0,7);
	} else {
	$('#headcol2').slideUp();
	$('#headcol').removeClass("kkk");
	$('#headcol').addClass("jjj");
	setCookie("col",63,7);
	}
	
	
	});
	
	$('#link1').hover(function(){
			$('#links1').show();
	},function(){
			$('#links1').hide();
	});

	$('#link2').hover(function(){
			$('#links2').show();
	},function(){
			$('#links2').hide();
	});

	$('#link3').hover(function(){
			$('#links3').show();
	},function(){
			$('#links3').hide();
	});
	
	$('#link4').hover(function(){
			$('#links4').show();
	},function(){
			$('#links4').hide();
	});

	$('#link5').hover(function(){
			$('#links5').show();
	},function(){
			$('#links5').hide();
	});		

	$('#link6').hover(function(){
			$('#links6').show();
	},function(){
			$('#links6').hide();
	});
	
	$('#link7').hover(function(){
			$('#links7').show();
	},function(){
			$('#links7').hide();
	});
	
	
	$('#links1').hover(function(){
			$('#alink1').toggleClass("greenHover"); 
	});
	
	
	$('#links2').hover(function(){
			$('#alink2').toggleClass("greenHover"); 
	});
	
	
	$('#links3').hover(function(){
			$('#alink3').toggleClass("greenHover"); 
	});
	
	
	$('#links4').hover(function(){
			$('#alink4').toggleClass("greenHover"); 
	});
	
	
	$('#links5').hover(function(){
			$('#alink5').toggleClass("greenHover"); 
	});
	
	
	$('#links6').hover(function(){
			$('#alink6').toggleClass("greenHover"); 
	});
	
	
	$('#links7').hover(function(){
			$('#alink7').toggleClass("greenHover"); 
	});	
	
});

//===================================================
function view_search_form()
{
	$(".adv_search_div").toggle("slow");
}


function rand_admin_module(val)
{
	if(val == 6)
	{
		var check=document.getElementsByName("auth_modules[]").item(15).checked;
		//alert(check);
		if(check){
		$(".feedback_div").show("slow");
		$('.feedback_div_dep').show();
		}
		else{
		  $(".feedback_div").hide("slow");
		  $('.feedback_div_dep').hide();
		}
	}
}

function show_feed_departments()
{
	
		$(".feedback_div_dep").toggle("slow");
}
function hide_dep_div()
{
	
		$('.feedback_div_dep').hide();
}

function displayRowByid(row_id,value){
	if(value=='other')
	{
	var row = document.getElementById(row_id);
	if (row.style.display == 'none')  row.style.display = '';
	}
	else
	{
	var row = document.getElementById(row_id);
	row.style.display = 'none';
	}
}

function dynamic_Select(ajax_page, parent_id) {

$.ajax({
type: "POST",
url: ajax_page,
data: "parent_id=" + parent_id,
success: function(data){
	$("#txtResult").html(data);
	}
}); 
}
function check_all_feedbacks()
{
	var isChecked = $('.feed_check').attr('checked')?true:false;
	if(isChecked)
	{
		$(".feed_check").removeAttr("checked");	
	}
	else
	{
		$(".feed_check").attr("checked","checked");
	}
}
function link_this_ads(ad_id)
{
	increaseclick(ad_id);
	link_url=$(".ads_link_url").val();
	if(link_url)
	{
		window.location=link_url;
		/*alert(link_url);*/
	}
}

function getarchivedata(){
	$('#next_button').css({"display":"none"});
	$('#more_data').css({"display":"block"});
	var URL = $('#khotab_url').val();	
	$.ajax({
			url: 'admin.php',
 		    type: "GET",
			data: ({op : 'KhotabLeechImages', url: URL}),
			success:function(data){
				$('#archive_feed').html(data);
		        $("#more_info").html($("#more_info_feed").html());
				$("#more_info_feed").html('');
			},
			error:function(){
				alert("error");
			}
		});
//	$('#archive_feed').load("http://www.way2allah.com/admin.php?op=KhotabLeechImages&url=" . $("#khotab_url").val());
}

function setframe(frame_url){
	$("#archive_frame").val(frame_url);
	$("#archive_frame_img").attr("src",frame_url);
}
function disableEnterKey(e)
{
     var key;
     if(window.event)
          key = window.event.keyCode;     //IE
     else
          key = e.which;     //firefox
     if(key == 13)
          return false;
     else
          return true;
}
function toggle_every_period(sh_h){
	if(sh_h == "show")
	$(".sharelist_send_every").show();
	else
	$(".sharelist_send_every").hide();
}
function downlaod_gellery_images(id){
	var ajax_url = "download-album-"+id+".htm";
	//alert(iid);
	$.ajax({
			url:ajax_url,
			type:"GET",
			success:function(data){
				//alert(data);
				window.location = data;
				//console.log(data);
			}
		});
}
function album_compress(id){
	//var ajax_url = "http://www.way2allah.com/admin.php?op=compress-album-"+id+".htm";
	//alert(id);
	$.ajax({
			url: 'admin.php',
 		    type: "post",
			data: ({op : 'compress-album', id: id}),
			success:function(data){
				//alert(data);
				$('#album_msg_'+id).html(data);
			},
			error:function(){
				alert("error");
				//$('.album_msg').html('<div style="padding:5px; width:100px; border-radius:5px; border:1px solid #009999; background-color:#ABE2F8;">الألبوم تم تجميعه من قبل,  إضغط هنا للحذف<button ondblclick="album_compress_delete($Album[album_id]);" class="btn btn-icon btn-primary glyphicons remove" style="margin:8px;" type="submit"></button></div>');
				}
		});
}
function album_compressed_delete(id){
	//var ajax_url = "delete-compressed-album-"+id+".htm";
	//alert(iid);
	$.ajax({
			url: 'admin.php',
 		    type: "post",
			data: ({op : 'delete-compressed-album', id: id}),
			success:function(data){
				//alert(data);
				$('#album_msg_'+id).html(data);
				//console.log(data);
			},
			error:function(){
				alert("error");
			}
		});
}
function messagebox(txt,timex){
	var per=(timex == "")?1000:timex;
	$("#messageBox").removeClass().addClass("confirmbox").html(txt).fadeIn(1000).fadeOut(per);
}

function alertbox(txt,timex){
	var per=(timex == "")?1000:timex;
	$("#messageBox").removeClass().addClass("errorbox").html(txt).fadeIn(1000).fadeOut(per);
}

jQuery(document).ready(function(){
	set_hedaya_current_time();
});

function set_hedaya_current_time(){

	 var current_time_style = $("#first_ls_hedaya").val();
	 if(current_time_style > 3666) //(48*94) - (9*94)  to show end of day from the last 4.5 h to make table complete
			current_time_style=3666;
	 
	 var lsContainer = $('.leason-con');	
	 lsContainer.animate({left: current_time_style+"px"}, 3000)
	 //lsContainer.css("left",current_time_style+"px");
}
function set_masajed_current_time(){

	 var current_time_style = $("#first_ls_masajed").val();
	 if(current_time_style > 3666)
			current_time_style=3666;
	 
	 var lsContainer = $('.leason-con');	
	 lsContainer.animate({left: current_time_style+"px"}, 3000)
	 //lsContainer.css("left",current_time_style+"px");
}
/*function set_current_time(){
	var x = new Date(); 
	var h = x.getHours(); 
	var m = x.getMinutes(); 
	var s = x.getSeconds(); 

	 var c_mWidth = (5328/(24*60))*m;
	 var current_time_style = (h*222) + c_mWidth - 20;
	 
	 var lsContainer = $('.leason-con');	
	 lsContainer.css("left",current_time_style+"px");
}*/
function move_right(){
	//jQuery(document).ready(function(){
		var lsContainer = $('.leason-con');	
//	$('.right_arrow').mousedown(function () {
			var left_valx=parseInt(lsContainer.css("left"));
			if(!left_valx)
			left_valx=0;
			//console.log(left_valx);
			if(left_valx > 0){
				/*lsContainer.animate({
					left: '-=200'
				}, 458);*/
				var new_left_valx=left_valx - 374;
				if(new_left_valx < 0)
				new_left_valx=0;
				lsContainer.css("left",new_left_valx+"px")
			}	
	//	});	
	//});
}

function move_left(){
	//jQuery(document).ready(function(){
		var lsContainer = $('.leason-con');	
//	$(document).on("mousedown",".left_arrow", function(){
	//$('.left_arrow').mousedown(function () {
		//alert('ssssss');
		var left_valx=parseInt(lsContainer.css("left"));
		if(!left_valx)
			left_valx=0;
		if(left_valx < 3666){
			/*lsContainer.animate({
				left: '+=200'
			}, 458);*/
			var new_left_valx=left_valx + 374;
			if(new_left_valx > 3666)
			new_left_valx=3666;
			lsContainer.css("left",new_left_valx+"px")
		}
    //});
	//});
}

jQuery(document).ready(function(){
	
		$('.room_hedaya').click(function(){
			if($(this).is('.current_room')){
				return false;	
			}else{
				$('.room_hedaya').toggleClass('current_room');
				$('.room_masajed').toggleClass('current_room');
			}
		});
		$('.room_masajed').click(function(){
			if($(this).is('.current_room')){
				return false;	
			}else{
				$('.room_hedaya').toggleClass('current_room');
				$('.room_masajed').toggleClass('current_room');
			}
		});
		$('.w2a_print').click(function(){
			$(".without_print,.ramd-cont,.ramadan-pics,.HeaderContainer,.GreenMenu,.Wayfooter").hide();
			$(".ContentContainer > div").hide();
			$(".HeaderBlockC > h1").attr("style","color:#333;	background:#F4F4F4;border-bottom:5px solid #333;");
			$(".print_me").show();
			window.print();
			$(".without_print,.ramd-cont,.ramadan-pics,.HeaderContainer,.GreenMenu,.Wayfooter").show();
			$(".HeaderBlockC > h1").removeAttr("style");
			$(".ContentContainer > div").show();			
		});
		
});

var room='';
function load_room_lessons(roomx){
	//var ajax_url = "delete-compressed-album-"+id+".htm";
	//alert(iid);
	console.log(room);
	$.ajax({
			url:"new_modules.php",
			data: ({name:'hedaya_table', op : 'Lessons_table', developer: 'yes', _room: roomx}),
			type:"GET",
			success:function(data){
				//alert(data);
				jQuery(".hedaya_table_cont").hide();
				jQuery(".loading_img").show();
				setTimeout(function() { 
    		    	jQuery('.loading_img').hide(); 
  				 }, 2000);
				 
				jQuery('.hedaya_table_cont').html(data);
				if(roomx == 'hedaya')
					set_hedaya_current_time();
				if(roomx == 'masajed')
					set_masajed_current_time();
				/*if(roomx == 'hedaya'){
					$("#hedaya_table").val(data);	
				}
				if(roomx == 'masajed'){
					$("#masajed_table").val(data);	
				}*/
				setTimeout(function() { 
    		    	jQuery('.hedaya_table_cont').show(); 
  				 }, 2000);
				//jQuery(".").show();
			},
			error:function(){
				alert("error");
			}
		});
}
function wait_60() { 
    var d = new Date();
    d.setTime(d.getTime() + (60*1000));
    var expires = "expires="+d.toUTCString();
    document.cookie = "available_60=false;" + expires;
}
function checkCookie(){
    var cookieEnabled=(navigator.cookieEnabled)? true : false   
    if (typeof navigator.cookieEnabled=="undefined" && !cookieEnabled){ 
        document.cookie="testcookie";
        cookieEnabled=(document.cookie.indexOf("testcookie")!=-1)? true : false;
    }
    return (cookieEnabled)?true:false;
}
function check_availability(ts){
	if(ts == 'undefined'){
		if(!final_submit(ts))return false;
	}
	else {
		if(ts != '1'){
			if(!final_submit(ts))return false;
		}
	}
	available = (document.cookie.indexOf("available_60")==-1);
	if(!available)alert("لا يمكنك البحث مرتين خلال 15 ثانية");
	return available;
}
/*
	if(!checkCookie()){
		// message saying you can't use search without cookies
	}else{
		if(check_availability()){
			wait_60();
			submit();
			// you can submit
		}else{
			// message saying you can't search in the same minute twice
		}
	}
<noscript> you can't search without enabling JS</noscript>	
*/
function final_submit(ts){
	if(ts.id == 'w2a_search_form'){
		t1 = document.getElementById('w2a_kh_title');
		t2 = document.getElementById('w2a_kh_dept');
	}else{
		t1 = document.getElementById('kh_title');
		t2 = document.getElementById('kh_dept');
	}
	valid_title(t1);valid_dept(t2);
	if(t1.value.length > 0 && t1.value.length > 3 && t2.value != 0)
		return true;
	return false;	
}
function valid_title(t){
	if(t.value.length == 0){
		if(t.id == 'kh_title')
			$("#kh_title_msg").html('هذا الحقل مطلوب');
		else $("#w2a_kh_title_msg").html('هذا الحقل مطلوب');
		return;
	}
	if(t.value.length <= 3){
		// at least 4 characters
		if(t.id == 'kh_title')
			$("#kh_title_msg").html('يجب ادخال 4 احرف على الاقل');
		else $("#w2a_kh_title_msg").html('يجب ادخال 4 احرف على الاقل');	
	}else{
		// okay
		if(t.id == 'kh_title')
			$("#kh_title_msg").html('');
		else $("#w2a_kh_title_msg").html('');		
	}
}
function valid_dept(t){
	if(t.value == 0){
		// required
		if(t.id == 'kh_dept')
			$("#kh_dept_msg").html('هذا الحقل مطلوب');
		else 	$("#w2a_kh_dept_msg").html('هذا الحقل مطلوب');
	}else{
		// okay
		if(t.id == 'kh_dept')
			$("#kh_dept_msg").html('');
		else 	$("#w2a_kh_dept_msg").html('');
	}
}
