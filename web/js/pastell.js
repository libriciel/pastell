
$(document).ready(function() {
	$(".noautocomplete").attr('autocomplete','off');

	$(".send_button").click(function(){
		$(this.form).append("<input type='hidden' name='" + this.name + "' value='true' />");
		$(this.form).append("<p>Sauvegarde en cours ...</p>");
		$(".send_button").attr('disabled', true);
		$(this.form).submit();
	});

	$('#select-all').click(function(event) {
		var result = this.checked;
		$(':checkbox').each(function() {
	            this.checked = result;
		});
	});

    $.fn.select2.defaults.set("language", "fr");

    $(".select2_entite").select2({
            placeholder: 'Sélectionner une entité',
						width: "25%"
	});

	$(".select2_role").select2({
        placeholder: 'Sélectionner un rôle',
				width: "25%"
    });

    $(".select2_document").select2({
        placeholder: 'Sélectionner un type de dossier'
    });

	$('.select2_breadcrumb').select2({
		placeholder: 'Sélectionner une entité fille',
		allowClear: true
	});

    $('.select2_etat').select2({
        placeholder: 'Sélectionner un état'
    });

		$('.select2_appearance').select2({
        minimumResultsForSearch: -1
    });

	 $('[data-toggle="tooltip"]').tooltip()


	$('.collapse-link').click(function(){
		$(this).find("i").toggleClass('fa-plus-square');
        $(this).find("i").toggleClass('fa-minus-square');
	});



	$(".fa-calendar").click(function(){
        var input = $(this).parents(".input-group").find("input");
        if (input.datepicker("widget").is(":visible")){
            input.datepicker('hide');
		} else {
            input.datepicker('show');
		}
	});


});

function split( val ) {
	return val.split( /,\s*/ );
}

function extractLast( term ) {
	return split( term ).pop();
}


(function ( $ ) {

$.fn.pastellAutocomplete = function(autocomplete_url,id_e,mail_only) {
	this.autocomplete({
		source: function( request, response ) {
			$.getJSON( autocomplete_url, {
				term: extractLast( request.term ), "id_e": id_e, "mail-only": mail_only
			}, response );
		},
		select: function( event, ui ) {
			var terms = split( this.value );
			terms.pop();
			terms.push( ui.item.value );
			terms.push( "" );
			this.value = terms.join( ", " );
			return false;
		} ,
	});
	return this;
};

}(jQuery));



function addFlowControl(query_param, pastell_flow_upload) {
	console.log(query_param);
	console.log(pastell_flow_upload);


	button_libelle = query_param.single_file?"Ajouter un fichier":"Ajouter un (ou des) fichier(s)";
	pastell_flow_upload.html(
		"        <div class=\"flow-error\">\n" +
		"            <input type='file' class='btn btn-outline-primary' id='" + query_param.field + "'  name='" + query_param.field + "' accept='" + query_param.accept + "'/>\n" +
		"        </div>\n" +
		"\n" +
		"         <div>\n " +
		"            <a class=\"flow-browse btn btn-outline-primary\"><i class='fa fa-plus-circle'></i>&nbsp;"+button_libelle+"</a>\n" +
		"            <a href=\"#\" class=\"progress-resume-link btn\">Reprendre</a>\n" +
		"            <a href=\"#\" class=\"progress-pause-link btn\">Pause</a>\n" +
		"            <a href=\"#\" class=\"progress-cancel-link btn\">Abandon</a>\n" +
		"        </div>\n" +
		"\n" +
		"        <div class=\"flow-progress\">\n" +
		"            <table>\n" +
		"                <tr>\n" +
		"                    <td><div class=\"progress-container\"><div class=\"progress-bar\"></div></div></td>\n" +
		"                </tr>\n" +
		"                <tr>\n" +
		"                    <td><ul class=\"flow-list unstyled\"></ul></td>\n" +
		"                </tr>\n" +
		"            </table>\n" +
		"        </div>\n");


	var r = new Flow({
		target: query_param.target,
		query: {
			'csrf_token': query_param.token_value,
			'id_e': query_param.id_e,
			'id_d': query_param.id_d,
			'id_ce': query_param.id_ce,
			'field': query_param.field,
            'key': query_param.key,
			'page': query_param.page
		},
		singleFile: query_param.single_file,
		chunkSize: 1024 * 1024,
		testChunks: true
	});

	// Flow.js isn't supported, fall back on a different method
	if (!r.support) {
		pastell_flow_upload.find('.flow-error').show();
		return;
	}

	pastell_flow_upload.find(".progress-pause-link").click(function () {
		r.pause();
		$(pastell_flow_upload).find('.progress-resume-link').show();
		$(pastell_flow_upload).find('.progress-pause-link').hide();
		return false;
	});

	pastell_flow_upload.find(".progress-resume-link").click(function () {
		r.resume();
		$(pastell_flow_upload).find('.progress-resume-link').hide();
		$(pastell_flow_upload).find('.progress-pause-link').show();
		return false;
	});

	pastell_flow_upload.find(".progress-cancel-link").click(function () {
		r.cancel();
		$(pastell_flow_upload).find('.progress-pause-link').hide();
		$(pastell_flow_upload).find('.progress-resume-link').hide();
		$(pastell_flow_upload).find('.progress-cancel-link').hide();
		$(pastell_flow_upload).find('.flow-progress').hide();
		$(pastell_flow_upload).find('.flow-file').remove();
		return false;
	});

	r.assignBrowse(
		pastell_flow_upload,
		false,
		query_param.single_file,
		{'accept': query_param.accept}
	);

	// Handle file add event
	r.on('fileAdded', function (file, event) {
		pastell_flow_upload = $(event.target).parents(".pastell-flow-upload")[0];

		// Show progress bar
		$(pastell_flow_upload).find('.flow-progress, .flow-list').show();
		const nameSpan = $('<span class="flow-file-name"></span>').text(file.name);

		// Add the file to the list
		$(pastell_flow_upload).find('.flow-list').append(
			'<li class="flow-file flow-file-' + file.uniqueIdentifier + '">' +
			nameSpan +
			'<span class="flow-file-size"></span> ' +
			'<span class="flow-file-progress"></span> ' + "</li>"
		);
	});

	r.on('filesSubmitted', function (file) {
		r.upload();
	});

	r.on('complete', function () {
		$(pastell_flow_upload).find('.progress-resume-link').hide();
		$(pastell_flow_upload).find('.progress-pause-link').hide();
		$(pastell_flow_upload).find('.progress-cancel-link').hide();

		var numberOfDownload = $(".progress-cancel-link:visible").length;
        if ( numberOfDownload === 0){
			$(pastell_flow_upload)
				.parents("form")
				.append("<input type='hidden' name='fieldSubmittedId' value='" + query_param.field + "'>");
			$(pastell_flow_upload).parents("form").append("<input type='hidden' name='ajouter' value='ajouter'>");
			$(pastell_flow_upload).parents("form").submit();
		}
    });

	r.on('fileSuccess', function (file, message) {
		var $self = $('.flow-file-' + file.uniqueIdentifier);
		$self.find('.flow-file-progress').text('(terminé)');
	});

	r.on('fileError', function (file, message) {
		// Reflect that the file upload has resulted in error
		$('.flow-file-' + file.uniqueIdentifier + ' .flow-file-progress').html('(file could not be uploaded: ' + message + ')');
	});
	r.on('fileProgress', function (file) {
		// Handle progress for both the file and the overall upload
		$('.flow-file-' + file.uniqueIdentifier + ' .flow-file-progress')
			.html(Math.floor(file.progress() * 100) + '% '
				+ readablizeBytes(file.averageSpeed) + '/s '
				+ secondsToStr(file.timeRemaining()) + ' restante(s)');

		var pastell_flow_upload = $('.flow-file-' + file.uniqueIdentifier + ' .flow-file-progress').parents(".pastell-flow-upload")[0];

		$(pastell_flow_upload).find('.progress-bar').css({width: Math.floor(r.progress() * 100) + '%'});
	});
	r.on('uploadStart', function () {
		$(pastell_flow_upload).find('.progress-resume-link').hide();
		$(pastell_flow_upload).find('.progress-pause-link').show();
		$(pastell_flow_upload).find('.progress-cancel-link').show();
	});
	r.on('catchAll', function () {
		console.log.apply(console, arguments);
	});
}

function readablizeBytes(bytes) {
	var s = ['bytes', 'kB', 'MB', 'GB', 'TB', 'PB'];
	var e = Math.floor(Math.log(bytes) / Math.log(1024));
	return (bytes / Math.pow(1024, e)).toFixed(2) + " " + s[e];
}

function secondsToStr(temp) {
	function numberEnding(number) {
		return (number > 1) ? 's' : '';
	}

	var years = Math.floor(temp / 31536000);
	if (years) {
		return years + ' year' + numberEnding(years);
	}
	var days = Math.floor((temp %= 31536000) / 86400);
	if (days) {
		return days + ' day' + numberEnding(days);
	}
	var hours = Math.floor((temp %= 86400) / 3600);
	if (hours) {
		return hours + ' hour' + numberEnding(hours);
	}
	var minutes = Math.floor((temp %= 3600) / 60);
	if (minutes) {
		return minutes + ' minute' + numberEnding(minutes);
	}
	var seconds = temp % 60;
	return seconds + ' seconde' + numberEnding(seconds);
}
