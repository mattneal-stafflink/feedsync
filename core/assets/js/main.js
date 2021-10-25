$(document).ready( function(jQuery) {

	$("a[rel^='prettyPhoto']").prettyPhoto({social_tools : false});

	$('[data-toggle="tooltip"]').tooltip();
 	//$('[data-toggle="popover"]').popover();

 // 	var getMap = function(opts) {
	//   var src = "http://maps.googleapis.com/maps/api/staticmap?",
	//       params = $.extend({
	//         markers: 'New York, NY',
	//         zoom: 17,
	//         size: '350x350',
	//         maptype: 'roadmap',
	//         sensor: false
	//       }, opts),
	//       query = [];

	//   $.each(params, function(k, v) {
	//     query.push(k + '=' + encodeURIComponent(v));
	//   });

	//   src += query.join('&');
	//   return '<img src="' + src + '" />';
	// }

 // 	$('.item-has-map').on('mouseenter', function(e) {
 // 		e.preventDefault();
 // 		var id = $(this).attr('id')+'-wrap';
 // 		var coords = $(this).attr('title');
 // 		var map_content = getMap({markers: coords});
	// 	$(this).popover({ html:true, content: map_content, placement:'left' });
	// 	$(this).popover('show');

		
 // 	});

 // 	$('.item-has-map').on('mouseleave', function(e) {
 // 		e.preventDefault();
	// 	$(this).popover('hide');

		
 // 	});
 	

	$('#test_connection').on('click',function(e) {
		e.preventDefault();
		var formData = $(this).closest('form').serialize();
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {formData : formData , action: "test_connection"},
			dataType : 'json'
		})
		.done(function( response ) {
			$('.row.response .form-group').html(response.message);
		});

	});

	$('#import_listings').on('click',function(e) {
		e.preventDefault();
		$('.fs-status-panel').removeClass( 'alert-danger' );
		$('.fs-status-panel').addClass( 'alert-success' );
		$('.fs-status-panel').html('Processing started ... <br> <strong>Currently processing your files, do not navigate away from this page. </strong> ').addClass('ajax-loading');
		//$('body').append('<div class="feedsync-overlay"></div>');
		import_listings();
	});

	function import_listings() {
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {action: "import_listings"},
			dataType : 'json'
		})
		.done(function( response ) {

			if(!response) {
				$('.fs-status-panel').removeClass("alert-success").addClass( "alert-danger");
				$('.alert.alert-danger').html('Oops! There seem to be a connection issue. Please click on <strong>process</strong> to continue processing.');
				$('.alert.alert-danger').removeClass("alert-danger").addClass( "alert-success");
			}

			if(response.status == 'success') {
				$('.fs-status-panel').html(response.message);

				if(response.buffer == 'processing') {
					try {
						import_listings();
					}
					catch(err) {
						$('.fs-status-panel').html('Please reload page & click on <strong>process</strong> to continue processing.');
					}

				} else {
					$('.fs-status-panel').removeClass('ajax-loading');
					//$('body').find('.feedsync-overlay').remove();
				}
			}
			if(response.status == 'fail') {
				$('.fs-status-panel').removeClass("alert-success").addClass( "alert-danger");
				$('.alert.alert-danger').html(response.message);

				if(response.buffer == 'processing') {
					try {
						import_listings();
					}
					catch(err) {
						$('.alert.alert-danger').html('Please reload page & click on <strong>process</strong> to continue processing.');
					}

				} else {
					$('.alert.alert-danger').removeClass('ajax-loading');
					//$('body').find('.feedsync-overlay').remove();
				}
				$('.alert.alert-danger').removeClass("alert-danger").addClass( "alert-success");
			}
		});
	}

	$('#process_missing_coordinates').on('click',function(e) {
		e.preventDefault();
		$('.fs-status-panel').removeClass( 'alert-danger' );
		$('.fs-status-panel').addClass( 'alert-success' );
		$('.fs-status-panel').html('Geocode processing started ... ');
		process_missing_coordinates();

	});

	$('.item-has-map').on('click',function(e) {
		e.preventDefault();
		var this_link = $(this);
		$('.fs-status-panel').html('Regenerating Coordinates... ');
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {
				action	: "regenerate_coordinates",
				id 		: $(this).closest('tr').data('id')
			},
			dataType : 'json'
		})
		.done(function( response ) {
			if( response.status == 'success' ) {
				this_link.attr('title', response.coordinates );
				this_link.attr('data-original-title', response.coordinates );
				var img = fs.images_url+'feedsync-map.svg';
				this_link.find('img').attr( 'src', img);
			}
		});

	});

	function process_missing_coordinates() {
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {action: "process_missing_coordinates"},
			dataType : 'json'
		})
		.done(function( response ) {

			var classname = response.status == 'success' ? 'alert-success' : 'alert-danger';
			$('.fs-status-panel').removeClass( 'alert-danger alert-success' );
			$('.fs-status-panel').addClass( classname );
			$('.fs-status-panel').html(response.message);
			if(response.buffer == 'processing') {
				try {
					process_missing_coordinates();
				}
				catch(err) {
					$('.fs-status-panel').html('Please reload page & click on <strong>process missing coordinates</strong> to continue processing.');
				}
			}
		});
	}

	$('#upgrade_table_data').on('click',function(e) {
		e.preventDefault();
		$('.fs-status-panel').removeClass( 'alert-danger' );
		$('.fs-status-panel').addClass( 'alert-success' );

		$('.fs-status-panel').html('upgrade started ... ');
		upgrade_table_data();

	});

	function upgrade_table_data() {
		
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {action: "upgrade_table_data"},
			dataType : 'json'
		})
		.done(function( response ) {
			$('.fs-status-panel').html(response.message);
			if(response.buffer == 'processing') {
				try {
					upgrade_table_data();
				}
				catch(err) {
					$('.fs-status-panel').html('Please reload page & click on <strong>Process Table Upgrade</strong> to continue processing.');
				}
			}
		});
	}

	$('#process_missing_listing_agents').on('click',function(e) {
		e.preventDefault();
		$('.fs-status-panel').removeClass( 'alert-danger' );
		$('.fs-status-panel').addClass( 'alert-success' );
		$('.fs-status-panel').html('Listing agents processing started ... ');
		process_missing_listing_agents();

	});

	function process_missing_listing_agents() {
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {action: "process_missing_listing_agents"},
			dataType : 'json'
		})
		.done(function( response ) {
			$('.fs-status-panel').html(response.message);

		});
	}

	$('#reset_feedsync').on('click',function(e) {
		e.preventDefault();
		$('.feedsync-reset-wrap').fadeToggle();
		$('#confirm_table_reset')
	});

	$('#confirm_table_reset').on('click',function(e) {
		var btn = $('#reset_confirm_pass');
		e.preventDefault();
		if( $('#reset_confirm_pass').val() == '') {
			alert('Admin Password is required for reset');
			return false;
		}

		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {action: "reset_feedsync_table" , pass : $('#reset_confirm_pass').val() },
			dataType : 'json'
		})
		.done(function( response ) {
			var tpl ='<div class="alert alert-'+response.status+'"><p>'+response.message+'</p></div>';
			btn.before(tpl);
		});
	});

	function feedsync_update_version(step = 'clean') {

		var data = {
			action	: "feedsync_update_version" ,
			link 	: $(this).data('link'),
			step 	: step
		}

		$.ajax({
			method		: "POST",
			url 		: fs.ajax_url,
			data 		: data,
			dataType	: 'json'
		})
		.done(function( response ) {
			if(response.status == 'processing') {

				if( $('.alert.alert-danger').length )
					$('.alert.alert-danger').removeClass("alert-danger").addClass( "alert-success");

				$('.fs-status-panel').html('<p><strong>'+response.message+'</strong></p>');
				feedsync_update_version(response.next_step);

			} else if(response.status == 'complete') {

				if( $('.alert.alert-danger').length )
					$('.alert.alert-danger').removeClass("alert-danger").addClass( "alert-success");

				$('.fs-status-panel').html('<p><strong>'+response.message+'</strong></p>');
			}else if(response.status == 'error') {

				if( $('.fs-status-panel').length )
					$('.fs-status-panel').removeClass("alert-success").addClass( "alert-danger");

				$('.alert.alert-danger').html('<p><strong>'+response.message+'</strong></p>');

			}
			
		});
	}

	$('#feedsync-upgrade').on('click',function(e) {

		e.preventDefault();
		$('.fs-status-panel').append('<p><strong>Upgrade process started...</strong></p>');
		feedsync_update_version('clean');
	});

	

	$('#delete-records-btn').on('click', function(e) {
	  var $form = $(this).closest('form');
	  e.preventDefault();
	  $('#confirm').modal({
	      backdrop: 'static',
	      keyboard: false
	    })
	    .one('click', '#delete', function(e) {
	      $form.trigger('submit');
	    });
	});

	$('#select_all_items').on('click', function(e) {
	  var $form = $(this).closest('form');
	  $form.find('input[name="delete_items[]"]').prop('checked', this.checked);
	});

	$('input[name="delete_items[]"],#select_all_items').on('click', function(e) {

	  	if( $('input[name="delete_items[]"]:checked').length > 0){
	  		$('#delete-records-btn').prop('disabled',false);
	  		$('#delete-records-btn').addClass('btn-danger');
	  	} else {
	  		$('#delete-records-btn').prop('disabled',true);
	  		$('#delete-records-btn').removeClass('btn-danger');
	  	}
	});

	$('th a').on('click', function(e) { 
		
		if( readCookie('order') == null || readCookie('order') == 'ASC' ) {
			createCookie('order', 'DESC');
		} else  {
			createCookie('order', 'ASC');
		}

	});

	$('.mark-fav').on('click',function(e) {
		var _this = $(this);
		e.preventDefault();
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {
				action	: "feedsync_mark_fav" ,
				id 		: $(this).closest('td').data('id')
			}
		})
		.done(function( response ) {
			_this.find('span').toggleClass('rated');
		});
	});

	$('#filter-listings').on('click',function(e) {

		e.preventDefault();
		var url 		= window.location.href.split('?')[0];
		var querystring = window.location.href.split('?')[1];


		if(querystring == null) {
			var newUrl 		= url +'?'+$('#filter-type').val()+'='+$('#filter-val').val();
		}
		else {
			var newUrl 		= url +'?'+querystring+'&'+$('#filter-type').val()+'='+$('#filter-val').val();
		}
		window.location.replace(newUrl);
	});

	$('.fs-status-dropdown').on( 'change', function() {
		let td = $(this).closest('td');
		let dataID = $(this).closest('tr').data('id');
		let status = $(this).val();
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {
				action	: "switch_status",
				id 		: dataID,
				status  : status
			},
			dataType : 'json'
		})
		.done(function( response ) {
			if( response.status == 'success' ) {
				td.find('.fs-status-text').text(status);
				td.removeClass().addClass('status fs-status-cell fs-editing-allowed '+status);
			}
		});
	});

	function fs_listings_map() {

		if ($( '#fs-listing-map' ).length == 0) {
			return false;
		}
		var address = $( '#fs-listing-map' ).data( 'address' );

		var address_coordinates = '';

		if ( ! window.google ) {
			return false;
		}

		if ( address == '' ) {
			return false;
		}
		
		var geocoder = new google.maps.Geocoder();
		geocoder.geocode(
			{
				"address": address
			},
			function(results) {
				address_coordinates = results[0].geometry.location;
				var map             = new google.maps.Map(
					document.getElementById( 'fs-listing-map' ),
					{
						zoom: 14,
						center: new google.maps.LatLng( address_coordinates.lat().toString() , address_coordinates.lng().toString() ),
						mapTypeId: google.maps.MapTypeId.ROADMAP
					}
				);

				var mapMarker = new google.maps.Marker(
					{

						position: new google.maps.LatLng( address_coordinates.lat().toString() , address_coordinates.lng().toString() ),
						draggable: true
					}
				);

				map.setCenter( mapMarker.position );
				mapMarker.setMap( map );
			}
		);

	}
	fs_listings_map();

	if( jQuery('.fs-images-table').length ) {

		/** Wait till all images are loaded before fetching their dimensions */
		imagesLoaded(document.body, function() {

			jQuery('.fs-images-table').find('img').each( function() {
			    let width = $(this).get(0).naturalWidth;
			    let height = $(this).get(0).naturalHeight;
			    let dimensions = width + ' x '+ height;
			    console.log($(this).get(0));
			    $(this).closest('.image-holder').find('.fs-image-dimension').html(dimensions);
			});

		});
		
	}

	/** Test connection **/
	$('#feedsync_reaxml_test_connection').on('click',function(e) {
		e.preventDefault();
		var wrapper = $(this).closest('.form-group');
		var formData = $(this).closest('form').serialize();
		$.ajax({
			method: "POST",
			url : fs.ajax_url,
			data: {formData : formData , action: "test_reaxml_fetch_connection"},
			dataType : 'json'
		})
		.done(function( response ) {
			$(response.message)
		    .appendTo(wrapper)
		    .timeout(5000, function(){ this.fadeOut().remove(); });
		});

	});
});

/** retain tab even after page reload */
$(document).ready(function() {
    if(location.hash) {
        $('a[href=' + location.hash + ']').tab('show');
    }
    $(document.body).on("click", "a[data-toggle]", function(event) {
        location.hash = this.getAttribute("href");
    });
});

$(window).on('popstate', function() {
    var anchor = location.hash || $("a[data-toggle=tab]").first().attr("href");
    $('a[href=' + anchor + ']').tab('show');
});

function createCookie(name, value, days) {
    var expires;

    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
    } else {
        expires = "";
    }
    document.cookie = encodeURIComponent(name) + "=" + encodeURIComponent(value) + expires + "; path=/";
}

function readCookie(name) {
    var nameEQ = encodeURIComponent(name) + "=";
    var ca = document.cookie.split(';');
    for (var i = 0; i < ca.length; i++) {
        var c = ca[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nameEQ) === 0) return decodeURIComponent(c.substring(nameEQ.length, c.length));
    }
    return null;
}

function eraseCookie(name) {
    createCookie(name, "", -1);
}
(function($){ 
    $.fn.timeout = function(ms, callback)
    {
        var self = this;
        setTimeout(function(){ callback.call(self); }, ms);
        return this;
    }
})(jQuery);