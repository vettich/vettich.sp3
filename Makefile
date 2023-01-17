HEAD=1

update-version:
	rm changes.zip
	git diff --name-only HEAD~$(HEAD) HEAD | zip changes.zip -@
