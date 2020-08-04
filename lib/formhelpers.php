<?php
namespace vettich\sp3;

use vettich\sp3\db\Accounts;
use vettich\sp3\devform\types\checkbox;

class FormHelpers
{
	private const ACC_NAME = '<span class="vettich-sp3-acc-link" target="_blank"><img src="#PIC#"><span>#NAME#</span></span>';

	public static function buildAccountsList($id)
	{
		$tabParams = ['h2' => 'heading:#.POST_HEADER_ACCOUNTS#'];

		$accList = (new Accounts())->getListType();
		foreach ($accList as $t => $accounts) {
			$accountsMap = [];
			foreach ($accounts as $account) {
				$name = TextProcessor::replace(self::ACC_NAME, [
					'PIC'             => $account['photo'],
					'TYPE'            => $account['type'],
					'LINK'            => $account['link'],
					'NAME'            => $account['name'],
					'OPEN_IN_NEW_TAB' => Module::m('OPEN_IN_NEW_TAB'),
				]);
				$accountsMap[$account['id']] = $name;
			}

			$tabParams[] = new checkbox($id, [
				'title'    => Module::m(strtoupper($t)),
				'options'  => $accountsMap,
				'multiple' => true,
			]);

			/* if (in_array($t, ['insta', 'tg', 'fb'])) { */
			/* 	$accWarningShow = true; */
			/* } */
		}

		/* if ($accWarningShow) { */
		$tabParams['acc_warn'] = 'note:#.ACC_WARN_NOTE#';
		/* } */

		return $tabParams;
	}
}
