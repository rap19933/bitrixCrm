<? if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true)
	die();
use Bitrix\Main;
use Bitrix\Sale;
use Bitrix\Main\Config;
use Bitrix\Main\Data;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale\Delivery;
use Bitrix\Catalog\Product\Sku;
use Bitrix\Main\Application;
use Bitrix\Sale\Location\LocationTable;

Loc::loadMessages(__FILE__);

class BuyOneClick extends CBitrixComponent{
	
	protected $element_id;
	protected $requestData = array();
	protected $requestDataD7;
	protected $propertyList;
	protected $ajax_id;
	protected $userAuth;
	protected $userID;
	protected $show_captha;
	protected $arOffers;
	protected $currentCache = null;
	protected $templateName = "";
	protected $needGetData = false;
	/**
	 * Fatal error list. Any fatal error makes useless further execution of a component code.
	 * In most cases, there will be only one error in a list according to the scheme "one shot - one dead body"
	 *
	 * @var string[] Array of fatal errors.
	 */
	protected $errorsFatal = array();
	
	/**
	 * Non-fatal error list. Some non-fatal errors may occur during component execution, so certain functions of the component
	 * may became defunct. Still, user should stay informed.
	 * There may be several non-fatal errors in a list.
	 *
	 * @var string[] Array of non-fatal errors.
	 */
	protected $errorsNonFatal = array();
	
	
	
	public function onPrepareComponentParams($arParams){
		global $USER;
		// common
		$arParams['SHOW_DELIVERY'] = ($arParams['SHOW_DELIVERY'] == 'Y' ? true : false);
		$arParams['SHOW_PAY_SYSTEM'] = ($arParams['SHOW_PAY_SYSTEM'] == 'Y' ? true : false);
		$arParams['SHOW_USER_DESCRIPTION'] = ($arParams['SHOW_USER_DESCRIPTION'] == 'Y' ? true : false);
		$arParams['CREATE_NEW_USER'] = ($arParams['CREATE_NEW_USER'] == 'Y' ? true : false);
		$arParams['SEND_MAIL'] = ($arParams['SEND_MAIL'] == 'Y' ? true : false);
		$arParams['SEND_MAIL_REQ'] = ($arParams['SEND_MAIL_REQ'] == 'Y' ? true : false);
		$arParams['SHOW_QUANTITY'] = ($arParams['SHOW_QUANTITY'] == 'Y' ? true : false);
		$arParams['BUY_CURRENT_BASKET'] = ($arParams['BUY_CURRENT_BASKET'] == 'Y' ? true : false);
		$arParams['PATH_TO_PAYMENT'] = strlen($arParams['PATH_TO_PAYMENT'])>0?$arParams['PATH_TO_PAYMENT']:"/personal/order/payment/";
		$arParams['NOT_AUTHORIZE_USER'] = ($arParams['NOT_AUTHORIZE_USER'] == 'Y' ? true : false);
		$arParams['ALLOW_ORDER_FOR_EXISTING_EMAIL'] = ($arParams['ALLOW_ORDER_FOR_EXISTING_EMAIL'] == 'Y' ? true : false);
		$arParams['ADD_NOT_AUTH_TO_ONE_USER'] = ($arParams['ADD_NOT_AUTH_TO_ONE_USER'] == 'Y' ? true : false);
		$arParams['ADD_NOT_AUTH_TO_ONE_USER_ID'] = intval($arParams['ADD_NOT_AUTH_TO_ONE_USER_ID']);
		$arParams['OFFERS_SORT_BY'] =
		$arParams["OFFERS_SORT_BY"] = trim($arParams["OFFERS_SORT_BY"]);
		$arParams["OFFERS_SORT_ORDER"] = trim($arParams["OFFERS_SORT_ORDER"]);
		$arParams["MASK_PHONE"] = trim($arParams["MASK_PHONE"]);
		if($arParams['MASK_PHONE'] == ""){
			$arParams['MASK_PHONE'] = "(999) 999-9999";
		}
		if(strlen($arParams["OFFERS_SORT_BY"])<=0)
			$arParams["OFFERS_SORT_BY"] = "SORT";
		if(!preg_match('/^(asc|desc|nulls)(,asc|,desc|,nulls){0,1}$/i', $arParams["OFFERS_SORT_ORDER"]))
			$arParams["OFFERS_SORT_ORDER"] = "ASC";


		if(!class_exists("Bitrix\Sale\Delivery\Services\Manager") ||
			!method_exists("Bitrix\Sale\Delivery\Services\Manager", "getActiveList") ||
			!class_exists("Bitrix\Sale\Delivery\Services\Table")){
			$arParams['USE_OLD_CLASS'] = 'Y';
		}
		
		return $arParams;
	}
	
	
	/** new component function  */
	
	protected function prepareData(){
		global $APPLICATION;
		
		$this->arResult = array();
		if($this->needGetData){
			$this->arResult['PAY_SYSTEM'] = $this->getPaySystem();
			$this->arResult['DELIVERY'] = $this->getDelivery();
			$this->arResult['USER_FIELDS'] = $this->getUserFields();
			$this->arResult['USER_FIELDS_REQUIRED'] = $this->getUserFieldsRequire();
			$this->arResult['SHOW_PROPERTIES'] = $this->getOrderProps();
			$this->arResult['SHOW_PROPERTIES_REQUIRED'] = $this->getOrderPropsRequire();
			$this->arResult['CURRENT_USER_FIELDS'] = $this->getCurrentUser();
			$this->arResult['CURRENT_USER_PROPS'] = $this->getCurrentUserProps();
			$this->arResult['POST'] = $this->requestData;//->getPostList();
			
			
			if(!empty($this->requestData['H2O_B1C_OFFER_ID'])){ //H2O_B1C_ - для обхода конфликтов с другими компонентами
				$this->element_id = $this->requestData['H2O_B1C_OFFER_ID'];
			}
			elseif(intval($this->requestData['H2O_B1C_ELEMENT_ID']) > 0){  //H2O_B1C_ - для обхода конфликтов с другими компонентами
				$this->element_id = $this->requestData['H2O_B1C_ELEMENT_ID'];
			}
			elseif(!empty($this->requestData['OFFER_ID'])){    //для старых шаблонов
				$this->element_id = $this->requestData['OFFER_ID'];
			}
			elseif(intval($this->requestData['ELEMENT_ID']) > 0){  //для старых шаблонов
				$this->element_id = $this->requestData['ELEMENT_ID'];
			}
			else{
				$this->element_id = $this->arParams['ELEMENT_ID'];
			}
			
			$this->arResult['ELEMENT_ID'] = $this->element_id;
			if($this->arParams["SHOW_OFFERS_FIRST_STEP"] == 'Y'){
				if(intval($this->requestData['H2O_B1C_ELEMENT_ID']) > 0){
					$this->arResult['OFFERS'] = $this->getOffers(intval($this->requestData['H2O_B1C_ELEMENT_ID']));
					$this->arResult['ELEMENT_ID'] = intval($this->requestData['H2O_B1C_ELEMENT_ID']);
					$this->arResult['CURRENT_OFFER_ID'] = intval($this->requestData['H2O_B1C_OFFER_ID']);
				}
				elseif(intval($this->requestData['ELEMENT_ID']) > 0){
					$this->arResult['OFFERS'] = $this->getOffers(intval($this->requestData['ELEMENT_ID']));
					$this->arResult['ELEMENT_ID'] = intval($this->requestData['ELEMENT_ID']);
					$this->arResult['CURRENT_OFFER_ID'] = intval($this->requestData['OFFER_ID']);
				}
				else{
					$this->arResult['OFFERS'] = $this->getOffers(intval($this->arParams['ELEMENT_ID']));
					$this->arResult['ELEMENT_ID'] = intval($this->arParams['ELEMENT_ID']);
					
				}
			}
			if(intval($this->arResult['CURRENT_OFFER_ID']) <= 0 && self::isNonemptyArray($this->arResult['OFFERS'])){
				reset($this->arResult['OFFERS']);
				$key = key($this->arResult['OFFERS']);
				$this->arResult['CURRENT_OFFER_ID'] = $this->arResult['OFFERS'][$key]['ID'];
			}
			
			$this->arResult['QUANTITY'] = (floatval($this->requestData['quantity_b1c']) > 0) ?
				floatval($this->requestData['quantity_b1c']) :
				1;
			$this->arResult['CURRENT_PRODUCT'] = $this->getCurrentProduct($this->arResult['ELEMENT_ID'], $this->arResult['QUANTITY']);
			
			if($this->show_captha){
				include_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/classes/general/captcha.php");
				$this->arResult["capCode"] = htmlspecialcharsbx($APPLICATION->CaptchaGetCode());
			}
			$this->arResult["SHOW_CAPTCHA"] = $this->show_captha ? "Y" : "N";
			$this->arResult['TITLE_MODAL'] = $this->getTitleModal();
		}
		$this->arResult["AJAX_ID"] = $this->ajax_id;
		
		//return $arFields;
	}

	protected function getTitleModal(){
		$title = $this->arResult['CURRENT_PRODUCT']['FIELDS'];
		if (isset($this->arResult['CURRENT_PRODUCT']['PRICE'])) {
			$price = $this->arResult['CURRENT_PRODUCT']['PRICE']['PRICE'];
			$currency = $this->arResult['CURRENT_PRODUCT']['PRICE']['CURRENCY'];
		} elseif(self::isNonemptyArray($this->arResult['CURRENT_PRODUCT']['OFFERS'])) {
			if (isset($this->arResult['CURRENT_OFFER_ID']) && intval($this->arResult['CURRENT_OFFER_ID']) > 0) {
				$price = $this->arResult['CURRENT_PRODUCT']['OFFERS'][intval($this->arResult['CURRENT_OFFER_ID'])]['PRICE'];
				$currency = $this->arResult['CURRENT_PRODUCT']['OFFERS'][intval($this->arResult['CURRENT_OFFER_ID'])]['CURRENCY'];
			} else {
				reset($this->arResult['CURRENT_PRODUCT']['OFFERS']);
				if (intval($this->arResult['CURRENT_PRODUCT']['OFFER_ID_MIN_PRICE']) > 0) {
					$ar_offers = $this->arResult['CURRENT_PRODUCT']['OFFERS'][intval($this->arResult['CURRENT_PRODUCT']['OFFER_ID_MIN_PRICE'])];
				} else {
					$ar_offers = current($this->arResult['CURRENT_PRODUCT']['OFFERS']);
				}
				$price = $ar_offers['PRICE'];
				$currency = $ar_offers['CURRENCY'];
			}
		}else{
			$price = 0;
			$currency = 'RUB';
		}
		if($price > 0){
			$formatPrice = FormatCurrency($price, $currency);
		}else{
			$formatPrice = "";
		}

		return array(
			"TITLE" => $title,
			"PRICE" => $price,
			"CURRENCY" => $currency,
			"FORMAT_PRICE" => $formatPrice
		);
	}
	
	protected function getPaySystem(){
		if ($this->startCache(array('b1c-paysystem')))
		{
			try
			{
				
				
				if($this->arParams['MODE_EXTENDED'] == 'Y'){
					if(!empty($this->arParams['PAY_SYSTEMS'])){
						$db_ptype = CSalePaySystem::GetList($arOrder = Array("SORT" => "ASC", "PSA_NAME" => "ASC"), Array("ACTIVE" => "Y", "PERSON_TYPE_ID" => $this->arParams['PERSON_TYPE_ID'], "ID" => $this->arParams['PAY_SYSTEMS']));
					}else{
						$db_ptype = CSalePaySystem::GetList($arOrder = Array("SORT" => "ASC", "PSA_NAME" => "ASC"), Array("ACTIVE" => "Y", "PERSON_TYPE_ID" => $this->arParams['PERSON_TYPE_ID']));
					}
				}else{
					$db_ptype = CSalePaySystem::GetList($arOrder = Array("SORT" => "ASC", "PSA_NAME" => "ASC"), Array("ACTIVE" => "Y", "PERSON_TYPE_ID" => $this->arParams['PERSON_TYPE_ID']));
				}
				$arPaySystems = array();
				while($arPaySystem = $db_ptype->Fetch()){
					if($arPaySystem["PSA_LOGOTIP"] > 0)
						$arPaySystem["PSA_LOGOTIP"] = CFile::GetFileArray($arPaySystem["PSA_LOGOTIP"]);
					$arPaySystem["PSA_NAME"] = htmlspecialcharsEx($arPaySystem["PSA_NAME"]);
					$arPaySystems[] = $arPaySystem;
				}
				
			}
			catch (Exception $e)
			{
				$this->abortCache();
				throw $e;
			}
			
			$this->endCache($arPaySystems);
			
		}
		else
			$arPaySystems = $this->getCacheData();
		
		
		return $arPaySystems;
	}
	
	protected function getDelivery(){
		if ($this->startCache(array('b1c-delivery')))
		{
			try
			{
				$arDelivery = array();
				if($this->arParams["USE_OLD_CLASS"] != 'Y'){
					$arDeliveryActive = Delivery\Services\Manager::getActiveList();
					if($this->arParams['MODE_EXTENDED'] == 'Y'){
						if(!empty($this->arParams['DELIVERY'])){
							$arDelivery = array_intersect_key($arDeliveryActive, array_flip($this->arParams['DELIVERY']));
						}else{
							$arDelivery = $arDeliveryActive;
						}
					}else{
						$deliveryId = Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
						$arDelivery[] = Delivery\Services\Manager::getById($deliveryId);
					}
				}else{
					if($this->arParams['MODE_EXTENDED'] == 'Y'){
						if(!empty($this->arParams['DELIVERY'])){
							$db_dtype = CSaleDelivery::GetList(array(
								"SORT" => "ASC",
								"NAME" => "ASC"
							), array(
								"ACTIVE" => "Y",
								"ID" => $this->arParams['DELIVERY']
							), false, false, array());
						}
						else{
							$db_dtype = CSaleDelivery::GetList(array(
								"SORT" => "ASC",
								"NAME" => "ASC"
							), array("ACTIVE" => "Y",), false, false, array());
						}
					}
					else{
						$db_dtype = CSaleDelivery::GetList(array(
							"SORT" => "ASC",
							"NAME" => "ASC"
						), array("ACTIVE" => "Y",), false, false, array());
					}
					//if ($ar_dtype = $db_dtype->Fetch()){
					while($ptype = $db_dtype->Fetch()){
						$arDelivery[$ptype['ID']] = $ptype;
					}
					
				}
				
			}
			catch (Exception $e)
			{
				$this->abortCache();
				throw $e;
			}
			
			$this->endCache($arDelivery);
			
		}
		else
			$arDelivery = $this->getCacheData();
		
		
		return $arDelivery;
	}
	
	protected function getUserFields(){
		$arUserFields = $this->arParams['USER_DATA_FIELDS'];
		
		
		
		return $arUserFields;
	}
	
	protected function getUserFieldsRequire(){
		if(is_array($this->arParams['USER_DATA_FIELDS_REQUIRED'])){                                        //Email only require
			foreach($this->arParams['USER_DATA_FIELDS_REQUIRED'] as $require_field){            //Translator add fields marked settings
				$arUserFieldsRequire[$require_field] = 'Y';
			}
			return $arUserFieldsRequire;
		}
	}
	
	protected function getOrderProps(){
		if ($this->startCache(array('b1c-orderprops')))
		{
			try
			{
				$arOrderProps = array();
				if(is_array($this->arParams['SHOW_PROPERTIES']) && !empty($this->arParams['SHOW_PROPERTIES'])){
					foreach($this->arParams['SHOW_PROPERTIES'] as $order_prop){
						$db_props = CSaleOrderProps::GetList(array("SORT" => "ASC"), array("ID" => $order_prop));
						if($arProps = $db_props->Fetch()){
							if($arProps['TYPE'] == 'SELECT' || $arProps['TYPE'] == 'MULTISELECT' || $arProps['TYPE'] == 'RADIO'){
								$arProps['VALUE'] = array();
								$db_vars = CSaleOrderPropsVariant::GetList(
									array("SORT" => "ASC"),
									array("ORDER_PROPS_ID" => $arProps["ID"])
								);
								while ($vars = $db_vars->Fetch())
								{
									$arProps['VALUE'][] = $vars;
								}
							}
							$arOrderProps[] = $arProps;
						}
					}
					
				}
				
			}
			catch (Exception $e)
			{
				$this->abortCache();
				throw $e;
			}
			
			$this->endCache($arOrderProps);
			
		}
		else
			$arOrderProps = $this->getCacheData();
		
		
		return $arOrderProps;
	}
	
	protected function getOrderPropsRequire(){
		if(is_array($this->arParams['SHOW_PROPERTIES_REQUIRED'])){
			foreach($this->arParams['SHOW_PROPERTIES_REQUIRED'] as $require_props){            //Translator add fields marked settings
				$arOrderPropsRequire[$require_props] = 'Y';
			}
			return $arOrderPropsRequire;
		}
	}
	
	/**
	 * Функция запоминает поля заказа указанные пользователем при
	 * сбрасывании полей в форме
	 * @return array
	 */
	protected function getCurrentUserProps(){
		$currentUserProps = array();
		global $USER;
		$user_id = $USER->GetID();
		if($this->arParams['PERSON_TYPE_ID'] > 0 && $user_id > 0){
			$dbUserProfiles = \CSaleOrderUserProps::GetList(array("DATE_UPDATE" => "DESC"), array(
				"PERSON_TYPE_ID" => $this->arParams['PERSON_TYPE_ID'],
				"USER_ID" => $user_id
			));
			$bFirst = $profileID = false;
			if ($arUserProfiles = $dbUserProfiles->GetNext())
			{
				$dbUserPropsValues = \CSaleOrderUserPropsValue::GetList(
					array('SORT' => 'ASC'),
					array(
						'USER_PROPS_ID' => intval($arUserProfiles["ID"]),
						'USER_ID' => $user_id,
					),
					false,
					false,
					array('VALUE', 'PROP_TYPE', 'VARIANT_NAME', 'SORT', 'ORDER_PROPS_ID')
				);
				while ($propValue = $dbUserPropsValues->Fetch())
				{
					if ($propValue['PROP_TYPE'] === 'ENUM')
					{
						$propValue['VALUE'] = explode(',', $propValue['VALUE']);
					}
					
					if ($propValue['PROP_TYPE'] === 'LOCATION' && !empty($propValue['VALUE']))
					{
						$arLoc = LocationTable::getById($propValue['VALUE'])->fetch();
						if (!empty($arLoc))
						{
							$propValue['VALUE'] = $arLoc['CODE'];
						}
					}
					
					if ($propValue['PROP_TYPE'] === 'FILE' && !empty($propValue['VALUE']))
					{
						if (\CheckSerializedData($propValue['VALUE'])
							&& ($values = @unserialize($propValue['VALUE'])) !== false)
						{
							$propValue['VALUE'] = array();
							
							foreach ($values as $value)
							{
								$propValue['VALUE'][] = \CFile::GetFileArray($value);
							}
						}
					}
					$prop = \CSaleOrderProps::GetByID($propValue['ORDER_PROPS_ID']);
					$currentUserProps[$prop['CODE']] = $propValue['VALUE'];
				}
			}
		}
		if(isset($this->requestData['ONECLICK_PROP'])){
			foreach($this->requestData['ONECLICK_PROP'] as $id => $value){
				$prop = \CSaleOrderProps::GetByID($id);
				$currentUserProps[$prop['CODE']] = $value;
			}
		}
		return $currentUserProps;
	}
	
	/*
	 * Функция запоминает обязательные поля заказа указанные пользователем при
	 * сбрасывании полей в форме
	 */
	protected function getCurrentUser(){
		global $USER;
		$user_id = $USER->GetID();
		
		if(intval($user_id) > 0){
			$rsUser = \CUser::GetByID(intval($user_id));
			$currentUser = $rsUser->Fetch();
		}else{
			$currentUser = array();
		}
		/** проверка поста */
		if(isset($this->requestData['ONECLICK'])) {
			foreach ($this->requestData['ONECLICK'] as $key => $value) {
				$currentUser[$key] = $value;
			}
		}
		return $currentUser;
	}
	
	
	/**
	 * Функция для получения цены, либо его торговых предложений, если они есть
	 *
	 * @param $ELEMENT_ID int ид элемента
	 * @param $arPriceCode array Массив типов цен
	 * @param $type_price string какое значение цены возвращать
	 * @param $USER_ID int Ид пользователя
	 * @param $SITE_ID int Ид сайта
	 * @param $floor bool Округлять цену
	 *
	 * @return float|bool|string|array
	 */
	protected function GetPriceProduct(
		$ELEMENT_ID,
		$arPriceCode = array(),
		$type_price = "DISCOUNT_VALUE",
		$USER_ID = 1,
		$SITE_ID = false,
		$floor = false)
	{
		if(!$SITE_ID){
			$SITE_ID = SITE_ID;
		}
		if(!\Bitrix\Main\Loader::IncludeModule("iblock"))
			return false;
		$ELEMENT_ID = intVal($ELEMENT_ID);
		if($ELEMENT_ID <= 0){
			return false;
		}
		$res = CIBlockElement::GetByID($ELEMENT_ID);
		if($ar_res = $res->GetNext()){
			$arElement = $ar_res;
		}else{
			return false;
		}
		if(empty($arPriceCode)){
			$arBasePrice = \CCatalogGroup::GetBaseGroup();
			if(is_array($arBasePrice) && !empty($arBasePrice))
				$arPriceCode = array($arBasePrice['NAME']);
			else
				return false;
		}
		
		if(!is_array($arPriceCode)){
			$arPriceCode = array($arPriceCode);
		}
		
		$arResultPrices = \CIBlockPriceTools::GetCatalogPrices($arElement["IBLOCK_ID"], $arPriceCode);
		$arSelectProperties = (is_array($this->arParams['LIST_OFFERS_PROPERTY_CODE']) && !empty($this->arParams['LIST_OFFERS_PROPERTY_CODE']))?$this->arParams['LIST_OFFERS_PROPERTY_CODE']:array();
		$arOffers = \CIBlockPriceTools::GetOffersArray(
			array(
				'IBLOCK_ID' => $arElement["IBLOCK_ID"],
				'HIDE_NOT_AVAILABLE' => 'Y',
			)						//arFilter
			,array($ELEMENT_ID)		//arElementID
			,array()				//arOrder
			,array()				//arSelectFields
			,$arSelectProperties	//arSelectProperties
			,0						//limit
			,$arResultPrices		//arPrices
			,true					//vat_include
			,array()				//arCurencyParams
			,$USER_ID				//USER_ID
			,$SITE_ID				//SITE_ID
		
		);
		
		if(!empty($arOffers)){
			
			return 'OFFERS';
		}
		$arSelect = array(
			"ID",
			"IBLOCK_ID",
			"CODE",
			"XML_ID",
			"NAME",
			"ACTIVE",
			"DATE_ACTIVE_FROM",
			"DATE_ACTIVE_TO",
			"SORT",
			"PREVIEW_TEXT",
			"PREVIEW_TEXT_TYPE",
			"DETAIL_TEXT",
			"DETAIL_TEXT_TYPE",
			"DATE_CREATE",
			"CREATED_BY",
			"TIMESTAMP_X",
			"MODIFIED_BY",
			"TAGS",
			"IBLOCK_SECTION_ID",
			"DETAIL_PAGE_URL",
			"LIST_PAGE_URL",
			"DETAIL_PICTURE",
			"PREVIEW_PICTURE",
			"PROPERTY_*",
		);
		
		foreach($arResultPrices as &$value)
		{
			if (!$value['CAN_VIEW'] && !$value['CAN_BUY'])
				continue;
			$arSelect[] = $value["SELECT"];
		}
		
		if (isset($value))
			unset($value);
		$res = \CIBlockElement::GetList(Array(), array("ID" => $ELEMENT_ID), false, false, $arSelect);
		if($ob = $res->GetNextElement()){
			$arElement = $ob->GetFields();
			
		}
		$arElement["CAT_PRICES"] = $arResultPrices;
		$arPrice = \CIBlockPriceTools::GetItemPrices($arElement["IBLOCK_ID"], $arResultPrices, $arElement, true, array(), $USER_ID, $SITE_ID);
		$min_price = false;
		$currency = false;
		if (!empty($arPrice))
		{
			
			foreach($arPrice as $code_price => $price){
				if(!$min_price || $price[$type_price] < $min_price){
					$min_price = $price[$type_price];
					$currency = $price['CURRENCY'];
				}
			}
			
		}
		if($floor && $min_price){
			$min_price = floor($min_price/$floor)*$floor;
		}
		$quantity = $this->arResult['QUANTITY'] > 0 ? $this->arResult['QUANTITY'] : 1;
		if($min_price !== false){
			return array(
				"PRICE" => $min_price * $quantity,
				"CURRENCY" => $currency
			);
		}
		return $min_price;
	}
	
	
	
	/**
	 * Функция выводит при заказе название товара, цену и количество
	 */
	protected function getCurrentProduct($element_id, $quantity = 1, $force = false)
	{
		if ($this->startCache(array('b1c-curentproduct-'.$element_id)) || $force)
		{
			try
			{
				$prod_fields = CIBlockElement::GetByID($element_id);
				$result = array();
				if ($arItem = $prod_fields->Fetch()) {
					$result["FIELDS"] = $arItem['NAME'];
					if($this->arParams["SHOW_OFFERS_FIRST_STEP"] != 'Y'){
						$result['OFFERS'] = $this->getOffers($element_id);
					}
					$min_price = $this->GetPriceProduct($element_id, $this->arParams['PRICE_CODE'], "DISCOUNT_VALUE", $this->userID, SITE_ID);
					if($min_price == 'OFFERS'){
						if(!empty($this->arOffers)) {
							$result['OFFERS'] = array();
							$offerID_min_price = 0;
							$offer_min_price = false;
							foreach ($this->arOffers as $arOffer) {
								$result['OFFERS'][$arOffer['ID']] = $this->GetPriceProduct($arOffer['ID'], $this->arParams['PRICE_CODE'], "DISCOUNT_VALUE", $this->userID, SITE_ID);    //получаем массив цен торговых предложений
								if(is_array($result['OFFERS'][$arOffer['ID']])){
									if(!$offer_min_price){
										$offer_min_price = $result['OFFERS'][$arOffer['ID']]['PRICE'];
										$offerID_min_price = $arOffer['ID'];
									}elseif($offer_min_price > $result['OFFERS'][$arOffer['ID']]['PRICE']){
										$offer_min_price = $result['OFFERS'][$arOffer['ID']]['PRICE'];
										$offerID_min_price = $arOffer['ID'];
									}
								}
							}
							$result['OFFER_ID_MIN_PRICE'] = $offerID_min_price;
							
						}
					}else{
						$result["PRICE"] = $min_price;        // получаем цену товара без торговых предложений
					}
				}
			}
			catch (Exception $e)
			{
				$this->abortCache();
				throw $e;
			}
			
			$this->endCache($result);
			
		}
		else
			$result = $this->getCacheData();
		
		
		
		return $result;
	}
	
	/**
	 * @param $email
	 * @return int|mixed
	 */
	protected function validateEmail($email){
		if(function_exists('filter_var')){
			return filter_var($email, FILTER_VALIDATE_EMAIL);
		}else{
			return preg_match("/^[a-zA-Z0-9_\-.]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-.]+$/", $email);
		}
	}
	
	protected function verifyFields($reqFields = array(), $reqFieldsProps = array()){
		$errors = array();
		if(is_array($this->requestData['ONECLICK'])){
			foreach($this->requestData['ONECLICK'] as $user_fields => $value){
				if($reqFields[$user_fields] == 'Y' && $value == ''){
					$errors[$user_fields] = 'Y';
				}
				if($user_fields == 'EMAIL'){
					if(!$this->validateEmail($value) && $reqFields[$user_fields] == 'Y'){
						$errors[$user_fields] = 'EMAIL';
					}
				}
			}
		}
		
		if(is_array($this->propertyList)){
			foreach($this->propertyList as $order_prop => $value_prop){
				$arProp_db = \CSaleOrderProps::GetList(array(), array('ID' => $order_prop));
				if($arProp = $arProp_db->fetch()){
					if($arProp['IS_EMAIL'] == 'Y'){
						if(!$this->validateEmail($value_prop) && $reqFieldsProps[$order_prop] == 'Y'){
							$errors[$order_prop] = 'EMAIL';
						}
					}
					/** Проверка местополжения */
					if($arProp['TYPE'] == 'LOCATION' && $value_prop != ""){
						if($item = \Bitrix\Sale\Location\LocationTable::getById($value_prop)->fetch()){
							$this->propertyList[$order_prop] = $item['CODE'];
						}else{
							$errors[$order_prop] = 'Y';
						}
						
					}
				}
				if($reqFieldsProps[$order_prop] == 'Y' && $value_prop == ''){
					$errors[$order_prop] = 'Y';
				}
				
			}
		}
		
		if($this->show_captha /*&& strlen($this->requestData['captcha_sid']) > 0 && $this->requestData['offers'] != "Y"*/){
			include_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/classes/general/captcha.php");
			$captcha_code = $this->requestData["captcha_sid"];
			$captcha_word = $this->requestData["captcha_word"];
			$cpt = new CCaptcha();
			$captchaPass = COption::GetOptionString("main", "captcha_password", "");
			if(strlen($captcha_word) > 0 && strlen($captcha_code) > 0){
				if(!$cpt->CheckCodeCrypt($captcha_word, $captcha_code, $captchaPass))
					$errors['CAPTCHA'] = Loc::getMessage("MF_CAPTCHA_WRONG");
			}else
				$errors["CAPTCHA"] = Loc::getMessage("MF_CAPTHCA_EMPTY");
			
		}elseif($this->show_captha && $this->requestData['offers'] != "Y"){
			$errors["CAPTCHA"] = Loc::getMessage("MF_CAPTHCA_EMPTY");
		}
		return $errors;
	}
	
	/**
	 * Проверка подключенных модулей
	 * @throws \Main\SystemException
	 */
	protected function checkRequiredModules(){
		if(!Loader::includeModule('h2o.buyoneclick'))
			throw new Main\SystemException(Loc::getMessage("H2O_MODULE_NOT_INSTALL"));
		if(!Loader::includeModule('iblock'))
			throw new Main\SystemException(Loc::getMessage("IBLOCK_MODULE_NOT_INSTALL"));
		if(!Loader::includeModule('sale'))
			throw new Main\SystemException(Loc::getMessage("SALE_MODULE_NOT_INSTALL"));
		if(!Loader::includeModule('catalog'))
			throw new Main\SystemException(Loc::getMessage("CATALOG_MODULE_NOT_INSTALL"));
		
	}
	
	/**
	 * Обработка $_REQUEST
	 */
	protected function processRequest(){
		global $APPLICATION;
		//todo: выпилить старый реквест
		$context = Application::getInstance()->getContext();
		$this->requestData = $APPLICATION->ConvertCharsetArray($_REQUEST, 'UTF-8', LANG_CHARSET);    //ajax запросы отправляются в UTF-8
		$this->requestDataD7 = $context->getRequest();
		$this->requestDataD7->addFilter(new Bitrix\Main\Web\PostDecodeFilter);
		
		/** @var  needGetData bool
		 * Получать ли данные
		 * !!!
		 * Скрытый параметр IS_POPUP_TEMPLATE, если равен Y,
		 * то данные при загрузке страницы не получаются.
		 * Нужен для кастомизации шаблонов.
		 * Скрытый потому, чтобы не путать людей
		 */
		$this->needGetData = (
				($this->templateName != '.default' || $this->templateName != '') &&
				$this->arParams['IS_POPUP_TEMPLATE'] != 'Y') ||
			$this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == $this->ajax_id ||
			$this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == "Y";
	}
	
	/**
	 * Returns array of order properties from request
	 *
	 * @return array
	 */
	protected function getPropertyValuesFromRequest()
	{
		$orderProperties = array();
		
		foreach ($this->requestData['ONECLICK_PROP'] as $k => $v)
		{
			$orderProperties[$k] = $v;
		}
		
		foreach ($this->requestDataD7->getFileList() as $k => $arFileData)
		{
			if ($k == "ONECLICK_PROP")
			{
				$orderPropId = intval(substr($k, strlen("ORDER_PROP_")));
				
				if (is_array($arFileData))
				{
					foreach ($arFileData as $param_name => $value)
					{
						if (is_array($value))
						{
							foreach ($value as $nIndex => $val)
							{
								if(!isset($orderProperties[$nIndex]['ID'])){
									$orderProperties[$nIndex]['ID'] = "";
								}
								if (strlen($arFileData["name"][$nIndex]) > 0)
									$orderProperties[$nIndex][$param_name] = $val;
							}
						}
					}
				}
			}
		}
		
		return $orderProperties;
	}
	
	/**
	 * Function wraps action list evaluation into try-catch block.
	 * @return void
	 */
	protected function performActions(){
		if(class_exists("Bitrix\Sale\Compatible\DiscountCompatibility") &&
			method_exists("Bitrix\Sale\Compatible\DiscountCompatibility", "stopUsageCompatible")){
			Sale\Compatible\DiscountCompatibility::stopUsageCompatible();
		}
		try{
			$this->performActionList();
		}catch(Exception $e){
			$this->errorsNonFatal[htmlspecialcharsEx($e->getCode())] = htmlspecialcharsEx($e->getMessage());
		}
		if(class_exists("Bitrix\Sale\Compatible\DiscountCompatibility") &&
			method_exists("Bitrix\Sale\Compatible\DiscountCompatibility", "revertUsageCompatible")){
			Sale\Compatible\DiscountCompatibility::revertUsageCompatible();
		}
	}
	
	/**
	 * Function perform pre-defined list of actions based on current state of $_REQUEST and parameters.
	 * @return void
	 */
	protected function performActionList(){
		// set coupon
		//$this->performActionCoupon();
		// add order
		$this->performActionBuy();
		
		// some other ...
	}
	
	protected function performActionCoupon(){
		if($this->requestData['coupon']){
			Bitrix\Sale\DiscountCouponsManager::init();
			$this->arResult['VALID_COUPON'] = Bitrix\Sale\DiscountCouponsManager::add($this->requestData['coupon']);
			$this->arResult['CURRENT_PRODUCT'] = $this->getCurrentProduct($this->arResult['ELEMENT_ID'], $this->arResult['QUANTITY'], true);
		}
		
	}
	
	/**
	 * Perform the following action: add order
	 * @throws Main\SystemException
	 * @return void
	 */
	protected function performActionBuy(){
		if($this->requestData['buy_one_click'] == 'Y' && !isset($this->arResult['FATAL_ERROR']) && ($this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == $this->ajax_id || $this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == 'Y')){
			$this->addNewOrder();
		}
	}
	
	protected function getOffers($ELEMENT_ID){
		if(intval($ELEMENT_ID) <= 0){
			return false;
		}
		$res = Bitrix\Iblock\ElementTable::getById($ELEMENT_ID);
		$arElement = $res->fetch();
		
		$arResultPrices = \CIBlockPriceTools::GetCatalogPrices($arElement["IBLOCK_ID"], $this->arParams['PRICE_CODE']);
		
		$arSelectProperties = (is_array($this->arParams['LIST_OFFERS_PROPERTY_CODE']) && !empty($this->arParams['LIST_OFFERS_PROPERTY_CODE']))?$this->arParams['LIST_OFFERS_PROPERTY_CODE']:array();
		$arOrder = array($this->arParams['OFFERS_SORT_BY'] => $this->arParams['OFFERS_SORT_ORDER']);
		$arOffers = \CIBlockPriceTools::GetOffersArray(
			array(
				'IBLOCK_ID' => $arElement["IBLOCK_ID"],
				'HIDE_NOT_AVAILABLE' => 'Y',
			)						//arFilter
			,array($ELEMENT_ID)		//arElementID
			,$arOrder				//arOrder
			,array()				//arSelectFields
			,$arSelectProperties	//arSelectProperties
			,0						//limit
			,$arResultPrices		//arPrices
			,true					//vat_include
			,array()				//arCurencyParams
		
		);
		
		$this->arOffers = $arOffers;
		return $arOffers;
	}
	
	/**
	 * @param $order_id
	 * Функция для установки глобальных переменных, необходимых для платежных систем
	 */
	protected function setOrderValueForPaySystem($order_id){
		$db_sales = \CSaleOrder::GetList(array("DATE_INSERT" => "ASC"), array("ID" => $order_id));
		if ($arOrder = $db_sales->Fetch())
		{
			if(!isset($GLOBALS["SALE_CORRESPONDENCE"])){
				$GLOBALS["SALE_CORRESPONDENCE"] = array(
					"AMOUNT" => array(),
					"CURRENCY" => array(),
					"ORDER_ID" => array()
				);
			}
			if(!isset($GLOBALS["SALE_INPUT_PARAMS"])){
				$GLOBALS["SALE_INPUT_PARAMS"] = array(
					"ORDER" => array()
				);
			}
			if(!isset($GLOBALS["SALE_CORRESPONDENCE"]["AMOUNT"]["VALUE"]) || $GLOBALS["SALE_CORRESPONDENCE"]["AMOUNT"]["VALUE"] == 0){
				$GLOBALS["SALE_CORRESPONDENCE"]["AMOUNT"]["VALUE"] = $arOrder['PRICE'];
			}
			if(!isset($GLOBALS['SALE_INPUT_PARAMS']['ORDER']['SHOULD_PAY']) || $GLOBALS['SALE_INPUT_PARAMS']['ORDER']['SHOULD_PAY'] == 0){
				$GLOBALS['SALE_INPUT_PARAMS']['ORDER']['SHOULD_PAY'] = $arOrder['PRICE'];
			}
			if(!isset($GLOBALS["SALE_CORRESPONDENCE"]["CURRENCY"]["VALUE"])){
				$GLOBALS["SALE_CORRESPONDENCE"]["CURRENCY"]["VALUE"] = $arOrder['CURRENCY'];
			}
			if(!isset($GLOBALS['SALE_INPUT_PARAMS']['ORDER']['CURRENCY'])){
				$GLOBALS['SALE_INPUT_PARAMS']['ORDER']['CURRENCY'] = $arOrder['CURRENCY'];
			}
			if(!isset($GLOBALS["SALE_CORRESPONDENCE"]["ORDER_ID"]["VALUE"])){
				$GLOBALS["SALE_CORRESPONDENCE"]["ORDER_ID"]["VALUE"] = $arOrder['ACCOUNT_NUMBER'];
			}
			if(!isset($GLOBALS['SALE_INPUT_PARAMS']['ORDER']['ID'])){
				$GLOBALS['SALE_INPUT_PARAMS']['ORDER']['ID'] = $arOrder['ACCOUNT_NUMBER'];
			}
		}
	}
	
	protected function addNewOrder(){
		$this->propertyList = $this->getPropertyValuesFromRequest();
		$this->arResult['ERRORS'] = $this->verifyFields($this->arResult['USER_FIELDS_REQUIRED'], $this->arResult['SHOW_PROPERTIES_REQUIRED']);
		if(empty($this->arResult['ERRORS'])){
			if($this->arParams["USE_OLD_CLASS"] != 'Y'){
				$class = new \h2o\buyoneclick\orderD7($this->element_id, $this->requestData['ONECLICK'], $this->propertyList, $this->arParams);
			}else{
				$class = new h2o\buyoneclick\h2obuyoneclick($this->element_id, $this->requestData['ONECLICK'], $this->propertyList, $this->arParams);
			}
			
			
			if(is_array($this->arParams['NEW_USER_GROUP_ID']) && !empty($this->arParams['NEW_USER_GROUP_ID'])){
				$class->SetNewUserGroupId($this->arParams['NEW_USER_GROUP_ID']);
			}else{
				$def_group = COption::GetOptionString("main", "new_user_registration_def_group", "");
				$class->SetNewUserGroupId(explode(",", $def_group));
			}
			
			if($this->arParams["SHOW_QUANTITY"] || floatval($this->requestData['quantity_b1c']) > 0){
				$class->SetQuantity($this->requestData['quantity_b1c']);
			}else{
				$class->SetQuantity(1);
			}
			
			if($this->arParams['ADD_NOT_AUTH_TO_ONE_USER']){
				$class->setFixUser($this->arParams['ADD_NOT_AUTH_TO_ONE_USER_ID']);
			}
			
			if($this->arParams['PERSON_TYPE_ID'] > 0){
				$class->SetPERSON_TYPE_ID($this->arParams['PERSON_TYPE_ID']);
			}else{
				$db_ptype = \CSalePersonType::GetList(Array("SORT" => "ASC"), Array("ACTIVE" => "Y"));
				if($ptype = $db_ptype->Fetch()){
					$def_person_type_id = $ptype['ID'];
				}else{
					throw new Main\SystemException(Loc::getMessage("NOT_FOUND_GROUP_ID"));
				}
				$class->SetPERSON_TYPE_ID($def_person_type_id);
			}
			
			
			if($this->arParams['SHOW_PAY_SYSTEM'] && $this->requestData['PAY_SYSTEM'] > 0){
				$class->SetPAY_SYSTEM_ID($this->requestData['PAY_SYSTEM']);
			}else{
				$find_paysystem = false;
				if($this->arParams['DEFAULT_PAY_SYSTEM'] != ""){
					$db_ptype = \CSalePaySystem::GetList($arOrder = Array("ID"=>"ASC", "PSA_NAME"=>"ASC"), Array("ID"=>$this->arParams['DEFAULT_PAY_SYSTEM']));
					if ($ptype = $db_ptype->Fetch())
					{
						$class->SetPAY_SYSTEM_ID($ptype["ID"]);
						$find_paysystem = true;
					}
				}
				if(!$find_paysystem){
					$db_ptype = \CSalePaySystem::GetList($arOrder = Array(
						"ID" => "ASC",
						"PSA_NAME" => "ASC"
					), Array("ACTIVE" => "Y"));
					if($ptype = $db_ptype->Fetch()){
						$class->SetPAY_SYSTEM_ID($ptype["ID"]);
					}
				}
			}
			
			if($this->arParams['SHOW_USER_DESCRIPTION'] && strlen($this->requestData['ONECLICK_COMMENT']) > 0){
				$class->SetUserDescription($this->requestData['ONECLICK_COMMENT']);
			}
			
			if($this->arParams['SHOW_DELIVERY'] && $this->requestData['DELIVERY'] > 0){
				$class->SetDELIVERY_ID($this->requestData['DELIVERY']);
				$class->SetPRICE_DELIVERY($this->arResult['DELIVERY'][$this->requestData['DELIVERY']]['PRICE']);
			}else{
				$find_delivery = false;
				if($this->arParams['DEFAULT_DELIVERY'] != ""){
					if(class_exists("Bitrix\Sale\Delivery\Services\Table") && method_exists("Bitrix\Sale\Delivery\Services\Table", "getById")){
						$dbDelivery = Delivery\Services\Table::getById($this->arParams['DEFAULT_DELIVERY']);
						if($delivery = $dbDelivery->fetch()){
							$class->SetDELIVERY_ID($delivery['ID']);
							$class->SetPRICE_DELIVERY(0);
							$find_delivery = true;
						}
					}else{
						$db_dtype = CSaleDelivery::GetList($arOrder = Array("ID" => "ASC"),
							array("ID" => $this->arParams['DEFAULT_DELIVERY']));
						if($delivery = $db_dtype->Fetch()){
							$class->SetDELIVERY_ID($delivery['ID']);
							$class->SetPRICE_DELIVERY($delivery['PRICE']);
							$find_delivery = true;
						}
					}
				}
				if(!$find_delivery){
					/** Устанавливаем !!!БЕСПЛАТНУЮ!!!(если бесплатной нет - то все плохо:)) доставку, без нее не будет резервирования товара! */
					$db_dtype = CSaleDelivery::GetList($arOrder = Array("ID" => "ASC"), array("ACTIVE" => "Y"));
					while($delivery = $db_dtype->Fetch()){
						if($delivery['PRICE'] > 0){
							continue;
						}
						$class->SetDELIVERY_ID($delivery['ID']);
						break;
					}
				}
			}
			
			if(is_array($this->arParams['PRICE_CODE'])){
				$class->SetPriceCode($this->arParams['PRICE_CODE']);
			}
			
			if($this->arParams['BUY_CURRENT_BASKET']){
				$class->BuyCurrentBasketMode(true);
			}
			
			$return = $class->Buy();
			
			if($this->arParams['USE_OLD_CLASS'] != "Y"){
				if(!empty($return['OFFERS'])){
					$this->arResult['OFFERS'] = $return['OFFERS'];
					if($this->arParams["SHOW_QUANTITY"] || floatval($this->requestData['quantity_b1c']) > 0){
						/** передаем количество*/
						$this->arResult["QUANTITY"] = $this->requestData['quantity_b1c'];
					}
				}
				if(isset($return['WARNING'])){
					$this->errorsNonFatal = array_merge($this->errorsNonFatal, $return['WARNING']);
				}
				$this->arResult['ORDER_ID'] = $return['ORDER_ID'];
				$this->arResult['SUCCESS'] = $return['ACCOUNT_NUMBER'];
				//if($this->arParams['MODE_EXTENDED'] == 'Y' && $this->arParams['SHOW_PAY_SYSTEM'] && $this->requestData['PAY_SYSTEM'] > 0){
				$dbPaySysAction = CSalePaySystemAction::GetList(array(), array(
					"PAY_SYSTEM_ID" => $class->GetPaySystemId(),
					"PERSON_TYPE_ID" => $class->GetPersonTypeId()
				), false, false, array(
					"NAME",
					"ACTION_FILE",
					"NEW_WINDOW",
					"PARAMS",
					"ENCODING",
					"LOGOTIP"
				));
				//$this->setOrderValueForPaySystem($return['ORDER_ID']);
				\CSalePaySystemAction::InitParamArrays(false, $return['ORDER_ID']);
				if($arPaySysAction = $dbPaySysAction->Fetch()){
					$arPaySysAction["NAME"] = htmlspecialcharsEx($arPaySysAction["NAME"]);
					if(strlen($arPaySysAction["ACTION_FILE"]) > 0){
						if($arPaySysAction["NEW_WINDOW"] != "Y"){
							CSalePaySystemAction::InitParamArrays(false, $return['ORDER_ID'], $arPaySysAction["PARAMS"]);
							
							$pathToAction = $_SERVER["DOCUMENT_ROOT"] . $arPaySysAction["ACTION_FILE"];
							
							$pathToAction = str_replace("\\", "/", $pathToAction);
							while(substr($pathToAction, strlen($pathToAction) - 1, 1) == "/")
								$pathToAction = substr($pathToAction, 0, strlen($pathToAction) - 1);
							
							if(file_exists($pathToAction)){
								if(is_dir($pathToAction) && file_exists($pathToAction . "/payment.php"))
									$pathToAction .= "/payment.php";
								
								$arPaySysAction["PATH_TO_ACTION"] = $pathToAction;
							}
							
							if(strlen($arPaySysAction["ENCODING"]) > 0){
								define("BX_SALE_ENCODING", $arPaySysAction["ENCODING"]);
								AddEventHandler("main", "OnEndBufferContent", "ChangeEncoding");
								function ChangeEncoding(&$content){
									global $APPLICATION;
									header("Content-Type: text/html; charset=" . BX_SALE_ENCODING);
									$content = $APPLICATION->ConvertCharset($content, SITE_CHARSET, BX_SALE_ENCODING);
									$content = str_replace("charset=" . SITE_CHARSET, "charset=" . BX_SALE_ENCODING, $content);
								}
							}
						}
					}
					
					if($arPaySysAction > 0)
						$arPaySysAction["LOGOTIP"] = CFile::GetFileArray($arPaySysAction["LOGOTIP"]);
					
					$this->arResult["PAY_SYSTEM"] = $arPaySysAction;
				}
				//}
			}else{
				if(is_array($return)){
					if($return['ERROR'] != ""){
						throw new Main\SystemException($return['ERROR']);
					}
					elseif(!empty($return['OFFERS'])){
						$this->arResult['OFFERS'] = $return['OFFERS'];
						if($this->arParams["SHOW_QUANTITY"]){
							/** передаем количество*/
							$this->arResult["QUANTITY"] = $this->requestData['quantity_b1c'];
						}
					}
					else{
						throw new Main\SystemException(implode(";", $return));
					}
				}
				else{
					global $APPLICATION;
					$APPLICATION->RestartBuffer();
					$this->arResult['SUCCESS'] = $return;
					if($this->arParams['MODE_EXTENDED'] == 'Y' && $this->arParams['SHOW_PAY_SYSTEM'] && $this->requestData['PAY_SYSTEM'] > 0){
						$dbPaySysAction = CSalePaySystemAction::GetList(array(), array(
							"PAY_SYSTEM_ID" => $class->GetPaySystemId(),
							"PERSON_TYPE_ID" => $class->GetPersonTypeId()
						), false, false, array(
							"NAME",
							"ACTION_FILE",
							"NEW_WINDOW",
							"PARAMS",
							"ENCODING",
							"LOGOTIP"
						));
						if($arPaySysAction = $dbPaySysAction->Fetch()){
							$arPaySysAction["NAME"] = htmlspecialcharsEx($arPaySysAction["NAME"]);
							if(strlen($arPaySysAction["ACTION_FILE"]) > 0){
								if($arPaySysAction["NEW_WINDOW"] != "Y"){
									CSalePaySystemAction::InitParamArrays(false, $return, $arPaySysAction["PARAMS"]);
									
									$pathToAction = $_SERVER["DOCUMENT_ROOT"] . $arPaySysAction["ACTION_FILE"];
									
									$pathToAction = str_replace("\\", "/", $pathToAction);
									while(substr($pathToAction, strlen($pathToAction) - 1, 1) == "/")
										$pathToAction = substr($pathToAction, 0, strlen($pathToAction) - 1);
									
									if(file_exists($pathToAction)){
										if(is_dir($pathToAction) && file_exists($pathToAction . "/payment.php"))
											$pathToAction .= "/payment.php";
										
										$arPaySysAction["PATH_TO_ACTION"] = $pathToAction;
									}
									
									if(strlen($arPaySysAction["ENCODING"]) > 0){
										define("BX_SALE_ENCODING", $arPaySysAction["ENCODING"]);
										AddEventHandler("main", "OnEndBufferContent", "ChangeEncoding");
										function ChangeEncoding(&$content){
											global $APPLICATION;
											header("Content-Type: text/html; charset=" . BX_SALE_ENCODING);
											$content = $APPLICATION->ConvertCharset($content, SITE_CHARSET, BX_SALE_ENCODING);
											$content = str_replace("charset=" . SITE_CHARSET, "charset=" . BX_SALE_ENCODING, $content);
										}
									}
								}
							}
							
							if($arPaySysAction > 0)
								$arPaySysAction["LOGOTIP"] = CFile::GetFileArray($arPaySysAction["LOGOTIP"]);
							
							$this->arResult["PAY_SYSTEM"] = $arPaySysAction;
						}
					}
				}
			}
		}
	}
	
	/**
	 * Получение ajaxID
	 * @return string
	 */
	protected function getAjaxID() {
		global $DB;
		
		$ajax_call_id = md5($this->getName()."|".$this->GetTemplateName()."|".serialize($this->arParams)."|".COption::GetOptionString("main", "server_uniq_id", $_SERVER['SERVER_NAME']));
		
		return $ajax_call_id;
	}
	
	/**
	 * Обработка ошибок
	 */
	protected function formatResultErrors(){
		$errors = array();
		if(!empty($this->errorsFatal))
			$errors['FATAL'] = $this->errorsFatal;
		if(!empty($this->errorsNonFatal))
			$errors['NONFATAL'] = $this->errorsNonFatal;
		
		
		if(!empty($errors['FATAL']))
			$this->arResult['FATAL_ERROR'] = $errors['FATAL'];
		if(!empty($errors['NONFATAL']))
			$this->arResult['ERROR_STRING'] = $errors['NONFATAL'];
		
		// backward compatiblity
		$error = each($this->errorsFatal);
		if(!empty($error['value']))
			$this->arResult['ERROR_MESSAGE'] = $error['value'];
	}

	/**
	 * Function checks if it`s argument is a legal array for foreach() construction
	 * @param mixed $arr data to check
	 * @return boolean
	 */
	protected static function isNonemptyArray($arr)
	{
		return is_array($arr) && !empty($arr);
	}
	
	////////////////////////
	// Cache functions
	////////////////////////
	/**
	 * Function checks if cacheing is enabled in component parameters
	 * @return boolean
	 */
	final protected function getCacheNeed()
	{
		return	intval($this->arParams['CACHE_TIME']) > 0 &&
		$this->arParams['CACHE_TYPE'] != 'N' &&
		Config\Option::get("main", "component_cache_on", "Y") == "Y";
	}
	
	/**
	 * Function perform start of cache process, if needed
	 * @param mixed[]|string $cacheId An optional addition for cache key
	 * @return boolean True, if cache content needs to be generated, false if cache is valid and can be read
	 */
	final protected function startCache($cacheId = array())
	{
		if(!$this->getCacheNeed())
			return true;
		
		$this->currentCache = Data\Cache::createInstance();
		
		return $this->currentCache->startDataCache(intval($this->arParams['CACHE_TIME']), $this->getCacheKey($cacheId), "/", array(), "cache/h2o");
	}
	
	/**
	 * Function perform start of cache process, if needed
	 * @throws Main\SystemException
	 * @param mixed[] $data Data to be stored in the cache
	 * @return void
	 */
	final protected function endCache($data = false)
	{
		if(!$this->getCacheNeed())
			return;
		
		if($this->currentCache == 'null')
			throw new Main\SystemException('Cache were not started');
		
		$this->currentCache->endDataCache($data);
		$this->currentCache = null;
	}
	
	/**
	 * Function discard cache generation
	 * @throws Main\SystemException
	 * @return void
	 */
	final protected function abortCache()
	{
		if(!$this->getCacheNeed())
			return;
		
		if($this->currentCache == 'null')
			throw new Main\SystemException('Cache were not started');
		
		$this->currentCache->abortDataCache();
		$this->currentCache = null;
	}
	
	/**
	 * Function return data stored in cache
	 * @throws Main\SystemException
	 * @return void|mixed[] Data from cache
	 */
	final protected function getCacheData()
	{
		if(!$this->getCacheNeed())
			return;
		
		if($this->currentCache == 'null')
			throw new Main\SystemException('Cache were not started');
		
		return $this->currentCache->getVars();
	}
	
	/**
	 * Function leaves the ability to modify cache key in future.
	 * @return string Cache key to be used in CPHPCache()
	 */
	final protected function getCacheKey($cacheId = array())
	{
		if(!is_array($cacheId))
			$cacheId = array((string) $cacheId);
		
		$cacheId['SITE_ID'] = SITE_ID;
		$cacheId['LANGUAGE_ID'] = LANGUAGE_ID;
		// if there are two or more caches with the same id, but with different cache_time, make them separate
		$cacheId['CACHE_TIME'] = intval($this->arResult['CACHE_TIME']);
		
		if(defined("SITE_TEMPLATE_ID"))
			$cacheId['SITE_TEMPLATE_ID'] = SITE_TEMPLATE_ID;
		
		return implode('|', $cacheId);
	}
	
	public function executeComponent(){
		global $APPLICATION, $USER;
		\CAjax::Init();
		$this->userAuth = $USER->IsAuthorized();
		$this->templateName = $this->getTemplateName();
		$this->userID = $USER->GetID();
		$this->ajax_id = $this->getAjaxID();
		$this->show_captha = (!$this->userAuth && $this->arParams['USE_CAPTCHA'] == 'Y');
		$this->arParams["SHOW_CAPTCHA"] = $this->show_captha ? "Y" : "N"; /** Для старых шаблонов */
		try{
			$this->checkRequiredModules();
			$this->processRequest();
			$this->prepareData();
			$this->performActions();
		}catch(Exception $e){
			$this->errorsFatal[htmlspecialcharsEx($e->getCode())] = htmlspecialcharsEx($e->getMessage());
		}
		
		$this->formatResultErrors();
		
		
		
		if($this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == $this->ajax_id || $this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == "Y"){
			$APPLICATION->RestartBuffer();
		}
		$this->includeComponentTemplate();
		if($this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == $this->ajax_id || $this->requestData['AJAX_CALL_BUY_ONE_CLICK'] == "Y"){
			die();
		}
	}
}
