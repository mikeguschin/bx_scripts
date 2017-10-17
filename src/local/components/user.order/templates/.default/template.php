<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
/** @var array $arResult */
/** @var array $arParams */
//echo "<pre>";print_r($arResult["CLIENTS"]);echo "</pre>";
global $APPLICATION;
use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Page\Asset;
use Arus\Main\Helpers\ArusDatesHelper;
Loc::loadLanguageFile(__FILE__);
$APPLICATION->SetTitle(GetMessage('ARUS_CORP_ORDER_TITLE'));

Asset::getInstance()->addCss("//cdnjs.cloudflare.com/ajax/libs/pikaday/1.4.0/css/pikaday.min.css");
Asset::getInstance()->addJs("//cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/moment.min.js");
Asset::getInstance()->addJs("//cdnjs.cloudflare.com/ajax/libs/moment.js/2.14.1/locale/ru.js");
Asset::getInstance()->addJs("//cdnjs.cloudflare.com/ajax/libs/pikaday/1.4.0/pikaday.min.js");
require_once(__DIR__.'/modal.php');

foreach ($arResult['ERROR'] as $error):?>
	<? ShowMessage($error) ?>
<? endforeach; ?>
<?foreach ($arResult["WARNING"]['CHECK_BASKET'] as $warning):?>
	<?=$warning?><br>
<?endforeach;?>
	<div id="order_form_div">
	<? if (empty($arParams['REQUEST']['ORDER_ID'])): ?>
			<? if (empty($arResult['ERROR_SORTED']['MAIN'])  && $arResult['products_not_checked'] !== 'Y'): //Нет ошибок не позволяющих оформилять заказ  ?>
			<form class="sale_order_table" name="CorpOrderForm" id="CorpOrderForm" action="" method="post">
				<div id="person_type">

					<div class="title"><?=GetMessage('ARUS_PERSON_TYPE_TITLE')?>
						<span class="css_popup glyphicon glyphicon-question-sign"></span></div>
					<table class="sale_order_table paysystem">
						<tr>
							<td colspan="2">
								<div class="paylogo">
									<input
											type="radio"
											id="PERSON_TYPE_2"
										<?=($arParams['REQUEST']['PERSON_TYPE'] === 'N' || empty($arParams['REQUEST']['PERSON_TYPE']))?'checked="checked"':'';?>
											name="PERSON_TYPE"
											value="N"
											onchange="changePersonType()"
									/>

									<label for="PERSON_TYPE_2">
										<img src="/bitrix/components/linemedia.auto/sale.order.ajax/templates/visual/images/logo-default-ps.gif" title="<?=$arPaySystem["PSA_NAME"];?>" />

										<div class="paysystem_name"><?=GetMessage('ARUS_PERSON_TYPE_2')?></div>
									</label>
								</div>
								<div class="paylogo">
									<input	type="radio"
											  id="PERSON_TYPE_1"
											  name="PERSON_TYPE"
											  value="Y"
											  onchange="changePersonType()"
										<?=($arParams['REQUEST']['PERSON_TYPE'] === 'Y')?'checked="checked"':'';?>
									/>

									<label for="PERSON_TYPE_1">
										<img src="/bitrix/components/linemedia.auto/sale.order.ajax/templates/visual/images/logo-default-ps.gif" title="<?=$arPaySystem["PSA_NAME"];?>" />

										<div class="paysystem_name"><?=GetMessage('ARUS_PERSON_TYPE_1')?></div>
									</label>
								</div>
							</td>
						</tr>
					</table>
				</div>
				<div class="title"><?=GetMessage('ARUS_CLIENT_INFORMATION')?></div>
				<table class="sale_order_table" id="clientInfo">
					<tr>
						<td>
							<label for="client"><?=GetMessage('ARUS_AVAILABLE_CLIENTS')?><span class="sof-req">*</span></label>
						</td>
						<td>
							<input type="hidden" name="client_ext_id" id="client_ext_id"
								   value="<?= ($arParams['REQUEST']['client_ext_id'] ?: 0) ?>">
							<input type="hidden" name="payer_ext_id" id="payer_ext_id"
								   value="<?= ($arParams['REQUEST']['payer_ext_id'] ?: 0) ?>">
							<input type="hidden" value="save" name="save">
							<select class="error_text" name="client" id="client" onchange="setClient(this.value);">
								<option value="">---</option>
								<? foreach ($arResult['CLIENTS'] as $client): ?>
									<option <? //=$client['USER_CLIENT_ID'] == $arParams['REQUEST']['client'] ?'selected':''?>
											value="<?= $client['USER_CLIENT_ID'] ?>"><?= $client['USER_CLIENT_NAME'] ?></option>
								<? endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
                <!--Здесь будет заголовок по дополнительной информации-->
				<div id="deliveryList">
					<div class="title"><?=GetMessage('ARUS_DELIVERY_TYPE')?></div>
					<? if (!empty($arResult["DELIVERY"])) {
					    $counter = 0;
						?>
						<table class="sale_order_table delivery">
							<?
							foreach ($arResult["DELIVERY"] as $delivery_id => $arDelivery) {
								list($dCode, $dProfile) = explode(':', $arDelivery['CODE']);
								?>
                                <?if ($counter%3 == 0):?>
                                <tr>
                                <?endif;?>
									<td class="prop text-left align-top col-xs-4">
										<input type="radio" id="ID_DELIVERY_ID_<?= $arDelivery["ID"] ?>"
											   name="<?= $arDelivery["FIELD_NAME"] ?>"
											   value="<?= $arDelivery["ID"] ?>"<? if ($arDelivery["CHECKED"] == "Y") echo " checked"; ?>
											   onclick="submitForm();">
										<label for="ID_DELIVERY_ID_<?= $arDelivery["ID"] ?>" <?= (count($arDelivery["STORE"]) > 0) ? 'onClick="fShowStore(\'' . $arDelivery["ID"] . '\');"' : ""; ?> >
											<? if (count($arDelivery["LOGOTIP"]) > 0): ?>
												<?= CFile::ShowImage($arDelivery["LOGOTIP"], 95, 55, "border=0", "", false); ?>
												<?
											else:?>
												<img src="/bitrix/components/bitrix/sale.order.ajax/templates/visual/images/logo-default-d.gif"
													 alt=""/>
											<? endif; ?>
											<div class="desc">
												<div class="name"><?= $arDelivery["NAME"] ?></div>
												<div class="desc">
													<?
													if (strlen($arDelivery["PERIOD_TEXT"]) > 0) {
														echo $arDelivery["PERIOD_TEXT"];
														?><br/><?
													}

													if ($dCode == 'sebekon_yaroute') {
														?>
														<?= GetMessage("SALE_DELIV_PRICE"); ?>
														<span class='delivery-price-wr-<?= $dCode ?>'><?= $arDelivery["PRICE_FORMATED"] ?></span>
														<br/>
														<?
													} elseif ($arDelivery["PRICE"] > 0) {
														?>
														<?= GetMessage("SALE_DELIV_PRICE"); ?>
														<span class='delivery-price-wr-<?= $dCode ?>'><?= $arDelivery["PRICE_FORMATED"] ?></span>
														<br/>
														<?
													}

													if (strlen($arDelivery["DESCRIPTION"]) > 0) {
														echo html_entity_decode($arDelivery["DESCRIPTION"]) . "<br />";
													}
													?>
												</div>
												<? if (count($arDelivery["STORE"]) > 0): ?>
													<span id="select_store"<? if (strlen($arResult["STORE_LIST"][$arResult["BUYER_STORE"]]["TITLE"]) <= 0) echo " style=\"display:none;\""; ?>>
										<span class="select_store"><?= GetMessage('SOA_ORDER_GIVE_TITLE'); ?>: </span>
										<span class="ora-store"
											  id="store_desc"><?= htmlspecialcharsbx($arResult["STORE_LIST"][$arResult["BUYER_STORE"]]["TITLE"]) ?></span>
									</span>
												<? endif; ?>
											</div>
										</label>
										<div class="clear"></div>
									</td>
                                <?if ($counter%3 == 2):?>
                                    </tr>
                                <?endif;?>
								<?
                                $counter++;
							}
							$counter--;
							?>
                            <?if ($counter%3 == 1 ):?>
                            <td class="col-xs-4">&nbsp;</td>
                            <?endif;?>
                            <?if ($counter%3 <= 1 ):?>
                            </tr>
                            <?endif;?>
						</table>
						<?
					}
					?>
					<input type="hidden" name="BUYER_STORE" id="BUYER_STORE" value="<?= $arParams['REQUEST']['BUYER_STORE']?>">
				</div>
                <table class="sale_data-table" id="additional_info">

                </table>
				<div id="paySystemInfo">
					<div class="title"><?=GetMessage("SOA_TEMPL_PAY_SYSTEM")?></div>
					<input type="hidden" name="PAY_CURRENT_ACCOUNT" value="N">
					<table class="sale_order_table paysystem">
						<tr>
							<td colspan="2">
								<?
								$bFirst = true;
								foreach($arResult["PAY_SYSTEM"] as $arPaySystem)
								{
									if(count($arResult["PAY_SYSTEM"]) == 1)
									{
										?>
										<div class="paylogo selected">
											<input type="hidden" name="PAY_SYSTEM_ID" value="<?=$arPaySystem["ID"]?>">
											<?if (count($arPaySystem["PSA_LOGOTIP"]) > 0):?>
												<img src="<?=$arPaySystem["PSA_LOGOTIP"]["SRC"]?>" title="<?=$arPaySystem["PSA_NAME"];?>"/>
											<?else:?>
												<img src="/bitrix/components/linemedia.auto/sale.order.ajax/templates/visual/images/logo-default-ps.gif" title="<?=$arPaySystem["PSA_NAME"];?>"/>
											<?endif;?>
											<div class="paysystem_name"><?=$arPaySystem["NAME"];?></div>
										</div>
										<?
										if (strlen($arPaySystem["DESCRIPTION"])>0)
										{
											?>
											<?=$arPaySystem["DESCRIPTION"]?>
											<?
										}
									}
									else
									{
										?>
										<div class="paylogo">
											<input type="radio" id="ID_PAY_SYSTEM_ID_<?= $arPaySystem["ID"] ?>" name="PAY_SYSTEM_ID" value="<?= $arPaySystem["ID"] ?>"<?if (($arParams['REQUEST']['PAY_SYSTEM_ID'] == $arPaySystem["ID"]) || (empty($arParams['REQUEST']['PAY_SYSTEM_ID']) && $bFirst)) echo " checked=\"checked\"";?> onclick="" />

											<label for="ID_PAY_SYSTEM_ID_<?= $arPaySystem["ID"] ?>">
												<?if (count($arPaySystem["PSA_LOGOTIP"]) > 0):?>
													<img src="<?=$arPaySystem["PSA_LOGOTIP"]["SRC"]?>" title="<?=$arPaySystem["PSA_NAME"];?>"/>
												<?else:?>
													<img src="/bitrix/components/linemedia.auto/sale.order.ajax/templates/visual/images/logo-default-ps.gif" title="<?=$arPaySystem["PSA_NAME"];?>" />
												<?endif;?>

												<div class="paysystem_name"><?=$arPaySystem["PSA_NAME"];?></div>
											</label>
										</div>
										<?
									}
									$bFirst = false;
								}
								?>
							</td>
						</tr>
					</table>
				</div>

			<div class="title"><?=GetMessage('SOA_TEMPL_SUM_TITLE')?></div>
			<table class="sale_data-table summary">
				<tr>
					<th><?=GetMessage('SOA_TEMPL_SUM_NAME')?></th>
					<th><nobr><?=GetMessage('SOA_TEMPL_SUM_QUANTITY')?></nobr></th>
					<th><?=GetMessage('SOA_TEMPL_SUM_PRICE')?></th>
					<th><?=GetMessage('ARUS_TEMPL_SUM_SUMMARY')?></th>
                    <th><?=GetMessage('ARUS_TEMPL_DELIVERY_TIME')?></th>
				</tr>
				<?
				foreach ($arResult['BASKET_ITEMS'] as $arItem) {
					?>
					<tr>
						<td class="nameTd"><?= $arItem['NAME'] ?></td>
						<td class="delivery_time"><?= Bitrix\Sale\BasketItem::formatQuantity($arItem['QUANTITY']) ?></td>
						<td class="price">
							<nobr><?= FormatCurrency($arItem['PRICE'], 'RUB') ?></nobr>
						</td>
						<td class="itog">
							<nobr><?= FormatCurrency($arItem['QUANTITY']*$arItem['PRICE'], 'RUB') ?></nobr>
						</td>
                        <td class="delivery_time">
                            <?echo ArusDatesHelper::FormatPartDeliveryTime($arItem["DELIVERY_TIME"]);?>
                        </td>
					</tr>
					<?
				}
				?>
                <tfoot>
				<tr class="last">
					<td></td>
					<td></td>
					<td><?=GetMessage('SOA_TEMPL_SUM_IT')?></td>
					<td class="itog">
						<nobr><?= $arResult['ORDER_TOTAL'] ?></nobr>
					</td>
                    <td></td>
				</tr>
                </tfoot>
			</table>

		<?/*<table class="sale_order_table">
			<tbody>
			<tr>
				<td class="order_comment">
					<div><?= GetMessage('ARUS_ORDER_DESCRIPTION_FIELD_NAME')?></div>
					<textarea name="ORDER_DESCRIPTION" id="ORDER_DESCRIPTION"></textarea>
				</td>
			</tr>
			</tbody>
		</table>*/?>

			</form>
			<div class="bx_ordercart_order_pay_center">
				<button id="ORDER_CONFIRM_BUTTON" style="display: none" onClick="checkFields();return false;">
					<?=GetMessage('SOA_TEMPL_BUTTON')?>
				</button>
			</div>
			<script>
				function setClient(client_id) {
					BuildForm('clientInfo', client_id);
				}
				function removeBySelector(selector) {
					var genlist = document.querySelectorAll(selector);
					for(var gen in genlist){
						if(genlist.hasOwnProperty(gen))
							genlist[gen].parentNode.removeChild(genlist[gen]);
					}
				}
				function genRow(htmlStr, rowClass) {
					var row = document.createElement('tr');
					if(rowClass === undefined)
						rowClass = 'gen';
					else
						rowClass += ' gen';
					row.className = rowClass;
					row.innerHTML = htmlStr;
					return row;
				}
                <?
                //Необходимо получить максимальное значение срока доставки из списка запчастей
                        $minDeliveryHours = $arResult['BASKET_ITEMS'][0]["DELIVERY_TIME"];
                        $maxDeliveryHours = $minDeliveryHours;
                        $arHours = array();
                foreach ($arResult['BASKET_ITEMS'] as $arItem) {
                    $arHours[] = $arItem["DELIVERY_TIME"];
                }
                $minDeliveryHours = min($arHours);
                $maxDeliveryHours = max($arHours);
                ?>
				function rebuildDeliveryDateInput(divID, inputID, clientSchedule, datePickerInputName){

                    var isPickup = isPickupCheck();
                    if (fullOrderCheck()){
                        var DeliveryHoursAdd = <?=$maxDeliveryHours;?>;
                        var fullOrder = true;
                    } else {
                        var DeliveryHoursAdd = <?=$minDeliveryHours;?>;
                        var fullOrder = false;
                    }
                    removeBySelector('#additional_info .payer-gen');
                    if (!isPickup) {
                        var container = BX(divID);
                        container.insertAdjacentHTML('beforeend', '                    <tr class="payer-gen gen">\n' +
                            '                        <td class="text-left col-xs-4">\n' +
                            '                            <label for="FULL_ORDER"><?= GetMessage('ARUS_FULL_ORDER_FIELD_NAME')?></label>\n'+
                            '                            <span class="css_popup_nomodal glyphicon glyphicon-question-sign">\n' +
                            '                                <div id="statusPopupDelivery" class="popup">\n' +
                            '                                    <?=Loc::getMessage('ARUS_CORP_FULL_ORDER_HELP_TEXT_POPUP')?>\n' +
                            '                                </div>\n' +
                            '                            </span>\n' +
                            '                        </td>\n' +
                            '                        <td class="text-left col-xs-8">\n' +
                            '                            <input id="FULL_ORDER_YES" type="radio" name="FULL_ORDER" value="Y" onchange="submitRadio();">Да <br />\n' +
                            '                            <input id="FULL_ORDER_NO" type="radio" name="FULL_ORDER" value="N" onchange="submitRadio();">Нет\n' +
                            '                        </td>\n' +
                            '                    </tr>' +
                            '<tr class="payer-gen gen">'
                            + '<td><label for="delivery-date"><?=Loc::getMessage("ARUS_WANTED_DELIVERY_DATE_TITLE");?></label><span class="sof-req">*</span></td>'
                            + '<td>'
                            + '<input autocomplete="off" id="' + inputID + '" class="ar-delivery-date" placeholder="дд.мм.гггг" type="text" name="DELIVERY_DATE" value="">'
                            + '</td></tr>');
//                        clickFullOrderInput(fullOrder);
                        initPDDInput(clientSchedule, datePickerInputName, DeliveryHoursAdd);
                    }
                }

				function BuildForm(container_id, client_id, payer_id) {
					var container = BX('clientInfo');

					BX('client_ext_id').value = '';
					BX('payer_ext_id').value = '';
					BX('ORDER_CONFIRM_BUTTON').setAttribute('style', 'display: none;');

					client_id = client_id || 0;
					payer_id = payer_id || 0;
					var obClients = <?=json_encode($arResult['CLIENTS'])?>;
					if (client_id > 0) {
                        var obProfile = <?=json_encode($arResult["CORP_USER_PROFILE"]);?>;
                        var CONTACT_PERSON = '',
                            PHONE = '';
                        if (obProfile !== null) {
                            if ('undefined' !== obProfile.CONTACT_PERSON) {
                                CONTACT_PERSON = obProfile.CONTACT_PERSON;
                            }
                            if ('undefined' !== obProfile.CONTACT_PERSON) {
                                PHONE = obProfile.PHONE;
                            }
                        }
						BX('client').className = "";
						var Client = obClients[client_id],
							errRow = genRow('<td></td><td><span class="error">У клиента отсутствуют плательщики</span></td>', 'payers-gen');
						if (Client.USER_CLIENT_CONTACT == 'Контактная информация не указана'){
                            Client.USER_CLIENT_CONTACT = '';
                        }
						if (container_id === 'clientInfo') {
							var payers_container = 'payerInfo';
							BX('ORDER_CONFIRM_BUTTON').style = 'display: none;';
							removeBySelector('#clientInfo .gen');
                            removeBySelector('#additional_info .payer-gen');
                            container.insertAdjacentHTML('beforeend','<tr class="gen"><td><?=GetMessage('ARUS_CLIENT_NAME')?> <span class="sof-req">*</span></td><td>'
                                + '<input type="text" id="CONTACT_PERSON" name="CONTACT_PERSON" value="' + CONTACT_PERSON + '"></td></tr>'
                                + '<tr class="gen"><td><?=GetMessage('ARUS_CLIENT_PHONE')?> <span class="sof-req">*</span></td>'
                                + '<td>+7 <input id="INPUT_PHONE" type="text" name="PHONE" value="' + PHONE + '" placeholder="10 цифр">'
                                + '</td></tr>'
								+ '<tr class="gen"><td><?=GetMessage('ARUS_CLIENT_ADDRESS')?></td><td> ' + Client.USER_CLIENT_ADDRESS + '</td></tr>');

							if (!Client.PAYERS[""]) { //если объект плательщиков не пуст, построим select

								var payersSelect = document.createElement('select'),
									payersLabel = '<tr class="gen"><td><label for="payer"><?=GetMessage('ARUS_AVAILABLE_PAYERS')?>'
										+'<span class="sof-req">*</span> </label></td><td id="payers_select_area"></td></tr>',
									payersHeader = '<tr class="gen"><td colspan="2"><div class="title"><?=GetMessage('ARUS_PAYERS_INFO')?></div></td></tr>',
									personType = document.querySelector('[name="PERSON_TYPE"]:checked').value;

								payersSelect.name = 'payer';
								payersSelect.id = 'payer';
								payersSelect.className = 'error_text';
								opt = document.createElement('option');
								opt.value = '';
								opt.textContent = '---';
								opt.className = 'empty';
								payersSelect.appendChild(opt);

								for (var key in Client.PAYERS) {
									if(!Client.PAYERS.hasOwnProperty(key))
										continue;
									var Payer = Client.PAYERS[key];
									if(personType === Client.PAYERS[key].IS_INDIVIDUAL){
										var opt = document.createElement('option'),
											payer = Client.PAYERS;
										opt.value = key;
										opt.textContent = Client.PAYERS[key].NAME;
										payersSelect.appendChild(opt);
									}
								}

                                if(payersSelect.children.length === 1){
									container.appendChild(errRow);
								}
								else{
									container.insertAdjacentHTML('beforeend', payersHeader + payersLabel);
								}
								var paersArea = document.getElementById('payers_select_area');
								if(!!paersArea){
									paersArea.appendChild(payersSelect);
									payersSelect.onchange = (function(){BuildForm(payers_container, client_id, this.value);});
								}
							}
							else{
								container.appendChild(errRow);
							}
                            /**
                             * Инициализация PikaDay
                             */
						}
						else {
							removeBySelector('#clientInfo .payer-gen');
							if (!!payer_id) {

								if(Object.keys(obClients[client_id].PAYERS).length === 1 || !document.getElementById('payers_select_area'))
									container.insertAdjacentHTML('beforeend', '<tr class="payer-gen gen"><td><?=GetMessage('ARUS_CORP_PAYERS_TITLE')?> </td><td>' + obClients[client_id].PAYERS[payer_id].NAME + '<td></tr>');

								container.insertAdjacentHTML('beforeend', '<tr class="payer-gen gen"><td><?=GetMessage('ARUS_PAYERS_INN')?> </td><td>' + obClients[client_id].PAYERS[payer_id].INN + '<td></tr>');
								container.insertAdjacentHTML('beforeend', '<tr class="payer-gen gen"><td><?=GetMessage('ARUS_PAYERS_KPP')?> </td><td>' + obClients[client_id].PAYERS[payer_id].KPP + '<td></tr>');
								BX('client_ext_id').value = obClients[client_id].USER_CLIENT_EXTERNAL_ID;
								BX('payer_ext_id').value = obClients[client_id].PAYERS[payer_id].EXTERNAL_ID;
								BX('ORDER_CONFIRM_BUTTON').setAttribute('style', 'display: inline-block;');
								if(!!BX('payer'))
									BX('payer').className = '';
                                rebuildDeliveryDateInput('additional_info', 'ar-delivery-date',obClients[client_id].USER_CLIENT_SCHEDULE, 'ar-delivery-date');
							}
							else {
                                if(!!BX('payer')) {
                                    BX('payer').className = 'error_text';
                                }
							}
						}
					}
					else{
						removeBySelector('#clientInfo .gen');
                        removeBySelector('#clientInfo .payer-gen');
                        removeBySelector('#additional_info .payer-gen');
						BX('client').className = "error_text";
					}
				}

				function changePersonType(){
					removeBySelector('#clientInfo .gen');
                    removeBySelector('#clientInfo .payer-gen');
                    removeBySelector('#additional_info .payer-gen');
					BX('client_ext_id').value = '';
					BX('payer_ext_id').value = '';
					BX('ORDER_CONFIRM_BUTTON').setAttribute('style', 'display: none;');

					var arClientsIds = Object.keys(<?=json_encode($arResult['CLIENTS'])?>);

					if(arClientsIds.length === 1){
						BX('client').value = arClientsIds[0];
						setClient(arClientsIds[0]);
					}
					else if(BX('client').value > 0){
						setClient(BX('client').value);
					}
				}

				var warningPopup = new BX.PopupWindow('warning_popup', window.body, {
					autoHide : true,
					offsetTop : 1,
					offsetLeft : 0,
					lightShadow : false,
					closeIcon : true,
					closeByEsc : true,
					overlay: {
						backgroundColor: '#313131', opacity: '80'
					}
				});

				function checkFields(){
				    var result = true;
					var isPickup = isPickupCheck(),
						storeIsEmpty = document.getElementById('BUYER_STORE').value.trim() === '';
					if(isPickup && storeIsEmpty){
						warningPopup.setContent('<?=GetMessage('ARUS_DELIVERY_STORE_NOT_SELECTED')?>');
						warningPopup.show();
						result = false;
					}
					var origPhone = BX('INPUT_PHONE').value;

                    var phone = origPhone.replace(/\D+/g,'').replace(/\D+$/, '');
                    if (phone.length != 10){
                        warningPopup.setContent('<?=GetMessage('ARUS_PHONE_NUMBER_INCORRECT')?>');
                        warningPopup.show();
                        BX('INPUT_PHONE').className = 'error_text';
                        BX.focus('INPUT_PHONE');
                        result = false;
                    }
                    else{
                        BX('INPUT_PHONE').className = '';
                    }
                    var contactPerson = BX('CONTACT_PERSON').value;
                    if (contactPerson.length == 0){
                        if (result) {
                            warningPopup.setContent('<?=GetMessage('ARUS_CONTACT_PERSON_INCORRECT')?>');
                            warningPopup.show();
                        }
                        BX('CONTACT_PERSON').className = 'error_text';
                        result = false;
                    }
                    else{
                        BX('CONTACT_PERSON').className = '';
                    }
                    //ЖДД проверяется только в случае доставки. При самовывозе поле прячется и может быть пустым
                    if (!isPickup) {

                        var deliveryDate = BX('ar-delivery-date').value;
                        if (deliveryDate.length == 0) {
                            if (result) {
                                warningPopup.setContent('<?=GetMessage('ARUS_WANTED_DELIVERY_DATE_TITLE_INCORRECT')?>');
                                warningPopup.show();
                                BX('ar-delivery-date').className = 'error_text';
                            }
                            else {
                                BX('ar-delivery-date').className = '';
                            }
                            result = false;
                        }
                        var fullOrderRadios = document.getElementsByName('FULL_ORDER'),
                            radioCnt = 0;
                        for (var i = 0, lenght = fullOrderRadios.length; i < lenght; i++) {
                            console.log(fullOrderRadios[i].checked);
                            if (fullOrderRadios[i].checked) {
                                radioCnt++;
                            }
                        }
                        if (result && radioCnt == 0) {
                            result = false;
                            warningPopup.setContent('<?=GetMessage('ARUS_FULL_ORDER_FIELD_NAME_ERROR')?>');
                            warningPopup.show();
                        }
                    }
					if (result){
                        BX('ORDER_CONFIRM_BUTTON').setAttribute('disabled', 'disabled');
                        BX('ORDER_CONFIRM_BUTTON').setAttribute('style', 'background-color: #e6e6e6;');
                        BX('CorpOrderForm').submit();
					}
					return false;
				}
				<?if(count($arResult['CLIENTS']) == 1):
				reset($arResult['CLIENTS']);
				$curClient = current($arResult['CLIENTS']);
				?>
				BX.ready(function(){
					setClient(<?=$curClient['USER_CLIENT_ID']?>);
					BX('client').value = <?=$curClient['USER_CLIENT_ID']?>;
				});
				<?endif;?>
			</script>
		<?elseif ($arResult['products_not_checked'] == 'Y'):?>
			<?=GetMessage('ARUS_RECALC_BASKET_MESSAGE')?>
			<form action="<?=$APPLICATION->GetCurPage();?>" method="POST" enctype="multipart/form-data">
				<input type="hidden" name="RECALC" value="Y" />
				<?=bitrix_sessid_post()?>
				<input style="float: right" class="btn btn-info" type="submit" name="BasketOrderRecalc" value="<?=GetMessage('ARUS_RECALC_BASKET_BUTTON')?>">
			</form>
		<? endif ?>
	<? else: ?>
		<b><?= GetMessage("SOA_TEMPL_ORDER_COMPLETE") ?></b><br/><br/>
		<table class="sale_order_full_table">
			<tr>
				<td>
					<?= GetMessage("SOA_TEMPL_ORDER_SUC", Array("#ORDER_DATE#" => $arResult["ORDER"]["DATE_INSERT"], "#ORDER_ID#" => $arResult["ORDER"]["ACCOUNT_NUMBER"])) ?>
					<br/><br/>
					<?= GetMessage("SOA_TEMPL_ORDER_SUC1", Array("#LINK#" => $arParams["PATH_TO_PERSONAL"])) ?>
				</td>
			</tr>
		</table>
	<? endif; ?>
	</div>
	<script>
		/**
		 * Заглушка
		 */
		function submitForm(arg) {
            if (isPickupCheck()){
                removeBySelector('#additional_info .payer-gen');
            }
            else{
                var container_id = 'PayerInfo';
                var client_id = BX('client').value || 0;
                var payer_id = 0;
                if (client_id) {
                    var payer_id = BX('payer').value || 0;
                }
                if (client_id && payer_id) {
                    BuildForm(container_id, client_id, payer_id);
                }
            }
		}

        function submitRadio(arg) {
            if (isPickupCheck()){
                removeBySelector('#additional_info .payer-gen');
            }
            else{
                var varFullOrderValue = fullOrderValue();
                var container_id = 'PayerInfo';
                var client_id = BX('client').value || 0;
                var payer_id = 0;
                if (client_id) {
                    var payer_id = BX('payer').value || 0;
                }
                if (client_id && payer_id) {
                    BuildForm(container_id, client_id, payer_id);
                    if (varFullOrderValue.length > 0){
                        clickFullOrderInput(varFullOrderValue);
                    }
                }
            }
        }
		/**
		 * Отображение карты с пунктами отгрузки
		 */
		function fShowStore(id) {
			var strUrl = '<?=$arParams['MAP_PATH']?>';
			var strUrlPost = 'delivery=' + id +
				'&siteId=<?=SITE_ID?>';

			var storeForm = new BX.CDialog({
				'title': '<?=GetMessage('SOA_ORDER_GIVE')?>',
				head: '',
				'content_url': strUrl,
				'content_post': strUrlPost,
				'width': 700,
				'height': 450,
				'resizable': false,
				'draggable': false
			});

			var button = [
				{
					title: '<?=GetMessage('SOA_POPUP_SAVE')?>',
					id: 'crmOk',
					'action': function () {
						GetBuyerStore();
						BX.WindowManager.Get().Close();
					}
				},
				BX.CDialog.btnCancel
			];
			storeForm.ClearButtons();
			storeForm.SetButtons(button);
			storeForm.Show();
		}

		function GetBuyerStore() {
			BX('BUYER_STORE').value = BX('POPUP_STORE_ID').value;
			//BX('ORDER_DESCRIPTION').value = '<?=GetMessage("SOA_ORDER_GIVE_TITLE")?>: '+BX('POPUP_STORE_NAME').value;
			BX('store_desc').innerHTML = BX('POPUP_STORE_NAME').value;
			BX.show(BX('select_store'));
		}

		BX.ready(function () {
			/**
			 * Восстановление введённых данных
			 */
			BX('ORDER_CONFIRM_BUTTON').style = 'display: none;';

			<?if(!empty($arParams['REQUEST']['client'])):?>
			BX('CorpOrderForm').client.value = <?=$arParams['REQUEST']['client']?>;
			setClient(<?=$arParams['REQUEST']['client']?>);
			<?endif;?>

			<?if(!empty($arParams['REQUEST']['payer'])):?>
			BX('CorpOrderForm').payer.value = <?=$arParams['REQUEST']['payer']?>;
			BuildForm('payerInfo', <?=$arParams['REQUEST']['client']?>, <?=$arParams['REQUEST']['payer']?>);
			BX('ORDER_CONFIRM_BUTTON').style = 'display: inline-block;';
			<?endif;?>

			<?if($arParams['SHOW_NOTE']):?>
			oPopup.show();
			<?endif;?>
        });
    </script>