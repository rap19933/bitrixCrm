<?
namespace h2o\buyoneclick;


use Bitrix\Highloadblock as HL,
	Bitrix\Main\Entity,
	Bitrix\Main,
	Bitrix\Main\Localization\Loc,
	Bitrix\Sale,
	Bitrix\Sale\Order,
	Bitrix\Sale\PersonType,
	Bitrix\Sale\Shipment,
	Bitrix\Sale\PaySystem,
	Bitrix\Sale\Payment,
	Bitrix\Sale\Delivery,
	Bitrix\Sale\Location,
	Bitrix\Sale\Location\LocationTable,
	Bitrix\Sale\Result,
	Bitrix\Sale\DiscountCouponsManager;

Loc::loadMessages(__FILE__);

class h2obuyoneclick{

	protected $name;
	protected $email;
	protected $phone;
	protected $element_id;
	protected $newUserGroup;
	protected $PERSON_TYPE_ID = false;
	protected $PAY_SYSTEM_ID = false;
	protected $PRICE_DELIVERY = 0;
	protected $DELIVERY_ID = false;
	protected $DISCOUNT_VALUE = 0;
	protected $USER_DESCRIPTION = "";
	protected $user_fields;
	protected $user_fields_prop;
	protected $arOffers;
	protected $arParams;
	protected $priceCode = array();
	protected $quantity;
	protected $cuser;
	protected $current_basket = false;
	protected $fix_user = false;

	function __construct($ELEMENT_ID, $user_fields, $user_fields_prop, $params){
		$this->element_id = $ELEMENT_ID;
		$this->arParams = $params;
		$this->user_fields = $user_fields;
		$this->user_fields_prop = $user_fields_prop;
		$this->cuser = new \CUser;
		$this->setFieldsUser();
		//$this->name = $user_fields['NAME'];
		//$this->email = $user_fields['EMAIL'];

		//$this->PAY_SYSTEM_ID = $pay_system;
		//$this->DELIVERY_ID = $delivery;
	}
	
	/**
	 * Установка ид пользователя, на которого будет оформляться
	 * заказы неавторизованных пользователей
	 * @param $user_id
	 */
	public function setFixUser($user_id){
		$user_id = intval($user_id);
		if($user_id >= 0){
			$this->fix_user = $user_id;
		}
	}

	/**
	 * Функция устанавливает имя и email пользователя
	 * @return void
	 */
	protected function setFieldsUser(){
		$name = $email = $phone = "";
		$isset_prop_phone = false;  //флаг - есть ли свойство заказа телефон в массиве user_fields_prop
		/** Ищем имя, телефон и email в полях пользователя */
		global $USER;
		$isAuthorized = $USER->isAuthorized();
		if(isset($this->user_fields['EMAIL'])){
			$email = $this->user_fields['EMAIL'];
		}elseif($isAuthorized){
			$email = $USER->GetEmail();
		}
		if(isset($this->user_fields['NAME'])){
			$name = $this->user_fields['NAME'];
		}elseif($isAuthorized){
			$name = $USER->GetFullName();
		}
		if(isset($this->user_fields['PERSONAL_PHONE'])){
			$phone = $this->user_fields['PERSONAL_PHONE'];
		}elseif($isAuthorized){
			$rsUser = \CUser::GetByID($USER->GetID());
			$arUser = $rsUser->Fetch();
			$phone = $arUser['PERSONAL_PHONE'];
		}
		/** Ищем имя, телефон и email в свойствах заказа */

		if(is_array($this->user_fields_prop) && !empty($this->user_fields_prop)){
			foreach($this->user_fields_prop as $key => $value){
				$arProp_db = \CSaleOrderProps::GetList(array(), array('ID' => $key));
				if($arProp = $arProp_db->fetch()){
					if($email == "" && $arProp['IS_EMAIL'] == 'Y'){
						$email = $value;
					}
					if($name == "" && $arProp['IS_PROFILE_NAME'] == 'Y'){
						$name = $value;
					}
					if($this->IsPhoneProp($arProp)){

						if($phone == ""){
							$phone = $value;
						}
						$isset_prop_phone = true;
					}
				}
			}
		}

		/**
		 * Генерируем email из телефона
		 */
		if($email == "" && $phone != ""){
			$tempPhone = preg_replace("/[^0-9a-zA-Z]/", '', $phone);
			if(strlen($tempPhone) <= 0){
				throw new Main\SystemException(Loc::getMessage("H2O_BUYONECLICK_WRONG_PHONE"));
			}
			$email = $tempPhone.'@'.SITE_SERVER_NAME;
		}

		/** Ищем свойство email, проверяем, есть ли оно в $this->user_fields_prop, если нет - добавляем */
		if($email != ""){
			$arProp_db = \CSaleOrderProps::GetList(array(), array('IS_EMAIL' => "Y"));
			if($arProp = $arProp_db->fetch()){
				if(isset($this->user_fields_prop[$arProp['ID']])){
					if($this->user_fields_prop[$arProp['ID']] == ""){
						$this->user_fields_prop[$arProp['ID']] = $email;
					}
				}else{
					$this->user_fields_prop[$arProp['ID']] = $email;
				}
			}
		}

		/**
		 * В случае если указано поле PERSONAL_PHONE, ищем свойство заказа,
		 * в символьном коде которого есть PHONE или в названии встречается слово телефон.
		 * Если находим - добавляем в массив user_fields_prop это поле
		 */
		if(!$isset_prop_phone && $phone != ""){
			$arFilterProp = array();
			if(is_array($this->user_fields_prop) && !empty($this->user_fields_prop)){
				$user_fields_prop_ids = array_keys($this->user_fields_prop);
				if(is_array($user_fields_prop_ids) && !empty($user_fields_prop_ids)){
					$arFilterProp["!ID"] = $user_fields_prop_ids;
				}
			}
			$arProp_db = \CSaleOrderProps::GetList(array(), $arFilterProp);
			while($arProp = $arProp_db->fetch()){
				if($this->IsPhoneProp($arProp)){
					$this->user_fields_prop[$arProp['ID']] = $phone;
					break;
				}
			}
		}

		$this->name = $name;
		$this->phone = $phone;
		$this->email = $email;
	}

	/**
	 * Функция для проверки является ли свойство телефоном
	 * @param $arProp
	 * @return bool
	 */
	protected function IsPhoneProp($arProp){
		$phone_name = (strlen(GetMessage("FIELD_PHONE"))>0?GetMessage("FIELD_PHONE"):"phone");
		if($arProp['TYPE'] == 'TEXT' && (preg_match("/PHONE/i",$arProp['CODE']) || preg_match("/".$phone_name."/i",$arProp['NAME']))){
			return true;
		}
		return false;
	}

	public function SetNewUserGroupId($arGroup){
		$this->newUserGroup = $arGroup;
	}
	public function SetPERSON_TYPE_ID($PERSON_TYPE_ID){
		$this->PERSON_TYPE_ID = $PERSON_TYPE_ID;
	}
	public function SetPAY_SYSTEM_ID($PAY_SYSTEM_ID){
		$this->PAY_SYSTEM_ID = $PAY_SYSTEM_ID;
	}
	public function SetPRICE_DELIVERY($PRICE_DELIVERY){
		$this->PRICE_DELIVERY = $PRICE_DELIVERY;
	}
	public function SetDELIVERY_ID($DELIVERY_ID){
		$this->DELIVERY_ID = $DELIVERY_ID;
	}
	public function SetDISCOUNT_VALUE($DISCOUNT_VALUE){
		$this->DISCOUNT_VALUE = $DISCOUNT_VALUE;
	}
	public function SetUserDescription($USER_DESCRIPTION){
		$this->USER_DESCRIPTION = $USER_DESCRIPTION;
	}
	public function SetPriceCode($arPriceCode){
		$this->priceCode = $arPriceCode;
	}
	public function GetPriceCode(){
		return $this->priceCode;
	}
	public function GetPaySystemId(){
		return $this->PAY_SYSTEM_ID;
	}
	public function GetPersonTypeId(){
		return $this->PERSON_TYPE_ID;
	}
	public function SetQuantity($quantity){
		if(intval($quantity) <= 0){
			$quantity = 1;
		}
		$this->quantity = intval($quantity);
	}
	public function BuyCurrentBasketMode($mode){
		$this->current_basket = $mode;
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
	public function GetPriceProduct(
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
		$res = \CIBlockElement::GetByID($ELEMENT_ID);
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
		$arCurrencyParams = array(
			"CURRENCY_ID" => \CCurrency::GetBaseCurrency()
		);
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
				,$arCurrencyParams				//arCurencyParams
				,$USER_ID				//USER_ID
				,$SITE_ID				//SITE_ID

		);

		if(!empty($arOffers)){
			$this->arOffers = $arOffers;
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
		$arPrice = \CIBlockPriceTools::GetItemPrices($arElement["IBLOCK_ID"], $arResultPrices, $arElement, true, $arCurrencyParams, $USER_ID, $SITE_ID);
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
		if($floor && $min_price !== false){
			$min_price = floor($min_price/$floor)*$floor;
		}
		if($min_price !== false){
			return array(
					"PRICE" => $min_price,
					"CURRENCY" => $currency
			);
		}
		return $min_price;
	}

	/**
	 * Get current basket
	 * @return array
	 */
	protected function getBasketItems(){
		$arSelFields = array("ID", "CALLBACK_FUNC", "MODULE", "PRODUCT_ID", "QUANTITY", "DELAY",
				"CAN_BUY", "PRICE", "WEIGHT", "NAME", "CURRENCY", "CATALOG_XML_ID", "VAT_RATE",
				"NOTES", "DISCOUNT_PRICE", "PRODUCT_PROVIDER_CLASS", "DIMENSIONS", "TYPE", "SET_PARENT_ID", "DETAIL_PAGE_URL"
		);
		$dbBasketItems = \CSaleBasket::GetList(
				array("ID" => "ASC"),
				array(
						"FUSER_ID" => \CSaleBasket::GetBasketUserID(),
						"LID" => SITE_ID,
						"ORDER_ID" => "NULL"
				),
				false,
				false,
				$arSelFields
		);
		$arBasket = array(
				"ORDER_PRICE" => 0,
				"ORDER_WEIGHT" => 0,
				"ITEMS" => array()
		);
		$DISCOUNT_PRICE_ALL = 0;
		$arSetParentWeight = array();
		while ($arItem = $dbBasketItems->GetNext()){
			if($arItem["DELAY"] == "N" && $arItem["CAN_BUY"] == "Y"){
				$arItem["PRICE"] = roundEx($arItem["PRICE"], SALE_VALUE_PRECISION);
				$arItem["QUANTITY"] = DoubleVal($arItem["QUANTITY"]);

				$arItem["WEIGHT"] = DoubleVal($arItem["WEIGHT"]);
				$arItem["VAT_RATE"] = DoubleVal($arItem["VAT_RATE"]);


				$arItem["PRICE_FORMATED"] = \SaleFormatCurrency($arItem["PRICE"], $arItem["CURRENCY"]);
				$arItem["WEIGHT_FORMATED"] = roundEx(DoubleVal($arItem["WEIGHT"]), SALE_WEIGHT_PRECISION);

				if($arItem["DISCOUNT_PRICE"] > 0){
					$arItem["DISCOUNT_PRICE_PERCENT"] = $arItem["DISCOUNT_PRICE"] * 100 / ($arItem["DISCOUNT_PRICE"] + $arItem["PRICE"]);
					$arItem["DISCOUNT_PRICE_PERCENT_FORMATED"] = roundEx($arItem["DISCOUNT_PRICE_PERCENT"], 0) . "%";
				}

				$arItem["PROPS"] = array();
				$dbProp = \CSaleBasket::GetPropsList(array("SORT" => "ASC", "ID" => "ASC"), array("BASKET_ID" => $arItem["ID"], "!CODE" => array("CATALOG.XML_ID", "PRODUCT.XML_ID")));
				while($arProp = $dbProp->GetNext()){
					if(array_key_exists('BASKET_ID', $arProp)){
						unset($arProp['BASKET_ID']);
					}
					if(array_key_exists('~BASKET_ID', $arProp)){
						unset($arProp['~BASKET_ID']);
					}

					$arProp = array_filter($arProp, array("CSaleBasketHelper", "filterFields"));

					$arItem["PROPS"][] = $arProp;
				}

				if(!\CSaleBasketHelper::isSetItem($arItem)){
					$DISCOUNT_PRICE_ALL += $arItem["DISCOUNT_PRICE"] * $arItem["QUANTITY"];
					$arItem["DISCOUNT_PRICE"] = roundEx($arItem["DISCOUNT_PRICE"], SALE_VALUE_PRECISION);
					$arBasket["ORDER_PRICE"] += $arItem["PRICE"] * $arItem["QUANTITY"];
				}

				if(!\CSaleBasketHelper::isSetItem($arItem)){
					$arBasket["ORDER_WEIGHT"] += $arItem["WEIGHT"] * $arItem["QUANTITY"];
				}

				if(\CSaleBasketHelper::isSetItem($arItem))
					$arSetParentWeight[$arItem["SET_PARENT_ID"]] += $arItem["WEIGHT"] * $arItem['QUANTITY'];


				$arBasket['ITEMS'][] = $arItem;
			}

		}
		foreach($arBasket['ITEMS'] as &$arItem){
			if (\CSaleBasketHelper::isSetParent($arItem))
			{
				$arItem["WEIGHT"] = $arSetParentWeight[$arItem["ID"]] / $arItem["QUANTITY"];
				$arItem["WEIGHT_FORMATED"] = roundEx(doubleval($arItem["WEIGHT"]), SALE_WEIGHT_PRECISION);
			}
		}
		unset($arItem);

		return $arBasket;
	}

	/**
	 * Функция добавляет свойство заказа
	 *
	 * @param $id int Код свойства заказа.
	 * @param $value string Значение свойства
	 * @param $order int Ид заказа
	 * @return void
	 */
	private function AddOrderProperty($id, $value, $order) {
		if (!strlen($id)) {
			return false;
		}
		if (\Bitrix\Main\Loader::IncludeModule('sale')) {
			if ($arProp = \CSaleOrderProps::GetList(array(), array('ID' => $id, "PERSON_TYPE_ID" => $this->PERSON_TYPE_ID))->Fetch()) {
				return \CSaleOrderPropsValue::Add(array(
						'NAME' => $arProp['NAME'],
						'CODE' => $arProp['CODE'],
						'ORDER_PROPS_ID' => $arProp['ID'],
						'ORDER_ID' => $order,
						'VALUE' => $value,
				));
			}
		}
	}

	/**
	 * implode для многомерного массива
	 * @param $sep
	 * @param $array
	 * @return string
	 */
	protected static function multi_implode($sep, $array) {
		foreach($array as $val)
			$_array[] = is_array($val)? self::multi_implode($sep, $val) : $val;
		return implode($sep, $_array);
	}


	/**
	 * Генерация пароля
	 * @param int $len
	 * @return bool|string
	 */
	protected static function generatePass($len = 8){
		if(!is_int($len)){
			return false;
		}
		return randString($len);
	}


	/**
	 * Функция добавления заказа
	 *
	 * @param $PRODUCT_ID int Ид товара
	 * @param $USER_ID int Ид пользователя
	 * @param $user_name string Имя пользователя
	 * @param $user_email string Email пользователя
	 * @param $quantity int Количество товара
	 * @param $addProperties array Свойства заказа
	 * @param $PERSON_TYPE_ID int Ид типа покупателя
	 * @param $PAY_SYSTEM_ID int Ид платежной системы
	 * @param $PRICE_DELIVERY float Цена доставки
	 * @param $DELIVERY_ID int Ид доставки
	 * @param $DISCOUNT_VALUE float Скидка
	 * @param $USER_DESCRIPTION string Комментарий пользователя
	 * @return mixed
	 */
	protected function addOrder(
			$PRODUCT_ID,
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
		global $DB;
		if(!$current_basket){
			if(!strlen($PRODUCT_ID)){
				return 'NOT_ELEMENT';
			}


			$res = \CIBlockElement::GetByID($PRODUCT_ID);
			if($arProduct = $res->GetNext()){

			}else{
				throw new Main\SystemException(Loc::getMessage('H2O_BUYONECLICK_NOT_ELEMENT'));
			}
			$arPrice = $this->GetPriceProduct($PRODUCT_ID, $this->priceCode, "DISCOUNT_VALUE", $USER_ID);// todo h2o Есть проблема с отображением скидок в заказе
			/** получаем цену продукта, или его торговые предложения если они есть*/
			if($arPrice == 'OFFERS'){
				/** проверка на торговые предложения, если они есть выходим из функции*/
				return $arPrice;
				/** иначе достаем цену продукта*/
			}
			$site_currency = \CSaleLang::GetLangCurrency(SITE_ID);
			$price_in_currency = \CCurrencyRates::ConvertCurrency($arPrice['PRICE'], $arPrice['CURRENCY'], $site_currency);
			$order_price = $total_price = $price_in_currency * $quantity; //переменная для подсчета полной стоимости заказа
			$currency = $arPrice['CURRENCY'];
			$arFieldsBasket = array(                       // поля корзины. В нашем случае тут всегда будет один товар
					"PRODUCT_ID" => $PRODUCT_ID,         //ИД товара
					"PRODUCT_PRICE_ID" => 0,
					"PRICE" => $arPrice['PRICE'],
					"CURRENCY" => $arPrice['CURRENCY'],
					"QUANTITY" => $quantity,
					"LID" => SITE_ID,
					"DELAY" => "N",
					"CAN_BUY" => "Y",
					"NAME" => $arProduct['NAME'],
					"DETAIL_PAGE_URL" => $arProduct['DETAIL_PAGE_URL'],
					"ORDER_ID" => false,
					"PRODUCT_PROVIDER_CLASS" => "CCatalogProductProvider",
					"MODULE" => "catalog",
					/** Thanks to Denis Didenko */
					"PRODUCT_XML_ID" => $arProduct["XML_ID"],
					"CATALOG_XML_ID" => $arProduct["IBLOCK_EXTERNAL_ID"],
			);
			/** теперь товар попадает в заказ, но мы не учли свойства заказа (список нужных свойств указывается в настройках компонента), на данном этапе
			 * мы имеем только свойства торговых предложений, добавлять свойства товара не вижу смысла.
			 * Теперь добавим нужные свойства */
			// todo h2o смысл есть)
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
			if(!empty($arProps)){
				$arFieldsBasket["PROPS"] = $arProps;
			}
			$arFieldsBaskets = array($arFieldsBasket);

		}else{
			$arBasket = $this->getBasketItems();
			$order_price = $total_price = $arBasket['ORDER_PRICE'];
			$currency = \CSaleLang::GetLangCurrency(SITE_ID);
			$arFieldsBaskets = $arBasket['ITEMS'];
		}
		if($PRICE_DELIVERY > 0){
			$total_price = $total_price + $PRICE_DELIVERY;
		}
		$arOrderData = array (
				'ORDER_PRICE' => $order_price,
			//'ORDER_WEIGHT' => 0,
				'CURRENCY' => $currency,
			//'WEIGHT_UNIT' => 'кг',
			//'WEIGHT_KOEF' => '1000',
				'BASKET_ITEMS' => $arFieldsBaskets,
				'SITE_ID' => SITE_ID,
				'LID' => SITE_ID,
				'USER_ID' => $USER_ID,
			//'USE_VAT' => false,
			//'VAT_RATE' => 0,
			//'VAT_SUM' => 0,
				'PROFILE_NAME' => $user_name,
				'PAYER_NAME' => $user_name,
				'ORDER_PROP' => $addProperties,
				'USER_EMAIL' => $user_email,
			//'DELIVERY_LOCATION_ZIP' => '101000',
			//'DELIVERY_LOCATION' => '2622',
				'DELIVERY_PRICE' => $PRICE_DELIVERY,
				'PRICE_DELIVERY' => $PRICE_DELIVERY,

				'DISCOUNT_PRICE' => $DISCOUNT_VALUE,
				'DISCOUNT_VALUE' => $DISCOUNT_VALUE,
			/*'DISCOUNT_LIST' =>
				array (
				),
			'FULL_DISCOUNT_LIST' =>
				array (
				),*/
				'PRICE' => $total_price,
				"ADDITIONAL_INFO" => "Buy one click",
				"USER_DESCRIPTION" => $USER_DESCRIPTION,


				'PAYED' => 'N',
				'CANCELED' => 'N',
				'STATUS_ID' => 'N',

				'TAX_VALUE' => NULL,
				'AFFILIATE_ID' => false,
				'COUNT_DELIVERY_TAX' => 'N',
		);
		if($PERSON_TYPE_ID){
			$arOrderData['PERSON_TYPE_ID'] = $PERSON_TYPE_ID;
		}
		if($DELIVERY_ID){
			$arOrderData['DELIVERY_ID'] = $DELIVERY_ID;
		}
		if($PAY_SYSTEM_ID){
			$arOrderData['PAY_SYSTEM_ID'] = $PAY_SYSTEM_ID;
		}
		// add Guest ID
		if (\Bitrix\Main\Loader::IncludeModule("statistic"))
			$arOrderData["STAT_GID"] = \CStatistic::GetEventParam();
		$arErrors = array();
		$ORDER_ID = (int)\CSaleOrder::DoSaveOrder($arOrderData, array(), 0, $arErrors);

		if ($ORDER_ID > 0 && empty($arErrors)){
			if(!$current_basket){
				//$arFieldsBasket['ORDER_ID'] = $ORDER_ID;
				//\CSaleBasket::Add($arFieldsBasket);             //привязываем корзину к заказу

				/** Резервирование товара */
				/*$arOrder = \CSaleOrder::GetByID($ORDER_ID);
				if(!$arOrder){
					throw new Main\SystemException(Loc::getMessage('H2O_BUYONECLICK_ERROR_RESERV'));
				}
				if($arOrder["RESERVED"] != "Y"){
					if(\COption::GetOptionString("sale", "product_reserve_condition", "O") == "O"){
						if(!\CSaleOrder::ReserveOrder($ORDER_ID, "Y"))
							throw new Main\SystemException(Loc::getMessage('H2O_BUYONECLICK_ERROR_RESERV'));
					}
				}*/

			}else{
				\CSaleBasket::OrderBasket($ORDER_ID, \CSaleBasket::GetBasketUserID(), SITE_ID, false);
			}


		}else{
			$string_error = "";
			foreach($arErrors as $error){
				$string_error .= $error['TEXT'].'; ';
			}
			throw new Main\SystemException($string_error);
		}

		/** Отправка писем*/
		$ORDER_LIST = "";
		$ar_meas = array();
		if(\Bitrix\Main\Loader::includeModule('catalog')){
			$ar_meas_db = \CCatalogMeasure::GetList();
			while($ar_res_meas = $ar_meas_db->GetNext()){
				$ar_meas[$ar_res_meas['ID']] = $ar_res_meas;
			}
		}
		if($current_basket){
			foreach($arBasket['ITEMS'] as $arItem){
				$ar_res = \CCatalogProduct::GetByID($arItem['PRODUCT_ID']);
				$measure = $ar_meas[$ar_res['MEASURE']];
				$ORDER_LIST .= $arItem['NAME']." - ".$arItem['QUANTITY'].$measure['SYMBOL_RUS']."\r\n";
			}
		}else{
			$ar_res = \CCatalogProduct::GetByID($PRODUCT_ID);
			$measure = $ar_meas[$ar_res['MEASURE']];
			$ORDER_LIST = $arProduct['NAME'].' - '.$quantity.' '.$measure['SYMBOL_RUS'];
		}
		$arFields = Array(
				"ORDER_ID" => $ORDER_ID,
				"ORDER_DATE" => date($DB->DateFormatToPHP(\CLang::GetDateFormat("SHORT", SITE_ID))),
				"ORDER_USER" => $user_name,
				"PRICE" => FormatCurrency($total_price, $arPrice['CURRENCY']),
				"EMAIL" => $user_email,
				"ORDER_LIST" => $ORDER_LIST,
				"DELIVERY_PRICE" => $PRICE_DELIVERY,
				"SALE_EMAIL" => \COption::GetOptionString("sale", "order_email", "order@".SITE_SERVER_NAME),
				"USER_DESCRIPTION" => $USER_DESCRIPTION
		);

		if($this->arParams['SEND_MAIL']){
			$eventName = "SALE_NEW_ORDER";
			$event = new \CEvent;
			if(is_array($this->arParams['EVENT_MESSAGE_ID']) && !empty($this->arParams['EVENT_MESSAGE_ID'])){
				foreach($this->arParams['EVENT_MESSAGE_ID'] as $event_id){
					$event->Send($eventName, SITE_ID, $arFields, "N", $event_id);
				}

			}else{
				$event->Send($eventName, SITE_ID, $arFields, "N");
			}

		}

		/** возвращаем номер заказа*/
		$newOrder = \CSaleOrder::GetByID($ORDER_ID);
		/** Вызов события*/
		foreach (GetModuleEvents("sale", "OnSaleComponentOrderOneStepComplete", true) as $arEvent)
			ExecuteModuleEventEx($arEvent, array($ORDER_ID, $newOrder, $this->arParams));

		//сохранение профиля
		$dbUserProfiles = \CSaleOrderUserProps::GetList(
			array("DATE_UPDATE" => "DESC"),
			array(
				"PERSON_TYPE_ID" => $PERSON_TYPE_ID,
				"USER_ID" => $USER_ID
			)
		);
		$profileID = null;
		if ($arUserProfiles = $dbUserProfiles->GetNext())
		{
			$profileID = $arUserProfiles['ID'];
		}

		\CSaleOrderUserProps::DoSaveUserProfile(
			$USER_ID,
			$profileID,
			$user_name,
			$PERSON_TYPE_ID,
			$addProperties,
			$arErrors
		);
		/** Возвращаем номер заказа */
		return $newOrder['ACCOUNT_NUMBER'];
		//CSaleMobileOrderPush::send("ORDER_CREATED", array("ORDER_ID" => $arFields["ORDER_ID"]));
	}

	/**
	 * Добавление нового пользователя
	 * @return mixed
	 */
	protected function AddNewUser(){
		if($this->fix_user){
			$this->arParams['NOT_AUTHORIZE_USER'] = true;
			return $this->fix_user;
		}
		if(strlen($this->email) <= 0){
			return false;
		}


		$new_login = $this->email;

		$pos = strpos($new_login, "@");
		if ($pos !== false)
			$new_login = substr($new_login, 0, $pos);

		if (strlen($new_login) > 47)
			$new_login = substr($new_login, 0, 47);

		if (strlen($new_login) < 3)
			$new_login .= "_";

		if (strlen($new_login) < 3)
			$new_login .= "_";

		$dbUserLogin = \CUser::GetByLogin($new_login);
		if ($arUserLogin = $dbUserLogin->Fetch())
		{
			$newLoginTmp = $new_login;
			$uind = 0;
			do
			{
				$uind++;
				if ($uind > 10)
				{
					$new_login = "buyer".time().GetRandomCode(2);
					$newLoginTmp = $new_login;
					break;
				}
				else
				{
					$newLoginTmp = $new_login.$uind;
				}
				$dbUserLogin = \CUser::GetByLogin($newLoginTmp);
			}
			while ($arUserLogin = $dbUserLogin->Fetch());
			$new_login = $newLoginTmp;
		}

		$new_pass = self::generatePass(8);
		$arFields = Array(          //поля нового пользователя
				"NAME" => $this->name,
			//"LAST_NAME" => $arName[1],
				"EMAIL" => $this->email,
				"LOGIN" => $new_login,
				"LID" => SITE_ID,
				"ACTIVE" => "Y",
				"GROUP_ID" => $this->newUserGroup,
				"PASSWORD" => $new_pass,
				"CONFIRM_PASSWORD" => $new_pass,
		);
		if(is_array($this->user_fields) && !empty($this->user_fields)){
			foreach($this->user_fields as $key => $value){
				if(!isset($arFields[$key]) || $arFields[$key] == ""){
					$arFields[$key] = $value;
				}
			}
			if(!array_key_exists("PERSONAL_PHONE", $arFields)){
				$arFields['PERSONAL_PHONE'] = $this->phone;
			}
		}else{
			$arFields['PERSONAL_PHONE'] = $this->phone;
		}
		$ID = $this->cuser->Add($arFields);     //регистрируем пользователя
		if(intval($ID) > 0){
			if($this->arParams['SEND_MAIL_REQ']){
				/** Отправка письма NEW_USER и при необходимости NEW_USER_CONFIRM */
				$option = new \COption;
				$bConfirmReq = ($option->GetOptionString("main", "new_user_registration_email_confirmation", "N") == "Y");
				$arEventFields = $arFields;
				$arEventFields["USER_ID"] = $ID;
				$arEventFields["CONFIRM_CODE"] = $bConfirmReq? randString(8): "";
				//unset($arEventFields["PASSWORD"]);
				unset($arEventFields["CONFIRM_PASSWORD"]);
				$event = new \CEvent;
				if(is_array($this->arParams['EVENT_MESSAGE_ID_REQ']) && !empty($this->arParams['EVENT_MESSAGE_ID_REQ'])){
					foreach($this->arParams['EVENT_MESSAGE_ID_REQ'] as $event_id){
						$event->Send("NEW_USER", SITE_ID, $arEventFields, "Y", $event_id);
					}

					\CUser::SendUserInfo($ID, SITE_ID);
					if($bConfirmReq){

						$event->Send("NEW_USER_CONFIRM", SITE_ID, $arEventFields, "Y");

					}
				}else{
					$event->Send("NEW_USER", SITE_ID, $arEventFields, "Y");
					\CUser::SendUserInfo($ID, SITE_ID);
					if($bConfirmReq){
						$event->Send("NEW_USER_CONFIRM", SITE_ID, $arEventFields, "Y");
					}
				}

			}
		}else{
			throw new Main\SystemException($this->cuser->LAST_ERROR);
		}
		return $ID;
	}

	/** основная функция модуля*/
	public function Buy(){
		if(! \Bitrix\Main\Loader::includeModule ('iblock'))
		{
			ShowError(GetMessage('IBLOCK_MODULE_NOT_INSTALL'));
			return;
		}
		if(! \Bitrix\Main\Loader::includeModule ('sale'))
		{
			ShowError(GetMessage('SALE_MODULE_NOT_INSTALL'));
			return;
		}
		if(! \Bitrix\Main\Loader::includeModule ('catalog'))
		{
			ShowError(GetMessage('CATALOG_MODULE_NOT_INSTALL'));
			return;
		}
		if(! \Bitrix\Main\Loader::includeModule ('main'))
		{
			ShowError(GetMessage('MAIN_MODULE_NOT_INSTALL'));
			return;
		}
		$return = false;
		global $USER;
		if ($USER->IsAuthorized()){     //Если пользователь авторизован
			$return = $this->addOrder(  //попытка оформления заказа, если у товара есть торговые предложения то
					$this->element_id,      //функция вернет "OFFERS", иначе вернет ид заказа
					$USER->GetID(),
					$this->name,
					$this->email,
					$this->quantity,
					$this->user_fields_prop,
					$this->PERSON_TYPE_ID,
					$this->PAY_SYSTEM_ID,
					$this->PRICE_DELIVERY,
					$this->DELIVERY_ID,
					$this->DISCOUNT_VALUE,
					$this->USER_DESCRIPTION,
					$this->current_basket
			);
		}else{                        //Если пользователь не авторизован
			/**
			 * Проверка опции "Разрешить оформлять неавторизованному пользователю заказ на существующий Е-mail"
			 */
			if($this->arParams["ALLOW_ORDER_FOR_EXISTING_EMAIL"]){
				$sort_by = "ID";
				$sort_ord = "ASC";
				$filter = Array("EMAIL" => ($this->email));
				$rsUsers = $this->cuser->GetList($sort_by, $sort_ord, $filter);
				if($arUser = $rsUsers->Fetch()){
					$ID = $arUser['ID'];
				}else{
					$ID = $this->AddNewUser();
				}
			}else{
				$ID = $this->AddNewUser();
			}

			if (intval($ID) > 0){
				if(!$this->arParams["ALLOW_ORDER_FOR_EXISTING_EMAIL"] && !$this->arParams['NOT_AUTHORIZE_USER']) {
					$USER->Authorize($ID);
				}
				$return = $this->addOrder(
						$this->element_id,
						$ID,
						$this->name,
						$this->email,
						$this->quantity,
						$this->user_fields_prop,
						$this->PERSON_TYPE_ID,
						$this->PAY_SYSTEM_ID,
						$this->PRICE_DELIVERY,
						$this->DELIVERY_ID,
						$this->DISCOUNT_VALUE,
						$this->USER_DESCRIPTION,
						$this->current_basket
				);

			}else{
				throw new Main\SystemException(GetMessage("H2O_BUYONECLICK_ERROR_AUTH"));
			}
		}
		if(!$return){
			throw new Main\SystemException(GetMessage("H2O_BUYONECLICK_ERROR_MAKE_ORDER"));
		}
		if($return=='OFFERS'){
			return array("OFFERS" => $this->arOffers);
		}elseif($return=='NOT_ELEMENT'){
			return array("ERROR" => GetMessage('NOT_ELEMENT'));
		}else{
			return $return;
		}

	}

}