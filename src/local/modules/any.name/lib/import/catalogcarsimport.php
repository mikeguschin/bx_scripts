<?php
namespace Arus\Main\Import;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\Type;
use Bitrix\Main\SystemException;
use Bitrix\Main\ArgumentNullException;
/**
 * Используется для выгрузки шин в HLBLOCK "Автомобили"
 * @see http://dev10.dev.voshod.local/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID=6&lang=ru
 */
class CatalogCarsImport implements ICatalogImport
{
	private static $entityClass;
	private static $entityId;
	private static $entityFields;
	private static $hlibName = 'TdCatalogCars';

	public static function getEntity()
	{
		if (is_null(self::$entityClass))
		{
	        $arHl = HighloadBlockTable::getList(array(
	            'filter' => [
	                '=NAME' => self::$hlibName,
	            ]
	        ))->fetch();
	        self::$entityId = $arHl['ID'];
	        self::$entityClass = HighloadBlockTable::compileEntity($arHl)->getDataClass();
		}
		return self::$entityClass;
	}
	public static function getFields()
	{
		if (is_null(self::$entityFields))
		{
			self::$entityFields = UserFieldTable::getList([
				'filter' => [
					'ENTITY_ID' => 'HLBLOCK_'.self::$entityId
				], 
				'select' => [
					'FIELD_NAME', 'XML_ID'
				]
			])->fetchAll();
		}
		return self::$entityFields;
	}
	public static function bind($fields)
	{
		$entity = self::getEntity();
		// поиск авто в локальной БД
		$car = $entity::getList([
			'filter' => [
				'UF_VENDOR_CARID' => $fields['UF_VENDOR_CARID'],
				'UF_MARKA' => $fields['UF_MARKA'],
				'UF_MODEL' => $fields['UF_MODEL'],
				'UF_BEGINYEAR' => $fields['UF_BEGINYEAR'],
				'UF_ENDYEAR' => $fields['UF_ENDYEAR']
			], 
			'select' => ['ID']
		]);
		$fields['UF_LAST_UPDATE'] = new Type\DateTime();

		$carId = false;
		if ($car->getSelectedRowsCount())
		{
			/**
			 * @todo: добавить проверку значений полей, чтобы не update-ить неизменяемые значения
			 */
			$carId = $car->fetch()['ID'];
			$action = $entity::update($carId, $fields);
			if (!$action->isSuccess())
			{
				throw new SystemException(
					"Не удалось обновить автомобиль с id=".$carId .' '. implode(', ', $action->getErrorMessages())
				);
			}
		}
		else
		{
			$action = $entity::add($fields);
			if ($action->isSuccess())
			{
				$carId = $action->getId();
			}
			else
			{
				throw new ArgumentNullException(implode(', ', $action->getErrorMessages().PHP_EOL));
			}
		}
		return $carId;
	}
}