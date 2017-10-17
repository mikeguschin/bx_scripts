<?php
namespace Arus\Main\Import;
/**
 * Имплементируется объектами Шины, Диски, Автомобили
 * @see local/modules/aurus.main/lib/import/catalogcarsimport.php
 * @see local/modules/aurus.main/lib/import/catalogdisksimport.php
 * @see local/modules/aurus.main/lib/import/catalogtyresimport.php
 */
interface ICatalogImport
{
	public static function getEntity();
	public static function bind($fields);
	public static function getFields();
}