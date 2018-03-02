<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true){
	die();
}
$success_head = ($arParams['SUCCESS_HEAD_MESS'] != "" ? $arParams['SUCCESS_HEAD_MESS'] : GetMessage('CONGRATULATION'));
$success_mess = ($arParams['SUCCESS_ADD_MESS'] != "" ? $arParams['SUCCESS_ADD_MESS'] : GetMessage('SUCCESS_ADD_NEW'));
?>
<input type="hidden" class="SUCCESS" value="Y"/>
<div class="modal-header">
	<span class="modal_title"><?=$success_head?></span>
</div>
<div class="modal-container">

	<p><?=str_replace('#ORDER_ID#',$arResult['SUCCESS'], $success_mess)?></p>
	<?
	if(!empty($arResult["PAY_SYSTEM"])){
		?>
		<br/><br/>

		<table class="sale_order_full_table">
			<tr>
				<td class="ps_logo">
					<div class="pay_name"><?=GetMessage("SOA_TEMPL_PAY")?></div>
					<?=CFile::ShowImage($arResult["PAY_SYSTEM"]["LOGOTIP"], 100, 100, "border=0", "", false);?>
					<div class="paysystem_name"><?=$arResult["PAY_SYSTEM"]["NAME"]?></div>
					<br>
				</td>
			</tr>
			<?
			if(strlen($arResult["PAY_SYSTEM"]["ACTION_FILE"]) > 0){
				?>
				<tr>
					<td>
						<?
						if($arResult["PAY_SYSTEM"]["NEW_WINDOW"] == "Y"){
							?>
							<script language="JavaScript">
								window.open('<?=$arParams["PATH_TO_PAYMENT"]?>?ORDER_ID=<?=urlencode(urlencode($arResult["ORDER_ID"]))?>');
							</script>
						<?=GetMessage("SOA_TEMPL_PAY_LINK", Array("#LINK#" => $arParams["PATH_TO_PAYMENT"] . "?ORDER_ID=" . urlencode(urlencode($arResult["ORDER_ID"]))))?>
							<?
							if(CSalePdf::isPdfAvailable() && CSalePaySystemsHelper::isPSActionAffordPdf($arResult['PAY_SYSTEM']['ACTION_FILE'])){
								?><br/>
								<?=GetMessage("SOA_TEMPL_PAY_PDF", Array("#LINK#" => $arParams["PATH_TO_PAYMENT"] . "?ORDER_ID=" . urlencode(urlencode($arResult["ORDER_ID"])) . "&pdf=1&DOWNLOAD=Y"))?>
								<?
							}
						}
						else{
							if(strlen($arResult["PAY_SYSTEM"]["PATH_TO_ACTION"]) > 0){
								try{
									include($arResult["PAY_SYSTEM"]["PATH_TO_ACTION"]);
								}catch(\Bitrix\Main\SystemException $e){
									if($e->getCode() == CSalePaySystemAction::GET_PARAM_VALUE)
										$message = GetMessage("SOA_TEMPL_ORDER_PS_ERROR");
									else
										$message = $e->getMessage();

									echo '<span style="color:red;">' . $message . '</span>';
								}
							}
						}
						?>
					</td>
				</tr>
				<?
			}
			?>
		</table>
		<?
	} ?>
</div>