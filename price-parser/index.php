<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
global $USER;
if ($USER->IsAdmin()) {
    $APPLICATION->IncludeComponent(
        "foxzaro:goods_uploader",
        ".default",
        array(
            'IBLOCK_ID' => '1',
            'IBLOCK_SKU_ID' => '2',
            'CODE_ARTICLE' => 'PROPERTY_ARTICLE',
            'CURSOR_ARTICLE' => '0',
            'CURSOR_PRICE' => '3',
        )
    );
}
else{
    LocalRedirect("/");
}
