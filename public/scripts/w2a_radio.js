/**
 *
 * HTML5 Audio player with playlist
 *
 * Licensed under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
 * 
 * Copyright 2012, Shams IT
 */
 // inner variables
var song;
var tracker = $('.tracker');
var volume = $('.volume');
var max_playlist_items = 50;
var elem_id;
var logged_in = false;
var last_listen_second = 0;
var is_mobile = false;
function initAudio(elem) {
	var url = elem.attr('audiourl');
	var title = elem.text();
	//var cover = elem.attr('cover');
	var artist = elem.attr('artist');
	
	$('.player .title').text(title);
	$('.player .artist').text(artist);
	//$('.player .cover').css('background-image',cover);
	elem_id = elem.attr('id');
	song = new Audio(url);

	// timeupdate event listener
	song.addEventListener('timeupdate',function (){
		var curtime = parseInt(song.currentTime, 10);
		tracker.slider('value', parseInt(curtime));
	});
	$('.playlist li').removeClass('active');
	elem.addClass('active');
}
function playAudio() {
	total_song_loaded = parseInt(jQuery(".player-timer span.current-t").html());
	if(total_song_loaded > 0){
		jQuery(".play-loading").show("slow");
	}
	var int_last = parseInt(last_listen_second);	
	if(int_last > 0 && logged_in){
		song.currentTime = last_listen_second;
		jQuery(".player-timer span.current-t").html(radio_format_time(last_listen_second));
		last_listen_second = 0;
		radio_createCookie("w2a-last-listen",0, 14);				
	}
	song.play();
	save_last_listen(elem_id);		
	song.addEventListener('ended', function(){
		 var next = $('.playlist li.active').next();
		 if (next.length == 0){
			 next = $('.playlist li:first-child');
		 }
		 //tracker.slider("option", "max", 0);
		 stopAudio()
		initAudio(next);
		playAudio();
	},false);	
	song.addEventListener('timeupdate', function(){
		total_t = jQuery(".player-timer span.total-t").html();
		total_t = total_t.replace(":","");
		total_t = parseInt(total_t);
		if(total_t == 0){
			console.log("timeupdate listener : "+jQuery(".player-timer span.total-t").html());
			tracker.slider("option", "max", song.duration);
			jQuery(".player-timer span.total-t").html(radio_format_time(song.duration));
		}
		var curtime = parseInt(song.currentTime, 10);
		//console.log(song.currentTime);
		//save coockie for 14 days
		if(logged_in){
			radio_createCookie("w2a-last-listen",song.currentTime, 14);
		}
		jQuery(".player-timer span.current-t").html(radio_format_time(song.currentTime));
	});
	/*song.onloadedmetadata = function() {
		if(is_mobile){
			alert("meta data loaded");
		}
		tracker.slider("option", "max", song.duration);
		jQuery(".player-timer span.total-t").html(radio_format_time(song.duration));
		jQuery(".play-loading").hide("fast");
	};*/
	song.addEventListener("loadedmetadata", function(_event) {
		if(is_mobile){
			//alert("meta data loaded");
		}
		tracker.slider("option", "max", song.duration);
		jQuery(".player-timer span.total-t").html(radio_format_time(song.duration));
		jQuery(".play-loading").hide("fast");
	});
	$('.play').addClass('hidden');
	$('.pause').addClass('visible');
	add_remove_action();
}
function stopAudio() {
	song.pause();
	$('.play').removeClass('hidden');
	$('.pause').removeClass('visible');
}
jQuery(document).ready(function() {
	check_logged_in_member();
	if(jQuery("#w2a_is_mobile").length){
		is_mobile = true;
	}
    // play click
    $('.play').click(function (e) {
        e.preventDefault();
        playAudio();
    });

    // pause click
    $('.pause').click(function (e) {
        e.preventDefault();
        stopAudio();
    });

    // forward click
    $('.fwd').click(function (e) {
        e.preventDefault();
        stopAudio();
        var next = $('.playlist li.active').next();
        if (next.length == 0) {
            next = $('.playlist li:first-child');
        }
        initAudio(next);
		playAudio();
    });

    // rewind click
    $('.rew').click(function (e) {
        e.preventDefault();
        stopAudio();
        var prev = $('.playlist li.active').prev();
        if (prev.length == 0) {
            prev = $('.playlist li:last-child');
        }
        initAudio(prev);
		playAudio();
    });

    // show playlist
    /*$('.pl').click(function (e) {
        e.preventDefault();

        $('.playlist').fadeIn(300);
    });*/

    add_item_to_playlist_action();

    // initialization - first element in playlist
	if(logged_in){
		li_elem = $('.playlist li.active');
		last_listen_second = radio_getCookie("w2a-last-listen");
	}else{
		li_elem = $('.playlist li:first-child');    	
	}
	initAudio(li_elem);
	if(!is_mobile){
		stopAudio();
		playAudio();
	}
    // set volume
    song.volume = 0.8;

    // initialize the volume slider
    volume.slider({
        range: 'min',
        min: 1,
        max: 100,
        value: 80,
        start: function(event,ui) {},
        slide: function(event, ui) {
            song.volume = ui.value / 100;
        },
        stop: function(event,ui) {},
    });

    // empty tracker slider
    tracker.slider({
        range: 'min',
        min: 1, max: 500,
		/*value:10,*/
        start: function(event,ui) {},
        slide: function(event, ui) {			
            song.currentTime = parseInt(ui.value);
        },
        stop: function(event,ui) {}
    });	
	add_remove_action();	
	jQuery(".add-pl-item").click(function (){
		var item_data = jQuery(this).attr("id");
		item_data = item_data.split("_");
		add_to_playlist(item_data[0],item_data[1]);
		add_remove_action();
		add_item_to_playlist_action();
	});
});
function add_remove_action(){
	jQuery(".remove-item-playlist").click(function (){
	/*jQuery(".remove-item-playlist").live("click",function (){*/
		var pl_item_id = jQuery(this).parent("li").attr("id");
		item_data = pl_item_id.split("_");
		var can_remove = true;
		if(parseInt(item_data[1]) > 0){
			//ajax function to delete playlist item here
			var request = $.ajax({
				url: "remove-playlist-item-"+item_data[1]+"-"+item_data[2].toLowerCase()+".htm",
				method: "POST",
				data: { },
				dataType: "html",
				success:function(data){
					 if(data == 1){
						 //item removed from  DB successfully
					 }else if(data == 2){
						 // not logged in so we just remove li from list
					 }else if(data == -1){
						 //you have no permision to delete this item
						 can_remove = false;
						 alert("عفوا ، ليس لديك الصلاحية الكافية لحذف هذا العنصر من قائمة التشغيل");
					 }else{
						 // not connected
					 }
				}
			});
		}
		if(can_remove){			
			var li_class = jQuery(this).parent("li").attr("class");
			if(jQuery(this).parent("li").hasClass("active")){
				//currently playing....
				var next = jQuery(this).parent("li").next("li");
				if (next.length == 0) {
					next = $('.playlist li:first-child');
				}
				next.addClass("active");
				stopAudio();
				initAudio(next);
				playAudio();
				jQuery(this).parent("li").remove();
			}else{
				//not current playing
				jQuery(this).parent("li").remove();
			}
		}
	add_remove_action();
	add_item_to_playlist_action();
	});
}
function add_to_playlist(item_id,item_section){
		if(!item_id || !item_section){
			return false;
		}
		var list_items_count = jQuery("ul.playlist li").length;
		var request = $.ajax({
			url: "playlist-item-"+item_id+".htm",
			method: "POST",
			data: {item_id : item_id ,item_section : item_section },
			dataType: "html",
			success:function(data){
				 if(data == -1){
					 //invalid Item
					 alert("عفوا ، لا يمكن اضافة هذا العنصر الى قائمة التشغيل");
				 }else if(data == 1){
					 //Item added before
					 alert("هذا العنصر تم اضافته الى قائمة التشغيل من قبل");
				 }else if(data == 2){
					 //PLAYLIST IS FULL
					 alert("عفوا لا يمكن اضافة اكثر من "+max_playlist_items+" عنصر في قائمة التشغيل");
				 }else{
					 // item data found
					 if(jQuery("ul.playlist").hasClass("has-no-items")){
						jQuery("ul.playlist").html(""); 
						jQuery("ul.playlist").removeClass("has-no-items"); 
						jQuery(".current-user-warning").remove();
						jQuery("ul.playlist").append(data);
						jQuery("ul.playlist li#li_"+item_id+"_"+item_section).addClass("active");
						stopAudio();
						initAudio(jQuery("ul.playlist li#li_"+item_id+"_"+item_section));
						playAudio();
					 }else{
						 if(jQuery("ul.playlist li#li_"+item_id+"_"+item_section).length){					
							 alert("هذا العنصر تم اضافته الى قائمة التشغيل من قبل");
						 }else{
							 if(list_items_count >= max_playlist_items){
								 alert("عفوا لا يمكن اضافة اكثر من "+max_playlist_items+" عنصر في قائمة التشغيل");
								 return false;
							 }
							jQuery("ul.playlist").append(data);
							jQuery("ul.playlist li#li_"+item_id+"_"+item_section).addClass("active");
							stopAudio();
							initAudio(jQuery("ul.playlist li#li_"+item_id+"_"+item_section));
							playAudio();
						 }
						 
					 }					 
				 }
			}
		});
	}
function add_item_to_playlist_action(){
	// playlist elements - click
    $('.playlist li').click(function () {
        stopAudio();
        initAudio($(this));
		playAudio();	
		//add_item_to_playlist_action();
    });
}
function save_last_listen(elem_id){
	if(!elem_id || !logged_in)
		return false;
	item_data = elem_id.split("_");
	var request = $.ajax({
		url: "save-last-listen.htm",
		method: "POST",
		data: {item_id : item_data[1] ,item_section : item_data[2] },
		dataType: "html",
		success:function(data){
			// last listen saved
		}
	});
}
function check_logged_in_member(){
	if(jQuery("#w2a_l").length){
		var w2a_l = parseInt(jQuery("#w2a_l").val());
		if(w2a_l > 0){
			logged_in = true;
		}
	}
}
function radio_createCookie(name,value, days) {
	if (days) {
		var date = new Date();
		date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
		var expires = "; expires=" + date.toGMTString();
	} else var expires = "";
	document.cookie = name + "=" + value + expires + "; path=/";
}
function radio_getCookie(cname) {
    var name = cname + "=";
    var ca = document.cookie.split(';');
    for(var i = 0; i <ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') {
            c = c.substring(1);
        }
        if (c.indexOf(name) == 0) {
            return c.substring(name.length,c.length);
        }
    }
    return "";
}
function radio_format_time(t){
	var sec_num = parseInt(t, 10); // don't forget the second param
    var hours   = Math.floor(sec_num / 3600);
    var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
    var seconds = sec_num - (hours * 3600) - (minutes * 60);

    if (hours   < 10) {hours   = "0"+hours;}
    if (minutes < 10) {minutes = "0"+minutes;}
    if (seconds < 10) {seconds = "0"+seconds;}
    return hours+':'+minutes+':'+seconds;
}
function getmetadata(){
	tracker.slider("option", "max", song.duration);
	jQuery(".player-timer span.total-t").html(radio_format_time(song.duration));
	jQuery(".play-loading").hide("fast");
}