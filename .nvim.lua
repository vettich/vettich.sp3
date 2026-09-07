local ok, lsp = pcall(require, 'lsp-zero')
if ok then
	lsp.configure('intelephense', {
		settings = {
			intelephense = {
				format = {
					enable = false
				},
				environment = {
					includePaths = {
						"../../../bitrix/modules"
					}
				}
			},
		}
	})
end
