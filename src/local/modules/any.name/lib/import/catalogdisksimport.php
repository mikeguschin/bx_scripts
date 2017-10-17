<?php
namespace Arus\Main\Import;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type;
use Bitrix\Main\UserFieldTable;
use Bitrix\Main\SystemException;
use Bitrix\Main\ArgumentNullException;
/**
 * Используется для выгрузки шин в HLBLOCK "Диски"
 * @see http://dev10.dev.voshod.local/bitrix/admin/highloadblock_rows_list.php?ENTITY_ID=8&lang=ru
 */
class CatalogDisksImport implements ICatalogImport
{
	private static $entityClass;
	private static $entityId;
	private static $entityFields;
	private static $hlibName = 'TdCatalogDisks';

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
		$disk = $entity::getList([
			'filter' => [
				'UF_CARID' => $fields['UF_CARID'],
				'UF_VENDOR_DISKID' => $fields['UF_VENDOR_DISKID']
			],
			'select' => ['ID']
		]);
		$fields['UF_LAST_UPDATE'] = new Type\DateTime();
		if ($disk->getSelectedRowsCount())
		{
			$diskId = $disk->fetch()['ID'];
			$action = $entity::update($diskId, $fields);
			if (!$action->isSuccess())
			{
				throw new SystemException(
					"Не удалось обновить Диск с id=".$diskId .' '. implode(', ', $action->getErrorMessages())
				);
			}
		}
		else
		{
			$action = $entity::add($fields);
			if ($action->isSuccess())
			{
				$diskId = $action->getId();
			}
			else
			{
				throw new ArgumentNullException(implode(', ', $action->getErrorMessages().PHP_EOL));
			}
		}
		return $diskId;
	}
}