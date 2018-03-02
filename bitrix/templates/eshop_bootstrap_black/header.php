<?if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();
IncludeTemplateLangFile($_SERVER["DOCUMENT_ROOT"]."/bitrix/templates/".SITE_TEMPLATE_ID."/header.php");
CJSCore::Init(array("fx"));
$curPage = $APPLICATION->GetCurPage(true);
$theme = COption::GetOptionString("main", "wizard_eshop_bootstrap_theme_id", "blue", SITE_ID);
?>
<!DOCTYPE html>
<html xml:lang="<?=LANGUAGE_ID?>" lang="<?=LANGUAGE_ID?>">
<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge" />
	<meta name="viewport" content="user-scalable=no, initial-scale=1.0, maximum-scale=1.0, width=device-width">
	<link rel="shortcut icon" type="image/x-icon" href="<?=SITE_DIR?>favicon.ico" />
	<?$APPLICATION->ShowHead();?>
	<?
	$APPLICATION->SetAdditionalCSS(SITE_TEMPLATE_PATH."/colors.css", true);
	$APPLICATION->SetAdditionalCSS("/bitrix/css/main/bootstrap.css");
	$APPLICATION->SetAdditionalCSS("/bitrix/css/main/font-awesome.css");
	?>
	<title><?$APPLICATION->ShowTitle()?></title>

    <!-- Global site tag (gtag.js) - Google Analytics -->
   <!-- <script async src="https://www.googletagmanager.com/gtag/js?id=UA-114519469-1"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'UA-114519469-1');
    </script>-->
    <!--GA-->
    <script type="text/javascript">
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

        ga('create', 'UA-114519469-1', 'auto');

        function getCookie(name) {
            var matches = document.cookie.match(new RegExp(
                "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
            ));

            return matches ? decodeURIComponent(matches[1]) : "";
        }

        ga('set', 'dimension1', getCookie("_ga"));
        ga('require', 'displayfeatures');
        //ga('send', 'pageview');

        /* Accurate bounce rate by time */

        if (!document.referrer || document.referrer.split('/')[2].indexOf(location.hostname) != 0)
            setTimeout(function(){
                ga('send', 'event', 'Новый посетитель', location.pathname);
            }, 15000);
        ga('send', 'pageview');
    </script>
    <!--Collector-->
    <script type="text/javascript">
        (function(_,r,e,t,a,i,l){_['retailCRMObject']=a;_[a]=_[a]||function(){(_[a].q=_[a].q||[]).push(arguments)};_[a].l=1*new Date();l=r.getElementsByTagName(e)[0];i=r.createElement(e);i.async=!0;i.src=t;l.parentNode.insertBefore(i,l)})(window,document,'script','https://collector.retailcrm.pro/w.js','_rc');

        _rc('create', 'RC-80522113463-3', {
            'customerId': '<?=$USER->GetID()?>'
        });
        _rc('require', 'capture-form', {
            'period': 60,
            'fields': {
                'name': {},
                'phone': { required: true, label: 'Телефон' },
            },
            orderMethod: 'back-call',
            itemId: '',
            labelPromo: "Хотите, мы вам перезвоним?",
            labelSend: "Перезвоните мне!"
        });
        //_rc('send', 'pageView');
    </script>

    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
                new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-565K7RG');</script>
    <!-- End Google Tag Manager -->
</head>

<body class="bx-background-image bx-theme-<?=$theme?>" <?=$APPLICATION->ShowProperty("backgroundImage")?>>

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-565K7RG"
                  height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

<div id="panel"><?$APPLICATION->ShowPanel();?></div>
<?$APPLICATION->IncludeComponent("bitrix:eshop.banner", "", array());?>
<div class="bx-wrapper" id="bx_eshop_wrap">
	<header class="bx-header">
		<div class="bx-header-section container">
			<div class="row">
				<div class="col-lg-3 col-md-3 col-sm-4 col-xs-12">
					<div class="bx-logo">
						<a class="bx-logo-block hidden-xs" href="<?=SITE_DIR?>">
							<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/company_logo.php"), false);?>
						</a>
						<a class="bx-logo-block hidden-lg hidden-md hidden-sm text-center" href="<?=SITE_DIR?>">
							<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/company_logo_mobile.php"), false);?>
						</a>
					</div>
				</div>
				<div class="col-lg-3 col-md-3 col-sm-4 col-xs-12">
					<div class="bx-inc-orginfo">
						<div>
							<span class="bx-inc-orginfo-phone"><i class="fa fa-phone"></i> <?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/telephone.php"), false);?></span>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-3 hidden-sm hidden-xs">
					<div class="bx-worktime">
						<div class="bx-worktime-prop">
							<?$APPLICATION->IncludeComponent("bitrix:main.include", "", array("AREA_FILE_SHOW" => "file", "PATH" => SITE_DIR."include/schedule.php"), false);?>
						</div>
					</div>
				</div>
				<div class="col-lg-3 col-md-3 col-sm-4 col-xs-12 hidden-xs">
					<?$APPLICATION->IncludeComponent("bitrix:sale.basket.basket.line", "", array(
							"PATH_TO_BASKET" => SITE_DIR."personal/cart/",
							"PATH_TO_PERSONAL" => SITE_DIR."personal/",
							"SHOW_PERSONAL_LINK" => "N",
							"SHOW_NUM_PRODUCTS" => "Y",
							"SHOW_TOTAL_PRICE" => "Y",
							"SHOW_PRODUCTS" => "N",
							"POSITION_FIXED" =>"N",
							"SHOW_AUTHOR" => "Y",
							"PATH_TO_REGISTER" => SITE_DIR."login/",
							"PATH_TO_PROFILE" => SITE_DIR."personal/"
						),
						false,
						array()
					);?>
				</div>
			</div>
            <? if ($APPLICATION->GetCurDir() == SITE_DIR."personal/cart/" || $APPLICATION->GetCurDir() == SITE_DIR."personal/"):?>
                <div class="row">
                    <div class="text-right">
                        <?$APPLICATION->IncludeComponent(
                            "h2o:buyoneclick",
                            "default_old1",
                            array(
                                "ADD_NOT_AUTH_TO_ONE_USER" => "Y",
                                "ADD_NOT_AUTH_TO_ONE_USER_ID" => "1",
                                "ALLOW_ORDER_FOR_EXISTING_EMAIL" => "N",
                                "BUY_CURRENT_BASKET" => "Y",
                                "CACHE_TIME" => "86400",
                                "CACHE_TYPE" => "N",
                                "COMPONENT_TEMPLATE" => "default_old1",
                                "DEFAULT_DELIVERY" => "1",
                                "DEFAULT_PAY_SYSTEM" => "1",
                                "DELIVERY" => array(
                                ),
                                "IBLOCK_ID" => "2",
                                "IBLOCK_TYPE" => "catalog",
                                "ID_FIELD_PHONE" => array(
                                ),
                                "LIST_OFFERS_PROPERTY_CODE" => array(
                                    0 => "SIZES_CLOTHES",
                                    1 => "",
                                ),
                                "MASK_PHONE" => "(999) 999-9999",
                                "MODE_EXTENDED" => "Y",
                                "NEW_USER_GROUP_ID" => array(
                                ),
                                "NOT_AUTHORIZE_USER" => "N",
                                "OFFERS_SORT_BY" => "ACTIVE_FROM",
                                "OFFERS_SORT_ORDER" => "DESC",
                                "PATH_TO_PAYMENT" => "/personal/order/payment/",
                                "PAY_SYSTEMS" => array(
                                ),
                                "PERSON_TYPE_ID" => "1",
                                "PRICE_CODE" => array(
                                    0 => "BASE",
                                ),
                                "SEND_MAIL" => "N",
                                "SEND_MAIL_REQ" => "N",
                                "SHOW_DELIVERY" => "Y",
                                "SHOW_OFFERS_FIRST_STEP" => "Y",
                                "SHOW_PAY_SYSTEM" => "Y",
                                "SHOW_PROPERTIES" => array(
                                ),
                                "SHOW_PROPERTIES_REQUIRED" => array(
                                ),
                                "SHOW_QUANTITY" => "Y",
                                "SHOW_USER_DESCRIPTION" => "N",
                                "SUCCESS_ADD_MESS" => "Вы успешно оформили заказ №#ORDER_ID#!",
                                "SUCCESS_HEAD_MESS" => "Поздравляем!",
                                "USER_CONSENT" => "N",
                                "USER_CONSENT_ID" => "0",
                                "USER_CONSENT_IS_CHECKED" => "Y",
                                "USER_CONSENT_IS_LOADED" => "N",
                                "USER_DATA_FIELDS" => array(
                                    0 => "NAME",
                                    1 => "PERSONAL_PHONE",
                                ),
                                "USER_DATA_FIELDS_REQUIRED" => array(
                                    0 => "NAME",
                                    1 => "PERSONAL_PHONE",
                                ),
                                "USE_CAPTCHA" => "N",
                                "USE_OLD_CLASS" => "N"
                            ),
                            false
                        );?>
                    </div>
                </div>
            <?endif;?>
			<div class="row">
				<div class="col-md-12 hidden-xs">
					<?$APPLICATION->IncludeComponent("bitrix:menu", "catalog_horizontal", array(
							"ROOT_MENU_TYPE" => "left",
							"MENU_CACHE_TYPE" => "A",
							"MENU_CACHE_TIME" => "36000000",
							"MENU_CACHE_USE_GROUPS" => "Y",
							"MENU_THEME" => "site",
							"CACHE_SELECTED_ITEMS" => "N",
							"MENU_CACHE_GET_VARS" => array(
							),
							"MAX_LEVEL" => "3",
							"CHILD_MENU_TYPE" => "left",
							"USE_EXT" => "Y",
							"DELAY" => "N",
							"ALLOW_MULTI_SELECT" => "N",
						),
						false
					);?>
				</div>
			</div>
			<?if ($curPage != SITE_DIR."index.php"):?>
			<div class="row">
				<div class="col-lg-12">
					<?$APPLICATION->IncludeComponent("bitrix:search.title", "visual", array(
							"NUM_CATEGORIES" => "1",
							"TOP_COUNT" => "5",
							"CHECK_DATES" => "N",
							"SHOW_OTHERS" => "N",
							"PAGE" => SITE_DIR."catalog/",
							"CATEGORY_0_TITLE" => GetMessage("SEARCH_GOODS") ,
							"CATEGORY_0" => array(
								0 => "iblock_catalog",
							),
							"CATEGORY_0_iblock_catalog" => array(
								0 => "all",
							),
							"CATEGORY_OTHERS_TITLE" => GetMessage("SEARCH_OTHER"),
							"SHOW_INPUT" => "Y",
							"INPUT_ID" => "title-search-input",
							"CONTAINER_ID" => "search",
							"PRICE_CODE" => array(
								0 => "BASE",
							),
							"SHOW_PREVIEW" => "Y",
							"PREVIEW_WIDTH" => "75",
							"PREVIEW_HEIGHT" => "75",
							"CONVERT_CURRENCY" => "Y"
						),
						false
					);?>
				</div>
			</div>
			<?endif?>

			<?if ($curPage != SITE_DIR."index.php"):?>
			<div class="row">
				<div class="col-lg-12" id="navigation">
					<?$APPLICATION->IncludeComponent("bitrix:breadcrumb", "", array(
							"START_FROM" => "0",
							"PATH" => "",
							"SITE_ID" => "-"
						),
						false,
						Array('HIDE_ICONS' => 'Y')
					);?>
				</div>
			</div>
			<h1 class="bx-title dbg_title" id="pagetitle"><?=$APPLICATION->ShowTitle(false);?></h1>
			<?endif?>
		</div>
	</header>

	<div class="workarea">
		<div class="container bx-content-seection">
			<div class="row">
			<?$needSidebar = preg_match("~^".SITE_DIR."(catalog|personal\/cart|personal\/order\/make)/~", $curPage);?>
				<div class="bx-content <?=($needSidebar ? "col-xs-12" : "col-md-9 col-sm-8")?>">