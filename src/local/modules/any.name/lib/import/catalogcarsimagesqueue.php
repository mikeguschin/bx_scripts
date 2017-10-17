<?php
namespace Arus\Main\Import;

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Type;
use Bitrix\Main\ArgumentNullException;

class CatalogCarsImagesQueue
{
	private static $hlibName = 'TdCatalogCarsImagesQueue',
		$entityClass, 
		$entityId,
		$statusList = [];
	
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

	public static function getStatusId ($xmlId)
	{
		if (isset(self::$statusList[$xmlId]))
		{
			return self::$statusList[$xmlId];
		}
		$propertyId = \CUserTypeEntity::GetList([
			'ID' => 'ASC'
		], [
			'ENTITY_ID' => 'HLBLOCK_'.self::$entityId,
			'XML_ID' => 'UF_STATUS_ID'
		])->fetch()['ID'];
		$dbRes = \CUserFieldEnum::GetList([
			'ID'=>'ASC'
		], [
			'XML_ID' => $xmlId,
			'USER_FIELD_ID' => $propertyId
		]);
		if (!$dbRes->selectedRowsCount())
		{
			return false;
		}
		else
		{
			return intval($dbRes->fetch()['ID']);
		}
	}

	/**
	 * добавление изображения @param $path в очередь на запись
	 */
	public static function push ($carid, $path, $color)
	{
		if (is_null($carid))
		{
			throw new ArgumentNullException('Не задан автомобиль');
		}
		$queue = self::getEntity();
		/**
		 * если загружаемого изображения нет в активной очереди, то загрузить
		 * @see дедубликация на этапе загрузки изображения по CRC-коду: self::exec()
		 */
		$search = (int) $queue::getCount([
			'=UF_CAR_ID' => $carid,
			'=UF_SRC' => $path,
			'UF_STATUS_ID' => self::getStatusId('wait')
		]);
		// по текущему цвету уже найден файл
		if (!$search)
		{
			$result = $queue::add([
				'UF_CAR_ID' => $carid,
				'UF_COLOR' => $color,
				'UF_SRC' => $path,
				'UF_STATUS_ID' => self::getStatusId('wait'),
				'UF_ATTEMPTS' => 0,
				'UF_LAST_UPDATE' => new Type\DateTime()
			]);
			return $result->isSuccess();
		}
		return false;
	}

	/**
	 * привязка изображения @param $fileId к авто @param $autoId
	 */
	public static function bindPictureToAutoGallery ($autoId, $fileId)
	{
		// получение автомобиля и фоток которые к нему привязаны
		$auto = CatalogCarsImport::getEntity();
		// получение текущей версии галереи
		$gallery = $auto::getList([
			'filter' => ['ID' => $autoId],
			'select' => ['UF_IMAGE']
		])->fetch()['UF_IMAGE'];

		$gallery[] = $fileId;

		return $auto::update($autoId, [
			'UF_IMAGE' => $gallery,
			'UF_LAST_UPDATE' => new Type\DateTime()
		])->isSuccess();
	}

	/**
	 * Скачивает изображение расположенное по пути @param $srcPath 
	 * и размещает его в папке @param $dstPath сайта
	 * @return bool - признак наличия пути $dstPath
	 */
	public function downloadImage($srcPath, $dstPath)
	{
		if (file_exists($dstPath))
		{
			unlink($dstPath);
		}
		$fd = fopen($dstPath, 'a+');

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $srcPath);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($curl, CURLOPT_FILE, $fd);
		curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$fd) 
		{
			return fwrite($fd, $data);
		});
		curl_exec($curl);
		curl_close($ch);
		fclose($fd);
		
		return file_exists($dstPath);
	}
 	/**
 	 * @param $limit int - кол-во записей очереди обрабатываемых за 1 шаг
 	 *
 	 */
	public static function exec ($limit=10)
	{
		$maxAttempts = 3;
		$queue = self::getEntity();
		$search = $queue::getList([
			'order' => ['ID' => 'ASC'],
			'filter' => [
				'<UF_ATTEMPTS' => $maxAttempts,
				'=UF_STATUS_ID' => [
					self::getStatusId('error'),
					self::getStatusId('wait')
				]
			],
			'limit' => $limit,
			'select' => [
				'ID', 
				'UF_CAR_ID', 
				'UF_SRC',
				'UF_COLOR',
				'UF_ATTEMPTS'
			]
		]);
		if ($search->getSelectedRowsCount())
		{
			while ($image = $search->fetch())
			{
				// изменение статуса текущей записи в очереди на "в обработке"
				$queue::update($image['ID'], [
					'UF_STATUS_ID' => self::getStatusId('in_progress'),
					'UF_ATTEMPTS' => intval($image['UF_ATTEMPTS'])+1,
					'UF_LAST_UPDATE' => new Type\DateTime()
				]);
				$filename = $_SERVER['DOCUMENT_ROOT'].'/upload/tmp/'.$image['UF_CAR_ID'].'.'.$image['UF_COLOR'].'.'.basename($image['UF_SRC']);
				if (self::downloadImage($image['UF_SRC'], $filename))
				{
					$arFields = [
						'UF_CRC_CODE' => hash_file('crc32b', $filename),
						'UF_FILE' => \CFile::makeFileArray( $filename )
					];
					// проверка наличия файла на сайте по цвету
					$existingImage = $queue::getList([
						'filter' => [
							'=UF_COLOR' => $image['UF_COLOR'],
							// '=UF_CRC_CODE' => $arFields['UF_CRC_CODE'],
							'=UF_CAR_ID' => $image['UF_CAR_ID'],
							'UF_STATUS_ID' => self::getStatusId('complete')
						],
						'select' => ['ID', 'UF_COLOR', 'UF_CRC_CODE'],
						'limit' => 1
					]);
					// удалить текущую запись из очереди, потому что она загружалась ранее
					if ($existingImage->getSelectedRowsCount())
					{
						$existingImage = $existingImage->fetch();
						// дедубликация по контрольной сумме
						if ($existingImage['UF_CRC_CODE'] == $arFields['UF_CRC_CODE'])
						{
							$queue::delete($image['ID']);
							// удаление временного файла
							unlink($filename);
							continue;
						}
						// заменить существующее изображение на сайте
						else
						{
							$queue::delete($existingImage['ID']);
						}
					}
					// если файл ранее не загружался
					if ($queue::update($image['ID'], $arFields)->isSuccess())
					{
						if (self::bindPictureToAutoGallery(
								$image['UF_CAR_ID'], 
								$queue::getList([
									'filter' => ['ID' => $image['ID']], 
									'select' => ['UF_FILE']
								])->fetch()['UF_FILE']
							))
						{
							$arFields = [
								'UF_STATUS_ID' => self::getStatusId('complete'),
								'UF_STATUS_DESC' => 'Изображение успешно выгружено и привязано к авто'
							];
						}
						else
						{
							$arFields = [
								'UF_STATUS_ID' => self::getStatusId('error'),
								'UF_STATUS_DESC' => 'Авто не найдено на сайте. Изображение не выгружено на сайт'
							];
						}
					}
					else
					{
						$arFields = [
							'UF_STATUS_ID' => self::getStatusId('error'),
							'UF_STATUS_DESC' => 'Не удалось загрузить изображение в очередь'
						];
					}
					// удаление временного файла
					unlink($filename);
				}
				else
				{
					$arFields = [
						'UF_STATUS_ID' => self::getStatusId('error'),
						'UF_STATUS_DESC' => 'Не удалось выгрузить файл изображения '.$image['UF_SRC']
					];
				}
				// изменение статуса записи в очереди
				$queue::update($image['ID'], 
					array_merge(['UF_LAST_UPDATE' => new Type\DateTime()], $arFields)
				);
			}
		}
	}
}