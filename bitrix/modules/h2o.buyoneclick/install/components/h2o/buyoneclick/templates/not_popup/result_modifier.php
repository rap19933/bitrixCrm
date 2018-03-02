<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
$arOffersProp = array();
$arOfferTree = array();
$arOfferPrice = array();
$arResult['OFFERS_PROPERTIES'] = array();
if(is_array($arResult['OFFERS']) && !empty($arResult['OFFERS']) && is_array($arParams['LIST_OFFERS_PROPERTY_CODE'])){
	foreach($arResult['OFFERS'] as $arOffer){
		if(!isset($arOffersProp[$arOffer['ID']])){
			$arOffersProp[$arOffer['ID']] = array();
		}
		$arOfferPrice[$arOffer['ID']] = array(
			'PRICE' => $arOffer['MIN_PRICE']['DISCOUNT_VALUE'],
			'CURRENCY' => $arOffer['MIN_PRICE']['CURRENCY']
		);
		if(is_array($arOffer['PROPERTIES']) && !empty($arOffer['PROPERTIES'])){
			foreach($arOffer['PROPERTIES'] as $arProperties){
				if(in_array($arProperties['CODE'], $arParams['LIST_OFFERS_PROPERTY_CODE']) && $arProperties['VALUE'] != ""){
					if(!isset($arOfferTree[$arProperties['CODE']])){
						$arOfferTree[$arProperties['CODE']] = array();
						$arResult['OFFERS_PROPERTIES'][$arProperties['CODE']] = $arProperties;
					}
					if(!isset($arOfferTree[$arProperties['CODE']][$arProperties['VALUE']])){
						$arOfferTree[$arProperties['CODE']][$arProperties['VALUE']] = array();
					}
					$arOffersProp[$arOffer['ID']][$arProperties['CODE']] = $arProperties['VALUE'];
					$arOfferTree[$arProperties['CODE']][$arProperties['VALUE']][] = $arOffer['ID'];
				}
			}
		}
	}
}
foreach($arOfferTree as $propCode => $arValue){
	foreach($arValue as $value => $arOfferId){
	
	}
}
$arResult['TREE_OFFERS'] = $arOfferTree;
$arResult['OFFERS_PROP'] = $arOffersProp;
$arResult['OFFERS_PRICE'] = $arOfferPrice;