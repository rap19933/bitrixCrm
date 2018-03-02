<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
?>
<div class="modal-header">
	<div  class="item_price">
		<div class="modal_title"><?=$arResult['CURRENT_PRODUCT']['FIELDS']?></div>
		<?
		$current_offer = false;
		if(isset($arResult['CURRENT_OFFER_ID']) && intval($arResult['CURRENT_OFFER_ID'])){
			$current_offer = intval($arResult['CURRENT_OFFER_ID']);
		}elseif(intval($arResult['CURRENT_PRODUCT']['OFFER_ID_MIN_PRICE']) > 0){
			$current_offer = intval($arResult['CURRENT_PRODUCT']['OFFER_ID_MIN_PRICE']);
		}else{
			$current_offer = $arResult['OFFERS'][0]['ID'];
		}
		?>
		<div class="item_current_price"
		     data-start-price="<?=$arResult['CURRENT_PRODUCT']['OFFERS'][$current_offer]['PRICE']?>"
		     data-currency="<?=$arResult['CURRENT_PRODUCT']['OFFERS'][$current_offer]['CURRENCY']?>">
			<?=FormatCurrency($arResult['CURRENT_PRODUCT']['OFFERS'][$current_offer]['PRICE'],
				$arResult['CURRENT_PRODUCT']['OFFERS'][$current_offer]["CURRENCY"])?>
		</div>

	</div>

	<span class="modal_title"><?=GetMessage("OFFERS")?></span>
</div>
<div class="modal-container">
	<input type="hidden" name="offers" value="Y"/>
	<input type="hidden" value="<?=$arResult['QUANTITY']?>" name="quantity_b1c"/>
	<? /*if($arParams["USE_CAPTCHA"] == "Y"){?>
			                        <input type="hidden" name="captcha_sid" value="<?=$arResult['POST']['captcha_sid']?>" />
			                        <input type="hidden" name="captcha_word" value="<?=$arResult['POST']['captcha_word']?>" />
			                    <?}*/
	?>
	<?
	if(is_array($arResult['POST']['ONECLICK']) && !empty($arResult['POST']['ONECLICK'])){
		foreach($arResult['POST']['ONECLICK'] as $name => $post){
			?>
			<input type="hidden" name="ONECLICK[<?=$name?>]" value="<?=$post?>"/>
			<?
		}
	}
	if(isset($arResult['POST']['ONECLICK_COMMENT'])){
		?>
		<input type="hidden" name="ONECLICK_COMMENT" value="<?=$arResult['POST']['ONECLICK_COMMENT']?>"/><?
	}
	?>
	<?
	if(is_array($arResult['POST']['ONECLICK_PROP']) && !empty($arResult['POST']['ONECLICK_PROP'])){
		foreach($arResult['POST']['ONECLICK_PROP'] as $nameP => $postP){
			?>
			<input type="hidden" name="ONECLICK_PROP[<?=$nameP?>]" value="<?=$postP?>"/>
			<?
		}
	} ?>
	<?include($server->getDocumentRoot().$templateFolder.'/offers_list.php');?>

	<!--	ƒобавим поле количество на втором шаге	-->

	<? if($arParams['SHOW_QUANTITY'] && !$arParams['BUY_CURRENT_BASKET']): ?>
		<div class="form-row">
			<div class="form-cell-3">
				<label
					for="quantity_b1c"><?=GetMessage('QUANTITY_LABEL');?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
						?>*<? } ?>:</label>
			</div>
			<div class="form-cell-9">
                                            <span class="item_buttons_counter_block">
												<a href="#" class="button_set_quantity minus bx_bt_button_type_2 bx_small bx_fwb">-</a>
												<input id="quantity_b1c" type="text" class="tac transparent_input" value="1"
												       name="quantity_b1c"/>
												<a href="#" class="button_set_quantity plus bx_bt_button_type_2 bx_small bx_fwb">+</a>
											</span>
			</div>
		</div>
	<? endif; ?>


	<div class="clr"></div>
	<? if($arResult['POST']['PAY_SYSTEM'] > 0){
		?>
		<input type="hidden" name="PAY_SYSTEM" value="<?=$arResult['POST']['PAY_SYSTEM']?>"/>
	<? } ?>
	<? if($arResult['POST']['DELIVERY'] > 0){
		?>
		<input type="hidden" name="DELIVERY" value="<?=$arResult['POST']['DELIVERY']?>"/>
	<? } ?>
	<div class="clr"></div>
	<div class="form-row">
		<button class="button" id="h2o_preorder_button_submit"><?=GetMessage("MAKE")?></button>
	</div>
</div>
