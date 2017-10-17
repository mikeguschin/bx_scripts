<?php
/**
 * Created by PhpStorm.
 * User: Mike Guschin
 * Date: 19.06.2017
 * Time: 12:45
 */

namespace Arus\Main;

use \Bitrix\Main\Context;
use \Bitrix\Main\ArgumentNullException;
use Bitrix\Main\ArgumentTypeException;
use COption;
use LinemediaAutoPartsHelper; //We will use 'clearArticle' method for to get picture name

class ArusPicture
{

    protected $basePath;
    protected $domainName;
    protected $imagesFolder;

    private $code;
    private $noPhotoFile;
    private $imageFileExtension;
    private $fileDelimiter;

    protected $mainPhoto;
    protected $morePhoto;

    protected $brandPhotoPath;

    /**
     * ArusPicture constructor.
     * @param bool $code - string or array вида ("BRAND" => НАЗВАНИЕ_БРЕНДА, "ARTICUL" => АРТИКУЛ_ДЕТАЛИ)
     */
    public function __construct($code = false)
    {
        $this->code = $code;
        $this->basePath = self::setBaseFilesPath();
        $this->fileDelimiter = self::setFileDelimiter();
        $this->noPhotoFile = self::setNoPhotoFile();
        $this->imageFileExtension = self::setImageFileExtension();

        $this->domainName = self::setDomainName();
        $this->imagesFolder = self::setImagesFolder();

        $this->brandPhotoPath = self::setBrandPhotoPath();

        //$code для совместимости может быть типа string или array
        //Если array - то выполняем другие методы для поиска картинки
        if (is_array($code)) {
            $this->mainPhoto = self::getDetailByBrand($code);
            $this->morePhoto = self::getMorePhotoArrayByBrand($code);
        } else {
            $this->mainPhoto = self::getDetailByCode($code);
            $this->morePhoto = self::getMorePhotoArray($code);
        }
    }

    public function __destruct()
    {
        unset($this->code);
        unset($this->show_more_photo);
        unset($this->mainPhoto);
        unset($this->morePhoto);
        // TODO: Implement __destruct() method.
    }

    /*Метод получения детальной картинки
    */
    final public function getDetailPictureFile()
    {
        return $this->mainPhoto;
    }

    /*Метод получения MORE_PHOTO
    */
    final public function getMorePhotoFiles()
    {
        return $this->morePhoto;
    }

    /*Получение URL основной картинки
    */
    final public function getDetailPictureUrl()
    {
        return $this->getPictureUrl($this->mainPhoto);
    }

    /*Получение URL для всех MorePhoto
    */
    final public function getMorePhotoUrls()
    {
        $arPics = array();
        foreach ($this->morePhoto as $key => $pic) {
            $arPics[$key] = $this->getPictureUrl($pic);
        }
        return $arPics;
    }

    final public function getAllPhotoFiles()
    {

        if (empty($result_array = $this->getMorePhotoFiles())) {
            $result_array[] = $this->getDetailPictureFile();
        } else {
            array_unshift($result_array, $this->getDetailPictureFile());
        }

        return $result_array;
    }

    final public function getAllPhotoUrls()
    {
        $result_array = $this->getMorePhotoUrls();
        array_unshift($result_array, $this->getDetailPictureUrl());
        return $result_array;
    }


    protected function getPictureUrl($filePath)
    {
        return str_replace($this->basePath, $this->domainName, $filePath);
    }

    /*Получить изображение NoPhoto
    */
    public final function getNoImageFile()
    {
        return $this->basePath . $this->noPhotoFile;
    }

    public final function getNoImageUrl()
    {
        $file = self::getNoImageFile();
        return ($this->getPictureUrl($file));
    }

    /*Получить имя файла по артикулу товара - только внутрии класса
    */
    private function getImagePathByProductCode($code)
    {
        return $this->basePath . $this->imagesFolder . $code . $this->imageFileExtension;
    }

    /*Получить картинку
    */
    private function getDetailByCode($code)
    {
        /*if (empty($code)){
            throw new ArgumentNullException(__METHOD__."Picture Identifier");
        }
        if (!$path = self::checkFileExistsByProductCode($code)){
            $path = self::getNoImageFile();
        }*/

        if (empty($code) || !$path = self::checkFileExistsByProductCode($code)) {
            $path = self::getNoImageFile();
        }
        return $path;
    }
    /* Получить картинку по Бренду и Артикулу
    */
    protected function getDetailByBrand($arSearch)
    {
        if (!array_key_exists("BRAND", $arSearch) || !array_key_exists("ARTICUL", $arSearch)) {
            return false;
        }

        if (!$path = self::checkFileExistsByProductBrand($arSearch)) {
            $path = self::getNoImageFile();
        }
        return $path;
    }
    //На входе - некая строка. Предполагаем любое имя файла без расширения
    //Возвращает путь
    protected function checkFileExistsByProductCode($code)
    {
        $file_path = self::getImagePathByProductCode($code);
        if (file_exists($file_path)) {
            return $file_path;
        } else {
            return false;
        }
    }

    protected function checkFileExistsByProductBrand($arBrandArt)
    {
        $folder = strtolower($arBrandArt["BRAND"]);
        $fileName = self::clearArticle($arBrandArt["ARTICUL"]);

        $filePath = $this->brandPhotoPath
            . $folder . '/'
            . $fileName
            . $this->imageFileExtension;

        if (file_exists($filePath))
            return $filePath;
        else {
            return false;
        }
    }

    private function getMorePhotoArray($code)
    {
        $result = array();
        if (empty($code)) {
            return $result;
        }
        //ToDo:: think about usign funtcion glob.
        //Now it's too slow
        //Example is here:
//        $mask = self::basePath.$code."#*".self::IMAGE_FILE_EXTENSION;
//        $result = glob($mask);
//        if (count($result)==0)
//            return false;
//        return $result;

        $picsCount = 1;
        while ($pic = $this->checkFileExistsByProductCode($code . $this->fileDelimiter . $picsCount)) {
            $result[] = $pic;
            $picsCount++;
            if ($picsCount > 100) {
                break; //Аварийнай остановка на всякий пожарный
            }
        }
        if (count($result) == 0) {
            return false;
        }
        return $result;
    }

    private function getMorePhotoArrayByBrand($arBrandArt)
    {
        $folder = strtolower($arBrandArt["BRAND"]);
        $fileName = self::clearArticle($arBrandArt["ARTICUL"]);

        $result = array();
        $picsCount = 1;
        $filePath = $this->brandPhotoPath
            . $folder . '/'
            . $fileName . $this->fileDelimiter . $picsCount
            . $this->imageFileExtension;
        while (file_exists($filePath)) {
            $result[] = $filePath;
            $picsCount++;
            $filePath = $this->brandPhotoPath
                . $folder . '/'
                . $fileName . $this->fileDelimiter . $picsCount
                . $this->imageFileExtension;
            if ($picsCount > 100) {
                break; //Аварийнай остановка на всякий пожарный
            }
        }
        if (count($result) == 0) {
            return false;
        }
        return $result;
    }

    public static function clearArticle($art)
    {
        $art = LinemediaAutoPartsHelper::clearArticle($art);
        if (defined('BX_UTF') && BX_UTF == true) {
            $result = mb_strtolower(str_replace(array('#'), '', $art), 'UTF-8');
            // нельзя задавать однобайтные символы через chr
        } else {
            $result = mb_strtolower(str_replace(array(chr(35)), '', $art));
        }
        return $result;
    }

    private static function setBaseFilesPath()
    {
        return COption::GetOptionString("arus.main", "BaseFilesPath", "/home/bitrix/project/src");
    }

    private static function setFileDelimiter()
    {
        return COption::GetOptionString("arus.main", "FileDelimiter", "_");
    }

    private static function setImageFileExtension()
    {
        return ".jpg";
    }

    private static function setNoPhotoFile()
    {
        return COption::GetOptionString("arus.main", "NoPhotoFile", "/images/no_photo.png");
    }

    private static function setDomainName()
    {
        return COption::GetOptionString("arus.main", "ImagesDomainName", Context::getCurrent()->getServer()->get("SERVER_NAME"));
    }

    private function setImagesFolder()
    {
        return COption::GetOptionString("arus.main", "ImagesFolder", "/images/catalog/");
    }

    private function setBrandPhotoPath()
    {
        return $this->basePath . $this->imagesFolder . "brand_photos/";
    }
}