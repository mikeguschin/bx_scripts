<?
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

$arComponentDescription = array(
	"NAME" => GetMessage("ARUS_CORP_COMPONENT_NAME"),
	"DESCRIPTION" => GetMessage("ARUS_CORP_COMPONENT_DESCRIPTION"),
	"ICON" => "/images/sale_order_full.gif",
	"PATH" => array(
		"ID" => "e-store",
		"CHILD" => array(
			"ID" => "arus_order",
			"NAME" => GetMessage("ARUS_CORP_NAME")
		)
	),
);
?>