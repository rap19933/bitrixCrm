<?
namespace h2o\buyoneclick;


use Bitrix\Highloadblock as HL;
use Bitrix\Main;
use Bitrix\Main\Loader;
use Bitrix\Main\Entity;
use Bitrix\Main\Localization\Loc;
use Bitrix\Sale;
use Bitrix\Sale\Delivery;
use Bitrix\Sale\Delivery\Restrictions;
use Bitrix\Sale\DiscountCouponsManager;
use Bitrix\Sale\Location;
use Bitrix\Sale\Location\LocationTable;
use Bitrix\Sale\Order;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem;
use Bitrix\Sale\PersonType;
use Bitrix\Sale\Shipment;
use Bitrix\Main\Context;
use Bitrix\Sale\Internals;

Loc::loadMessages(__FILE__);

/**
 * Class orderD7
 * @package h2o\buyoneclick
 */
class orderD7 extends h2obuyoneclick{
	protected $context;
	protected $arParams;
	protected $arResult;
	protected $arUserResult;
	protected $request;
	protected $prePaymentService;
	protected $order;
	/** @var Delivery\Services\Base[] $arDeliveryServiceAll */
	protected $arDeliveryServiceAll = array();
	protected $arPaySystemServiceAll = array();
	protected $arActivePaySystems = array();
	protected static $cacheUserData = array();

	function __construct($ELEMENT_ID, $user_fields, $user_fields_prop, $params)
	{
		$this->context = Main\Application::getInstance()->getContext();
		$this->arParams = $params;
		$this->request = Context::getCurrent()->getRequest();
		$this->arResult = array();

		parent::__construct($ELEMENT_ID, $user_fields, $user_fields_prop, $params);
	}

	protected function addOrder($PRODUCT_ID,
		$USER_ID,
		$user_name = '',
		$user_email = '',
		$quantity = 1,
		$addProperties = array(),
		$PERSON_TYPE_ID = false,
		$PAY_SYSTEM_ID = false,
		$PRICE_DELIVERY = 0,
		$DELIVERY_ID = false,
		$DISCOUNT_VALUE = 0,
		$USER_DESCRIPTION = "",
		$current_basket = false)
	{
		global $APPLICATION;
		$APPLICATION->RestartBuffer();

		$this->makeUserResultArray();
		$arUserResult = $this->arUserResult;


		if (intval($PERSON_TYPE_ID) > 0){
			$arUserResult["PERSON_TYPE_ID"] = intval($PERSON_TYPE_ID);
			$this->arUserResult["PERSON_TYPE_ID"] = intval($PERSON_TYPE_ID);
		}

		foreach (\GetModuleEvents("sale", 'OnSaleComponentOrderUserResult', true) as $arEvent)
			\ExecuteModuleEventEx($arEvent, array(&$arUserResult, $this->request, &$this->arParams));

		DiscountCouponsManager::init();

		$this->executeEvent('OnSaleComponentOrderOneStepDiscountBefore');
		$order = Order::create($this->context->getSite(), $USER_ID);
		$order->isStartField();
		//$this->initPersonType($order);
		$isPersonTypeChanged = $this->initPersonType($order);
		if ($this->arParams["USE_PREPAYMENT"] == "Y")
			$this->usePrepayment($order);

		if(!empty($addProperties)){
			$this->arUserResult['ORDER_PROP'] = $addProperties;
			$propertyCollection = $order->getPropertyCollection();
			$res = $propertyCollection->setValuesFromPost(array('PROPERTIES' => $addProperties), array());
			if(!$res->isSuccess()){
				$errors = $res->getErrorMessages();
				throw new Main\SystemException(implode("; ",$errors));
			}
		}

		$order->setField("USER_DESCRIPTION", $USER_DESCRIPTION);
		$order->setField("ADDITIONAL_INFO", "BuyOneClick");
		if(!$current_basket){
			$arPrice = $this->GetPriceProduct($PRODUCT_ID, $this->priceCode, "DISCOUNT_VALUE", $USER_ID);
			/** получаем цену продукта, или его торговые предложения если они есть*/
			if($arPrice == 'OFFERS'){
				/** проверка на торговые предложения, если они есть выходим из функции*/
				return $arPrice;
				/** иначе достаем цену продукта*/
			}
			$res = \CIBlockElement::GetByID($PRODUCT_ID);
			if(!($arProduct = $res->GetNext())){
				throw new Main\SystemException(Loc::getMessage('H2O_BUYONECLICK_NOT_ELEMENT'));
			}
			$res = \CIBlockElement::GetProperty($arProduct['IBLOCK_ID'], $PRODUCT_ID, "sort", "asc", array()); //получаем все свойства
			$arProps = array();
			while($ob = $res->GetNext()){
				if(in_array($ob['CODE'], $this->arParams['LIST_OFFERS_PROPERTY_CODE'])){ //проверка на только указанные свойства
					if($ob['VALUE'] == ""){
						continue;
					}
					if($ob['PROPERTY_TYPE'] == 'S'){
						if($ob['USER_TYPE'] == "directory"){    //highload инфоблок (нам нужно достать название свойства)
							$hlblock = \Bitrix\Highloadblock\HighloadBlockTable::getList(array("filter" => array('TABLE_NAME' => $ob['USER_TYPE_SETTINGS']['TABLE_NAME'])))->fetch();
							\CModule::IncludeModule("highloadblock");

							$entity = HL\HighloadBlockTable::compileEntity($hlblock);
							$entity_data_class = $entity->getDataClass();
							$rsPropEnums = $entity_data_class::getList(array('filter' => array('UF_XML_ID' => $ob['VALUE'])));
							if($arEnum = $rsPropEnums->fetch()){
								$arProps[] = array("NAME" => $ob["NAME"], "VALUE" => $arEnum["UF_NAME"]);
							}
						}else{  //string
							$arProps[] = array("NAME" => $ob["NAME"], "VALUE" => $ob["VALUE"]);
						}

					}elseif($ob['PROPERTY_TYPE'] == 'L'){ //"L" - тип список (этот тип хранит нужное значение не в "VALUE", а в "VALUE_ENUM",
						$arProps[] = array(             //для этого выносим его тоже в отдельный оператор)
						                                "NAME" => $ob["NAME"], "VALUE" => $ob["VALUE_ENUM"]);
					}else{                              //для всех остальных свойств
						$arProps[] = array("NAME" => $ob["NAME"], "VALUE" => $ob["VALUE"]);
					}
				}
			}
			$basketFields = array(
				"PRODUCT_ID" => $PRODUCT_ID,
				"QUANTITY" => $quantity,
				"PRICE" => $arPrice['PRICE'],
				"CURRENCY" => $arPrice['CURRENCY'],
				"NAME" => htmlspecialchars_decode($arProduct["NAME"]),
				"DETAIL_PAGE_URL" => $arProduct["DETAIL_PAGE_URL"],
				"PRODUCT_XML_ID" => $arProduct["XML_ID"],
				"CATALOG_XML_ID" => $arProduct["IBLOCK_EXTERNAL_ID"],
			);
			$basket = $this->addBasket(\CSaleBasket::GetBasketUserID(),$basketFields, $arProps, $order);

		}else{
			$basket = Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), $this->context->getSite());
			$result = $basket->refreshData(array('PRICE', 'QUANTITY', 'COUPONS'));
			$basket = $basket->getOrderableItems();
			
			$order->setBasket($basket);
		}




		$shipment = $this->initShipment($order);
		


		$this->initOrderFields($order);

		$order->doFinalAction(true);
		
		$this->initDelivery($shipment);
		$this->initPayment($order);
		$this->recalculatePayment($order);

		foreach (\GetModuleEvents("sale", 'OnSaleComponentOrderCreated', true) as $arEvent)
			\ExecuteModuleEventEx($arEvent, array($order, &$this->arUserResult, $this->request, &$this->arParams));
		$this->order = $order;

		return $this->saveOrder();
	}

	public function addBasket($fuserId, $basketFields, $arProp, $order){
		if(intval($basketFields['PRODUCT_ID']) <= 0){
			return false;
		}
		if(intval($basketFields['QUANTITY']) <= 0){
			$basketFields['QUANTITY'] = 1;
		}
		$basket = Sale\Basket::create(Context::getCurrent()->getSite());
		if ($fuserId !== null)
			$basket->setFUserId($fuserId);
		//$basket->setOrder($order);

		if($item = $basket->getExistsItem('catalog', $basketFields['PRODUCT_ID'])){
			$item->setField('QUANTITY', $item->getQuantity() + $basketFields['QUANTITY']);
		}
		else{

			$item = $basket->createItem('catalog', $basketFields['PRODUCT_ID']);
			if (isset($arProp) && is_array($arProp))
			{
				/** @var Sale\BasketPropertiesCollection $property */
				$property = $item->getPropertyCollection();
				$property->setProperty($arProp);
			}
			$arFields = array(
				'LID' => Context::getCurrent()->getSite(),
				'PRODUCT_PROVIDER_CLASS' => 'CCatalogProductProvider',
			);
			$arFields = array_merge($arFields, $basketFields);
			$item->setFields($arFields);
		}
		$basket->save();
		$order->setBasket($basket);
		return $basket;
	}

	/**
	 * Action - saves order if there are no errors
	 * Execution of 'OnSaleComponentOrderOneStepComplete' event
	 *
	 * @param bool $saveToSession
	 */
	protected function saveOrder($saveToSession = false)
	{
		$arResult =& $this->arResult;

		$this->initStatGid();
		$this->initAffiliate();

		$res = $this->order->save();
		if ($res->isSuccess())
		{
			$arResult["ORDER_ID"] = $res->getId();
			$arResult["ACCOUNT_NUMBER"] = $this->order->getField('ACCOUNT_NUMBER');
		}
		else{
			$e = $res->getErrorMessages();
			throw new Main\SystemException(implode(";",$e));

		}



		/*if ($arResult["HAVE_PREPAYMENT"] && empty($arResult["ERROR"]))
			$this->prepayOrder();*/



		if (empty($arResult["ERROR"]))
		{
			$this->addStatistic();

			if ($saveToSession)
			{
				if (!is_array($_SESSION['SALE_ORDER_ID']))
					$_SESSION['SALE_ORDER_ID'] = array();

				$_SESSION['SALE_ORDER_ID'][] = $res->getId();
			}
		}

		foreach (\GetModuleEvents("sale", "OnSaleComponentOrderOneStepComplete", true) as $arEvent)
			\ExecuteModuleEventEx($arEvent, array($arResult["ORDER_ID"], $this->order->getFieldValues(), $this->arParams));

		if($this->arParams['SEND_MAIL']){
			$this->sendEmailNewOrder();
		}
		$this->obtainFormattedProperties();
		$this->saveProfileData();
		return $arResult;
	}
	
	protected function obtainFormattedProperties()
	{
		$arResult =& $this->arResult;
		$arDeleteFieldLocation = array();
		$propIndex = array();
		$arOrderProps = $this->order->getPropertyCollection()->getArray();
		$propsSortedByGroup = array();
		foreach ($arOrderProps['groups'] as $group)
		{
			foreach ($arOrderProps['properties'] as $prop)
			{
				if ($prop['UTIL'] == 'Y' || !empty($prop['RELATION']))
					continue;
				
				if ($group['ID'] == $prop['PROPS_GROUP_ID'])
				{
					$prop['GROUP_NAME'] = $group['NAME'];
					$propsSortedByGroup[] = $prop;
				}
			}
		}
		
		foreach ($propsSortedByGroup as $arProperty)
		{
			$arProperties = $this->getOrderPropFormatted($arProperty, $arDeleteFieldLocation);
			
			$flag = $arProperties["USER_PROPS"] == "Y" ? 'Y' : 'N';
			
			$arResult["ORDER_PROP"]["USER_PROPS_".$flag][$arProperties["ID"]] = $arProperties;
			$propIndex[$arProperties["ID"]] =& $arResult["ORDER_PROP"]["USER_PROPS_".$flag][$arProperties["ID"]];
			
			$arResult["ORDER_PROP"]["PRINT"][$arProperties["ID"]] = array(
				"ID" => $arProperties["ID"],
				"NAME" => $arProperties["NAME"],
				"VALUE" => $arProperties["VALUE_FORMATED"],
				"SHOW_GROUP_NAME" => $arProperties["SHOW_GROUP_NAME"]
			);
		}
		
		// additional city property process
		foreach ($propIndex as $propId => $propDesc)
		{
			if (intval($propDesc['INPUT_FIELD_LOCATION']) && isset($propIndex[$propDesc['INPUT_FIELD_LOCATION']]))
			{
				$propIndex[$propDesc['INPUT_FIELD_LOCATION']]['IS_ALTERNATE_LOCATION_FOR'] = $propId;
				$propIndex[$propId]['CAN_HAVE_ALTERNATE_LOCATION'] = $propDesc['INPUT_FIELD_LOCATION']; // more strict condition rather INPUT_FIELD_LOCATION, check if the property really exists
			}
		}
		
		//delete prop for text location (town)
		if (count($arDeleteFieldLocation) > 0)
		{
			foreach ($arDeleteFieldLocation as $fieldId)
				unset($arResult["ORDER_PROP"]["USER_PROPS_Y"][$fieldId]);
		}
		
		$this->executeEvent('OnSaleComponentOrderOneStepOrderProps', $this->order);
	}
	
	protected function getOrderPropFormatted($arProperty, &$arDeleteFieldLocation = array())
	{
		static $propertyGroupID = 0;
		static $propertyUSER_PROPS = '';
		
		$arProperty['FIELD_NAME'] = 'ORDER_PROP_'.$arProperty['ID'];
		
		if ($arProperty['CODE'] != '')
		{
			$arProperty['FIELD_ID'] = 'ORDER_PROP_'.$arProperty['CODE'];
		}
		else
		{
			$arProperty['FIELD_ID'] = 'ORDER_PROP_'.$arProperty['ID'];
		}
		
		if (intval($arProperty['PROPS_GROUP_ID']) != $propertyGroupID || $propertyUSER_PROPS != $arProperty['USER_PROPS'])
		{
			$arProperty['SHOW_GROUP_NAME'] = 'Y';
		}
		
		$propertyGroupID = $arProperty['PROPS_GROUP_ID'];
		$propertyUSER_PROPS = $arProperty['USER_PROPS'];
		
		if ($arProperty['REQUIRED'] === 'Y' || $arProperty['IS_PROFILE_NAME'] === 'Y'
			|| $arProperty['IS_LOCATION'] === 'Y' || $arProperty['IS_LOCATION4TAX'] === 'Y'
			|| $arProperty['IS_PAYER'] === 'Y' || $arProperty['IS_ZIP'] === 'Y')
		{
			$arProperty['REQUIED'] = 'Y';
			$arProperty['REQUIED_FORMATED'] = 'Y';
		}
		
		if ($arProperty['IS_LOCATION'] === 'Y')
		{
			$deliveryId = \CSaleLocation::getLocationIDbyCODE(current($arProperty['VALUE']));
			$this->arUserResult['DELIVERY_LOCATION'] = $deliveryId;
			$this->arUserResult['DELIVERY_LOCATION_BCODE'] = current($arProperty['VALUE']);
		}
		
		if ($arProperty['IS_ZIP'] === 'Y')
		{
			$this->arUserResult['DELIVERY_LOCATION_ZIP'] = current($arProperty['VALUE']);
		}
		
		if ($arProperty['IS_LOCATION4TAX'] === 'Y')
		{
			$taxId = \CSaleLocation::getLocationIDbyCODE(current($arProperty['VALUE']));
			$this->arUserResult['TAX_LOCATION'] = $taxId;
			$this->arUserResult['TAX_LOCATION_BCODE'] = current($arProperty['VALUE']);
		}
		
		if ($arProperty['IS_PAYER'] === 'Y')
		{
			$this->arUserResult['PAYER_NAME'] = current($arProperty['VALUE']);
		}
		
		if ($arProperty['IS_EMAIL'] === 'Y')
		{
			$this->arUserResult['USER_EMAIL'] = current($arProperty['VALUE']);
		}
		
		if ($arProperty['IS_PROFILE_NAME'] === 'Y')
		{
			$this->arUserResult['PROFILE_NAME'] = current($arProperty['VALUE']);
		}
		
		switch ($arProperty['TYPE'])
		{
			case 'Y/N': self::formatYN($arProperty); break;
			case 'STRING': self::formatString($arProperty); break;
			case 'NUMBER': self::formatNumber($arProperty); break;
			case 'ENUM': self::formatEnum($arProperty); break;
			case 'LOCATION':
				self::formatLocation($arProperty, $arDeleteFieldLocation, $this->arResult['LOCATION_ALT_PROP_DISPLAY_MANUAL']);
				break;
			case 'FILE': self::formatFile($arProperty); break;
			case 'DATE': self::formatDate($arProperty); break;
		}
		
		return $arProperty;
	}
	
	public static function formatYN(array &$arProperty)
	{
		$curVal = $arProperty['VALUE'];
		
		if (current($curVal) == "Y")
		{
			$arProperty["CHECKED"] = "Y";
			$arProperty["VALUE_FORMATED"] = Loc::getMessage("SOA_Y");
		}
		else
			$arProperty["VALUE_FORMATED"] = Loc::getMessage("SOA_N");
		
		$arProperty["SIZE1"] = (intval($arProperty["SIZE1"]) > 0) ? $arProperty["SIZE1"] : 30;
		
		$arProperty["VALUE"] = current($curVal);
		$arProperty["TYPE"] = 'CHECKBOX';
	}
	
	public static function formatString(array &$arProperty)
	{
		$curVal = $arProperty['VALUE'];
		
		if (!empty($arProperty["MULTILINE"]) && $arProperty["MULTILINE"] == 'Y')
		{
			$arProperty["TYPE"] = 'TEXTAREA';
			$arProperty["SIZE2"] = (intval($arProperty["ROWS"]) > 0) ? $arProperty["ROWS"] : 4;
			$arProperty["SIZE1"] = (intval($arProperty["COLS"]) > 0) ? $arProperty["COLS"] : 40;
		}
		else
			$arProperty["TYPE"] = 'TEXT';
		
		$arProperty["SOURCE"] = current($curVal) == $arProperty['DEFAULT_VALUE'] ? 'DEFAULT' : 'FORM';
		$arProperty["VALUE"] = current($curVal);
		$arProperty["VALUE_FORMATED"] = $arProperty["VALUE"];
	}
	
	public static function formatNumber(array &$arProperty)
	{
		$curVal = $arProperty['VALUE'];
		$arProperty["TYPE"] = 'TEXT';
		$arProperty["VALUE"] = current($curVal);
		$arProperty["VALUE_FORMATED"] = $arProperty["VALUE"];
	}
	
	public static function formatEnum(array &$arProperty)
	{
		$curVal = $arProperty['VALUE'];
		
		if ($arProperty["MULTIELEMENT"] == 'Y')
		{
			if ($arProperty["MULTIPLE"] == 'Y')
			{
				$setValue = array();
				$arProperty["FIELD_NAME"] = "ORDER_PROP_".$arProperty["ID"].'[]';
				$arProperty["SIZE1"] = (intval($arProperty["SIZE1"]) > 0) ? $arProperty["SIZE1"] : 5;
				
				$i = 0;
				foreach ($arProperty["OPTIONS"] as $val => $name)
				{
					$arVariants = array(
						'VALUE' => $val,
						'NAME' => $name
					);
					if ((is_array($curVal) && in_array($arVariants["VALUE"], $curVal)))
					{
						$arVariants["SELECTED"] = "Y";
						if ($i > 0)
							$arProperty["VALUE_FORMATED"] .= ", ";
						$arProperty["VALUE_FORMATED"] .= $arVariants["NAME"];
						$setValue[] = $arVariants["VALUE"];
						$i++;
					}
					$arProperty["VARIANTS"][] = $arVariants;
				}
				
				$arProperty["TYPE"] = 'MULTISELECT';
			}
			else
			{
				foreach ($arProperty['OPTIONS'] as $val => $name)
				{
					$arVariants = array(
						'VALUE' => $val,
						'NAME' => $name
					);
					if ($arVariants["VALUE"] == current($curVal))
					{
						$arVariants["CHECKED"] = "Y";
						$arProperty["VALUE_FORMATED"] = $arVariants["NAME"];
					}
					
					$arProperty["VARIANTS"][] = $arVariants;
				}
				$arProperty["TYPE"] = 'RADIO';
			}
		}
		else
		{
			if ($arProperty["MULTIPLE"] == 'Y')
			{
				$setValue = array();
				$arProperty["FIELD_NAME"] = "ORDER_PROP_".$arProperty["ID"].'[]';
				$arProperty["SIZE1"] = ((intval($arProperty["SIZE1"]) > 0) ? $arProperty["SIZE1"] : 5);
				
				$i = 0;
				foreach ($arProperty["OPTIONS"] as $val => $name)
				{
					$arVariants = array(
						'VALUE' => $val,
						'NAME' => $name
					);
					if (is_array($curVal) && in_array($arVariants["VALUE"], $curVal))
					{
						$arVariants["SELECTED"] = "Y";
						if ($i > 0)
							$arProperty["VALUE_FORMATED"] .= ", ";
						$arProperty["VALUE_FORMATED"] .= $arVariants["NAME"];
						$setValue[] = $arVariants["VALUE"];
						$i++;
					}
					$arProperty["VARIANTS"][] = $arVariants;
				}
				
				$arProperty["TYPE"] = 'MULTISELECT';
			}
			else
			{
				$arProperty["SIZE1"] = ((intval($arProperty["SIZE1"]) > 0) ? $arProperty["SIZE1"] : 1);
				$flagDefault = "N";
				$nameProperty = "";
				foreach ($arProperty["OPTIONS"] as $val => $name)
				{
					$arVariants = array(
						'VALUE' => $val,
						'NAME' => $name
					);
					if ($flagDefault == "N" && $nameProperty == "")
					{
						$nameProperty = $arVariants["NAME"];
					}
					if ($arVariants["VALUE"] == current($curVal))
					{
						$arVariants["SELECTED"] = "Y";
						$arProperty["VALUE_FORMATED"] = $arVariants["NAME"];
						$flagDefault = "Y";
					}
					$arProperty["VARIANTS"][] = $arVariants;
				}
				if ($flagDefault == "N")
				{
					$arProperty["VARIANTS"][0]["SELECTED"]= "Y";
					$arProperty["VARIANTS"][0]["VALUE_FORMATED"] = $nameProperty;
				}
				$arProperty["TYPE"] = 'SELECT';
			}
		}
	}
	
	public static function formatLocation(array &$arProperty, array &$arDeleteFieldLocation, $locationAltPropDisplayManual = null)
	{
		$curVal = \CSaleLocation::getLocationIDbyCODE(current($arProperty['VALUE']));
		$arProperty["VALUE"] = $curVal;
		
		$locationFound = false;
		//todo select via D7
		$dbVariants = \CSaleLocation::GetList(
			array("SORT" => "ASC", "COUNTRY_NAME_LANG" => "ASC", "CITY_NAME_LANG" => "ASC"),
			array("LID" => LANGUAGE_ID),
			false,
			false,
			array("ID", "COUNTRY_NAME", "CITY_NAME", "SORT", "COUNTRY_NAME_LANG", "CITY_NAME_LANG", "CITY_ID", "CODE")
		);
		while ($arVariants = $dbVariants->GetNext())
		{
			$city = !empty($arVariants['CITY_NAME']) ? ' - '.$arVariants['CITY_NAME'] : '';
			
			if ($arVariants['ID'] === $curVal)
			{
				// set formatted value
				$locationFound = $arVariants;
				$arVariants['SELECTED'] = 'Y';
				$arProperty['VALUE_FORMATED'] = $arVariants['COUNTRY_NAME'].$city;
			}
			
			$arVariants['NAME'] = $arVariants['COUNTRY_NAME'].$city;
			// save to variants
			$arProperty['VARIANTS'][] = $arVariants;
		}
		
		if(!$locationFound && intval($curVal))
		{
			$item = \CSaleLocation::GetById($curVal);
			if ($item)
			{
				// set formatted value
				$locationFound = $item;
				$arProperty["VALUE_FORMATED"] = $item["COUNTRY_NAME"].((strlen($item["CITY_NAME"]) > 0) ? " - " : "").$item["CITY_NAME"];
				$item['SELECTED'] = 'Y';
				$item['NAME'] = $item["COUNTRY_NAME"].((strlen($item["CITY_NAME"]) > 0) ? " - " : "").$item["CITY_NAME"];
				
				// save to variants
				$arProperty["VARIANTS"][] = $item;
			}
		}
		
		if ($locationFound)
		{
			// enable location town text
			if (isset($locationAltPropDisplayManual)) // its an ajax-hit and sale.location.selector.steps is used
			{
				if (intval($locationAltPropDisplayManual[$arProperty["ID"]])) // user MANUALLY selected "Other location" in the selector
					unset($arDeleteFieldLocation[$arProperty["ID"]]);
				else
					$arDeleteFieldLocation[$arProperty["ID"]] = $arProperty["INPUT_FIELD_LOCATION"];
			}
			else
			{
				if ($arProperty["IS_LOCATION"] == "Y" && intval($arProperty["INPUT_FIELD_LOCATION"]) > 0)
				{
					if (intval($locationFound["CITY_ID"]) <= 0)
						unset($arDeleteFieldLocation[$arProperty["ID"]]);
					else
						$arDeleteFieldLocation[$arProperty["ID"]] = $arProperty["INPUT_FIELD_LOCATION"];
				}
			}
		}
		else
		{
			// nothing found, may be it is the first load - hide
			$arDeleteFieldLocation[$arProperty["ID"]] = $arProperty["INPUT_FIELD_LOCATION"];
		}
	}
	
	public static function formatFile(array &$arProperty)
	{
		$curVal = $arProperty['VALUE'];
		
		$arProperty["SIZE1"] = intval($arProperty["SIZE1"]);
		if ($arProperty['MULTIPLE'] == 'Y')
		{
			$arr = array();
			$curVal = isset($curVal) ? $curVal : $arProperty["DEFAULT_VALUE"];
			foreach ($curVal as $file)
			{
				$arr[] = $file['ID'];
			}
			$arProperty["VALUE"] = serialize($arr);
		}
		else
		{
			$arFile = isset($curVal) && is_array($curVal) ? current($curVal) : $arProperty["DEFAULT_VALUE"];
			if (is_array($arFile))
				$arProperty["VALUE"] = $arFile['ID'];
		}
	}
	
	public static function formatDate(array &$arProperty)
	{
		$arProperty["VALUE"] = current($arProperty['VALUE']);
		$arProperty["VALUE_FORMATED"] = $arProperty["VALUE"];
	}
	
	protected function saveProfileData()
	{
		$arResult =& $this->arResult;
		$profileId = null;
		$profileName = '';
		$properties = array();
		
		if (isset($arResult['ORDER_PROP']) && is_array($arResult['ORDER_PROP']['USER_PROFILES']))
		{
			foreach ($arResult['ORDER_PROP']['USER_PROFILES'] as $profile)
			{
				if ($profile['CHECKED'] == 'Y')
				{
					$profileId = $profile['ID'];
					break;
				}
			}
		}
		
		$propertyCollection = $this->order->getPropertyCollection();
		if (!empty($propertyCollection))
		{
			if ($profileProp = $propertyCollection->getProfileName())
				$profileName = $profileProp->getValue();
			
			/** @var Sale\PropertyValue $property */
			foreach ($propertyCollection as $property)
			{
				$properties[$property->getField('ORDER_PROPS_ID')] = $property->getValue();
			}
		}

		$dbUserProfiles = \CSaleOrderUserProps::GetList(
			array("DATE_UPDATE" => "DESC"),
			array(
				"PERSON_TYPE_ID" => $this->order->getPersonTypeId(),
				"USER_ID" => $this->order->getUserId()
			)
		);
		$profileID = null;
		if ($arUserProfiles = $dbUserProfiles->GetNext())
		{
			$profileID = $arUserProfiles['ID'];
		}
		\CSaleOrderUserProps::DoSaveUserProfile(
			$this->order->getUserId(),
			$profileID,
			$profileName,
			$this->order->getPersonTypeId(),
			$properties,
			$arResult["ERROR"]
		);
	}

	protected function sendEmailNewOrder()
	{
		if (!$this->order instanceof Order)
		{
			throw new Main\ArgumentTypeException('entity', '\Bitrix\Sale\Order');
		}

		$basketList = '';
		/** @var Basket $basket */
		if ($basket = $this->order->getBasket())
		{
			if ($basketTextList = $basket->getListOfFormatText())
			{
				foreach ($basketTextList as $basketItemCode => $basketItemData)
				{
					$basketList .= $basketItemData."\n";
				}
			}
		}

		$fields = Array(
				"ORDER_ID" => $this->order->getField("ACCOUNT_NUMBER"),
				"ORDER_REAL_ID" => $this->order->getField("ID"),
				"ORDER_ACCOUNT_NUMBER_ENCODE" => urlencode(urlencode($this->order->getField("ACCOUNT_NUMBER"))),
				"ORDER_DATE" => $this->order->getDateInsert()->toString(),
				"ORDER_USER" => static::getUserName($this->order),
				"PRICE" => SaleFormatCurrency($this->order->getPrice(), $this->order->getCurrency()),
				"BCC" => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER["SERVER_NAME"]),
				"EMAIL" => static::getUserEmail($this->order),
				"ORDER_LIST" => $basketList,
				"SALE_EMAIL" => Main\Config\Option::get("sale", "order_email", "order@".$_SERVER["SERVER_NAME"]),
				"DELIVERY_PRICE" => $this->order->getDeliveryPrice(),
		);

		$eventName = "SALE_NEW_ORDER";
		$send = true;

		foreach(GetModuleEvents("sale", "OnOrderNewSendEmail", true) as $oldEvent)
		{
			if (ExecuteModuleEventEx($oldEvent, array($this->order->getId(), &$eventName, &$fields)) === false)
			{
				$send = false;
			}
		}

		if($send)
		{
			$event = new \CEvent;
			if(is_array($this->arParams['EVENT_MESSAGE_ID']) && !empty($this->arParams['EVENT_MESSAGE_ID'])){
				foreach($this->arParams['EVENT_MESSAGE_ID'] as $event_id){
					$event->Send($eventName, SITE_ID, $fields, "N", $event_id);
				}

			}else{
				$event->Send($eventName, SITE_ID, $fields, "N");
			}
		}

		//\CSaleMobileOrderPush::send(static::EVENT_MOBILE_PUSH_ORDER_CREATED, array("ORDER" => static::getOrderFields($this->order)));
	}

	protected function addStatistic()
	{
		if (Loader::includeModule("statistic"))
		{
			$event1 = "eStore";
			$event2 = "order_confirm";
			$event3 = $this->order->getId();

			$e = $event1."/".$event2."/".$event3;

			if (!is_array($_SESSION["ORDER_EVENTS"]) || (is_array($_SESSION["ORDER_EVENTS"]) && !in_array($e, $_SESSION["ORDER_EVENTS"])))
			{
				\CStatistic::Set_Event($event1, $event2, $event3);
				$_SESSION["ORDER_EVENTS"][] = $e;
			}
		}
	}

	protected function makeUserResultArray()
	{

		$arUserResult = array(
			"PERSON_TYPE_ID" => false,
			"PERSON_TYPE_OLD" => false,
			"PAY_SYSTEM_ID" => false,
			"DELIVERY_ID" => false,
			"ORDER_PROP" => false,
			"DELIVERY_LOCATION" => false,
			"TAX_LOCATION" => false,
			"PAYER_NAME" => false,
			"USER_EMAIL" => false,
			"PROFILE_NAME" => false,
			"PAY_CURRENT_ACCOUNT" => false,
			"CONFIRM_ORDER" => false,
			"FINAL_STEP" => false,
			"ORDER_DESCRIPTION" => false,
			"PROFILE_ID" => false,
			"PROFILE_CHANGE" => false,
			"DELIVERY_LOCATION_ZIP" => false
		);

		$this->arUserResult = $arUserResult;
	}

	/**
	 * Initialization of shipment object. Filling with basket items.
	 *
	 * @param Order $order
	 * @return Shipment
	 * @throws Main\ArgumentTypeException
	 * @throws Main\NotSupportedException
	 */
	public function initShipment(Order $order)
	{
		$shipmentCollection = $order->getShipmentCollection();
		$shipment = $shipmentCollection->createItem();
		$shipmentItemCollection = $shipment->getShipmentItemCollection();
		$shipment->setField('CURRENCY', $order->getCurrency());

		/** @var Sale\BasketItem $item */
		foreach ($order->getBasket() as $item)
		{
			/** @var Sale\ShipmentItem $shipmentItem */
			$shipmentItem = $shipmentItemCollection->createItem($item);
			$shipmentItem->setQuantity($item->getQuantity());
		}

		return $shipment;
	}

	/**
	 * Initialization of inner/external payment objects with first/selected pay system services.
	 *
	 * @param Order $order
	 * @throws Main\ObjectNotFoundException
	 */
	protected function initPayment(Order $order)
	{
		$paySystemId = intval($this->PAY_SYSTEM_ID);
		$paymentCollection = $order->getPaymentCollection();
		$innerPayment = null;

		list($sumToSpend, $arPsFromInner) = $this->getInnerPaySystemInfo($order);

		if ($sumToSpend > 0)
		{
			$this->arPaySystemServiceAll = $arPsFromInner;
			$this->arActivePaySystems = $arPsFromInner;

			if ($this->arUserResult['PAY_CURRENT_ACCOUNT'] == "Y")
			{
				$innerPayment = $this->getInnerPayment($order);
				$sumToPay = $sumToSpend >= $order->getPrice() ? $order->getPrice() : $sumToSpend;
				$innerPayment->setField('SUM', $sumToPay);
			}
			else
			{
				$paymentCollection->getInnerPayment()->delete();
			}
		}

		$remainingSum = $order->getPrice() - $paymentCollection->getSum();

		if ($remainingSum > 0 || $order->getPrice() == 0)
		{
			/** @var Payment $extPayment */
			$extPayment = $paymentCollection->createItem();
			$extPayment->setField('SUM', $remainingSum);
			$arPaySystemServices = PaySystem\Manager::getListWithRestrictions($extPayment);

			if ($sumToSpend > 0)
				$this->arActivePaySystems = array_intersect_key($this->arActivePaySystems, $arPaySystemServices);
			else
				$this->arActivePaySystems = $arPaySystemServices;

			$this->arPaySystemServiceAll += $arPaySystemServices;

			if (array_key_exists($paySystemId, $this->arActivePaySystems))
			{
				$arPaySystem = $this->arActivePaySystems[$paySystemId];
			}
			else
			{
				reset($this->arActivePaySystems);

				if (key($this->arActivePaySystems) == PaySystem\Manager::getInnerPaySystemId())
				{
					if ($sumToSpend > 0)
					{
						if (count($this->arActivePaySystems) > 1)
						{
							next($this->arActivePaySystems);
						}
						else if (empty($innerPayment))
						{
							$remainingSum = $remainingSum > $sumToSpend ? $sumToSpend : $remainingSum;
							$extPayment->setField('SUM', $remainingSum);
						}
						else
							$extPayment->delete();

						$remainingSum = $order->getPrice() - $paymentCollection->getSum();
						if ($remainingSum > 0)
						{
							$this->addWarning(Loc::getMessage("INNER_PAYMENT_BALANCE_ERROR"), "PAY_SYSTEM");
							$order->setFields(array(
								'MARKED' => 'Y',
								'REASON_MARKED' => Loc::getMessage("INNER_PAYMENT_BALANCE_ERROR")
							));
						}
					}
					else
					{
						unset($this->arActivePaySystems[PaySystem\Manager::getInnerPaySystemId()]);
						unset($this->arPaySystemServiceAll[PaySystem\Manager::getInnerPaySystemId()]);
					}
				}

				$arPaySystem = current($this->arActivePaySystems);

				if (!empty($arPaySystem) && $paySystemId != 0)
					$this->addWarning(Loc::getMessage("PAY_SYSTEM_CHANGE_WARNING"), "PAY_SYSTEM");
			}

			if (!empty($arPaySystem))
			{
				$extPayment->setFields(array(
					'PAY_SYSTEM_ID' => $arPaySystem["ID"],
					'PAY_SYSTEM_NAME' => $arPaySystem["NAME"]
				));
				$this->SetPAY_SYSTEM_ID($arPaySystem["ID"]);
			}
			else
				$extPayment->delete();
		}

		if (empty($this->arPaySystemServiceAll))
			throw new Main\SystemException(Loc::getMessage("SOA_ERROR_PAY_SYSTEM"));

		if (!empty($this->arUserResult["PREPAYMENT_MODE"]))
			$this->showOnlyPrepaymentPs($paySystemId);
	}

	/**
	 * Initialization of shipment object with first/selected delivery service.
	 *
	 * @param Shipment $shipment
	 * @throws Main\NotSupportedException
	 */
	protected function initDelivery(Shipment $shipment)
	{
		$deliveryId = $this->DELIVERY_ID;
		$shipmentCollection = $shipment->getCollection();
		$order = $shipmentCollection->getOrder();
		if(!isset($deliveryId)){
			$deliveryId = Delivery\Services\EmptyDeliveryService::getEmptyDeliveryServiceId();
		}
		$service = Delivery\Services\Manager::getById($deliveryId);
		$deliveryObj = Delivery\Services\Manager::createObject($service);
		try
		{
			$calcResult = $deliveryObj->calculate($shipment);
			if ($calcResult->isSuccess()){
				$price = $calcResult->getPrice();
			}
		}
		catch (\Exception $exception)
		{
			//цена доставки не расчитывается для автоматизированных доставок
		}

		$shipment->setFields(array(
				'DELIVERY_ID' => $service['ID'],
				'DELIVERY_NAME' => $service['NAME'],
				'CURRENCY' => $order->getCurrency(),
				'PRICE_DELIVERY' => $price
		));

	}

	/**
	 * Check required fields for actual properties(with/without relations). Set user description.
	 *
	 * @param Order $order
	 * @throws Main\ObjectNotFoundException
	 */
	protected function initOrderFields(Order $order)
	{

		$actualProperties = array();
		$paymentSystemIds = $order->getPaymentSystemId();
		$deliverySystemIds = $order->getDeliverySystemId();
		$propertyCollection = $order->getPropertyCollection();
		/** @var Sale\PropertyValue $property */
		foreach ($propertyCollection as $property)
		{
			if ($property->isUtil())
				continue;

			$arProperty = $property->getProperty();
			if (isset($arProperty['RELATION'])
				&& !$this->checkRelatedProperty($arProperty, $paymentSystemIds, $deliverySystemIds)
			)
			{
				unset($this->arUserResult['ORDER_PROP'][$property->getPropertyId()]);
				continue;
			}

			$actualProperties[$property->getPropertyId()] = $this->arUserResult['ORDER_PROP'][$property->getPropertyId()];
		}

		/*$res = $propertyCollection->checkRequired(array_keys($actualProperties), array('PROPERTIES' => $actualProperties));
		if (!$res->isSuccess()){
			$errors = $res->getErrorMessages();
			throw new Main\SystemException(implode("; ",$errors));
		}*/
	}

	/**
	 * Returns true if current property is valid for selected payment & delivery
	 *
	 * @param $property
	 * @param $arPaymentId
	 * @param $arDeliveryId
	 * @return bool
	 */
	protected function checkRelatedProperty($property, $arPaymentId, $arDeliveryId)
	{
		$okByPs = null;
		$okByDelivery = null;

		if (is_array($property['RELATION']) && !empty($property['RELATION']))
		{
			foreach ($property['RELATION'] as $relation)
			{
				if (empty($okByPs) && $relation['ENTITY_TYPE'] == 'P')
					$okByPs = in_array($relation['ENTITY_ID'], $arPaymentId);

				if (empty($okByDelivery) && $relation['ENTITY_TYPE'] == 'D')
					$okByDelivery = in_array($relation['ENTITY_ID'], $arDeliveryId);
			}
		}

		return ((is_null($okByPs) || $okByPs) && (is_null($okByDelivery) || $okByDelivery));
	}

	/**
	 * Recalculates payment prices which could change due to shipment/discounts.
	 *
	 * @param Order $order
	 * @throws Main\ObjectNotFoundException
	 */
	protected function recalculatePayment(Order $order)
	{
		$paySystemId = intval($this->PAY_SYSTEM_ID);
		$paymentCollection = $order->getPaymentCollection();

		list($sumToSpend, $arPsFromInner) = $this->getInnerPaySystemInfo($order, true);

		if ($this->arUserResult['PAY_CURRENT_ACCOUNT'] == "Y" && $sumToSpend > 0)
		{
			$this->arActivePaySystems = array_intersect_key($this->arActivePaySystems, $arPsFromInner);
			if ($innerPayment = $this->getInnerPayment($order))
			{
				$sumToPay = $sumToSpend >= $order->getPrice() ? $order->getPrice() : $sumToSpend;
				$innerPayment->setField('SUM', $sumToPay);
			}
		}
		else
		{
			$paymentCollection->getInnerPayment()->delete();
		}

		/** @var Payment $extPayment */
		$extPayment = $this->getExternalPayment($order);
		$remainingSum = empty($innerPayment) ? $order->getPrice() : $order->getPrice() - $innerPayment->getSum();
		if ($remainingSum > 0 || $order->getPrice() == 0)
		{
			if (empty($extPayment))
			{
				$extPayment = $paymentCollection->createItem();
			}
			$extPayment->setField('SUM', $remainingSum);

			$this->arActivePaySystems = array_intersect_key($this->arActivePaySystems, PaySystem\Manager::getListWithRestrictions($extPayment));
			if (array_key_exists($paySystemId, $this->arActivePaySystems))
			{
				$arPaySystem = $this->arActivePaySystems[$paySystemId];
			}
			else if (array_key_exists($paySystemId, $this->arPaySystemServiceAll))
			{
				$arPaySystem = $this->arPaySystemServiceAll[$paySystemId];
			}
			else
			{
				if (key($this->arActivePaySystems) == PaySystem\Manager::getInnerPaySystemId())
				{
					if ($sumToSpend > 0)
					{
						if (count($this->arActivePaySystems) > 1)
						{
							next($this->arActivePaySystems);
						}
						else if (empty($innerPayment))
						{
							$remainingSum = $remainingSum > $sumToSpend ? $sumToSpend : $remainingSum;
							$extPayment->setField('SUM', $remainingSum);
						}
						else
							$extPayment->delete();

						$remainingSum = $order->getPrice() - $paymentCollection->getSum();
						if ($remainingSum > 0)
						{
							$this->addWarning(Loc::getMessage("INNER_PAYMENT_BALANCE_ERROR"), "PAY_SYSTEM");
							$order->setFields(array(
								'MARKED' => 'Y',
								'REASON_MARKED' => Loc::getMessage("INNER_PAYMENT_BALANCE_ERROR")
							));
						}
					}
					else
					{
						unset($this->arActivePaySystems[PaySystem\Manager::getInnerPaySystemId()]);
						unset($this->arPaySystemServiceAll[PaySystem\Manager::getInnerPaySystemId()]);
					}
				}

				$arPaySystem = current($this->arActivePaySystems);
			}

			if (!array_key_exists(intval($arPaySystem['ID']), $this->arActivePaySystems))
			{
				throw new Main\SystemException(Loc::getMessage("P2D_CALCULATE_ERROR"));

			}

			if (!empty($arPaySystem))
			{
				$needSum = !empty($innerPayment) ? $order->getPrice() - $innerPayment->getSum() : $order->getPrice();
				$extPayment->setFields(array(
					'PAY_SYSTEM_ID' => $arPaySystem["ID"],
					'PAY_SYSTEM_NAME' => $arPaySystem["NAME"],
					'SUM' => $needSum
				));
				$this->PAY_SYSTEM_ID = $arPaySystem["ID"];
			}

			if (!empty($this->arUserResult["PREPAYMENT_MODE"]))
				$this->showOnlyPrepaymentPs($paySystemId);
		}

		if (!empty($innerPayment) && !empty($extPayment) && $remainingSum == 0)
		{
			$extPayment->delete();
		}
	}

	protected function initStatGid()
	{
		if (Loader::includeModule("statistic"))
			$this->order->setField('STAT_GID', \CStatistic::GetEventParam());
	}

	protected function initAffiliate()
	{
		$affiliateID = \CSaleAffiliate::GetAffiliate();
		if ($affiliateID > 0)
		{
			$dbAffiliate = \CSaleAffiliate::GetList(array(), array("SITE_ID" => $this->context->getSite(), "ID" => $affiliateID));
			$arAffiliates = $dbAffiliate->Fetch();
			if (count($arAffiliates) > 1)
				$this->order->setField('AFFILIATE_ID', $affiliateID);
		}
	}

	/**
	 * Set user budget data to $this->arResult. Returns sum to spend(including restrictions).
	 *
	 * @param Order $order
	 * @param bool  $recalculate
	 * @return array
	 * @throws Main\ObjectNotFoundException
	 */
	protected function getInnerPaySystemInfo(Order $order, $recalculate = false)
	{
		$arResult =& $this->arResult;
		$innerPsId = PaySystem\Manager::getInnerPaySystemId();
		$arPaySystemServices = array();
		$sumToSpend = 0;

		if ($this->arParams["PAY_FROM_ACCOUNT"] == "Y")
		{
			$this->loadUserAccount($order);

			if (!empty($arResult["USER_ACCOUNT"]) && $arResult["USER_ACCOUNT"]["CURRENT_BUDGET"] > 0)
			{
				$innerPayment = $order->getPaymentCollection()->getInnerPayment();
				$arPaySystemServices = $recalculate ? $this->arPaySystemServiceAll : PaySystem\Manager::getListWithRestrictions($innerPayment);

				if (array_key_exists($innerPsId, $arPaySystemServices))
				{
					$userBudget = floatval($arResult["USER_ACCOUNT"]["CURRENT_BUDGET"]);
					$sumRange = Sale\Services\PaySystem\Restrictions\Manager::getPriceRange($innerPayment,  $innerPsId);

					if ($this->arParams['ONLY_FULL_PAY_FROM_ACCOUNT'] == 'Y')
						$sumRange['MIN'] = $order->getPrice();

					if (!empty($sumRange))
					{
						if ((empty($sumRange['MIN']) || $sumRange['MIN'] <= $userBudget)
							&& (empty($sumRange['MAX']) || $sumRange['MAX'] >= $userBudget))
							$sumToSpend = $userBudget;

						if (!empty($sumRange['MAX']) && $sumRange['MAX'] <= $userBudget)
							$sumToSpend = $sumRange['MAX'];
					}
					else
						$sumToSpend = $userBudget;

					if ($sumToSpend > 0)
					{
						$arResult["PAY_FROM_ACCOUNT"] = "Y";
						$arResult["CURRENT_BUDGET_FORMATED"] = SaleFormatCurrency($arResult["USER_ACCOUNT"]["CURRENT_BUDGET"], $order->getCurrency());
					}
					else
					{
						$arResult["PAY_FROM_ACCOUNT"] = "N";
						unset($arResult["CURRENT_BUDGET_FORMATED"]);
					}
				}
				else
					$arResult["PAY_FROM_ACCOUNT"] = "N";
			}
			else
				$arResult["PAY_FROM_ACCOUNT"] = "N";
		}

		return array($sumToSpend, $arPaySystemServices);
	}

	protected function loadUserAccount(Order $order)
	{
		if (!isset($this->arResult["USER_ACCOUNT"]))
		{
			$dbUserAccount = \CSaleUserAccount::GetList(
				array(),
				array(
					"USER_ID" => $order->getUserId(),
					"CURRENCY" => $order->getCurrency(),
				)
			);
			$this->arResult["USER_ACCOUNT"] = $dbUserAccount->Fetch();
		}
	}

	public function getInnerPayment(Order $order)
	{
		/** @var Payment $payment */
		foreach ($order->getPaymentCollection() as $payment)
		{
			if ($payment->getPaymentSystemId() == PaySystem\Manager::getInnerPaySystemId())
				return $payment;
		}

		return null;
	}

	protected function addWarning($res, $type)
	{
		if (!empty($type))
			$this->arResult["WARNING"][$type][] = $res;
	}

	protected function showOnlyPrepaymentPs($paySystemId)
	{
		if (empty($this->arPaySystemServiceAll) || intval($paySystemId) == 0)
			return;

		foreach ($this->arPaySystemServiceAll as $key => $psService)
		{
			if ($paySystemId != $psService['ID'])
				unset($this->arPaySystemServiceAll[$key]);
		}
	}

	/**
	 * Initialization of person types. Set person type data to $this->arResult.
	 * Return true if person type changed.
	 * Execution of 'OnSaleComponentOrderOneStepPersonType' event
	 *
	 * @param Order $order
	 * @return bool
	 * @throws Main\ArgumentException
	 */
	protected function initPersonType(Order $order)
	{
		$arResult =& $this->arResult;
		$personTypeId = intval($this->arUserResult['PERSON_TYPE_ID']);
		$personTypeIdOld = intval($this->arUserResult['PERSON_TYPE_OLD']);

		$personTypes = PersonType::load($this->context->getSite());
		foreach ($personTypes as $personType)
		{
			if ($personTypeId === intval($personType["ID"]) || $personTypeId == 0)
			{
				$personTypeId = intval($personType["ID"]);
				$order->setPersonTypeId($personTypeId);
				$this->arUserResult['PERSON_TYPE_ID'] = $personTypeId;
				$personType["CHECKED"] = "Y";
			}
			$arResult["PERSON_TYPE"][$personType["ID"]] = $personType;
		}

		if ($personTypeId == 0){
			throw new Main\SystemException(Loc::getMessage("SOA_ERROR_PERSON_TYPE"));
		}


		$this->executeEvent('OnSaleComponentOrderOneStepPersonType', $order);

		return count($arResult["PERSON_TYPE"]) > 1 && ($personTypeId !== $personTypeIdOld);
	}

	public function getExternalPayment(Order $order)
	{
		/** @var Payment $payment */
		foreach ($order->getPaymentCollection() as $payment)
		{
			if ($payment->getPaymentSystemId() != PaySystem\Manager::getInnerPaySystemId())
				return $payment;
		}

		return null;
	}

	/**
	 * Check if PayPal prepayment is available
	 *
	 * @param Order $order
	 * @throws Main\ArgumentException
	 * @throws Main\NotSupportedException
	 */
	protected function usePrepayment(Order $order)
	{
		global $APPLICATION;
		$arResult =& $this->arResult;

		$arPersonTypes = PersonType::load($this->context->getSite());
		$arPersonTypes = array_keys($arPersonTypes);
		if (!empty($arPersonTypes))
		{
			$paySysAction = PaySystem\Manager::getList(array(
					'select' => array(
							"ID", "PAY_SYSTEM_ID", "PERSON_TYPE_ID", "NAME", "ACTION_FILE", "RESULT_FILE",
							"NEW_WINDOW", "PARAMS", "ENCODING", "LOGOTIP"
					),
					'filter'  => array(
							"ACTIVE" => "Y",
							"HAVE_PREPAY" => "Y",
							"PERSON_TYPE_ID" => $arPersonTypes,
					)
			));
			if ($arPaySysAction = $paySysAction->fetch())
			{
				$arResult["PREPAY_PS"] = $arPaySysAction;
				$arResult["HAVE_PREPAYMENT"] = true;

				$this->prePaymentService = new PaySystem\Service($arPaySysAction);
				if ($this->prePaymentService->isPrePayable())
				{
					$this->prePaymentService->initPrePayment(null, $this->request);
					if ($this->request->get('paypal') == 'Y' && $this->request->get('token'))
					{
						$arResult["PREPAY_ORDER_PROPS"] = $this->prePaymentService->getPrePaymentProps();
						if (intval($this->PAY_SYSTEM_ID) <= 0)
						{
							$this->arUserResult["PERSON_TYPE_ID"] = $arResult["PREPAY_PS"]["PERSON_TYPE_ID"];
						}
						$this->arUserResult["PREPAYMENT_MODE"] = true;
						$this->arUserResult["PAY_SYSTEM_ID"] = $arResult["PREPAY_PS"]["ID"];
					}
					else
					{
						if ($this->arUserResult["PAY_SYSTEM_ID"] == $arResult["PREPAY_PS"]["ID"])
						{
							$basketItems = array();
							$basket = Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), $this->context->getSite())->getOrderableItems();
							/** @var Sale\BasketItem $item */
							foreach ($basket as $key => $item)
							{
								$basketItems[$key]["NAME"] = $item->getField('NAME');
								$basketItems[$key]["PRICE"] = $item->getPrice();
								$basketItems[$key]["QUANTITY"] = $item->getQuantity();
							}
							$orderData = array(
									"PATH_TO_ORDER" => $APPLICATION->GetCurPage(),
									"AMOUNT" => $order->getPrice(),
									"ORDER_REQUEST" => "Y",
									"BASKET_ITEMS" => $basketItems,
							);
							$arResult["REDIRECT_URL"] = $this->prePaymentService->basketButtonAction($orderData);
							if ($arResult["REDIRECT_URL"] != '')
							{
								$arResult["NEED_REDIRECT"] = "Y";
							}
						}
					}

					ob_start();
					$this->prePaymentService->setTemplateParams(array(
							'TOKEN' => $this->request->get('token'),
							'PAYER_ID' => $this->request->get('PayerID')
					));
					$this->prePaymentService->showTemplate(null, 'prepay_hidden_fields');
					$arResult["PREPAY_ADIT_FIELDS"] = ob_get_contents();
					ob_end_clean();
				}
			}
		}
	}

	/**
	 * Wrapper for event execution method.
	 *
	 * @param string $eventName
	 * @param null   $order
	 */
	protected function executeEvent($eventName = '', $order = null)
	{
		$arModifiedResult = $this->arUserResult;

		foreach (\GetModuleEvents("sale", $eventName, true) as $arEvent)
			\ExecuteModuleEventEx($arEvent, array(&$this->arResult, &$arModifiedResult, &$this->arParams));


	}

	/**
	 * @param Order $order
	 *
	 * @return mixed|null|string
	 * @throws Main\ArgumentException
	 */
	protected static function getUserName(Order $order)
	{
		$userName = "";

		if (!empty(static::$cacheUserData[$order->getUserId()]))
		{
			$userData = static::$cacheUserData[$order->getUserId()];
			if (!empty($userData['USER_NAME']))
			{
				$userName = $userData['USER_NAME'];
			}
		}


		if (empty($userName))
		{
			/** @var PropertyValueCollection $propertyCollection */
			if ($propertyCollection = $order->getPropertyCollection())
			{
				if ($propPayerName = $propertyCollection->getPayerName())
				{
					$userName = $propPayerName->getValue();
					static::$cacheUserData[$order->getUserId()]['PAYER_NAME'] = $userName;
				}
			}
		}

		if (empty($userName))
		{
			$userRes = Main\UserTable::getList(array(
					'select' => array('ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL'),
					'filter' => array('=ID' => $order->getUserId()),
			));
			if ($userData = $userRes->fetch())
			{
				$userData['PAYER_NAME'] = \CUser::FormatName(\CSite::GetNameFormat(null, $order->getSiteId()), $userData, true);
				static::$cacheUserData[$order->getUserId()] = $userData;
				$userName = $userData['PAYER_NAME'];
			}
		}

		return $userName;
	}

	/**
	 * @param Order $order
	 *
	 * @return null|string
	 * @throws Main\ArgumentException
	 */
	protected static function getUserEmail(Order $order)
	{
		$userEmail = "";

		if (!empty(static::$cacheUserData[$order->getUserId()]))
		{
			$userData = static::$cacheUserData[$order->getUserId()];
			if (!empty($userData['EMAIL']))
			{
				$userEmail = $userData['EMAIL'];
			}
		}


		if (empty($userEmail))
		{
			/** @var PropertyValueCollection $propertyCollection */
			if ($propertyCollection = $order->getPropertyCollection())
			{
				if ($propUserEmail = $propertyCollection->getUserEmail())
				{
					$userEmail = $propUserEmail->getValue();
					static::$cacheUserData[$order->getUserId()]['EMAIL'] = $userEmail;
				}
			}
		}

		if (empty($userEmail))
		{
			$userRes = Main\UserTable::getList(array(
					'select' => array('ID', 'LOGIN', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'EMAIL'),
					'filter' => array('=ID' => $order->getUserId()),
			));
			if ($userData = $userRes->fetch())
			{
				static::$cacheUserData[$order->getUserId()] = $userData;
				$userEmail = $userData['EMAIL'];
			}
		}

		return $userEmail;
	}

}
?>