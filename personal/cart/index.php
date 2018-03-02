<?
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetTitle("Корзина");
?><?$APPLICATION->IncludeComponent(
	"bitrix:sale.basket.basket",
	"",
	Array(
		"AJAX_MODE" => "N",
		"AJAX_OPTION_ADDITIONAL" => "",
		"AJAX_OPTION_HISTORY" => "N",
		"AJAX_OPTION_JUMP" => "N",
		"AJAX_OPTION_STYLE" => "Y",
		"COLUMNS_LIST" => array(0=>"NAME",1=>"DISCOUNT",2=>"PRICE",3=>"QUANTITY",4=>"SUM",5=>"PROPS",6=>"DELETE",7=>"DELAY",),
		"COUNT_DISCOUNT_4_ALL_QUANTITY" => "N",
		"HIDE_COUPON" => "N",
		"OFFERS_PROPS" => array(0=>"SIZES_SHOES",1=>"SIZES_CLOTHES",2=>"COLOR_REF",),
		"PATH_TO_ORDER" => "/personal/order/make/",
		"PRICE_VAT_SHOW_VALUE" => "Y",
		"QUANTITY_FLOAT" => "N",
		"SET_TITLE" => "Y",
		"TEMPLATE_THEME" => "site"
	)
);?><br>
 <?$APPLICATION->IncludeComponent(
	"h2o:buyoneclick", 
	".default", 
	array(
		"ADD_NOT_AUTH_TO_ONE_USER" => "Y",
		"ADD_NOT_AUTH_TO_ONE_USER_ID" => "1",
		"ALLOW_ORDER_FOR_EXISTING_EMAIL" => "N",
		"BUY_CURRENT_BASKET" => "Y",
		"CACHE_TIME" => "86400",
		"CACHE_TYPE" => "N",
		"COMPONENT_TEMPLATE" => ".default",
		"DEFAULT_DELIVERY" => "1",
		"DEFAULT_PAY_SYSTEM" => "1",
		"DELIVERY" => array(
		),
		"IBLOCK_ID" => "2",
		"IBLOCK_TYPE" => "catalog",
		"ID_FIELD_PHONE" => array(
		),
		"LIST_OFFERS_PROPERTY_CODE" => array(
			0 => "SIZES_CLOTHES",
			1 => "",
		),
		"MASK_PHONE" => "(999) 999-9999",
		"MODE_EXTENDED" => "Y",
		"NEW_USER_GROUP_ID" => array(
		),
		"NOT_AUTHORIZE_USER" => "N",
		"OFFERS_SORT_BY" => "ACTIVE_FROM",
		"OFFERS_SORT_ORDER" => "DESC",
		"PATH_TO_PAYMENT" => "/personal/order/payment/",
		"PAY_SYSTEMS" => "",
		"PERSON_TYPE_ID" => "1",
		"PRICE_CODE" => array(
			0 => "BASE",
		),
		"SEND_MAIL" => "N",
		"SEND_MAIL_REQ" => "N",
		"SHOW_DELIVERY" => "N",
		"SHOW_OFFERS_FIRST_STEP" => "N",
		"SHOW_PAY_SYSTEM" => "N",
		"SHOW_PROPERTIES" => array(
		),
		"SHOW_PROPERTIES_REQUIRED" => array(
		),
		"SHOW_QUANTITY" => "N",
		"SHOW_USER_DESCRIPTION" => "N",
		"SUCCESS_ADD_MESS" => "Вы успешно оформили заказ №#ORDER_ID#!",
		"SUCCESS_HEAD_MESS" => "Поздравляем!",
		"USER_CONSENT" => "N",
		"USER_CONSENT_ID" => "0",
		"USER_CONSENT_IS_CHECKED" => "Y",
		"USER_CONSENT_IS_LOADED" => "N",
		"USER_DATA_FIELDS" => array(
			0 => "NAME",
			1 => "EMAIL",
			2 => "PERSONAL_PHONE",
		),
		"USER_DATA_FIELDS_REQUIRED" => array(
			0 => "NAME",
			1 => "EMAIL",
			2 => "PERSONAL_PHONE",
		),
		"USE_CAPTCHA" => "N",
		"USE_OLD_CLASS" => "N"
	),
	false
);?><br><?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>