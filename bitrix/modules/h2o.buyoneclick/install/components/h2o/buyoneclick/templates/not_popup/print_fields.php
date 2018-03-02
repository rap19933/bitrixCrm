<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
?>
<? if(is_array($arResult['USER_FIELDS']) && !empty($arResult['USER_FIELDS'])):
	foreach($arResult['USER_FIELDS'] as $user_fields):?>
		<div class="form-row">
			<div class="form-cell-3">
				<label
					for="individual<?=$user_fields?>"><?=GetMessage("$user_fields");?><? if($arResult['USER_FIELDS_REQUIRED'][$user_fields] == 'Y'){
						?>*<? } ?>:</label>
			</div>
			<div class="form-cell-9">
				<input type="text" name="ONECLICK[<?=$user_fields?>]"
				       value="<?=$arResult['CURRENT_USER_FIELDS'][$user_fields]?>"
				       id="individual<?=$user_fields?>"/>
				<? if($arResult['ERRORS'][$user_fields] == 'Y'):
					?>
					<small class="error"><?=GetMessage("ERROR")?></small>
				<? elseif($arResult['ERRORS'][$user_fields] == 'EMAIL'): ?>
					<small class="error"><?=GetMessage("ERROR_EMAIL")?></small>
				<? endif; ?>
			</div>
		</div>
	<? endforeach ?>
<? endif; ?>
<? if(is_array($arResult['SHOW_PROPERTIES']) && !empty($arResult['SHOW_PROPERTIES'])): ?>
	<? foreach($arResult['SHOW_PROPERTIES'] as $order_props): ?>
		<?if($order_props['UTIL'] == 'Y'):?>
			<input type="hidden" name="ONECLICK_PROP[<?=$order_props['ID']?>]" value="<?=$order_props['DEFAULT_VALUE']?>">
			<?continue;?>
		<?endif;?>
		<? if($order_props['TYPE'] == "TEXT"): ?>
			<div class="form-row">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props['NAME'];?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<div class="form-cell-9">
					<input type="text" name="ONECLICK_PROP[<?=$order_props['ID']?>]"
					       value="<?=$arResult['CURRENT_USER_PROPS'][$order_props['CODE']]?>"
					       id="individual<?=$order_props['ID']?>"/>
					<? if($arResult['ERRORS'][$order_props['ID']] == 'Y'):?>
						<small class="error"><?=GetMessage("ERROR")?></small>
					<? elseif($arResult['ERRORS'][$order_props['ID']] == 'EMAIL'): ?>
						<small class="error"><?=GetMessage("ERROR_EMAIL")?></small>
					<? endif; ?>
				</div>
			</div>
		<? elseif($order_props['TYPE'] == "CHECKBOX"): ?>
			<div class="form-row">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props['NAME'];?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<div class="form-cell-9">
					<input type="checkbox" name="ONECLICK_PROP[<?=$order_props['ID']?>]"
					       value="Y<?/*=$arResult['CURRENT_USER_PROPS'][$order_props['CODE']]*/?>"
					       id="individual<?=$order_props['ID']?>"/>
					<? if($arResult['ERRORS'][$order_props['ID']] == 'Y'){
						?>
						<small class="error"><?=GetMessage("ERROR")?></small>
					<? } ?>
				</div>
			</div>
		<? elseif($order_props['TYPE'] == "TEXTAREA"): ?>
			<div class="form-row">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props['NAME'];?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<div class="form-cell-9">
													<textarea name="ONECLICK_PROP[<?=$order_props['ID']?>]"
													          id="individual<?=$order_props['ID']?>"><?=$arResult['CURRENT_USER_PROPS'][$order_props['CODE']]?></textarea>
					<? if($arResult['ERRORS'][$order_props['ID']] == 'Y'){
						?>
						<small class="error"><?=GetMessage("ERROR")?></small>
					<? } ?>
				</div>
			</div>
		<? elseif($order_props["TYPE"] == "LOCATION"): ?>
			<? $locationTemplate = ".default" ?>
			<div class="form-row reg-individual show">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props["NAME"]?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<? $value = $order_props['DEFAULT_VALUE']; ?>
				<? if($_REQUEST['ONECLICK_PROP'][$order_props['ID']] > 0){
					$value = $arResult['CURRENT_USER_PROPS'][$order_props['CODE']];
				} ?>
				<div class="form-cell-9">
					<? $APPLICATION->IncludeComponent("bitrix:sale.ajax.locations", "popup", array("COMPONENT_TEMPLATE" => "popup", "CITY_OUT_LOCATION" => "Y", "ALLOW_EMPTY_CITY" => "Y", "COUNTRY_INPUT_NAME" => "COUNTRY", "REGION_INPUT_NAME" => "REGION", "CITY_INPUT_NAME" => "ONECLICK_PROP[" . $order_props['ID'] . "]", "COUNTRY" => "1", "LOCATION_VALUE" => $value, "ONCITYCHANGE" => "", "NAME" => "q"), false); ?>
					<? if($arResult['ERRORS'][$order_props['ID']] == 'Y'){
						?>
						<small class="error"><?=GetMessage("ERROR")?></small>
					<? } ?>
				</div>
			</div>
		<? elseif($order_props['TYPE'] == 'RADIO'): ?>
			<div class="form-row">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props['NAME'];?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<div class="form-cell-9">
					<?foreach($order_props['VALUE'] as $prop_value):?>
						<label for="individualradio<?=$order_props['ID']?>"><?=$prop_value['NAME']?></label>
						<input type="checkbox" name="ONECLICK_PROP[<?=$order_props['ID']?>]"
						       value="<?=$prop_value['VALUE']?>"
						       id="individualradio<?=$order_props['ID']?>"/>
					<?endforeach;?>
				</div>
			</div>
		<? elseif($order_props['TYPE'] == 'FILE'):?>
			<div class="form-row">
				<div class="form-cell-3">
					<label
						for="individual<?=$order_props['ID']?>"><?=$order_props['NAME'];?><? if($arResult['SHOW_PROPERTIES_REQUIRED'][$order_props['ID']] == 'Y'){
							?>*<? } ?>:</label>
				</div>
				<div class="form-cell-9">
					<input type="file" name="ONECLICK_PROP[<?=$order_props['ID']?>]"
					       value="<?=$arResult['CURRENT_USER_PROPS'][$order_props['CODE']]?>"
					       id="individual<?=$order_props['ID']?>"/>
					<? if($arResult['ERRORS'][$order_props['ID']] == 'Y'):?>
						<small class="error"><?=GetMessage("ERROR")?></small>
					<? elseif($arResult['ERRORS'][$order_props['ID']] == 'EMAIL'): ?>
						<small class="error"><?=GetMessage("ERROR_EMAIL")?></small>
					<? endif; ?>
				</div>
			</div>
		<? endif; ?>
	<? endforeach ?>
<? endif; ?>
<? if($arParams['SHOW_USER_DESCRIPTION']): ?>
	<div class="form-row">
		<div class="form-cell-3">
			<label for="oneclickcomment"><?=GetMessage("H2O_BUYONECLICK_USER_DESCRIPTION")?>:</label>
		</div>
		<div class="form-cell-9">
			<textarea name="ONECLICK_COMMENT" id="oneclickcomment" cols="30" rows="10"><?=$arResult['POST']['ONECLICK_COMMENT']?></textarea>
		</div>
	</div>

<? endif; ?>