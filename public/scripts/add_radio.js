var max_playlist_items = 50;
jQuery(document).ready(function (){
	jQuery(".media-add-to-radio").click(function (){
		var item_data = jQuery(this).attr("id");
		item_data_arr = item_data.split("-");
		var request = $.ajax({
			url: "playlist-item-"+item_data_arr[1]+".htm",
			method: "POST",
			data: {item_id : item_data_arr[1] ,item_section : item_data_arr[2],only_insert : true },
			dataType: "html",
			success:function(data){
				console.log(data);
				 if(data == -1){
					 //invalid Item
					 alert("عفوا ، لا يمكن اضافة هذه المادة الى قائمة التشغيل");
				 }else if(data == 1){
					 //Item added before
					 alert("هذه المادة تم اضافتها الى قائمة التشغيل الخاصة بك من قبل");
				 }else if(data == 2){
					 //PLAYLIST IS FULL
					 alert("عفوا لا يمكن اضافة اكثر من "+max_playlist_items+" مادة في قائمة التشغيل");
				 }else{
					 // item data added to your playlist
					 alert("تم اضافة المادة الى قائمة التشغيل الخاصة بك بنجاح");
				 }
			}
		});
	});
});