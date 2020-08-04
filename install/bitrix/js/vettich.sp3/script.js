VettichSP3 = {
	re: /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/,
	dialogs: {
		templatesList: new BX.CDialog({
			title: BX.message('VETTICH_SP3_LIST_TEMPLATES'),
			content: '',
			buttons: [],
		}),
		result: new BX.CDialog({
			title: BX.message('VETTICH_SP3_RESULT'),
			content: '',
			buttons: [BX.CDialog.prototype.btnClose],
		}),
	},
}

VettichSP3.m = function(langKey) {
	var msg = BX.message('VETTICH_SP3_' + langKey);
	if(!msg) msg = langKey;
	return msg;
}

VettichSP3.ajaxUrl = '/bitrix/tools/vettich.sp3.ajax.php';
VettichSP3.userUrl = '/bitrix/admin/vettich.sp3.user.php';
VettichSP3.startUseUrl = '/bitrix/admin/vettich.sp3.start_use.php';

VettichSP3.clearResult = function (elem) {
	elem.innerHTML = '';
}

VettichSP3.setResult = function (elem, text, color) {
	elem.innerHTML = '<span style="color:' + color + '">' + text + '</span>';
}

VettichSP3.login = function () {
	var query = {
		method:   'login',
		username: document.getElementById('lusername').value,
		password: document.getElementById('lpassword').value,
	};
	var lresult = document.getElementById('lresult');
	VettichSP3.clearResult(lresult);
	if (!query.username.length || !query.password.length) {
		VettichSP3.setResult(lresult, VettichSP3.m('FILL_ALL_FIELDS'), 'red');
		return;
	}

	var show = BX.showWait("FORM_devform");
	jQuery.get(VettichSP3.ajaxUrl + VettichSP3.queryStringify(query), function(data) {
		var dataJson = JSON.parse(data);
		if(!dataJson.error) {
			VettichSP3.setResult(lresult, VettichSP3.m('SUCCESS'), 'green');
			window.location = VettichSP3.userUrl;
		} else {
			VettichSP3.setResult(lresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.passGen = function() {
	var length = 16,
		charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789$@_-/",
		retVal = "";
	for (var i = 0, n = charset.length; i < length; ++i) {
		var ch = charset.charAt(Math.floor(Math.random() * n));
		if(i != 0 && retVal[i-1] == ch) {
			--i;
			continue;
		}
		retVal += ch;
	}
	document.getElementById('rpassword').value = retVal;
	document.getElementById('rpassword2').value = retVal;
	document.getElementById('rpassword').type = 'text';
	document.getElementById('rpassword2').type = 'text';
}

VettichSP3.signup = function () {
	var username = document.getElementById('rusername').value;
	var password = document.getElementById('rpassword').value;
	var password2 = document.getElementById('rpassword2').value;
	var rresult = document.getElementById('rresult');
	VettichSP3.clearResult(rresult);

	if (!username.length || !password.length) {
		VettichSP3.setResult(rresult, VettichSP3.m('FILL_ALL_FIELDS'), 'red');
		return;
	}
	if (!VettichSP3.re.test(String(username).toLowerCase())) {
		VettichSP3.setResult(rresult, VettichSP3.m('EMAIL_INCORRECT'), 'red');
		return;
	}
	if (password.length < 6) {
		VettichSP3.setResult(rresult, VettichSP3.m('PASS_MIN_LEN'), 'red');
		return;
	}
	if (password != password2) {
		VettichSP3.setResult(rresult, VettichSP3.m('PASS_NOT_MATCH'), 'red');
		return;
	}
	if (!document.getElementById('politika').checked) {
		VettichSP3.setResult(rresult, VettichSP3.m('POLITIKA_NEED_CONFIRM'), 'red');
		return;
	}

	var show = BX.showWait("FORM_devform");
	var queries = VettichSP3.queryStringify({
		method: 'signup',
		username: username,
		password: password,
	});
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('SUCCESS'), 'green');
			window.location = VettichSP3.userUrl;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.forgotPassword = function () {
	var username = document.getElementById('fusername').value;
	var rresult = document.getElementById('fresult');
	VettichSP3.clearResult(rresult);

	if (!username.length) {
		VettichSP3.setResult(rresult, VettichSP3.m('FILL_ALL_FIELDS'), 'red');
		return;
	}
	if (!VettichSP3.re.test(String(username).toLowerCase())) {
		VettichSP3.setResult(rresult, VettichSP3.m('EMAIL_INCORRECT'), 'red');
		return;
	}

	var show = BX.showWait("FORM_devform");
	var queries = VettichSP3.queryStringify({
		method: 'forgotPassword',
		username: username,
		callback_url: location.origin + '/bitrix/admin/vettich.sp3.reset_password.php',
	});
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('FORGOT_PASS_SENT'), 'green');
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.resetPassword = function () {
	var token = document.getElementById('token').value;
	var password = document.getElementById('rpassword').value;
	var password2 = document.getElementById('rpassword2').value;
	var rresult = document.getElementById('rresult');
	VettichSP3.clearResult(rresult);

	if (!password.length) {
		VettichSP3.setResult(rresult, VettichSP3.m('FILL_ALL_FIELDS'), 'red');
		return;
	}
	if (password.length < 6) {
		VettichSP3.setResult(rresult, VettichSP3.m('PASS_MIN_LEN'), 'red');
		return;
	}
	if (password != password2) {
		VettichSP3.setResult(rresult, VettichSP3.m('PASS_NOT_MATCH'), 'red');
		return;
	}

	var show = BX.showWait("FORM_devform");
	var queries = VettichSP3.queryStringify({
		method: 'resetPassword',
		token: token,
		password: password,
	});
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('SUCCESS'), 'green');
			window.location = VettichSP3.userUrl;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.logout = function () {
	var rresult = document.getElementById('logout_res');
	VettichSP3.clearResult(rresult);
	var show = BX.showWait("FORM_devform");
	var queries = '?method=logout'
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('SUCCESS'), 'green');
			window.location = VettichSP3.startUseUrl;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.connectAccount = function(type) {
	var rresult = document.getElementById(type + '_login_res');
	var show = BX.showWait("FORM_devform");
	var queries = VettichSP3.queryStringify({
		method: 'getConnectUrl',
		type: type,
		callback: location.origin + '/bitrix/admin/vettich.sp3.accounts_list.php',
	});
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.m('REDIRECTING'), 'green');
			window.location = dataJson.response.url;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
			BX.closeWait("FORM_devform", show);
		}
	});
}

VettichSP3.connectInsta = function() {
	var fields = {
		username: document.getElementById('insta_username').value,
		password: document.getElementById('insta_password').value,
		proxy: document.getElementById('insta_proxy').value,
		code: document.getElementById('insta_code').value,
	}
	var result = document.getElementById('insta_login_res');
	VettichSP3.clearResult(result);
	if(!fields.username.length && !fields.password.length) {
		VettichSP3.setResult(result, VettichSP3.m('INSTA_AUTH_FIELDS_EMPTY'), 'red');
		return;
	}
	var show = BX.showWait('adm-workarea');
	var queries = VettichSP3.queryStringify({
		method: 'connect',
		type: 'insta',
		fields: fields,
	});
	jQuery.get(VettichSP3.ajaxUrl + '?' + queries, function (data) {
		try {
			var res = JSON.parse(data);
			if(res.error) {
				VettichSP3.setResult(result, res.error.msg, 'red');
				return;
			}
			if(res.response.need_challenge) {
				jQuery('#insta_code-wrap').show();
				VettichSP3.setResult(result, VettichSP3.m('INSTA_ENTER_CODE'), 'red');
				return;
			}
			VettichSP3.setResult(result, VettichSP3.m('SUCCESS'), 'green');
			window.location = '/bitrix/admin/vettich.sp3.accounts_list.php';
			return;
		} catch (e) {
			VettichSP3.setResult(result, VettichSP3.m('SOME_ERROR'), 'red');
		}
	}).always(function () {
		BX.closeWait('adm-workarea', show);
	});
}

VettichSP3.connectTg = function() {
	var fields = {
		username: document.getElementById('tg_username').value,
		bot_token: document.getElementById('tg_bot_token').value,
	}
	var result = document.getElementById('tg_login_res');
	VettichSP3.clearResult(result);
	if(!fields.username.length && !fields.bot_token.length) {
		VettichSP3.setResult(result, VettichSP3.m('TG_FIELDS_EMPTY'), 'red');
		return;
	}
	var show = BX.showWait('adm-workarea');
	var query = VettichSP3.queryStringify({
		method: 'connect',
		type: 'tg',
		fields: fields,
	});
	jQuery.get(VettichSP3.ajaxUrl + queries, function (data) {
		try {
			var res = JSON.parse(data);
			if(res.error) {
				VettichSP3.setResult(result, res.error.msg, 'red');
				return;
			}
			VettichSP3.setResult(result, VettichSP3.m('SUCCESS'), 'green');
			window.location = '/bitrix/admin/vettich.sp3.accounts_list.php';
			return;
		} catch (e) {
			VettichSP3.setResult(result, VettichSP3.m('SOME_ERROR'), 'red');
		}
	}).always(function () {
		BX.closeWait('adm-workarea', show);
	});
}

VettichSP3.MenuSendWithTemplate = function (query) {
	var show = BX.showWait('adm-workarea');
	// VettichSP3.fixClosePopupMenu();
	jQuery.get(VettichSP3.ajaxUrl + '?method=listTemplates&' + VettichSP3.queryStringify(query, true), function (data) {
		var publishBtn = {
			title: VettichSP3.m('PUBLISH'),
			onclick: 'VettichSP3.MenuSendWithTemplateStep2(' + JSON.stringify(query) + ');',
		};
		var buttons = [publishBtn, BX.CDialog.prototype.btnClose];
		var html = '';
		var htmlTemplate = '<input type="checkbox" name="{id}[{val}]" {checked} id="{id}-{val}" value="{val}"> <label for="{id}-{val}">{label}</label><br>';
		try {
			var json = JSON.parse(data);
			var templatesKeys = Object.keys(json.templates);
			if (templatesKeys.length == 0) {
				html = VettichSP3.m('TEMPLATES_NOT_FOUND');
				buttons = [BX.CDialog.prototype.btnClose];
			} else {
				var checked = templatesKeys.length > 1 ? '' : 'checked="checked"';
				htmlTemplate = htmlTemplate.split('{id}').join('TEMPLATES');
				html = VettichSP3.m('CHOOSE_TEMPLATE') + ' <br/><br/>';
				for(var i = 0; i < templatesKeys.length; i++) {
					inputHtml = htmlTemplate
						.split('{val}').join(templatesKeys[i])
						.split('{label}').join(json.templates[templatesKeys[i]])
						.split('{checked}').join(checked);
					html += inputHtml;
				}
			}
		} catch (e) {
			html = VettichSP3.m('SOME_ERROR');
		}
		if(!query.ELEMS && !query.SECTIONS) {
			query = VettichSP3.getSelectedIblockElements(query);
		}
		var link = '/bitrix/admin/vettich.sp3.posts_custom.php' + VettichSP3.queryStringify(query);
		html += '<br/><br/><a href="{link}" target="_blank" onclick="{onclick}">{text}</a>'
			.split('{link}').join(link)
			.split('{onclick}').join('VettichSP3.dialogs.templatesList.Close()')
			.split('{text}').join(VettichSP3.m('WITHOUT_TEMPLATE'));
		var publishBtn = {
			title: VettichSP3.m('PUBLISH'),
			onclick: 'VettichSP3.MenuSendWithTemplateStep2(' + JSON.stringify(query) + ');',
		};
		VettichSP3.dialogs.templatesList.SetContent(html);
		VettichSP3.dialogs.templatesList.ClearButtons();
		VettichSP3.dialogs.templatesList.SetButtons(buttons);
		VettichSP3.dialogs.templatesList.Show();
	}).always(function () {
		BX.closeWait('adm-workarea', show);
	});
}

VettichSP3.MenuSendWithTemplateStep2 = function(query) {
	prevDialog = VettichSP3.dialogs.templatesList
	var show = BX.showWait(prevDialog.DIV.id);
	var selectedTemplates = prevDialog.PARTS.CONTENT_DATA.querySelectorAll('input:checked');
	if(selectedTemplates.length == 0) {
		alert(VettichSP3.m('CHOOSE_TEMPLATE_FROM_LIST'));
		return;
	}
	if(!query.ELEMS && !query.SECTIONS) {
		query = VettichSP3.getSelectedIblockElements(query);
	}
	query.TEMPLATES = [];
	for(var i = 0; i < selectedTemplates.length; i++) {
		query.TEMPLATES.push(selectedTemplates[i].value);
	}
	query.method = 'publishWithTemplate';
	squery = VettichSP3.queryStringify(query);
	jQuery.get(VettichSP3.ajaxUrl + squery).always(function(data) {
		BX.closeWait(prevDialog.DIV.id, show);
		var html = '';
		try {
			var dataJson = JSON.parse(data);
			if (dataJson.error) {
				html = dataJson.error.msg;
			} else {
				html = VettichSP3.m('ADDED_N_POST') + dataJson.length;
			}
		} catch(e) {
			console.log(e);
			html = VettichSP3.m('SOME_ERROR2');
		}
		prevDialog.AllowClose();
		prevDialog.Close();

		VettichSP3.dialogs.result.SetContent(html);
		VettichSP3.dialogs.result.Show();
	});
}

VettichSP3.fixClosePopupMenu = function() {
	Object.keys(BX.PopupMenu.Data).map(function(v, i) {
		BX.PopupMenu.Data[v].close();
	})
}

VettichSP3.getSelectedIblockElements = function(query) {
	var elems = [];
	var sections = [];
	document.querySelectorAll('.adm-list-table-checkbox input:checked, .main-grid-row-checkbox.main-grid-checkbox:checked').forEach(function(node) {
		var name = node.name;
		var value = node.value;
		if(name && name.length && value.length > 1) {
			if(value[0] == 'E') {
				elems.push(value.substr(1));
			} else if(value[0] == 'S') {
				sections.push(value.substr(1));
			} else {
				elems.push(value);
			}
		}
	});
	if(elems.length) {
		query.ELEMS = elems;
	}
	if(sections.length) {
		query.SECTIONS = sections;
	}
	return query;
}

VettichSP3.queryStringify = function(query, onlyQS, prefix) {
	var res = [];
	prefix = prefix || "";
	function buildKey(key) {
		if(!prefix.length) {
			return key
		}
		return prefix + '[' + key + ']';
	}

	Object.keys(query).map(function(key) {
		if(Array.isArray(query[key])) {
			query[key].map(function(val) {
				res.push(buildKey(key) + '[]=' + encodeURIComponent(val));
			});
		} else if(typeof query[key] == "object") {
			res = res.concat(VettichSP3.queryStringify(query[key], true, buildKey(key)).split('&'));
		} else {
			res.push(buildKey(key) + '=' + encodeURIComponent(query[key]));
		}
	});
	var s = res.join('&');
	return onlyQS ? s : '?'+s;
}

VettichSP3.unloadDateTimeAdd = function(event, weekKey) {
	var val;
	var select = '<span class="vettich-sp3-time-item-wrap">';
	select += '<select class="vettich-sp3-time-item" name="_UNLOAD_DATETIME[' + weekKey + '][]">';
	for(var i = 0; i < 24; i++) {
		val = '' + i + ':00';
		select += '<option value="'+val+'">'+val+'</option>';
		val = '' + i + ':30';
		select += '<option value="'+val+'">'+val+'</option>';
	}
	select += '</select>';
	select += '<span class="vettich-sp3-time-remove-btn" onclick="VettichSP3.unloadDateTimeRemove(event)">x</span>';
	select += '</span>';
	select = jQuery(select);
	select.insertBefore(event.target);
}

VettichSP3.unloadDateTimeRemove = function(event) {
	jQuery(event.target).parent().remove();
}
