<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
?>
<? if(count($arResult['DELIVERY']) > 1 && $arParams['SHOW_DELIVERY']):?>
	<div class="modal-header">
		<span class="modal_title"><?=GetMessage("DELIVERY")?></span>
	</div>
	<div class="form-row">
		<? if(is_array($arResult['DELIVERY']) && !empty($arResult['DELIVERY'])): ?>
			<? $first = true; ?>
			<? foreach($arResult['DELIVERY'] as $delivery): ?>
				<div class="form-cell-6">
					<input id="DELIVERY_<?=$delivery['ID']?>"
					       type="radio" <?=($arResult['POST']['DELIVERY'] == $delivery['ID']?'checked':'')?>
					       name="DELIVERY" value="<?=$delivery['ID']?>" required="required" <? if($first){
						$first = false;
						print 'checked';
					} ?> hidden="hidden"/>
					<label
						for="DELIVERY_<?=$delivery['ID']?>"><?=$delivery['NAME']?> <?=FormatCurrency($delivery['PRICE'], $delivery['CURRENCY'])?></label>
				</div>
			<? endforeach ?>
		<? endif; ?>
	</div>
<? elseif($arParams['SHOW_DELIVERY']): ?>
	<input type="hidden" name="DELIVERY" value="<?=$arResult['DELIVERY'][0]['ID']?>"/>
<? endif; ?>