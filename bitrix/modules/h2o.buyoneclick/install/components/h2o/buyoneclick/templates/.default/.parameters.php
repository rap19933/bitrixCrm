<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arTemplateParameters = array(
	"ID_FIELD_PHONE" => Array(
		"NAME" => GetMessage("ID_FIELD_PHONE"),
		"TYPE" => "STRING",
		"MULTIPLE" => "Y"
	),
	"MASK_PHONE" => Array(
		"NAME" => GetMessage("MASK_PHONE"),
		"TYPE" => "STRING",
		"MULTIPLE" => "N",
		"DEFAULT" => "(999) 999-9999"
	)
);