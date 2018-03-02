<?
use Bitrix\Sale\Delivery;
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();
/** @var array $arCurrentValues */
/** @global CUserTypeManager $USER_FIELD_MANAGER */
global $USER_FIELD_MANAGER;
$use_old_class = "N";
if(!\Bitrix\Main\Loader::includeModule("iblock") || !\Bitrix\Main\Loader::includeModule("sale"))
	return;

$boolCatalog = \Bitrix\Main\Loader::includeModule("catalog");

$arIBlockType = CIBlockParameters::GetIBlockTypes();

$arIBlock=array();
$rsIBlock = CIBlock::GetList(array("sort" => "asc"), array("TYPE" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE"=>"Y"));
while($arr=$rsIBlock->Fetch())
{
	$arIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];
}
$arSorts = array("ASC"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_ASC"), "DESC"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_DESC"));
$arSortFields = array(
	"ID"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_FID"),
	"NAME"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_FNAME"),
	"ACTIVE_FROM"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_FACT"),
	"SORT"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_FSORT"),
	"TIMESTAMP_X"=>GetMessage("H2O_BUYONECLICK_OFFERS_SORT_FTSAMP")
);
$arProperty = array();
$arProperty_N = array();
$arProperty_X = array();
if (0 < intval($arCurrentValues["IBLOCK_ID"]))
{
	$rsProp = CIBlockProperty::GetList(array("sort"=>"asc", "name"=>"asc"), array("IBLOCK_ID"=>$arCurrentValues["IBLOCK_ID"], "ACTIVE"=>"Y"));
	while ($arr=$rsProp->Fetch())
	{
		if($arr["PROPERTY_TYPE"] != "F")
			$arProperty[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];

		if($arr["PROPERTY_TYPE"] == "N")
			$arProperty_N[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];

		if ($arr["PROPERTY_TYPE"] != "F")
		{
			if($arr["MULTIPLE"] == "Y")
				$arProperty_X[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
			elseif($arr["PROPERTY_TYPE"] == "L")
				$arProperty_X[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
			elseif($arr["PROPERTY_TYPE"] == "E" && $arr["LINK_IBLOCK_ID"] > 0)
				$arProperty_X[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
		}
	}
}
$arProperty_LNS = $arProperty;

$arIBlock_LINK = array();
$rsIblock = CIBlock::GetList(array("sort" => "asc"), array("TYPE" => $arCurrentValues["LINK_IBLOCK_TYPE"], "ACTIVE"=>"Y"));
while($arr=$rsIblock->Fetch())
	$arIBlock_LINK[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];

$arProperty_LINK = array();
if (0 < intval($arCurrentValues["LINK_IBLOCK_ID"]))
{
	$rsProp = CIBlockProperty::GetList(array("sort"=>"asc", "name"=>"asc"), array("IBLOCK_ID"=>$arCurrentValues["LINK_IBLOCK_ID"], 'PROPERTY_TYPE' => 'E', "ACTIVE"=>"Y"));
	while ($arr=$rsProp->Fetch())
	{
		$arProperty_LINK[$arr["CODE"]] = "[".$arr["CODE"]."] ".$arr["NAME"];
	}
}

$arUserFields_S = array("-"=>" ");
$arUserFields = $USER_FIELD_MANAGER->GetUserFields("IBLOCK_".$arCurrentValues["IBLOCK_ID"]."_SECTION");
foreach($arUserFields as $FIELD_NAME=>$arUserField)
	if($arUserField["USER_TYPE"]["BASE_TYPE"]=="string")
		$arUserFields_S[$FIELD_NAME] = $arUserField["LIST_COLUMN_LABEL"]? $arUserField["LIST_COLUMN_LABEL"]: $FIELD_NAME;

$arOffers = CIBlockPriceTools::GetOffersIBlock($arCurrentValues["IBLOCK_ID"]);
$OFFERS_IBLOCK_ID = is_array($arOffers)? $arOffers["OFFERS_IBLOCK_ID"]: 0;
$arProperty_Offers = array();
$arProperty_OffersWithoutFile = array();
if($OFFERS_IBLOCK_ID)
{
	$rsProp = CIBlockProperty::GetList(array("sort"=>"asc", "name"=>"asc"), array("IBLOCK_ID"=>$OFFERS_IBLOCK_ID, "ACTIVE"=>"Y"));
	while($arr=$rsProp->Fetch())
	{
		$arr['ID'] = intval($arr['ID']);
		if ($arOffers['OFFERS_PROPERTY_ID'] == $arr['ID'])
			continue;
		$strPropName = '['.$arr['ID'].']'.('' != $arr['CODE'] ? '['.$arr['CODE'].']' : '').' '.$arr['NAME'];
		if ('' == $arr['CODE'])
			$arr['CODE'] = $arr['ID'];
		$arProperty_Offers[$arr["CODE"]] = $strPropName;
		if ('F' != $arr['PROPERTY_TYPE'])
			$arProperty_OffersWithoutFile[$arr["CODE"]] = $strPropName;
	}
}

$arSort = CIBlockParameters::GetElementSortFields(
	array('SHOWS', 'SORT', 'TIMESTAMP_X', 'NAME', 'ID', 'ACTIVE_FROM', 'ACTIVE_TO'),
	array('KEY_LOWERCASE' => 'Y')
);

$arPrice = array();
if ($boolCatalog)
{
	$rsPrice=CCatalogGroup::GetList($v1="sort", $v2="asc");
	while($arr=$rsPrice->Fetch()) $arPrice[$arr["NAME"]] = "[".$arr["NAME"]."] ".$arr["NAME_LANG"];
}
else
{
	$arPrice = $arProperty_N;
}

$arAscDesc = array(
	"asc" => GetMessage("IBLOCK_SORT_ASC"),
	"desc" => GetMessage("IBLOCK_SORT_DESC"),
);


//$site = ($_REQUEST["site"] <> ''? $_REQUEST["site"] : ($_REQUEST["src_site"] <> ''? $_REQUEST["src_site"] : false));
$arFilter = Array( "ACTIVE" => "Y");
//if($site !== false)
//	$arFilter["LID"] = $site;

$arEvent = Array();
$arFilterEvent = $arFilter;
$arFilterEvent['TYPE_ID'] = "SALE_NEW_ORDER";
$dbType = CEventMessage::GetList($by="ID", $order="DESC", $arFilterEvent);
while($arType = $dbType->GetNext())
	$arEvent[$arType["ID"]] = "[".$arType["ID"]."] ".$arType["SUBJECT"];

$arEventReq = Array();
$arFilterEvent['TYPE_ID'] = "NEW_USER";
$dbTypeReq = CEventMessage::GetList($by="ID", $order="DESC", $arFilterEvent);
while($arTypeReq = $dbTypeReq->GetNext())
	$arEventReq[$arTypeReq["ID"]] = "[".$arTypeReq["ID"]."] ".$arTypeReq["SUBJECT"];

$PSpersonType = array();
$dbPersonType = CSalePersonType::GetList(Array("ID" => "ASC", "NAME" => "ASC"), Array("ACTIVE" => "Y"));

while($arPersonType = $dbPersonType->GetNext())
{
	$PSpersonType[$arPersonType["ID"]] = "[".$arPersonType["ID"]."] ".$arPersonType["NAME"];
}


$db_ptype = CSalePaySystem::GetList($arOrder = Array("ID"=>"ASC", "PSA_NAME"=>"ASC"), Array("ACTIVE"=>"Y", "PERSON_TYPE_ID"=>isset($arCurrentValues['PERSON_TYPE_ID'])?$arCurrentValues['PERSON_TYPE_ID']:key($PSpersonType)));
$arPaySystem = array();
while ($ptype = $db_ptype->Fetch())
{
	$arPaySystem[$ptype['ID']] = "[".$ptype["ID"]."] ".$ptype['NAME'];
}

if(class_exists("Bitrix\Sale\Delivery\Services\Manager") &&
	method_exists("Bitrix\Sale\Delivery\Services\Manager", "getActiveList") &&
	class_exists("Bitrix\Sale\Delivery\Services\Table")){
	$arDeliveryActive = Delivery\Services\Manager::getActiveList();
	$arDelivery = array();
	foreach($arDeliveryActive as $delivery){
		$arDelivery[$delivery['ID']] = "[" . $delivery["ID"] . "] " . $delivery['NAME'];
	}
}else{
	$use_old_class = 'Y';
	$db_dtype = CSaleDelivery::GetList($arOrder = Array("ID"=>"ASC"),array("ACTIVE" => "Y", "PERSON_TYPE_ID"=>isset($arCurrentValues['PERSON_TYPE_ID'])?$arCurrentValues['PERSON_TYPE_ID']:key($PSpersonType)));
	$arDelivery = array();
	while ($ar_dtype = $db_dtype->Fetch())
	{
		$arDelivery[$ar_dtype['ID']] = "[".$ar_dtype["ID"]."] ".$ar_dtype['NAME'];
	}
}

$db_props = CSaleOrderProps::GetList(array("ID" => "ASC"),array("PERSON_TYPE_ID" => isset($arCurrentValues['PERSON_TYPE_ID'])?$arCurrentValues['PERSON_TYPE_ID']:key($PSpersonType)));
$arPropsOrder = array();
while ($props = $db_props->Fetch()){
	$arPropsOrder[$props['ID']] = "[".$props["ID"]."] ".$props['NAME'];
}
$rsGroups = CGroup::GetList($by = "c_sort", $order = "asc", array());
$arUsersGroups = array();
if(intval($rsGroups->SelectedRowsCount()) > 0)
{
	while($arGroups = $rsGroups->Fetch())
	{
		$arUsersGroups[$arGroups['ID']] = "[".$arGroups["ID"]."] ".$arGroups['NAME'];
	}
}
$arComponentParameters = array(
	"GROUPS" => array(
		"MODE" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_MODE"),
		),
		"SELECT_FIELD" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_SELECT_FIELD"),
		),
		"USER_SETTINGS" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_USER_SETTINGS"),
		),
		"PRICES_SETTINGS" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_PRICES_SETTINGS"),
		),
		"SEND_SETTINGS" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_SEND_SETTINGS"),
		),
		"LIST_SETTINGS" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_LIST_SETTINGS"),
		),
		"MORE" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_MORE_SETTINGS"),
		)




	),
	"PARAMETERS" => array(
		"USER_CONSENT" => array(),
		"IBLOCK_TYPE" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("IBLOCK_TYPE"),
			"TYPE" => "LIST",
			"VALUES" => $arIBlockType,
			"REFRESH" => "Y",
		),
		"IBLOCK_ID" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("IBLOCK_IBLOCK"),
			"TYPE" => "LIST",
			"ADDITIONAL_VALUES" => "Y",
			"VALUES" => $arIBlock,
			"REFRESH" => "Y",
		),
		"CACHE_TIME"  =>  array("DEFAULT"=>86400),
		/**
		 * Параметр не обязателен, путает пользователей
		 * ид элемента передается через атрибут кнопки data-id
		 */
		/*"ELEMENT_ID" => array(
			"PARENT" => "BASE",
			"NAME" => GetMessage("H2O_BUYONECLICK_ELEMENT_ID"),
			"TYPE" => "STRING",
			"DEFAULT" => '={$_REQUEST["ELEMENT_ID"]}',
		),*/
		"MODE_EXTENDED" => array(
			"PARENT" => "MODE",
			"NAME" => GetMessage("H2O_BUYONECLICK_MODE_EXTENDED"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
			"REFRESH" => "Y",
		),
		"USER_DATA_FIELDS" => Array(
			"NAME" => GetMessage("H2O_BUYONECLICK_USER_DATA_FIELDS"),
			"TYPE"=>"LIST",
			"VALUES" => array(
				"NAME" => GetMessage("H2O_BUYONECLICK_USER_DATA_NAME"),
				"EMAIL" => GetMessage("H2O_BUYONECLICK_USER_DATA_EMAIL"),
				"PERSONAL_PHONE" => GetMessage("H2O_BUYONECLICK_USER_DATA_PHONE"),
			),
			"DEFAULT"=>array(
				"NAME",
				"EMAIL",
				"PERSONAL_PHONE",
			),
			"MULTIPLE"=>"Y",
			"COLS"=>25,
			"PARENT" => "SELECT_FIELD",
		),
		"USER_DATA_FIELDS_REQUIRED" => Array(
			"NAME" => GetMessage("H2O_BUYONECLICK_USER_DATA_FIELDS_REQUIRED"),
			"TYPE"=>"LIST",
			"VALUES" => array(
				"NAME" => GetMessage("H2O_BUYONECLICK_USER_DATA_NAME"),
				"EMAIL" => GetMessage("H2O_BUYONECLICK_USER_DATA_EMAIL"),
				"PERSONAL_PHONE" => GetMessage("H2O_BUYONECLICK_USER_DATA_PHONE"),
			),
			"DEFAULT"=>array(
				"NAME",
				"EMAIL",
				"PERSONAL_PHONE",
			),
			"MULTIPLE"=>"Y",
			"COLS"=>25,
			"PARENT" => "SELECT_FIELD",
		),
		'SHOW_PROPERTIES' => array(
			"PARENT" => "SELECT_FIELD",
			"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_PROPERTIES"),
			"TYPE" => "LIST",
			"MULTIPLE" => "Y",
			"VALUES" => $arPropsOrder,
		),
		'SHOW_PROPERTIES_REQUIRED' => array(
			"PARENT" => "SELECT_FIELD",
			"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_PROPERTIES_REQUIRED"),
			"TYPE" => "LIST",
			"MULTIPLE" => "Y",
			"VALUES" => $arPropsOrder,
		),
		'SHOW_USER_DESCRIPTION' => array(
			"PARENT" => "SELECT_FIELD",
			"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_USER_DESCRIPTION"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		),
		'SHOW_OFFERS_FIRST_STEP' => array(
			"PARENT" => "SELECT_FIELD",
			"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_OFFERS_FIRST_STEP"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "Y",
		),
		"SEND_MAIL" => array(
			"PARENT" => "SEND_SETTINGS",
			"NAME" => GetMessage("H2O_BUYONECLICK_SEND_MAIL"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
			"REFRESH" => "Y",
		),

		"SEND_MAIL_REQ" => array(
			"PARENT" => "SEND_SETTINGS",
			"NAME" => GetMessage("H2O_BUYONECLICK_SEND_MAIL_REQ"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
			"REFRESH" => "Y",
		),

		"USE_CAPTCHA" => Array(
			"NAME" => GetMessage("H2O_BUYONECLICK_CAPTCHA"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
			"PARENT" => "BASE",
		),

		"NEW_USER_GROUP_ID" => array(
			"NAME" => GetMessage("H2O_BUYONECLICK_NEW_USER_GROUP_ID"),
			"TYPE" => "LIST",
			"VALUES" => $arUsersGroups,
			"PARENT" => "MORE",
			"MULTIPLE" => "Y",
		),

		"ALLOW_ORDER_FOR_EXISTING_EMAIL" => array(
			"PARENT" => "MORE",
			"NAME" => GetMessage("H2O_BUYONECLICK_ALLOW_ORDER_FOR_EXISTING_EMAIL"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		),

		/*"GENERATE_EMAIL_FROM_PHONE" => array(
			"PARENT" => "MORE",
			"NAME" => GetMessage("H2O_BUYONECLICK_GENERATE_EMAIL_FROM_PHONE"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		),*/

		"NOT_AUTHORIZE_USER" => array(
			"PARENT" => "MORE",
			"NAME" => GetMessage("H2O_BUYONECLICK_NOT_AUTHORIZE_USER"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => "N",
		),
		
		

		"USE_OLD_CLASS" => array(
			"PARENT" => "MORE",
			"NAME" => GetMessage("H2O_BUYONECLICK_USE_OLD_CLASS"),
			"TYPE" => "CHECKBOX",
			"DEFAULT" => $use_old_class,
		),

	),
);
if($arCurrentValues["SEND_MAIL"] == "Y"){
	$arComponentParameters["PARAMETERS"]["EVENT_MESSAGE_ID"] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_EMAIL_TEMPLATES"),
		"TYPE"=>"LIST",
		"VALUES" => $arEvent,
		"DEFAULT"=>"",
		"MULTIPLE"=>"Y",
		"COLS"=>25,
		"PARENT" => "SEND_SETTINGS",
	);
}
if($arCurrentValues["SEND_MAIL_REQ"] == "Y"){
	$arComponentParameters["PARAMETERS"]["EVENT_MESSAGE_ID_REQ"] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_EMAIL_TEMPLATES_REQ"),
		"TYPE"=>"LIST",
		"VALUES" => $arEventReq,
		"DEFAULT"=>"",
		"MULTIPLE"=>"Y",
		"COLS"=>25,
		"PARENT" => "SEND_SETTINGS",
	);
}
if($arCurrentValues["MODE_EXTENDED"] == "Y"){
	$arComponentParameters["PARAMETERS"]['SHOW_QUANTITY'] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_QUANTITY"),
		"TYPE" => "CHECKBOX",
		"DEFAULT" => "Y",
		"PARENT" => "SELECT_FIELD",
	);
	$arComponentParameters["PARAMETERS"]['PERSON_TYPE_ID'] = array(
		"PARENT" => "SELECT_FIELD",
		"NAME" => GetMessage("H2O_BUYONECLICK_PERSON_TYPE_ID"),
		"TYPE" => "LIST",
		"VALUES" => $PSpersonType,
		"REFRESH" => "Y",
	);

	
	
	$arComponentParameters["PARAMETERS"]["PATH_TO_PAYMENT"] = array(
			"PARENT" => "SELECT_FIELD",
			"NAME" => GetMessage("H2O_BUYONECLICK_PATH_TO_PAYMENT"),
			"TYPE" => "STRING",
			"DEFAULT" => '/personal/order/payment/',
	);
	
	$arComponentParameters["PARAMETERS"]["SUCCESS_HEAD_MESS"] = array(
		"PARENT" => "MORE",
		"NAME" => GetMessage("H2O_BUYONECLICK_SUCCESS_HEAD_MESS"),
		"TYPE" => "STRING",
		"DEFAULT" => GetMessage("H2O_BUYONECLICK_SUCCESS_HEAD_MESS_VAL"),
	);
	
	$arComponentParameters["PARAMETERS"]["SUCCESS_ADD_MESS"] = array(
		"PARENT" => "MORE",
		"NAME" => GetMessage("H2O_BUYONECLICK_SUCCESS_ADD_MESS"),
		"TYPE" => "STRING",
		"DEFAULT" => GetMessage("H2O_BUYONECLICK_SUCCESS_ADD_MESS_VAL"),
	);

	


	$arComponentParameters["PARAMETERS"]['PRICE_CODE'] = array(
		"PARENT" => "PRICES_SETTINGS",
		"NAME" => GetMessage("H2O_BUYONECLICK_PRICE_CODE"),
		"TYPE" => "LIST",
		"MULTIPLE" => "Y",
		"VALUES" => $arPrice,
	);

	//if (!empty($arOffers)){
	//$arComponentParameters["PARAMETERS"]["LIST_OFFERS_FIELD_CODE"] = CIBlockParameters::GetFieldCode(GetMessage("CP_BC_LIST_OFFERS_FIELD_CODE"), "LIST_SETTINGS");
	$arComponentParameters["PARAMETERS"]["LIST_OFFERS_PROPERTY_CODE"] = array(
		"PARENT" => "LIST_SETTINGS",
		"NAME" => GetMessage("H2O_LIST_OFFERS_PROPERTY_CODE"),
		"TYPE" => "LIST",
		"MULTIPLE" => "Y",
		"VALUES" => $arProperty_Offers,
		"ADDITIONAL_VALUES" => "Y",
	);
	$arComponentParameters['PARAMETERS']['OFFERS_SORT_BY'] = array(
		"PARENT" => "LIST_SETTINGS",
		"NAME" => GetMessage("H2O_BUYONECLICK_OFFERS_SORT_IBORD1"),
		"TYPE" => "LIST",
		"DEFAULT" => "ACTIVE_FROM",
		"VALUES" => $arSortFields,
		"ADDITIONAL_VALUES" => "Y",
	);
	$arComponentParameters['PARAMETERS']['OFFERS_SORT_ORDER'] = array(
		"PARENT" => "LIST_SETTINGS",
		"NAME" => GetMessage("H2O_BUYONECLICK_OFFERS_SORT_IBBY1"),
		"TYPE" => "LIST",
		"DEFAULT" => "DESC",
		"VALUES" => $arSorts,
		"ADDITIONAL_VALUES" => "Y",
	);
	//}

	$arComponentParameters["PARAMETERS"]['BUY_CURRENT_BASKET'] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_BUY_CURRENT_BASKET"),
		"TYPE" => "CHECKBOX",
		"DEFAULT" => "N",
		"PARENT" => "MORE",
		"REFRESH" => "N",
	);
	
	$arComponentParameters["PARAMETERS"]["ADD_NOT_AUTH_TO_ONE_USER"] = array(
		"PARENT" => "MORE",
		"NAME" => GetMessage("H2O_BUYONECLICK_ADD_NOT_AUTH_TO_ONE_USER"),
		"TYPE" => "CHECKBOX",
		"DEFAULT" => "N",
		"REFRESH" => "Y"
	);
	
	$arComponentParameters["PARAMETERS"]['SHOW_PAY_SYSTEM'] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_PAY_SYSTEM"),
		"TYPE" => "CHECKBOX",
		"DEFAULT" => "Y",
		"PARENT" => "SELECT_FIELD",
		"REFRESH" => "Y",
	);
	$arComponentParameters["PARAMETERS"]['SHOW_DELIVERY'] = Array(
		"NAME" => GetMessage("H2O_BUYONECLICK_SHOW_DELIVERY"),
		"TYPE" => "CHECKBOX",
		"DEFAULT" => "Y",
		"PARENT" => "SELECT_FIELD",
		"REFRESH" => "Y",
	);
	
	
	
}
if($arCurrentValues['SHOW_PAY_SYSTEM'] == 'Y'){
	$arComponentParameters["PARAMETERS"]['PAY_SYSTEMS'] = array(
		"PARENT" => "SELECT_FIELD",
		"NAME" => GetMessage("H2O_BUYONECLICK_PAY_SYSTEMS"),
		"TYPE" => "LIST",
		"MULTIPLE" => "Y",
		"VALUES" => $arPaySystem,
	);
}else{
	$arComponentParameters["PARAMETERS"]["DEFAULT_PAY_SYSTEM"] = array(
		"PARENT" => "SELECT_FIELD",
		"NAME" => GetMessage("H2O_BUYONECLICK_DEFAULT_PAY_SYSTEM"),
		"TYPE" => "LIST",
		"VALUES" => $arPaySystem,
	
	);
}
if($arCurrentValues['SHOW_DELIVERY'] == 'Y'){
	$arComponentParameters["PARAMETERS"]['DELIVERY'] = array(
		"PARENT" => "SELECT_FIELD",
		"NAME" => GetMessage("H2O_BUYONECLICK_DELIVERY"),
		"TYPE" => "LIST",
		"MULTIPLE" => "Y",
		"VALUES" => $arDelivery,
	);
}else{
	$arComponentParameters["PARAMETERS"]["DEFAULT_DELIVERY"] = array(
		"PARENT" => "SELECT_FIELD",
		"NAME" => GetMessage("H2O_BUYONECLICK_DEFAULT_DELIVERY"),
		"TYPE" => "LIST",
		"VALUES" => $arDelivery,
	);
}
if($arCurrentValues["ADD_NOT_AUTH_TO_ONE_USER"] == "Y"){
	$arComponentParameters["PARAMETERS"]["ADD_NOT_AUTH_TO_ONE_USER_ID"] = array(
		"PARENT" => "MORE",
		"NAME" => GetMessage("H2O_BUYONECLICK_ADD_NOT_AUTH_TO_ONE_USER_ID"),
		"TYPE" => "STRING",
	);
}
?>