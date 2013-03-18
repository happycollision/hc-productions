(function($){
	var $undo = new Array();

	$.fn.hideShowDetails = function (){
		if($(this).parent().find('.hidden').is(':visible')){
			$(this).html('Show Details:');
			$(this).parent().find('.hidden').stop().slideUp();
		}else{
			$(this).html('Hide Details:');
			$(this).parent().find('.hidden').stop().slideDown();
		}
	}
	
	$.fn.clearForm = function () {return this.each(function(){
		$(this).find(':input').each(function() {
			switch(this.type) {
				case 'password':
				case 'select-multiple':
				case 'select-one':
				case 'text':
				case 'textarea':
				case 'hidden':
					$(this).val('');
					break;
				case 'checkbox':
				case 'radio':
					this.checked = false;
			}
		});
	});}

	$.fn.repeatForm = function repeatForm() {
		if(typeof repeatForm.theCount ==='undefined'){
			repeatForm.theCount = $('#exact_running_dates_meta input[type="text"]:last').attr('name').match(/([A-z]+-[A-z]+-)(\d+)/)[2];
		}
		var theCount = ++repeatForm.theCount;
		return this.each(function(){
			var $input = $(this).find(':input');
			$input.each(function(){
				try{
					var matched = $(this).attr('name').match(/([A-z]+-[A-z]+-)(\d+)/);
					$(this).attr('name',matched[1] + theCount);
				}catch(err){}
			});
		});
	}
	
	//show and hide the extras
	$('body').on('click','.hide_show', function(){
		$(this).hideShowDetails();
	});
	$('body').on('focusin','.performances', function(){
		$(this).find('.hide_show').html('Hide Details:');
		$(this).find('.hidden').stop().slideDown();
	});
	
	//create/destroy form fields
	//unhide the buttons
	$('#exact_running_dates_meta .button.hidden').removeClass('hidden');
	
	//add another field
	$('body').on('click','#exact_running_dates_meta .button[role="add"]',function(){
		var $new_performance = $(this).prev('.performances').clone();
		$new_performance.clearForm().find('.hidden').hide();
		$new_performance.repeatForm();
		$new_performance.find('.hide_show').html('Show Details:');
		$new_performance.insertBefore($(this)).hide().show('fast');
	});
	
	//remove/clear current field
	$('body').on('click','#exact_running_dates_meta .button[role="remove"]', function(){
		if($('.performances').length > 1){
			$(this).parent().css('background-color','#d44').fadeOut(function(){
				$undo.push($(this).remove());
				if($('input[type="button"].undoRemove').length < $('.performances hr').length){
					$('input[type="button"].undoRemove').remove();
					$('<input type="button" style="float:right;" class="button undoRemove" value="Oops. Undo that remove."/>').insertAfter($('#exact_running_dates_meta hr')).hide().fadeIn('slow');
				}
			});
		}else{
			var $old_performance = $(this).parent();
			var $new_performance = $old_performance.clone();
			$undo.push($old_performance.clone());
			$new_performance.clearForm().find('.hidden').hide();
			$new_performance.find('.hide_show').html('Show Details:');
			$old_performance.replaceWith($new_performance);
		}
	});
	
	$('body').on('click','#exact_running_dates_meta .button.undoRemove', function(){
		console.log($undo);
		var $redo = $undo.pop();
		$redo.insertAfter('.performances:last').attr('style','').css('opacity','0');
		 $('html, body').animate({
			 scrollTop: $redo.offset().top
		 }, 300, function(){
			$redo.animate({'opacity':'1'},400);
		 });		
		if($undo.length < 1){
			$('.undoRemove').fadeOut().remove();
		}
	});
	
	
})(jQuery)