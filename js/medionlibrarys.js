var medionlibrarysPage = 0;
var medionlibrarysLoading = false;
var dialog;
var medionlibrarysSorting = 'medionlibrarys_sorting_recent';
var fullTags = [];
var ajaxCallCount = 0;

$(document).ready(function () {
	getTags();
	watchUrlField();
	$('#bm_import').change(attachSettingEvent);
	$('#add_url').on('keydown keyup change click', watchUrlField);
	$('#app-settings').on('click keydown', toggleSettings);
	$('#bm_export').click(exportBm);
	$('#emptycontent-setting').click(function () {
		if (!$('#app-settings').hasClass('open')) {
			$('#app-settings').click();
		}
	});
	$('.medionlibrarys_list').scroll(updateOnBottom).empty();
	$('#tag_filter input').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		onTagFinishRemoved: filterTagsChanged,
		placeholderText: t('medionlibrarys', 'Filter by tag')
	}).tagit('option', 'onTagAdded', filterTagsChanged);
	getMedionlibrarys();
});

function getTags() {
	jQuery.ajax({
		url: 'tag',
		success: function (result) {
			fullTags = result;
		},
		async: false
	});
}

var formatString = (function () {
	var replacer = function (context) {
		return function (s, name) {
			return context[name];
		};
	};

	return function (input, context) {
		return input.replace(/\{(\w+)\}/g, replacer(context));
	};
})();

function increaseAjaxCallCount() {
	ajaxCallCount++;
	if (ajaxCallCount - 1 === 0) {
		updateLoadingAnimation();
	}
}

function decreaseAjaxCallCount() {
	if (ajaxCallCount > 0) {
		ajaxCallCount--;
		updateLoadingAnimation();
	}
}

function updateLoadingAnimation() {
	if (ajaxCallCount === 0) {
		$('#medionlibrary_add_submit').removeClass('icon-loading-small');
		$('#medionlibrary_add_submit').addClass('icon-add');
	} else {
		$('#medionlibrary_add_submit').removeClass('icon-add');
		$('#medionlibrary_add_submit').addClass('icon-loading-small');
	}
}

function watchClickInSetting(e) {
	if ($('#app-settings').find($(e.target)).length === 0) {
		toggleSettings();
	}
}

function checkURL(url) {
	if (url.substring(0, 3) === "htt") {
		return url;
	}
	return "http://" + url;
}

function toggleSettings() {
	if ($('#app-settings').hasClass('open')) { //Close
		$('#app-settings').switchClass("open", "");
		$('body').unbind('click', watchClickInSetting);
	}
	else {
		$('#app-settings').switchClass("", "open");
		$('body').bind('click', watchClickInSetting);
	}
}
function addFilterTag(event) {
	event.preventDefault();
	$('#tag_filter input').tagit('createTag', $(this).text());
}

function updateTagsList(tag) {
	var html = tmpl("tag_tmpl", tag);
	$('.tag_list').append(html);
}

function filterTagsChanged()
{
	$('#medionlibraryFilterTag').val($('#tag_filter input').val());
	$('.medionlibrarys_list').empty();
	medionlibrarysPage = 0;
	getMedionlibrarys();
}
function getMedionlibrarys() {
	if (medionlibrarysLoading) {
		//have patience :)
		return;
	}
	increaseAjaxCallCount();
	medionlibrarysLoading = true;
	//Update Rel Tags if first page
	if (medionlibrarysPage === 0) {

		$.ajax({
			type: 'GET',
			url: 'medionlibrary',
			data: {type: 'rel_tags', tag: $('#medionlibraryFilterTag').val(), page: medionlibrarysPage, sort: medionlibrarysSorting},
			success: function (tags) {
				$('.tag_list').empty();
				for (var i in tags.data) {
					updateTagsList(tags.data[i]);
				}
				$('.tag_list .tag_edit').click(renameTag);
				$('.tag_list .tag_delete').click(deleteTag);
				$('.tag_list a.tag').click(addFilterTag);


			}
		});
	}
	$.ajax({
		type: 'GET',
		url: 'medionlibrary',
		data: {type: 'medionlibrary', tag: $('#medionlibraryFilterTag').val(), page: medionlibrarysPage, sort: medionlibrarysSorting},
		complete: function () {
			decreaseAjaxCallCount();
		},
		success: function (medionlibrarys) {
			if (medionlibrarys.data.length) {
				medionlibrarysPage += 1;
			}
			$('.medionlibrary_link').unbind('click', recordClick);
			$('.medionlibrary_delete').unbind('click', delMedionlibrary);
			$('.medionlibrary_edit').unbind('click', editMedionlibrary);

			for (var i in medionlibrarys.data) {
				updateMedionlibrarysList(medionlibrarys.data[i]);
			}
			checkEmpty();

			$('.medionlibrary_link').click(recordClick);
			$('.medionlibrary_delete').click(delMedionlibrary);
			$('.medionlibrary_edit').click(editMedionlibrary);

			medionlibrarysLoading = false;
			if (medionlibrarys.data.length) {
				updateOnBottom();
			}
		}
	});
}

function watchUrlField() {
	var form = $('#add_form');
	var el = $('#add_url');
	var button = $('#medionlibrary_add_submit');
	form.unbind('submit');
	if (!acceptUrl(el.val())) {
		form.bind('submit', function (e) {
			e.preventDefault();
		});
		button.addClass('disabled');
	}
	else {
		button.removeClass('disabled');
		form.bind('submit', addMedionlibrary);
	}
}

function acceptUrl(url) {
	return url.replace(/^\s+/g, '').replace(/\s+$/g, '') !== '';
}

function addMedionlibrary(event) {
	event.preventDefault();
	var url = $('#add_url').val();
	//If trim is empty
	if (!acceptUrl(url)) {
		return;
	}

	$('#add_url').val('');
	var medionlibrary = {url: url, description: '', title: '', from_own: 0, added_date: new Date()};
	increaseAjaxCallCount();
	$.ajax({
		type: 'POST',
		url: 'medionlibrary',
		data: medionlibrary,
		complete: function () {
			decreaseAjaxCallCount();
		},
		success: function (data) {
			if (data.status === 'success') {
				// First remove old BM if exists
				$('.medionlibrary_single').filterAttr('data-id', data.item.id).remove();

				var medionlibrary = $.extend({}, medionlibrary, data.item);
				updateMedionlibrarysList(medionlibrary, 'prepend');
				checkEmpty();
				watchUrlField();
			}
		},
		error: function () {
			OC.Notification.showTemporary(t('medionlibrarys', 'Could not add medionlibrary.'));
		}
	});
}

function delMedionlibrary() {
	var record = $(this).parent().parent();
	OC.dialogs.confirm(t('medionlibrarys', 'Are you sure you want to remove this medionlibrary?'),
			t('medionlibrarys', 'Warning'), function (answer) {
		if (answer) {
			$.ajax({
				type: 'DELETE',
				url: 'medionlibrary/' + record.data('id'),
				success: function (data) {
					if (data.status === 'success') {
						record.remove();
						checkEmpty();
					}
				}
			});
		}
	});
}

function checkEmpty() {
	if ($('.medionlibrarys_list').children().length === 0) {
		$("#emptycontent").show();
		$("#bm_export").addClass('disabled');
		$('.medionlibrarys_list').hide();
	} else {
		$("#emptycontent").hide();
		$("#bm_export").removeClass('disabled');
		$('.medionlibrarys_list').show();
	}
}
function editMedionlibrary() {
	if ($('.medionlibrary_single_form').length) {
		$('.medionlibrary_single_form .reset').click();
	}
	var record = $(this).parent().parent();
	var medionlibrary = record.data('record');
	var html = tmpl("item_form_tmpl", medionlibrary);

	record.after(html);
	record.hide();
	var rec_form = record.next().find('form');
	rec_form.find('.medionlibrary_form_tags ul').tagit({
		allowSpaces: true,
		availableTags: fullTags,
		placeholderText: t('medionlibrarys', 'Tags')
	});

	rec_form.find('.reset').bind('click', cancelMedionlibrary);
	rec_form.bind('submit', function (event) {
		event.preventDefault();
		var form_values = $(this).serialize();
		$.ajax({
			type: 'PUT',
			url: $(this).attr('action') + "/" + this.elements['record_id'].value,
			data: form_values,
			success: function (data) {
				if (data.status === 'success') {
					//@TODO : do better reaction than reloading the page
					filterTagsChanged();
				} else { // On failure
					//@TODO : show error message?
				}
			}
		});
	});
}

function cancelMedionlibrary(event) {
	event.preventDefault();
	var rec_form = $(this).closest('form').parent();
	rec_form.prev().show();
	rec_form.remove();
}

function updateMedionlibrarysList(medionlibrary, position) {
	position = typeof position !== 'undefined' ? position : 'append';
	medionlibrary = $.extend({title: '', description: '', added_date: new Date('now'), tags: []}, medionlibrary);
	var tags = medionlibrary.tags;
	var taglist = '';
	for (var i = 0, len = tags.length; i < len; ++i) {
		if (tags[i] !== '')
			taglist = taglist + '<a class="medionlibrary_tag" href="#">' + escapeHTML(tags[i]) + '</a> ';
	}
	if (!hasProtocol(medionlibrary.url)) {
		medionlibrary.url = 'http://' + medionlibrary.url;
	}

	if (medionlibrary.added) {
		medionlibrary.added_date.setTime(parseInt(medionlibrary.added) * 1000);
	}

	if (!medionlibrary.title)
		medionlibrary.title = '';

	var html = tmpl("item_tmpl", medionlibrary);
	if (position === "prepend") {
		$('.medionlibrarys_list').prepend(html);
	} else {
		$('.medionlibrarys_list').append(html);
	}
	var line = $('div[data-id="' + medionlibrary.id + '"]');
	line.data('record', medionlibrary);
	if (taglist !== '') {
		line.append('<p class="medionlibrary_tags">' + taglist + '</p>');
	}
	line.find('a.medionlibrary_tag').bind('click', addFilterTag);
	line.find('.medionlibrary_link').click(recordClick);
	line.find('.medionlibrary_delete').click(delMedionlibrary);
	line.find('.medionlibrary_edit').click(editMedionlibrary);

}

function updateOnBottom() {
	//check wether user is on bottom of the page
	var top = $('.medionlibrarys_list>:last-child').position().top;
	var height = $('.medionlibrarys_list').height();
	// use a bit of margin to begin loading before we are really at the
	// bottom
	if (top < height * 1.2) {
		getMedionlibrarys();
	}
}

function recordClick() {
	$.ajax({
		type: 'POST',
		url: 'medionlibrary/click',
		data: 'url=' + encodeURIComponent($(this).attr('href'))
	});
}

function hasProtocol(url) {
	var regexp = /(ftp|http|https|sftp)/;
	return regexp.test(url);
}

function renameTag() {
	if ($('input[name="tag_new_name"]').length)
		return; // Do nothing if a tag is currenlty edited
	var tagElement = $(this).closest('li');
	tagElement.append('<form><input name="tag_new_name" type="text"></form>');
	var form = tagElement.find('form');
	//tag_el.find('.tags_actions').hide();
	var tagName = tagElement.find('.tag').hide().text();
	tagElement.find('input').val(tagName).focus().bind('blur', function () {
		form.trigger('submit');
	});
	form.bind('submit', submitTagName);
}

function submitTagName(event) {
	event.preventDefault();
	var tagElement = $(this).closest('li');
	var newTagName = tagElement.find('input').val();
	var oldTagName = tagElement.find('.tag').show().text();
	//tag_el.find('.tag_edit').show();
	//tag_el.find('.tags_actions').show();
	tagElement.find('input').unbind('blur');
	tagElement.find('form').unbind('submit').remove();

	if (newTagName !== oldTagName && newTagName !== '') {
		//submit
		$.ajax({
			type: 'POST',
			url: 'tag',
			data: {old_name: oldTagName, new_name: newTagName},
			success: function (medionlibrarys) {
				if (medionlibrarys.status === 'success') {
					filterTagsChanged();
				}
			}
		});
	}
}

function deleteTag() {
	var tag_el = $(this).closest('li');
	var old_tag_name = tag_el.find('.tag').show().text();
	OC.dialogs.confirm(t('medionlibrarys', 'Are you sure you want to remove this tag from every entry?'),
			t('medionlibrarys', 'Warning'), function (answer) {
		if (answer) {
			$.ajax({
				type: 'DELETE',
				url: 'tag',
				data: {old_name: old_tag_name},
				success: function (medionlibrarys) {
					if (medionlibrarys.status === 'success') {
						filterTagsChanged();
					}
				}
			});
		}
	});
}
