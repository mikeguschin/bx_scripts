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
 * Используется для выгрузки шин в HLBLOCK "Шины"
 * @see http://dev10.dev.voshod.local/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID=10&lang=ru
 */
class CatalogTyresImport implements ICatalogImport
{
	private static $entityClass;
	private static $entityId;
	private static $entityFields;
	private static $hlibName = 'TdCatalogTypes';

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
		if (is_null($fields['UF_WIDTH']))
		{
			unset($fields['UF_WIDTH']);
		}
		if (is_null($fields['UF_HEIGHT']))
		{
			unset($fields['UF_HEIGHT']);
		}
		if (is_null($fields['UF_DIAMETER']))
		{
			unset($fields['UF_DIAMETER']);
		}
		$entity = self::getEntity();
		$tyre = $entity::getList([
			'filter' => [
				'UF_CARID' => $fields['UF_CARID'],
				'UF_DISKID' => $fields['UF_DISKID']
			],
			'select' => ['ID']
		]);
		$fields['UF_LAST_UPDATE'] = new Type\DateTime();
		$tyreId = false;
		if ($tyre->getSelectedRowsCount())
		{
			$tyreId = $tyre->fetch()['ID'];
			$action = $entity::update($tyreId, $fields);
			if (!$action->isSuccess())
			{
				throw new SystemException(
					"Не удалось обновить Шины с id=".$tyreId .' '. implode(', ', $action->getErrorMessages())
				);
			}
		}
		else
		{
			$action = $entity::add($fields);
			if ($action->isSuccess())
			{
				$tyreId = $action->getId();
			}
			else
			{
				throw new ArgumentNullException(implode(', ', $action->getErrorMessages().PHP_EOL));
			}
		}
		return $tyreId;
	}
}