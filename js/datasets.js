/*
   datasets.js
   Javascript for the Labrador Datasets page
*/

// View - Filter datasets by text entry
$('#filter-datasets').keyup(function(e){
	if($(this).val().length == 0){
		$('#existing_datasets_table tbody tr').show();
	} else {
		$('#existing_datasets_table tbody tr').hide();
		var filterText = $(this).val().toLowerCase();
		$('#existing_datasets_table tbody tr td').filter(function(i){
			if($(this).text().toLowerCase().indexOf(filterText) >= 0){
				return true;
			} else {
				return false;
			}
		}).parent().show();
	}
});

// Edit - Batch update checked datasets
$('.bulk_update').keyup(function(e){
	var id = $(this).attr('id');
	var newval = $(this).val();
	$('.table_form tbody tr').each(function(){
		if($(this).find('.select-row').is(':checked')){
			$(this).find('input.'+id).val(newval);
		}
	});
});

///////////////////
// ADD DATASETS
///////////////////

// Add x number of dataset rows
$('#btn_add_datasets').click(function(e){
	e.preventDefault();
	var num = Number($.trim($('#num_datasets').val()));
	if(isNaN(num) || num < 1){
		num = 1;
	}
	$('#num_datasets').val(num);
	var numRows = $('#add_existing_datasets_table tbody tr').length;
	for (var i=1; i <= num; i++) {
		var j = i + numRows;
		$('#add_existing_datasets_table tbody').append('<tr><td class="select"><input type="checkbox" class="select-row" id="check_'+j+'"></td><td><input class="name" type="text" name="name_'+j+'" id="name_'+j+'"></td><td><input class="species" type="text" id="species_'+j+'" name="species_'+j+'"></td><td><input class="cell_type" type="text" id="cell_type_'+j+'" name="cell_type_'+j+'"></td><td><input class="data_type" type="text" id="data_type_'+j+'" name="data_type_'+j+'"></td><td><input class="accession_geo" type="text" id="accession_geo_'+j+'" name="accession_geo_'+j+'"></td><td><input class="accession_sra" type="text" id="accession_sra_'+j+'" name="accession_sra_'+j+'"></td></tr>');
	}
});

// Remove checked datasets
$('#remove_datasets_button').click(function(){
	$('.table_form tbody tr').each(function(){
		if($(this).find('.select-row').is(':checked')){
			$(this).remove();
		}
	});
	// go through renumbering everything
	var i = 1;
	$('.table_form tbody tr').each(function(){
		$(this).find('td input').each(function(){
			var id = $(this).attr('id').replace(/[0-9]/g, '') + i;
			$(this).attr('id', id);
			$(this).attr('name', id);
		});
		i++;
	});
});

// GEO ACCESSION LOOKUP
$('.geo_accession_lookup').click(function(e){
	e.preventDefault();
	
	// Change the icon and disable other buttons
	var icon = $(this).children();
	icon.removeClass('icon-search icon-remove').addClass('icon-refresh icon-rotate-animate');
	$('.action_buttons button').attr('disabled','disabled');
	
	// Find the accession number that we're looking for
	var acc = $(this).attr('data-accession');	
	
	$.getJSON('ajax/get_geo_datasets.php?acc='+acc, function(data) {
		
		// Check that the call succeeded
		if(data.status == 1){
			var duplicates = 0;
			var numRows = $('#add_existing_datasets_table tbody tr').length;
			// If we only have one empty row, remove it
			if(numRows == 1 &&
					(typeof $('#name_1').val() == 'undefined' || $('#name_1').val().length == 0) &&
					(typeof $('#species_1').val() == 'undefined' || $('#species_1').val().length == 0) &&
					(typeof $('#cell_type_1').val() == 'undefined' || $('#cell_type_1').val().length == 0) &&
					(typeof $('#data_type_1').val() == 'undefined' || $('#data_type_1').val().length == 0) &&
					(typeof $('#accession_geo_1').val() == 'undefined' || $('#accession_geo_1').val().length == 0) &&
					(typeof $('#accession_sra_1').val() == 'undefined' || $('#accession_sra_1').val().length == 0) &&
					(typeof $('#notes_1').val() == 'undefined' || $('#notes_1').val().length == 0) ){
				numRows = 0;
				$('#add_existing_datasets_table tbody').html('');
			}
			
			// Go through each JSON sample
			$.each(data.samples, function(i, sample){
				if(sample.duplicate == 'false'){
					// Add table row
					var j = Number(i) + 1 + numRows;
					$('#add_existing_datasets_table tbody').append('<tr><td class="select"><input type="checkbox" class="select-row" id="check_'+j+'"></td><td><input class="name" type="text" name="name_'+j+'" id="name_'+j+'"></td><td><input class="species" type="text" id="species_'+j+'" name="species_'+j+'"></td><td><input class="cell_type" type="text" id="cell_type_'+j+'" name="cell_type_'+j+'"></td><td><input class="data_type" type="text" id="data_type_'+j+'" name="data_type_'+j+'"></td><td><input class="accession_geo" type="text" id="accession_geo_'+j+'" name="accession_geo_'+j+'"></td><td><input class="accession_sra" type="text" id="accession_sra_'+j+'" name="accession_sra_'+j+'"></td></tr>');
					// Fill in inputs
					$('#name_'+j).val(sample.name);
					$('#species_'+j).val(sample.species);
					// $('#cell_type_'+j).val(sample.cell_type); // CAN'T EXTRACT THIS YET :(
					$('#data_type_'+j).val(sample.data_type);
					$('#accession_geo_'+j).val(sample.accession_geo);
					$('#accession_sra_'+j).val(sample.accession_sra);
				} else {
					duplicates++;
				}
			});
			
			// Did we ignore any duplicates?
			if(duplicates > 0){
				$('#lookup_error .msg_content').html(duplicates+' datasets were ignored due to sharing accession numbers with datasets already in Labrador.');
				$('#lookup_error').slideDown();
			}
			
			// Finished!
			// Show info about not adding all datasets if they're not all wanted
			$('#lookup_warning').slideDown();
		} else {
			// Status 0 - Something went wrong
			$('#lookup_error .msg_content').html('<strong>Error:</strong> '+data.message);
			$('#lookup_error').slideDown();
		}
		
		// All done! Remove spinner and disabled states
		icon.removeClass('icon-refresh icon-rotate-animate').addClass('icon-search');
		$('.action_buttons button').removeAttr('disabled');
	});
});