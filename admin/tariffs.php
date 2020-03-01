<?php
require(__DIR__.'/../include/prolog_authorized_page.php');
IncludeModuleLangFile(__FILE__);
use vettich\sp3\Api;
use vettich\sp3\Module;

if (!empty($_POST)) {
	if ($_POST['case'] == '1') {
		$site = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['HTTP_HOST'];
		$params = [
			'tariff_id' => $_POST['tariff_id'],
			'period' => intval($_POST['period']),
			'success_url' => $site.'/bitrix/admin/vettich.sp3.payment_success.php',
			'fail_url' => $site.'/bitrix/admin/vettich.sp3.payment_fail.php',
		];
		/* var_dump($params); */
		$res = Api::createTransaction($params);
		if (!empty($res['response']['payment_url'])) {
			header('Location: '.$res['response']['payment_url']);
			exit;
		}
		var_dump($res);
	} elseif ($_POST['case'] == '2') {
		$res = Api::setUserTariff($_POST['tariff_id']);
		var_dump($res);
	}
}

$APPLICATION->SetTitle(Module::m('TARIFFS_PAGE_TITLE'));

$res = Api::me();
$user = $res['response'] ?: [];
$res = Api::tariffsList();
$tariffs = $res['response']['tariffs'];
$tariffs = Module::convertToSiteCharset($tariffs);
$currentTariff = null;
$otherTariffs = [];
$balans = 0;
foreach ($tariffs as $tariff) {
	if ($user['tariff']['id'] == $tariff['id']) {
		$currentTariff = $tariff;
		$expiry_at = strtotime($user['tariff']['expiry_at']);
		$leftTime = $expiry_at - strtotime('now');
		$expired = $leftTime < 0;
		if ($expired) {
			continue;
		}
		$diff = strtotime('now +1 month') - strtotime('now');
		$pricePerTime = $tariff['price'] / $diff;
		$balans = $pricePerTime * $leftTime;
	} else {
		$otherTariffs[] = $tariff;
	}
}

?>
<?php if (!empty($currentTariff)): ?>
<h2><?=Module::m('PROLONG_TARIFF')?></h2>
<div class="vettich_sp3_tariffs_list">
	<div class="vettich_sp3_tariffs_item">
		<h3 class="vettich_sp3_tariff_title">
			<?=$currentTariff['name']?>
			<?=Module::m('EXPIRY_AT', ['#date#' => date('d.m.Y', strtotime($user['tariff']['expiry_at']))]) ?>
		</h3>
		<div>
			<b><?=Module::m('POSTS_CNT')?></b>:
			<?=Module::m('UNLIMITED')?>
		</div>
		<br/>
		<div>
			<b><?=Module::m('ACCOUNTS_CNT')?></b>:
			<?=$currentTariff['limits']['accounts_cnt']?>
		</div>

		<br/>
		<form method="post">
			<?=bitrix_sessid_post() ?>
			<input type="hidden" name="tariff_id" value="<?=$currentTariff['id']?>" />
			<?php foreach ([1, 3, 6, 12] as $period): ?>
				<label>
					<?php $amount = calcAmount($period, $currentTariff['price']) ?>
					<input name="period" value="<?=$period?>" type="radio" checked="checked" />
					<?=Module::m('MONTH_'.$period)?>:
					<?=Module::m($period == 1 ? 'AMOUNT' : 'AMOUNT2', [
						'#amount#' => $amount['value'],
						'#saving#' => $amount['saving'],
					])?>
				</label>
				<br/>
			<?php endforeach ?>
			<br/>
			<button class="adm-btn vettich_sp3_tariff_btn" type="submit"><?=Module::m('SUBMIT')?></button>
		</form>
	</div>
	<br/>
	<br/>
	<h2><?=Module::m('SWITCH_TARIFF')?></h2>
<?php endif ?>

<div class="vettich_sp3_tariffs_list">
	<?php foreach ($otherTariffs as $tariff): ?>
	<div class="vettich_sp3_tariffs_item">
		<h3 class="vettich_sp3_tariff_title">
			<?=$tariff['name']?>
		</h3>
		<div>
			<b><?=Module::m('POSTS_CNT')?></b>:
			<?=Module::m('UNLIMITED')?>
		</div>
		<br/>
		<div>
			<b><?=Module::m('ACCOUNTS_CNT')?></b>:
			<?=$tariff['limits']['accounts_cnt']?>
		</div>

		<br/>
		<form method="post">
			<?=bitrix_sessid_post() ?>
			<input type="hidden" name="tariff_id" value="<?=$tariff['id']?>" />
			<?php if ($balans > 0): ?>
				<?php $diff = strtotime('now +1 month') - strtotime('now') ?>
				<?php $pricePerTime = $tariff['price'] / $diff ?>
				<?php $leftTime = $balans / $pricePerTime ?>
				<input type="hidden" name="case" value="2" />
				<?=Module::m('LEFT_TIME', ['#time#' => date('d.m.Y', strtotime('now') + $leftTime)]) ?>
				<br/>
				<br/>
				<button class="adm-btn" type="submit"><?=Module::m('SELECT_TARIFF') ?></button>
				<br/>
				<br/>
			<?php else: ?>
				<input type="hidden" name="case" value="1" />
			<?php endif ?>
			<?php foreach ([1, 3, 6, 12] as $period): ?>
				<label>
					<?php $amount = calcAmount($period, $tariff['price']) ?>
					<?php $checked = $period == 1 ? 'checked="checked"' : '' ?>
					<input name="period" value="<?=$period?>" type="radio" <?=$checked ?> />
					<?=Module::m('MONTH_'.$period)?>:
					<?=Module::m($period == 1 ? 'AMOUNT' : 'AMOUNT2', [
						'#amount#' => $amount['value'],
						'#saving#' => $amount['saving'],
					])?>
				</label>
				<br/>
			<?php endforeach ?>
			<br/>
			<?php if ($balans > 0): ?>
				<input type="submit" disabled="disabled" value="<?=Module::m('SUBMIT')?>" />
			<?php else: ?>
				<input type="submit" value="<?=Module::m('SUBMIT')?>" />
			<?php endif ?>
		</form>
	</div>
	<?php endforeach ?>
</div>

<style>
	.vettich_sp3_tariffs_list {
		width: 100%;
	}
	.vettich_sp3_tariffs_item {
		display: inline-block;
		width: 30%;
		padding-left: 2em;
	}
	.vettich_sp3_tariffs_item:not(:first-child){
		border-left:1px solid #9d9d9d;
	}
	.vettich_sp3_tariff_btn {
		display: block;
		margin: 0 auto;
	}
</style>

<?php
require(__DIR__.'/../include/epilog_authorized_page.php');

function calcAmount($period, $price)
{
	$percent = ($period-1) * 2;
	if ($percent > 30) {
		$percent = 30;
	}
	$full = $period * $price / 100;
	$amount = [];
	$amount['value'] = $full - ($full * $percent / 100);
	$amount['saving'] = $full - $amount['value'];
	return $amount;
}
