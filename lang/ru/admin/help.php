<?php

$MESS['VETTICH_SP3_HELP_PAGE_TITLE'] = '�������� ������';

ob_start();
?>
	<h2>����������</h2>
	<div class="vettich-sp3-content">
		<ul>
			<li><a href="#about">� ������</a></li>
			<li><a href="#general_menus">�������� ������ ����</a></li>
			<li><a href="#auth">�����������/�����������</a></li>
			<li><a href="#connect">���������� �������� ���������� ����</a></li>
			<li><a href="#post_creating">�������� �����</a></li>
		</ul>
	</div>

	<h2 id="about">� ������</h2>
	<div class="vettich-sp3-content">
		������ �������������� � ���. ���� 3.0 - ������������ ��� ���������� ���������� ��������� ����� � ������ ����� � ��������� ���������� ����. ������ ������������ �������������� ���������� ��������, ������� � �.�. ��� �� ����������.
	</div>

	<h2 id="general_menus">�������� ������ ����</h2>
	<div class="vettich-sp3-content">
		<ul>
			<li>������������ - �� ���� �������� ���������� ���������� � ����� �������� � �������� ������� ������</li>
			<li>�������� - � ���� ������� ����� ���������� ��������, ������, �������� ����� ���������� �����</li>
			<li>����� - ������ ������. ����� ����� ������� ����� ����: ������� ����� �����, ��������, ���� ����������, ������� ������ ��������, � ������������</li>
			<li>������� - � ���� ������� ����� ��������� ��������� ����������. ��� ��������� ����������� ������ ���������� �� ����������</li>
			<li>������ - ��� �������� ������</li>
		</ul>
	</div>

	<h2 id="auth">�����������/�����������</h2>
	<div class="vettich-sp3-content">
		����� ������������ ��������, ������������� ��� ����������������� � �������� ������� ������.
		<h4>�����������</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-auth-1.png"/>
		<h4>�����������</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-auth-2.png"/>
	</div>

	<h2 id="connect">���������� �������� ���������� ����</h2>
	<div class="vettich-sp3-content">
		����� �������� ������� ������������ ���. ����, ��� ����� ������� �� ������ �������, � ��������� �����������. ���� ���. ���� ������������� ������� API � OAuth2 ������������, ��� ���������� ������ �� ������ ������� � ���������� <���. ����>.
		<h4>���������� ��������</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-connect-1.png"/>
	</div>

	<h2 id="post_creating">�������� �����</h2>
	<div class="vettich-sp3-content">
		��� ����, ����� ������� ����, ��������� � ������ �����. �� ������� ��������� ������ �����:
		<ul>
			<li>�������� ������ - � ���� ������ ����� �� ������ ������� �����, ����, ������, �������� ������� ������ ������������, � ����� ���� ����������</li>
			<li>����� ��������� - ����� ���������� ������� ���� �� ���� ������� ���. ����, � ������� ����� ����� ������������ ������</li>
			<li>�������������� ��������� ���. ����� - ������ ���� �������������� ���������, ������� �������������� ������ ���������� ���. �����</li>
		</ul>
		<h4>�������� �����</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-1.png"/>
		<h4>����� ���������</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-2.png"/>
		<h4>�������������� ���������</h4>
		<img class="vettich-sp3-img" src="/bitrix/images/vettich.sp3/help-3.png"/>
	</div>
<?php
$MESS['VETTICH_SP3_HELP_PAGE_CONTENT'] = ob_get_contents();
ob_end_clean();
