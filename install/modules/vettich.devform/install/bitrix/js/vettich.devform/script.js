if(typeof Vettich == 'undefined') {
	Vettich = {};
}
Vettich.Devform = {};

$(document).ready(function(){
	$('.js-vform').show();
	Vettich.Devform.TextareaChooseShow();
	$('select.js-text-option').on('change', Vettich.Devform.SelectTextOption);

	$(document).click(function(event) {
		if(Vettich.Devform.TextareaChooseBlock) {
			Vettich.Devform.TextareaChooseBlock = false;
			return;
		}
		if ($(event.target).closest('.textarea_select .items').length) return;
		Vettich.Devform.TextareaChooseHide('.textarea_select .items')
		event.stopPropagation();
	});
	Vettich.Devform.TextareaChooseInit();

	$('input[type="submit"]').click(function(e){
		BX.adminPanel.closeWait(this);
	});
	Vettich.Devform.HeadingsInit();
})


Vettich.Devform.Refresh = function () {
	var _link = window.location.href;
	var show = BX.showWait('adm-workarea');
	var origFormid = $('.js-vform form').attr('id').substr("FORM_".length);
	var formid = 'TAB_CONTROL_' + origFormid;
	$('body').append('<div id="voptions_overlay" class="vettich-devform-overlay"></div>');
	var _linkHash = -1;
	if((_linkHash = _link.indexOf('#')) > 0) {
		_link = _link.substring(0, _linkHash);
	}
	_link += (_link.indexOf('?') > 0 ? '&' : '?') + 'ajax=Y';
	_link += '&ajax_formid=' + origFormid;
	console.log('ajax to ' + _link);
	var _data = $('.js-vform form').serialize();
	if(_data.indexOf('_active_tab') < 0)
		_data += '&' + formid + '_active_tab=' + $('#' + formid + '_active_tab').val();
	jQuery.ajax({
		url: _link,
		type: "POST",
		data: _data,
		timeout: 10000,
		success: function(data){
			BX.closeWait('adm-workarea', show);
			$('#voptions_overlay').remove();
			eval(formid + ' = null;');
			$('#'+ formid + '_layout').html(data);
			$('.js-vform').show();
			Vettich.Devform.HeadingsInit();
			Vettich.Devform.TextareaChooseShow();
			Vettich.Devform.TextareaChooseInit();
		},
		error: function(response, status) {
			BX.closeWait('adm-workarea', show);
			$('#voptions_overlay').remove();
			new BX.CDialog({
				'title':'Error',
				'content':'<center>Error: ' + status + '</center>',
				'width':400,
				'height':150,
				'buttons':[
					BX.CDialog.prototype.btnClose,
				]
			}).Show();
		}
	});
}

Vettich.Devform.SelectTextOption = function() {
	var $this = $(this);
	if(!!$this.find('option:selected').data('text-option')) {
		$this.parent().append('<input name="' + $this.attr('name') + '" style="display: block;">');
	} else {
		$this.parent().find('input').remove();
	}
}

Vettich.Devform.TextareaChooseShow = function () {
	$('.textarea_select').each(function() {
		var $this = $(this);
		if($this.find('.items').children().length) {
			$this.css('display', 'block');
		}
	})
}

Vettich.Devform.TextareaChooseBlock = false;
Vettich.Devform.TextareaChooseInit = function () {
	$('.textarea_select .adm-btn').click(function(){
		$this = $(this);
		var items;
		if((items = $this.parent().find('.items')).css('display') == 'none') {
			var bottom = $this.offset().top + $this.outerHeight();
			var top = $this.offset().top - $(window).scrollTop();
			items.css('display', 'block');
			top -= items.height() - 24;
			if(top < 10) {
				top = 10;
			} else if(top + items.outerHeight() > document.body.clientHeight) {
				top = document.body.clientHeight - items.outerHeight() - 6;
			}
			items.css({
				left: ($this.offset().left - items.outerWidth() - 5) + 'px',
				top: top,
			});
			$('body').addClass('shadow').css('overflow-y', 'hidden');
		} else {
			Vettich.Devform.TextareaChooseHide(items);
		}
		Vettich.Devform.TextareaChooseBlock = true;
	});
	$('.textarea_select .items > div').click(Vettich.Devform.PasteToTextarea);
}

Vettich.Devform.TextareaChooseHide = function (selector) {
	$(selector).css('display', 'none');
	$('body').removeClass('shadow').css('overflow-y', 'auto');
}

Vettich.Devform.PasteToTextarea = function() {
	var $this = $(this);
	var value = $this.data('value');
	var textarea = $this.closest('td').find('textarea')[0];
	var istart = textarea.selectionStart;
	var iend = textarea.selectionEnd;
	var itxt = textarea.value;
	textarea.value = itxt.substr(0, istart) + value + itxt.substr(iend);
	textarea.focus();
	var cursor = value.length + istart;
	textarea.setSelectionRange(cursor, cursor);
}

Vettich.Devform.GroupAdd = function(elem) {
	var prefix = $(elem).closest('tr').parent().closest('tr').data('id');
	if(prefix) {
		$(elem).append('<input type="hidden" name="'+ prefix+ '[_add]" value=Y id="groupAddTmp123">');
		Vettich.Devform.Refresh();
		$('#groupAddTmp123').remove();
	}
}

Vettich.Devform.GroupDelete = function(elem) {
	$(elem).closest('tr').remove();
}

Vettich.Devform.Heading = function(elem) {
	var $elem = $(elem);
	var cook = Vettich.Cookie.Get('heading');
	if(cook) {
		cook = JSON.parse(cook);
	} else {
		cook = [];
	}
	var elemid = $elem.attr('id');
	var cookIndex = cook.indexOf(elemid);
	if($elem.hasClass('hidden')) {
		if(cookIndex >= 0) {
			cook = cook.slice(0, cookIndex).concat(cook.slice(cookIndex+1));
		}
	} else if(cookIndex === -1) {
		cook.push(elemid);
	}
	cook = JSON.stringify(cook);
	Vettich.Cookie.Set('heading', cook, {expires: 3600*24*30*6, path: window.location.path});
	Vettich.Devform.HeadingToggle($elem);
}

Vettich.Devform.HeadingToggle = function ($elem) {
	$elem.toggleClass('hidden');
	while(!!($elem = $elem.next()).length && !$elem.hasClass('heading')) {
		$elem.toggleClass('hidden');
	}
}

Vettich.Devform.HeadingsInit = function() {
	var cook = Vettich.Cookie.Get('heading');
	if(!cook) {
		return;
	}
	cook = JSON.parse(cook);
	for (var h in cook) {
		Vettich.Devform.HeadingToggle($('#'+cook[h]));
	}
}


/**
 * Coooookies
 */

Vettich.Cookie = {};

Vettich.Cookie.Set = function(name, value, options) {
	options = options || {};
	var expires = options.expires;
	if (typeof expires == "number" && expires) {
		var d = new Date();
		d.setTime(d.getTime() + expires * 1000);
		expires = options.expires = d;
	}
	if (expires && expires.toUTCString) {
		options.expires = expires.toUTCString();
	}
	value = encodeURIComponent(value);
	var updatedCookie = name + "=" + value;
	for (var propName in options) {
		updatedCookie += "; " + propName;
		var propValue = options[propName];
		if (propValue !== true) {
			updatedCookie += "=" + propValue;
		}
	}
	document.cookie = updatedCookie;
}

Vettich.Cookie.Get = function(name) {
	var matches = document.cookie.match(new RegExp(
		"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
	));
	return matches ? decodeURIComponent(matches[1]) : undefined;
}

Vettich.Cookie.Delete = function(name) {
	Vettich.Cookie.Set(name, "", {
		expires: -1
	});
}
