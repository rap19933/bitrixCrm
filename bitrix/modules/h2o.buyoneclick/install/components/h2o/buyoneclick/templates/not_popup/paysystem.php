<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
?>
<? if(count($arResult['PAY_SYSTEM']) > 1 && $arParams['SHOW_PAY_SYSTEM']):?>
	<div class="modal-header">
		<span class="modal_title"><?=GetMessage("PAY_SYSTEM")?></span>
	</div>
	<div class="form-row">
		<? if(is_array($arResult['PAY_SYSTEM']) && !empty($arResult['PAY_SYSTEM'])): ?>
			<? $first = true; ?>
			<? foreach($arResult['PAY_SYSTEM'] as $pay_system): ?>
				<div class="form-cell-6">
					<input type="radio"
					       id="PAY_SYSTEM_<?=$pay_system['ID']?>" <?=($arResult['POST']['PAY_SYSTEM'] == $pay_system['ID']?'checked':'')?>
					       name="PAY_SYSTEM" value="<?=$pay_system['ID']?>" required="required" <? if($first){
						$first = false;
						print"checked";
					} ?> hidden="hidden"/>
					<label for="PAY_SYSTEM_<?=$pay_system['ID']?>"><?=$pay_system['NAME']?></label>
				</div>
			<? endforeach; ?>
		<? endif; ?>
	</div>
<?elseif($arParams['SHOW_PAY_SYSTEM']):?>
	<input type="hidden" name="PAY_SYSTEM" value="<?=$arResult['PAY_SYSTEM'][0]['ID']?>"/>
<? endif; ?>