<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
?>
<div id="userconsent_h2o">
	<?if ($arParams['USER_CONSENT'] == 'Y'):?>
		<?$APPLICATION->IncludeComponent(
			"bitrix:main.userconsent.request",
			"",
			array(
				"ID" => $arParams["USER_CONSENT_ID"],
				"IS_CHECKED" => $arParams["USER_CONSENT_IS_CHECKED"],
				"AUTO_SAVE" => "N",
				"IS_LOADED" => "Y",//$arParams["USER_CONSENT_IS_LOADED"],
				'SUBMIT_EVENT_NAME' => 'submit_buy_one_click_form',
				"REPLACE" => array(
					'button_caption' => GetMessage("MAKE"),
					'fields' => array(GetMessage("EMAIL"), GetMessage("PERSONAL_PHONE"), GetMessage("NAME"))
				),
			)
		);?>
	<?endif;?>
</div>
