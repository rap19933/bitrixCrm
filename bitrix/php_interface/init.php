<?php
function data($arr, $params=false){
    if($params){
        $str = 'class="prered"';
        $strTime = 'class="timParams"';
    } else {
        $str = 'class="preblack"';
        $strTime = 'class="tim"';
    }

    echo "<style type=\"text/css\">
                div.tim {font-size:12px; color: blue;}
                div.timParams {font-size:12px; color: green;}
                div.prered {font-size:10px; color: red;}
                div.preblack {font-size:10px;  text-align: left;}
          </style>";
    echo "<div $strTime>";
    echo date("H:i:s"). substr((string)microtime(), 1, 6)."</br>";
    echo "</div>";

    echo "<div $str>";
    echo "<pre>";
    print_r($arr);
    echo "</pre>";
    echo "</div>";
    echo "<br>";
}


function retailCrmBeforeOrderSend($order, $arFields)
{

    if (empty($order['items'])) {
        return false;
    }
    if (empty($order['delivery']['address']['text'])) {
        $request = Bitrix\Main\Application::getInstance()->getContext()->getRequest();
        $value = $request->getPost("ONECLICK_PROP");
        if (empty($value[2])) {
            $order['orderMethod'] = 'back-call';
        } else {
            $order['orderMethod'] = 'one-click';
        }
    }


    $key = array_search('preliminary_call', array_column($arFields['PROPS']['properties'], 'CODE'));
    if ($arFields['PROPS']['properties'][$key]['VALUE'][0] == 'Y') {
        $order['call'] = true;
    } else {
        $order['call'] = false;
    }

    $key = array_search('delivery_date', array_column($arFields['PROPS']['properties'], 'CODE'));
    $order['delivery']['date'] = $arFields['PROPS']['properties'][$key]['VALUE'][0];

    $key = array_search('delivery_time', array_column($arFields['PROPS']['properties'], 'CODE'));
    $selectKey = $arFields['PROPS']['properties'][$key]['VALUE'][0];
    $order['delivery']['time']['custom'] = $arFields['PROPS']['properties'][$key]['OPTIONS'][$selectKey];

    $arIdProduct = array();
    foreach ($arFields['BASKET'] as $item) {
        $arIdProduct[$item['PRODUCT_ID']] = CCatalogSku::GetProductInfo($item['PRODUCT_ID'])['ID'];
    }

    if (CModule::IncludeModule('catalog')) {
        $dbProducts = CIBlockElement::GetList(
            [],
            ['ID' => $arIdProduct, 'IBLOCK_ID' => 2],
            false,
            false,
            ['ID', 'IBLOCK_ID', 'PROPERTY_FIRST_PROP_ORDER', 'PROPERTY_SECOND_PROP_ORDER']
        );
        $arPropProduct = array();
        while ($dbProduct = $dbProducts->GetNext()) {
            $arPropProduct[array_search($dbProduct['ID'], $arIdProduct)] = array(
                'FIRST_PROP_ORDER'  => $dbProduct['PROPERTY_FIRST_PROP_ORDER_VALUE'],
                'SECOND_PROP_ORDER' => $dbProduct['PROPERTY_SECOND_PROP_ORDER_VALUE'],
            );
        }
        foreach ($order['items'] as &$item) {
            $prop = array();
            if ($arPropProduct[$item['offer']['externalId']]['FIRST_PROP_ORDER']) {
                $prop[] = array(
                    'name'  => 'Первое свойство заказа',
                    'value' => $arPropProduct[$item['offer']['externalId']]['FIRST_PROP_ORDER'],
                );
            }
            if ($arPropProduct[$item['offer']['externalId']]['SECOND_PROP_ORDER']) {
                $prop[] = array(
                    'name'  => 'Второе свойство заказа',
                    'value' => $arPropProduct[$item['offer']['externalId']]['SECOND_PROP_ORDER'],
                );
            }

            $item['properties'] = $prop;
        }
    }


/*    data(\RetailCrmInventories::inventoriesUpload());
    //data($arFields);
    die;*/
    return $order;
}
