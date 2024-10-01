
function update_disableEmail(check,id = ''){
	var val = (check)?1:0;

	$('#manage_disability').toggleClass('disableEmail');
	$('.toggleread').prop('readonly',check);
	$('.togglefile').prop('disabled',check);
	
	if(id != ''){
		$.ajax({
		    url: "admin-ajax.php",
		    type: "POST",
		    dataType: 'JSON',
		    data: {
	        	action:'update_disableEmail',
	        	disableEmail: val,
	        	where:id,
	    	},
		    success: function(data){
		    	console.log(data);		
		    }
		});
	}

}