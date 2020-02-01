VettichSP3 = {
	langs: {
		success: "Успешно",
		fillAllFields: "Заполните все поля",
		passwordsNotMatch: "Пароли не совпадают",
	},
	dialogs: {
		templatesList: new BX.CDialog({
			title: 'Список шаблонов',
			content: '',
			buttons: [],
		}),
		result: new BX.CDialog({
			title: 'Результат',
			content: '',
			buttons: [BX.CDialog.prototype.btnClose]
		}),
	},
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
	var username = document.getElementById('lusername').value;
	var password = document.getElementById('lpassword').value;
	var lresult = document.getElementById('lresult');
	VettichSP3.clearResult(lresult);
	if (!username.length || !password.length) {
		VettichSP3.setResult(lresult, VettichSP3.langs.fillAllFields, 'red');
		return;
	}
	var show = BX.showWait("FORM_devform");
	var queries = '?method=login' +
		'&username=' + username +
		'&password=' + password;
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(lresult, VettichSP3.langs.success, 'green');
			window.location = VettichSP3.userUrl;
		} else {
			VettichSP3.setResult(lresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.signup = function () {
	var username = document.getElementById('rusername').value;
	var password = document.getElementById('rpassword').value;
	var password2 = document.getElementById('rpassword2').value;
	var rresult = document.getElementById('rresult');
	VettichSP3.clearResult(rresult);
	if (!username.length || !password.length) {
		VettichSP3.setResult(rresult, VettichSP3.langs.fillAllFields, 'red');
		return;
	}
	if (password != password2) {
		VettichSP3.setResult(rresult, VettichSP3.langs.passwordsNotMatch, 'red');
		return;
	}
	var show = BX.showWait("FORM_devform");
	var queries = '?method=signup' +
		'&username=' + username +
		'&password=' + password;
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.langs.success, 'green');
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
			VettichSP3.setResult(rresult, VettichSP3.langs.success, 'green');
			window.location = VettichSP3.startUseUrl;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
		}
	}).always(function() {
		BX.closeWait("FORM_devform", show);
	});
}

VettichSP3.vkLogin = function() {
	var rresult = document.getElementById('vk_login_res');
	var show = BX.showWait("FORM_devform");
	var callback = location.origin + '/bitrix/admin/vettich.sp3.accounts_list.php';
	var queries = '?method=vkLogin&callback=' + callback;
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.langs.success, 'green');
			window.location = dataJson.response.url;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
			BX.closeWait("FORM_devform", show);
		}
	});
}

VettichSP3.okLogin = function() {
	var rresult = document.getElementById('ok_login_res');
	var show = BX.showWait("FORM_devform");
	var callback = location.origin + '/bitrix/admin/vettich.sp3.accounts_list.php';
	var queries = '?method=okLogin&callback=' + callback;
	jQuery.get(VettichSP3.ajaxUrl + queries, function(data) {
		var dataJson = JSON.parse(data)
		if(!dataJson.error) {
			VettichSP3.setResult(rresult, VettichSP3.langs.success, 'green');
			window.location = dataJson.response.url;
		} else {
			VettichSP3.setResult(rresult, dataJson.error.msg, 'red');
			BX.closeWait("FORM_devform", show);
		}
	});
}

VettichSP3.MenuSendWithTemplate = function (query) {
	var show = BX.showWait('adm-workarea');
	// VettichSP3.fixClosePopupMenu();
	jQuery.get(VettichSP3.ajaxUrl + '?method=listTemplates&' + VettichSP3.queryStringify(query), function (data) {
		var html = '';
		var htmlTemplate = '<input type="checkbox" name="{id}[{val}]" {checked} id="{id}-{val}" value="{val}"> <label for="{id}-{val}">{label}</label><br>';
		try {
			var json = JSON.parse(data);
			var templatesKeys = Object.keys(json.templates);
			if (templatesKeys.length == 0) {
				html = 'Подходящих шаблонов не найдено.';
			} else {
				var checked = templatesKeys.length > 1 ? '' : 'checked="checked"';
				htmlTemplate = htmlTemplate.split('{id}').join('TEMPLATES');
				html = 'Выберите шаблон, с помощью которого нужно опубликовать <br/><br/>';
				for(var i = 0; i < templatesKeys.length; i++) {
					inputHtml = htmlTemplate
						.split('{val}').join(templatesKeys[i])
						.split('{label}').join(json.templates[templatesKeys[i]])
						.split('{checked}').join(checked);
					html += inputHtml;
				}
			}
		} catch (e) {
			html = 'Произошла какая-то ошибка при получении списка шаблонов...';
		}
		if(!query.ELEMS && !query.SECTIONS) {
			query = VettichSP3.getSelectedIblockElements(query);
		}
		var link = '/bitrix/admin/vettich.sp3.posts_custom.php?' + VettichSP3.queryStringify(query);
		html += '<br/><br/>Или <a href="{link}" target="_blank" onclick="{onclick}">опубликовать НЕ используя шаблон</a>'
			.split('{link}').join(link)
			.split('{onclick}').join('VettichSP3.dialogs.templatesList.Close()');
		var publishBtn = {
			title: 'Опубликовать',
			onclick: 'VettichSP3.MenuSendWithTemplateStep2(' + JSON.stringify(query) + ');',
		};
		VettichSP3.dialogs.templatesList.SetContent(html);
		VettichSP3.dialogs.templatesList.ClearButtons();
		VettichSP3.dialogs.templatesList.SetButtons([publishBtn, BX.CDialog.prototype.btnClose]);
		VettichSP3.dialogs.templatesList.Show();
	}).always(function () {
		BX.closeWait('adm-workarea', show);
	});
}

VettichSP3.MenuSendWithTemplateStep2 = function(query) {
	prevDialod = VettichSP3.dialogs.templatesList
	var selectedTemplates = prevDialod.PARTS.CONTENT_DATA.querySelectorAll('input:checked');
	if(selectedTemplates.length == 0) {
		alert('Выберите шаблон из списка');
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
	squery = '?' + VettichSP3.queryStringify(query);
	jQuery.get(VettichSP3.ajaxUrl + squery).always(function(data) {
		var html = '';
		try {
			var dataJson = JSON.parse(data);
			if (dataJson.error) {
				html = dataJson.error.msg;
			} else {
				html = 'Было добавлено постов: ' + dataJson.length;
			}
		} catch(e) {
			console.log(e);
			html = 'Произошла какая-то ошибка';
		}
		prevDialod.AllowClose();
		prevDialod.Close();

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

VettichSP3.queryStringify = function(query) {
	var res = [];
	Object.keys(query).map(function(key) {
		if(Array.isArray(query[key])) {
			query[key].map(function(val) {
				res.push(key + '[]=' + val);
			});
		} else {
			res.push(key + '=' + query[key]);
		}
	});
	return res.join('&');
}
