<?
namespace Vettich\devform\db;
use Bitrix\Main\Entity;

class optionTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return 'vettich_devform_option';
	}

	public static function getMap()
	{
		$arMap = array(
			new Entity\IntegerField('ID', array(
				'primary' => true,
				'autocomplete' => true
			)),
			new Entity\StringField('MODULE_ID'),
			new Entity\StringField('NAME'),
			new Entity\TextField('VALUE'),
		);
		return $arMap;
	}

	public static function createTable()
	{
		try
		{
			$entity = self::getEntity();
			$connection = $entity->getConnection();
			if(!$connection->isTableExists($entity->getDBTableName()))
			{
				$sql = $entity->compileDbTableStructureDump();
				$connection->query($sql[0]);
				return $connection->isTableExists($entity->getDBTableName());
			}
			return true;
		}
		catch(\Exception $e){}
		return false;
	}

	public static function dropTable()
	{
		try
		{
			$entity = self::getEntity();
			$connection = $entity->getConnection();
			if($connection->isTableExists($entity->getDBTableName()))
			{
				$connection->dropTable($entity->getDBTableName());
				return !$connection->isTableExists($entity->getDBTableName());
			}
			return true;
		}
		catch(\Exception $e){}
		return false;
	}
}
