<? if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

/**
 * @var array $arParams
 * @var array $arResult
 * @var SaleOrderAjax $component
 */

$component = $this->__component;
$component::scaleImages($arResult['JS_DATA'], $arParams['SERVICES_IMAGES_SCALING']);

if (!empty($arResult["ORDER"])) {
    $rsSites = CSite::GetByID(SITE_ID);
    $arSite = $rsSites->Fetch();
    $arResult['SITE_NAME'] = $arSite['SITE_NAME'];

    if (CModule::IncludeModule("sale")) {
        $dbOrder = CSaleBasket::GetList(
            array("ID" => "DESC"),
            array(
                "ORDER_ID" => $arResult["ORDER"]['ID'],
            ),
            false,
            false,
            array("ID", "PRODUCT_ID", "NAME" , "PRICE", "QUANTITY")
        );

        while ($arOrder = $dbOrder->GetNext()) {
            $arResult['ORDER']['PRODUCTS'][] = array(
                'PRODUCT_ID' => $arOrder['PRODUCT_ID'],
                'NAME' => $arOrder['NAME'],
                'PRICE' => $arOrder['PRICE'],
                'QUANTITY' => $arOrder['QUANTITY'],
            );
        }
    }
}
