<?
namespace vettich\devform;
use Bitrix\Main\Entity;

class dbTable extends Entity\DataManager
{
	public static function getTableName()
	{
		return 'vettich_devform_db';
	}

	public static function getMap()
	{
		$arMap = array(
			new Entity\IntegerField('ID', array(
				'primary' => true,
				'autocomplete' => true
			)),
			new Entity\StringField('NAME'),
			new Entity\StringField('IS_ENABLE'),
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
