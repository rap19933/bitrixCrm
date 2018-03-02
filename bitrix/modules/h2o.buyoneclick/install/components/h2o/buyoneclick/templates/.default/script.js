window.JCH2oBuyOneClick = function (arParams) {
	var self = this;
	this.containerID = "#unitable_container";
	this.formID = "#unitable_form";
	this.ajaxID = "";
	this.oPopupBuy = false;
	this.list_phone_prop = [];
	this.maskPhone = '(999) 999-9999';
	this.blockModal = '.h2o_block_modal_wrap';

	this.errorCode = 0;
	if (typeof arParams === 'object') {
		this.params = arParams;
		this.initConfig();

		this.errorCode = 0;
	}
	if (0 === this.errorCode) {
		if (window.frameCacheVars !== undefined)
		{
			BX.addCustomEvent("onFrameDataReceived" , function(json) {
				BX.ready(BX.delegate(self.Init, self));
			});
		} else {
			BX.ready(BX.delegate(this.Init, this));
		}
	}else{
		console.log('this.errorCode ',this.errorCode);
	}
};

window.JCH2oBuyOneClick.prototype.reInit = function (arParams) {
	this.params = arParams;
	this.initConfig();
	this.InitOffers();
};

window.JCH2oBuyOneClick.prototype.initConfig = function () {
	if (!!this.params.CONTAINER_ID) {
		this.containerID = "#"+this.params.CONTAINER_ID;
		this._containerID = this.params.CONTAINER_ID;
	}
	if (!!this.params.FORM_ID) {
		this.formID = "#"+this.params.FORM_ID;
		this._formID = this.params.FORM_ID;
	}
	if (!!this.params.AJAX_ID) {
		this.ajaxID = this.params.AJAX_ID;
	}
	if (!!this.params.ID_FIELD_PHONE) {
		this.list_phone_prop = this.params.ID_FIELD_PHONE;
	}
  if (!!this.params.MASK_PHONE) {
    this.maskPhone = this.params.MASK_PHONE;
  }
  if (!!this.params.TREE_OFFERS) {
    this.treeOffers = this.params.TREE_OFFERS;
  }
	if (!!this.params.OFFERS_PROP) {
		this.offersProp = this.params.OFFERS_PROP;
	}
	if (!!this.params.OFFERS_PRICE) {
		this.offersPrice = this.params.OFFERS_PRICE;
	}
  if (!!this.params.CURRENT_OFFER) {
    this.currentOffer = this.params.CURRENT_OFFER;
	  //основное свойство торг.предложения, которое всегда доступно
	  if(this.currentOffer > 0) {
		  if (typeof this.offersProp === 'object') {
			  if (typeof this.offersProp[this.currentOffer] === 'object') {
				  this.mainProp = this.getFirstKey(this.offersProp[this.currentOffer]);
			  }
		  }
	  }
  }
};


window.JCH2oBuyOneClick.prototype.AjaxSubmit = function (postArray, link, dataType, successFunction) {
	var self = this;
	if(link === undefined){
		link = window.location.pathname;
	}
	if(dataType === undefined){
		dataType = 'html';
	}
	if(postArray === undefined){
		postArray = {};
	}
	if(typeof successFunction !== 'function'){
		successFunction = function (data) {
			var obj = $("<div />").html(data);
			$(self.containerID).html(obj.find(self.containerID).html());
		}
	}
	$.ajax({
		url: link,
		type: "POST",
		data: postArray,
		success: successFunction,
		dataType: dataType
	});
};

window.JCH2oBuyOneClick.prototype.ArrayIntersect = function(arr0) {
  var retArr = [];
  var argl = arr0.length;
  var arr1 = arr0[0];
  var arglm1 = argl - 1;
  var k1 = '';
  var arr = {};
  var i = 0;
  var k = '';
  if(arglm1 <= 0){
  	return arr1.slice();
	}
  arr1keys: for (k1 in arr1) {
    arrs: for (i = 1; i < argl; i++) {
      arr = arr0[i];
      for (k in arr) {
        if (arr[k] === arr1[k1]) {
          if (i === arglm1) {
            retArr.push(arr1[k1]);
          }

          continue arrs;
        }
      }
      // If it reaches here, it wasn't found in at least one array, so try next value
      continue arr1keys;
    }
  }
  return retArr
};

window.JCH2oBuyOneClick.prototype.UpdateMask = function () {
	var self = this;
	$.each(self.list_phone_prop, function(index, value){
		$("#"+value).mask(self.maskPhone);
	})
};

window.JCH2oBuyOneClick.prototype.ShowPopup = function (e) {
	e.preventDefault();
	var self = this,
			element = e.target,
			id = $(element).data('id'),
			offer_id = $(element).attr('data-offer-id'),
			quantity = $(element).attr('data-quantity'),
			link = window.location.pathname,
			postArray = {
				H2O_B1C_ELEMENT_ID: id
			};
	if(this.ajaxID){
		postArray.AJAX_CALL_BUY_ONE_CLICK = this.ajaxID;
	}else{
		postArray.AJAX_CALL_BUY_ONE_CLICK = 'Y';
	}
	if ( offer_id !== undefined && offer_id !== false){
		postArray.H2O_B1C_OFFER_ID  = offer_id;
	}
	if ( quantity !== undefined && quantity !== false){
		postArray.quantity_b1c  = quantity;
	}
	this.AjaxSubmit(postArray, link, 'html', function (data) {
		var obj = $("<div />").html(data);
		$(self.containerID).html(obj.find(self.containerID).html());
		self.oPopupBuy.show();
    self.InitOffers();
		self.UpdateMask();
		self.InitConsument();
	});
};

window.JCH2oBuyOneClick.prototype.SetQuantity = function (e) {
	e.preventDefault();
	var cur_val,
			element = e.target;
	if($(element).hasClass('minus')){
		cur_val = $(element).parent().find('input[name=quantity_b1c]').val();
		if(cur_val > 1){
			cur_val--;
		}
		$(element).parent().find('input[name=quantity_b1c]').val(cur_val);
	}
	if($(element).hasClass('plus')){
		cur_val = $(element).parent().find('input[name=quantity_b1c]').val();
		cur_val++;

		$(element).parent().find('input[name=quantity_b1c]').val(cur_val);
	}
	/** Заполнение контейнера с ценой */
	var price_container = $(element).closest("form").find(".item_current_price");
	if(price_container.length > 0) {
		var start_price = price_container.data('start-price');
		var currency = price_container.data('currency');
		var cur_price = start_price * cur_val;
		price_container.html(BX.Currency.currencyFormat(cur_price, currency, true));
	}
};

window.JCH2oBuyOneClick.prototype.ChangeOffer = function (e){
	var self = this,
			element = e.target;
	var price_container = $(element).closest("form").find(".item_current_price");
	var cur_quantity = 1;
	if($(element).closest('form').find("input[name=quantity_b1c]").length > 0) {
		cur_quantity = $(element).closest('form').find("input[name=quantity_b1c]").val();
	}
	if(price_container.length>0){
		var start_price = $(element).data('start-price');
		var currency = $(element).data('currency');
		var cur_price = start_price * cur_quantity;
		price_container.html(BX.Currency.currencyFormat(cur_price, currency, true));
		price_container.data('start-price', start_price);
		price_container.data('currency', currency);
	}
};

window.JCH2oBuyOneClick.prototype.ChangeOfferProp = function (e){
	var self = this,
			element = e.target,
			codeProp = $(element).data('code'),
			value = $(element).val(),
			tempArray = [],
			price_container = $(element).closest("form").find(".item_current_price");
	$(this.formID).find(".offers_prop_radio").each(function () {
		if($(this).prop('checked')){
      tempArray.push(self.treeOffers[$(this).data('code')][$(this).val()]);
		}
  });
	var intersect = this.ArrayIntersect(tempArray);
	var offerId = intersect.shift();
	if(offerId === undefined){
		//выбран основной параметр, и ему не нашлось комбинации второстепенных параметров
		//выбираем первый по порядку
		var mainValue = $(this.formID).find(".offers_prop_radio[data-code="+this.mainProp+"]:checked").val();
		for(offerId in this.offersProp){
			if(this.offersProp[offerId][this.mainProp] === mainValue){
				break;
			}
		}
	}
	$("[name=H2O_B1C_OFFER_ID]").val(offerId);
	this.currentOffer = offerId;
	if(price_container.length>0){
		var cur_quantity = 1;
		if($(element).closest('form').find("input[name=quantity_b1c]").length > 0) {
			cur_quantity = $(element).closest('form').find("input[name=quantity_b1c]").val();
		}
		var start_price = this.offersPrice[offerId]['PRICE'];
		var currency = this.offersPrice[offerId]['CURRENCY'];
		var cur_price = start_price * cur_quantity;
		price_container.html(BX.Currency.currencyFormat(cur_price, currency, true));
		price_container.data('start-price', start_price);
		price_container.data('currency', currency);
	}
	this.DisabledOffers();


};

window.JCH2oBuyOneClick.prototype.H2osaveConsent = function (item, callback){
	BX.UserConsent.setCurrent(item);
	var data = {
		'id': item.config.id,
		'sec': item.config.sec,
		'url': window.location.href
	};
	if (item.config.originId)
	{
		var originId = item.config.originId;
		if (item.formNode && originId.indexOf('%') >= 0)
		{
			var inputs = item.formNode.querySelectorAll('input[type="text"], input[type="hidden"]');
			inputs = BX.convert.nodeListToArray(inputs);
			inputs.forEach(function (input) {
				if (!input.name)
				{
					return;
				}
				originId = originId.replace('%' + input.name +  '%', input.value ? input.value : '');
			});
		}
		data.originId = originId;
	}
	if (item.config.originatorId)
	{
		data.originatorId = item.config.originatorId;
	}
	BX.UserConsent.sendActionRequest(
		'saveConsent',
		data,
		callback,
		callback
	);
};


	/**
	 * Кроссбраузерное получение XMLHttpRequest
	 */
window.JCH2oBuyOneClick.prototype.h2ob1cgetXmlHttp = function(){
	var xmlhttp;
	try {
		xmlhttp = new ActiveXObject("Msxml2.XMLHTTP");
		if(Object.keys(xmlhttp).length === 0){
			throw new Error();
		}
	} catch (e) {
		try {
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
			if(Object.keys(xmlhttp).length === 0){
				throw new Error();
			}
		} catch (E) {
			xmlhttp = false;
		}
	}
	var req = window.XMLHttpRequest?
		new XMLHttpRequest() :
		new ActiveXObject("Microsoft.XMLHTTP");

	if (!xmlhttp && typeof XMLHttpRequest!='undefined') {
		xmlhttp = new XMLHttpRequest();
	}
	return xmlhttp;
};

/**
 * Отправка формы аяксом с отправкой файлов
 * @param form
 * @param link
 * @param addValue
 */
window.JCH2oBuyOneClick.prototype.h2ob1csendForm = function(form, link, addValue){
	var formData = new FormData(form),
			self = this;
	if (addValue !== undefined) {
		for (var v in addValue) {
			formData.append(v,addValue[v]);
		}
	}
	var xhr2 = self.h2ob1cgetXmlHttp();
	xhr2.open("POST", link);
	//xhr2.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded')
	xhr2.onreadystatechange = function() {
		if (xhr2.readyState === 4) {
			if(xhr2.status === 200) {
				data = xhr2.responseText;
				var obj = $("<div />").html(data);
				/** если офрмили заказ, то удаляем кнопку отправки формы */
				if(obj.find(".SUCCESS").val() === 'Y'){
					obj.find("#buy_one_click_form input.submit").remove();
				}
				$(self.containerID).html(obj.find(self.containerID).html());
				self.oPopupBuy.adjustPosition();
				self.HideBlockModal();
				self.UpdateMask();
				self.InitConsument();
			}
		}
	};
	xhr2.send(formData);
};

window.JCH2oBuyOneClick.prototype.ShowBlockModal = function () {
	$(this.blockModal).show();
};

window.JCH2oBuyOneClick.prototype.HideBlockModal = function () {
  $(this.blockModal).hide();
};

window.JCH2oBuyOneClick.prototype.SubmitForm = function (e) {
	e.preventDefault();
	var self = this,
			form = e.target,
			consentInput = $(form).find("#userconsent_h2o input");
	self.ShowBlockModal();
	if(!!BX.UserConsent && consentInput.length > 0){
		if(!consentInput.prop('checked')) {
			self.HideBlockModal();
			return false;
		}else{
			self.H2osaveConsent(BX.UserConsent.find(BX($(self.formID)[0]))[0]);
		}
	}
	var link = window.location.pathname;
	var addValue = {};
	//var postArray = $(this).serialize();
	if(self.ajaxID.length > 0){
		//postArray = postArray + '&AJAX_CALL_BUY_ONE_CLICK='+$(this).find(".input_ajax_id").val();
		addValue.AJAX_CALL_BUY_ONE_CLICK = self.ajaxID;
	}else{
		//postArray = postArray + '&AJAX_CALL_BUY_ONE_CLICK=Y';
		addValue.AJAX_CALL_BUY_ONE_CLICK = 'Y';
	}
	self.h2ob1csendForm(form, link, addValue);
};

window.JCH2oBuyOneClick.prototype.InitEvents = function () {
	var self = this;
	$(document).on('click', '.buy_one_click_popup', BX.delegate(self.ShowPopup, self));
	$(document).on('click', '.buy_one_click_popup_order', BX.delegate(self.ShowPopup, self));
	$(document).on('click', self.formID+' .button_set_quantity', BX.delegate(self.SetQuantity, self));
	$(document).on('change', self.formID+' input[name=H2O_B1C_OFFER_ID]', BX.delegate(self.ChangeOffer, self));
	$(document).on('change', self.formID+' input[type=radio].offers_prop_radio', BX.delegate(self.ChangeOfferProp, self));
	$(document).on('submit', self.formID, BX.delegate(self.SubmitForm, self));
};

window.JCH2oBuyOneClick.prototype.InitModal = function () {
	var self = this;
	/**
	 * Описываем модальное окно
	 * https://dev.1c-bitrix.ru/api_help/main/js_lib/popup/index.php
	 */
	this.oPopupBuy = new BX.PopupWindow('call_feedback',
		null,
		{
			content: BX( $(self.containerID)[0]),//$(self.containerID).html(),
			autoHide : false,
			/*titleBar: {content: BX.create("span", {html: '<b>Покупка</b>', 'props': {'className': 'access-title-bar'}})},*/
			offsetTop : 1,
			offsetLeft : 0,
			lightShadow : true,
			closeIcon : true,
			closeByEsc : true,
			draggable: {restrict: false},
			overlay: {
				backgroundColor: 'grey', opacity: '80'
			}
		});
};


window.JCH2oBuyOneClick.prototype.InitLocation = function () {
	var self = this;
	if(typeof(TCJsUtils) === 'object'){
		TCJsUtils.show = function(oDiv, iLeft, iTop)
		{
			if (typeof oDiv !== 'object')
				return;
			//document.getElementById("buy_one_click_ajaxwrap").appendChild(oDiv);
			$(self.containerID+" .modal-body").append(oDiv);
			var zIndex = parseInt(oDiv.style.zIndex);
			if(zIndex <= 0 || isNaN(zIndex))
				zIndex = 10000;
			oDiv.style.zIndex = zIndex;
			oDiv.style.left = iLeft + "px";
			oDiv.style.top = iTop + "px";

			return oDiv;
		};
		TCJsUtils.GetRealPos = function(el)
		{
			if(!el || !el.offsetParent)
				return false;
			var res = [];
			var objParent = el.offsetParent;
			res["left"] = el.offsetLeft;
			res["top"] = el.offsetTop;
			res["right"]=res["left"] + el.offsetWidth;
			res["bottom"]=res["top"] + el.offsetHeight;
			res["width"]=el.offsetWidth;
			res["height"]=el.offsetHeight;
			return res;
		}
	}
};

window.JCH2oBuyOneClick.prototype.InitConsument = function () {
	var self = this;
	if (!!BX.UserConsent) {
		//var control = BX.UserConsent.load(BX($(self.formID)[0]));
		var control = BX.UserConsent.load(BX(this._formID));
		if (!!control) {
			BX.addCustomEvent(
				control,
				BX.UserConsent.events.save,
				function (data) {
					console.log(data);
				}
			);
		}
	}
};

window.JCH2oBuyOneClick.prototype.getFirstKey = function (arr) {
	var key;
	if (typeof arr === 'object') {
		for (key in arr) {
			break;
		}
	}
	return key;
};

window.JCH2oBuyOneClick.prototype.getPossibleOffers = function (code, value) {
	var self = this, offerId, arReturn = [];
	for(offerId in self.offersProp){
		if(self.offersProp[offerId][code] === value){
			arReturn.push(offerId);
		}
	}
	return arReturn;
};

window.JCH2oBuyOneClick.prototype.filterSelector = function (text) {
  return text.replace(/ /g,"_").replace(/\*/g,"_").replace(/&quot;/g,'_').replace(/\./g,"_").replace(/:/g,"_").replace(/\"/g,'_');
};

window.JCH2oBuyOneClick.prototype.InitOffers = function () {
  if(typeof this.offersProp !== 'object'  || this.offersProp.length <= 0 || this.currentOffer <= 0){
		return;
	}
	var key, formatKey;
	for(key in this.offersProp[this.currentOffer]){
		formatKey = this.filterSelector(this.offersProp[this.currentOffer][key]);
		$('#'+this._containerID+'_OFFER_'+key+'_'+formatKey).prop('checked', true);
	}
	$("[name=H2O_B1C_OFFER_ID]").val(this.currentOffer);
	this.DisabledOffers();
};

window.JCH2oBuyOneClick.prototype.DisabledOffers = function () {
	var
		key,
		codeProp,
		mainProp = this.mainProp,
		mainValue = this.offersProp[this.currentOffer][mainProp],
		possibleOffer = this.getPossibleOffers(mainProp, mainValue),
		$radio = $(this.formID).find('.offers_prop_radio');
	//скрываем все свойства
	$radio.each(function () {
		$(this).attr('disabled', true).attr('checked', false).closest('span').hide();
	});

	//выделяем инпуты текущего торг.предложения
	var formatKey;
	for(key in this.offersProp[this.currentOffer]){
		formatKey = this.filterSelector(this.offersProp[this.currentOffer][key]);
		$('#'+this._containerID+'_OFFER_'+key+'_'+formatKey).prop('checked', true).attr('disabled', false).closest('span').show();
	}

	//показываем пункты основного свойства
	$radio.each(function(){
		if($(this).data('code') === mainProp){
			$(this).attr('disabled', false).closest('span').show();
		}
	});

	//показываем возможные комбинации с текущим значением основного свойства
	for(key in possibleOffer){
		for(codeProp in this.offersProp[possibleOffer[key]]){
			formatKey = this.offersProp[possibleOffer[key]][codeProp].replace(/&quot;/g,"\"");
			$(this.formID).find(".offers_prop_radio[data-code='"+codeProp+"'][value='"+formatKey+"']").attr('disabled', false).closest('span').show();
		}
	}
};
window.JCH2oBuyOneClick.prototype.Init = function () {
	this.InitEvents();
	this.InitModal();
	this.InitLocation();
	this.InitOffers();
};
