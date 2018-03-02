<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
use Bitrix\Highloadblock as HL,
	Bitrix\Main\Entity;
$arReplace = array(' ',':','.','*', '&quot;');
?>

<input type="hidden" name="H2O_B1C_OFFER_ID" value="<?=$arResult['CURRENT_OFFER_ID']?>">
<?foreach($arResult['TREE_OFFERS'] as $codeProp => $arValues):?>
	<div class="offer-block">
		<b><?=$arResult['OFFERS_PROPERTIES'][$codeProp]['NAME']?></b>
		<?foreach($arValues as $value => $arOfferID):?>
			<span>
					<input id="<?=$arJSParams['CONTAINER_ID']?>_OFFER_<?=$codeProp?>_<?=str_replace($arReplace,"_",$value)?>" class="offers_prop_radio" type="radio" name="<?=$arJSParams['CONTAINER_ID']?>_OFFERS_PROP_<?=$codeProp?>" data-code="<?=$codeProp?>" value="<?=$value?>">
					<label for="<?=$arJSParams['CONTAINER_ID']?>_OFFER_<?=$codeProp?>_<?=str_replace($arReplace,"_",$value)?>">
						<?
						
						/**
						 * Далее идет проверка, является ли свойство типа Справочник.
						 * Получаем массив со значениями элемента highload блока.
						 * По умолчанию выводим поле UF_NAME.
						 * Если указать параметр у компонента HIGHLOAD_SHOW_FIELD,
						 * то будет выводится указанное поле.
						 * Если в указанном поле хранится число, то предполагаем,
						 * что это файл, и пробуем получить его
						 */
						if($arResult['OFFERS_PROPERTIES'][$codeProp]['PROPERTY_TYPE'] == 'S' &&
							$arResult['OFFERS_PROPERTIES'][$codeProp]['USER_TYPE'] == 'directory' &&
							$arResult['OFFERS_PROPERTIES'][$codeProp]['USER_TYPE_SETTINGS']['TABLE_NAME'] != ""){ //highload block
							$hlblock = HL\HighloadBlockTable::getList(
								array('filter' =>
									      array('TABLE_NAME' => $arResult['OFFERS_PROPERTIES'][$codeProp]['USER_TYPE_SETTINGS']['TABLE_NAME'])
								)
							)->fetch();
							// get entity
							$entity = HL\HighloadBlockTable::compileEntity($hlblock);
							$entity_data_class = $entity->getDataClass();
							$main_query = new Entity\Query($entity);
							$main_query->setSelect(array('*'));
							$main_query->setFilter(array(
								'UF_XML_ID' => $value
							));
							$result = $main_query->exec();
							$result = new CDBResult($result);
							if($row = $result->Fetch())
							{
								if($arParams['HIGHLOAD_SHOW_FIELD'] != "" && isset($row[$arParams['HIGHLOAD_SHOW_FIELD']])){
									if(is_numeric($row[$arParams['HIGHLOAD_SHOW_FIELD']])){ //проверяем, файл ли это
										if($file = CFile::ResizeImageGet($row[$arParams['HIGHLOAD_SHOW_FIELD']], array('width'=>16, 'height'=>16), BX_RESIZE_IMAGE_EXACT, true)){
											$value = '<img src="'.$file['src'].'"/>';
										}else{
											$value = $row[$arParams['HIGHLOAD_SHOW_FIELD']];
										}
									}else{
										$value = $row[$arParams['HIGHLOAD_SHOW_FIELD']];
									}
								}elseif(isset($row['UF_FILE'])){
									if(is_numeric($row['UF_FILE'])){ //проверяем, файл ли это
										if($file = CFile::ResizeImageGet($row['UF_FILE'], array('width'=>16, 'height'=>16), BX_RESIZE_IMAGE_EXACT, true)){
											$value = '<img style="border: 1px solid grey" src="'.$file['src'].'"/>';
										}else{
											$value = $row['UF_NAME'];
										}
									}else{
										$value = $row['UF_NAME'];
									}
								}else{
									$value = $row['UF_NAME'];
								}
							}
						}
						?>
						<?=$value?>
					</label>
				</span>
		<?endforeach;?>
	</div>

<?endforeach;?>
	