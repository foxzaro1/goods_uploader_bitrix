<?php
use Bitrix\Main\Localization\Loc,
    Bitrix\Main\SystemException,
    Bitrix\Main\Loader,
    Bitrix\Main\Type\Date,
    Bitrix\Main\Page\Asset,
    Bitrix\Main\Diag\Debug,
    Bitrix\Main\Data\Cache,
    Bitrix\Main\Application,
    CFile,
    CIBlockElement;

if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

class xParser extends CBitrixComponent
{
    protected $users    = [];
    protected $errors   = [];
    protected $code_article = 'PROPERTY_ARTICLE';
    protected $default_iblock_id = 1;
    protected $default_iblock_sku_id = 2;
    protected $cursor_article = 0;
    protected $cursor_price = 3;
    protected $classes_paths = [
        "SimpleXLS" => "/local/php_interface/custom/classes/SimpleXLS.php",
        "SimpleXLSX" => "/local/php_interface/custom/classes/SimpleXLSX.php"
    ];

    public function onPrepareComponentParams($arParams)
    {
        if(!isset($arParams["CACHE_TIME"]))
            $arParams["CACHE_TIME"] = 3600;

        $arParams["IBLOCK_ID"] = intval($arParams["IBLOCK_ID"]);

        return $arParams;
    }

    public function executeComponent()
    {
        try
        {
            global $APPLICATION;

            if($this->arParams['IBLOCK_ID']) {
                $this->default_iblock_id = $this->arParams['IBLOCK_ID'];
            }
            if($this->arParams['IBLOCK_SKU_ID']) {
                $this->default_iblock_sku_id = $this->arParams['IBLOCK_SKU_ID'];
            }
            if($this->arParams['CODE_ARTICLE']){
                $this->code_article = $this->arParams['CODE_ARTICLE'];
            }
            if($this->arParams['CURSOR_ARTICLE']){
                $this->cursor_article = $this->arParams['CURSOR_ARTICLE'];
            }
            if($this->arParams['CURSOR_PRICE']){
                $this->cursor_price = $this->arParams['CURSOR_PRICE'];
            }

            $request = Application::getInstance()->getContext()->getRequest();

            if ($request->getPost('DETAIL_URL')) {
                $APPLICATION->RestartBuffer();
                $this->initClasses();
                $file_list = $request->getFileList()->toArray();
                if($res = $this->checkFile($file_list['FILE'])){
                    if($this->upload($res)){
                        echo json_encode(["status" => true, 'message' => 'новые цены загрузились']);
                    }
                    else{
                        echo json_encode(["status" => false, 'message'=> 'ошибка загрузки']);
                    }
                }
            }
            else {
                $this->includeComponentTemplate();
            }

        }
        catch (SystemException $e)
        {
            Debug::dumpToFile(date("Y-m-d H:i:s")." ".$e->getMessage(),"","logs/CTreeSections_error.log");
        }
    }
    protected function initClasses(){
        foreach ($this->classes_paths as $path){
            try {
                require_once ($_SERVER["DOCUMENT_ROOT"].$path);
            }catch (Exception $e){

            }
        }
    }
    protected function checkModules()
    {
        if (!Loader::includeModule('iblock'))
            throw new SystemException(Loc::getMessage('CPS_MODULE_NOT_INSTALLED', array('#NAME#' => 'iblock')));
    }
    protected function upload($path){
        $extension = end(explode(".", $path));
        if($extension == 'xlsx'){
            $parser = new SimpleXLSX();
        }
        elseif($extension == "xls"){
            $parser = new SimpleXLS();
        }
        else{
            return 'bad extension file';
        }
        if ($xlsx = $parser->parse($path)) {
                $data = $xlsx->rows();
                $uploadData = [];
                unset($data[0]);
                foreach ($data as $item) {
                    if ($item[$this->cursor_article] && $item[$this->cursor_price] && is_numeric($item[$this->cursor_price])) {
                        $uploadData[$item[$this->cursor_article]] = [
                            'PRICE' => $item[$this->cursor_price]
                        ];
                    }
                }

                if($uploadData) {
                    $ids = [];
                    $data = [];
                    $elements = $this->getElements(array_keys($uploadData));
                    foreach ($elements as $key=>$element){
                        $data[$element['ID']] = $element;
                        $data[$element['ID']]['PRICE'] = $uploadData[$key]['PRICE'];
                        $ids[] = $element['ID'];
                    }

                    if($ids && $data){
                        if($res = $this->upgradePrices($data,$ids)){
                            return $res;
                        }
                    }
                }
                else{
                    return false;
                }
            }
        else{
            echo SimpleXLSX::parseError();
        }
    }
    private function getElements($filter){

        $arrMain = [];
        $arSelect = Array("ID", "NAME", $this->code_article);
        $arFilter = Array("IBLOCK_ID"=>IntVal($this->default_iblock_id), $this->code_article => $filter);
        $res = CIBlockElement::GetList(Array(), $arFilter, false, false, $arSelect);
        while($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            $arrMain[$arFields[$this->code_article.'_VALUE']] = [
                "ID" => $arFields['ID'],
                'NAME' => $arFields['NAME'],
                "SKU"  => false,
            ];
            unset($filter[array_search($arFields[$this->code_article.'_VALUE'], $filter)]);
        }

        if($filter) {
            $arSelect = array("ID", "NAME", $this->code_article);
            $arFilter = array("IBLOCK_ID" => IntVal($this->default_iblock_sku_id), $this->code_article => $filter);
            $res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
            while ($ob = $res->GetNextElement()) {
                $arFields = $ob->GetFields();
                $arrMain[$arFields[$this->code_article.'_VALUE']] = [
                    "ID" => $arFields['ID'],
                    'NAME' => $arFields['NAME'],
                    "SKU" => true,
                ];
            }
        }

        return $arrMain;
    }
    private function upgradePrices($data, $ids){

        try {
            $res = CPrice::GetList(
                array(),
                array(
                    "PRODUCT_ID" => $ids,
                )
            );

            while ($arr = $res->Fetch()) {
                $arFields = array(
                    "PRODUCT_ID" => $arr['PRODUCT_ID'],
                    "CATALOG_GROUP_ID" => 1,
                    "PRICE" => $data[$arr['PRODUCT_ID']]['PRICE'],
                    "CURRENCY" => "RUB",
                );
                CPrice::Update($arr["ID"], $arFields);
            }
            return true;
        }catch(Exception $e){
            return false;
        }

    }
    protected function saveFile($file){
        $extension = end(explode(".", $file['name']));
        if(!in_array($extension, ['xls','xlsx'])){
            return 'bad extension file';

        }
        if(move_uploaded_file($file['tmp_name'], dirname(__FILE__) .'/'.'prices.'.$extension)) {
            return dirname(__FILE__) . '/' . 'prices.'.$extension;
        }else{
            return false;
        }
    }
    protected function checkFile($file){
        if($file['error'] == 0 && $file['size']){
            return $this->saveFile($file);
        }
        else{
            return false;
        }

    }


}