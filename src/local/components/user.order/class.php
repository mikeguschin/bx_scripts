<?

use Bitrix\Main\Loader,
    Arus\Corp\ArusCorpClientsManager,
    Bitrix\Main,
    Bitrix\Sale,
    Bitrix\Main\Config\Option,
    Bitrix\Sale\Result,
    Bitrix\Main\Localization\Loc,
    Bitrix\Sale\PaySystem,
    Bitrix\Main\Application,
    Bitrix\Main\Web\Cookie,
    Bitrix\Sale\BasketPropertiesCollection,
    Bitrix\Sale\Order,
    Bitrix\Sale\PersonType,
    Bitrix\Sale\Internals\UserPropsTable,
    Bitrix\Sale\Internals\UserPropsValueTable;

if (!Loader::IncludeModule('sale'))
	die();
if (!Loader::IncludeModule('arus.corp') || !Loader::includeModule("arus.main"))
	die();
Loc::loadMessages(__FILE__);
class ArusCorpOrderAjax extends CBitrixComponent{
	protected $context;
	protected $checkSession;
	protected $isRequestViaAjax;
	protected $action;
	protected $useCatalog;

	/** @var Sale\Order $order */
	protected $order;
	protected $arUserResult;

	/** @var Sale\Delivery\Services\Base[] $arDeliveryServiceAll */
	protected $arDeliveryServiceAll;
	protected $arPaySystemServices;

	public function onPrepareComponentParams($arParams)
	{
		$arParams['AJAX_PATH'] = $this->getPath().'/ajax.php';
		$arParams['MAP_PATH'] = $this->getPath().'/map.php';
		$arParams['CURRENCY'] = Option::get('sale', 'default_currency', 'RUB');
		foreach ($this->request as $key=>$value){
			$arParams['REQUEST'][$key] = $value;
		}
		$arParams['SHOW_NOTE'] = false;
		if(empty($this->request->getCookie('CORP_NOTE_SHOWED'))){
			$arParams['SHOW_NOTE'] = true;
			$cookie = new Cookie('CORP_NOTE_SHOWED', 'Y');
			Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
		}

		return $arParams;
	}

    /**
     * Журнал ошибок
     * @param $res
     * @param string $type
     */
    protected function addError($res, $type = 'MAIN')
    {
        if ($res instanceof Result) {
            foreach ($res->getErrorMessages() as $error) {
                $this->arResult["ERROR"][] = $error;
                $this->arResult["ERROR_SORTED"][$type][] = $error;
            }
        } else {
            $this->arResult["ERROR"][] = $res;
            $this->arResult["ERROR_SORTED"][$type][] = $res;
        }
    }

    protected function addWarning($res, $type)
    {
        if (!empty($type))
            $this->arResult["WARNING"][$type][] = $res;
    }

    /**
     * Prepares action string to execute in doAction
     *
     * refreshOrderAjax/saveOrderAjax - process/save order via JSON (new template)
     * enterCoupon/removeCoupon - add/delete coupons via JSON (new template)
     * showAuthForm - show authorization form (old/new templates)                 [including component template]
     * processOrder - process order (old(all hits)/new(first hit) templates) [including component template]
     * showOrder - show created order (old/new templates)                             [including component template]
     *
     * @return null|string
     */
    protected function prepareAction()
    {
//		global $USER;

        $action = $this->request->get('action');

//		if (!$USER->IsAuthorized() && $this->arParams["ALLOW_AUTO_REGISTER"] == "N")
//			$action = 'showAuthForm';

        if (empty($action)) {
            if (strlen($this->request->get("ORDER_ID")) <= 0)
                $action = 'processOrder';
            else
                $action = 'showOrder';
        }

        return $action;
    }

	protected function doAction($action)
	{
		if (is_callable(array($this, $action."Action")))
		{
			call_user_func(
				array($this, $action."Action")
			);
		}
	}

	protected function getPropertyByCode($propertyCollection, $code){
		/** @var Sale\PropertyValueCollection $propertyCollection */
		foreach ($propertyCollection as $property)
		{
			if($property->getField('CODE') == $code)
				return $property;
		}
	}

	protected function showOrderAction(){
		$orderId = $this->request->get('ORDER_ID');
		$order = Sale\Order::load($orderId);
		$this->arResult['ORDER'] = $order->getFieldValues();
	}

	protected function saveOrderAction(Sale\Payment $extPayment, $remainingSum){
		$payer_ext_id = $this->request->get('payer_ext_id');
		$client_ext_id = $this->request->get('client_ext_id');

		$arAllowedClients = [];
		foreach ($this->arResult['CLIENTS'] as $client){
			foreach ($client['PAYERS'] as $payer){
				$arAllowedClients[$client['USER_CLIENT_EXTERNAL_ID']][] = $payer['EXTERNAL_ID'];
			}
		};

		//если переданный клиент и плательщик не привязаны к пользователю — вернём ошибку.
		if(empty($arAllowedClients[$client_ext_id]) || !in_array($payer_ext_id, $arAllowedClients[$client_ext_id]))
			$this->addError(Loc::getMessage('ARUS_CLIENT_ACCESS_DENIED') , 'ORDER_SAVE');
		if(empty($this->request->get('DELIVERY_ID')))
			$this->addError(Loc::getMessage('ARUS_EMPTY_DELIVERY_SERVICE_ERROR') , 'ORDER_SAVE');
		if(empty($this->request->get('PAY_SYSTEM_ID')))
			$this->addError(Loc::getMessage('ARUS_EMPTY_PAY_SYSTEM_ERROR') , 'ORDER_SAVE');

		if(empty($this->arResult['ERROR'])){

			/** @var Sale\ShipmentCollection $shipmentCollection */
			$currencyCode = $this->arParams['CURRENCY'];
			$order =& $this->order;

			/*Shipment*/
			$shipment = $this->getCurrentShipment($order);
			$shipmentCollection = $shipment->getCollection();

			if (!empty($this->arDeliveryServiceAll)) {
				$deliveryObj = $this->arDeliveryServiceAll[$this->request->get('DELIVERY_ID')];

				if ($deliveryObj->isProfile())
					$name = $deliveryObj->getNameWithParent();
				 else
					$name = $deliveryObj->getName();

				$shipment->setFields(array(
					'DELIVERY_ID' => $deliveryObj->getId(),
					'DELIVERY_NAME' => $name,
					'CURRENCY' => $order->getCurrency()
				));
				$shipmentCollection->calculateDelivery();
			}

			/*Payment*/
			$arPaySystemServiceAll = [];
			$paySystemId = $this->request->get('PAY_SYSTEM_ID');
			if ($remainingSum > 0 || $order->getPrice() == 0)
			{
				$arPaySystemServices = $this->arResult['PAY_SYSTEM'];
				$arPaySystemServiceAll += $arPaySystemServices;

				if (array_key_exists($paySystemId, $arPaySystemServiceAll))
					$arPaySystem = $arPaySystemServiceAll[$paySystemId];
				else
				{
					reset($arPaySystemServiceAll);
					$arPaySystem = current($arPaySystemServiceAll);
				}

				if (!empty($arPaySystem))
				{
					$extPayment->setFields(array(
						'PAY_SYSTEM_ID' => $arPaySystem["ID"],
						'PAY_SYSTEM_NAME' => $arPaySystem["NAME"]
					));
				}
				else
					$extPayment->delete();
			}

			$order->doFinalAction(true);
			$propertyCollection = $order->getPropertyCollection();

			$addressProperty = $this->getPropertyByCode($propertyCollection, 'PAYER_EXTERNAL_ID');
			$addressProperty->setValue($this->request->get('payer_ext_id'));
			$companyProperty = $this->getPropertyByCode($propertyCollection, 'CLIENT_EXTERNAL_ID');
			$companyProperty->setValue($this->request->get('client_ext_id'));

            $profileFields = array();
            /**
             * Если был выбран пункт самовывоза, добавим в свойства заказа
             */
            if (!empty($this->request->get('BUYER_STORE'))) {
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'STORE_ID');
                $companyProperty->setValue($this->request->get('BUYER_STORE'));
                $profileFields["BUYER_STORE"] = $this->request->get('BUYER_STORE');
            }

            /**
             * Уведомим что заказ как от физ. лица
             */
            if ($this->request->get('PERSON_TYPE') === 'Y') {
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'IS_INDIVIDUAL');
                $companyProperty->setValue('Y');
                $profileFields["IS_INDIVIDUAL"] = "Y";
            }

            /**
             * Не отгружать частями
             */
            if (!empty($this->request->get('FULL_ORDER'))) {
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'FULL_ORDER');
                $companyProperty->setValue($this->request->get('FULL_ORDER'));
                $profileFields["FULL_ORDER"] = $this->request->get('FULL_ORDER');
            }

            /**
             * Желаемая дата доставки
             * На 11.07.2017 - в 1С прописано условие, где обязательно должно СУЩЕСТВОВАТЬ и время (DELIVERY_TIME)
             */
            if(!empty($this->request->get('DELIVERY_DATE'))){
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'DELIVERY_DATE');
                $companyProperty->setValue($this->request->get('DELIVERY_DATE'));
            }
            /**
             * Контактное лицо
             */
            if(!empty($this->request->get('CONTACT_PERSON'))){
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'CONTACT_PERSON');
                $companyProperty->setValue($this->request->get('CONTACT_PERSON'));
                $profileFields["CONTACT_PERSON"] = $this->request->get('CONTACT_PERSON');
            }
            /**
             * Контактный телефон
             */
            if(!empty($this->request->get('PHONE'))){
                $companyProperty = $this->getPropertyByCode($propertyCollection, 'PHONE');
                $companyProperty->setValue($this->request->get('PHONE'));
                $profileFields["PHONE"] = $this->request->get('PHONE');
            }
            /**
             * комментарий пользователя
             */
            if (!empty(trim($this->request->get('ORDER_DESCRIPTION'))))
                $order->setField('USER_DESCRIPTION', $this->request->get('ORDER_DESCRIPTION'));

			$order->setField('CURRENCY', $currencyCode);


			$res = $order->save();

            if ($res->isSuccess()) {
                /**
                 *Сохраним или обновим профиль пользователя, если такового еще нет
                 */

                if (is_array($profileFields)){
                    ArusCorpClientsManager::saveUserProfile($order->getUserId(),$profileFields);
                }

                LocalRedirect('?ORDER_ID=' . $order->getId());
            }
            else {
                $this->addError($res);
            }
        }
    }

	protected function processOrderAction(){
		global $USER;

		if(!$USER->IsAuthorized())
			$this->addError(Loc::getMessage('ARUS_ERROR_IS_NOT_AUTHORISED'));

		if(!ArusCorpClientsManager::checkUserGroup($USER->GetUserGroupArray()))
			$this->addError(Loc::getMessage('ARUS_ERROR_NO_CORP_USER'));

		$basket = Bitrix\Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), SITE_ID)->getOrderableItems();
		$basketItems = $this->obtainBasketItems();
		$this->getCorpUserProfile($USER->GetID());


		if(empty($basketItems))
			$this->addError(Loc::getMessage('ARUS_ERROR_EMPTY_BASCKET'));

        if (empty($this->arResult['ERROR'])) {

			$this->arResult['CLIENTS'] = \Arus\Corp\ArusCorpClientsManager::getClientsByUserID($USER->GetID());
            $this->arResult['BASKET_ITEMS'] = $basketItems;

			Sale\DiscountCouponsManager::init();
			$order = Bitrix\Sale\Order::create(SITE_ID, $USER->GetID());

			$personType = Option::get('arus.corp', 'person_type'); //тип плательщика "корпоративный клиент"
			if(empty($personType))
				$personType = 3; //	todo: обработать как ошибку ненастроенного модуля

			$order->setPersonTypeId($personType);
			$order->setBasket($basket);

			$this->arResult['ORDER_TOTAL'] = FormatCurrency($order->getPrice(), $order->getCurrency());
			$this->order = $order;
			$this->obtainDelivery();

			$paymentCollection = $order->getPaymentCollection();
			/** @var Sale\Payment $extPayment */
			$extPayment = $paymentCollection->createItem();
			$remainingSum = $order->getPrice() - $paymentCollection->getSum();
			$extPayment->setField('SUM', $remainingSum);
			$this->arResult['PAY_SYSTEM'] = PaySystem\Manager::getListWithRestrictions($extPayment);

			if(!empty($this->request->get('save')))
				$this->saveOrderAction($extPayment, $remainingSum);
		}
	}


	protected function obtainBasketItems(){
		if(!Loader::includeModule('linemedia.auto'))
			return $this->addError(GetMessage("LM_AUTO_MODULE_NOT_INSTALL"));

		$basket = Bitrix\Sale\Basket::loadItemsForFUser(\CSaleBasket::GetBasketUserID(), SITE_ID)->getOrderableItems();
		$basketItems = $basket->getBasketItems();
		/** @var Sale\BasketItem $basketItem */
		$arBasketItems = [];
		$items = [];
		foreach ($basketItems as $basketItem){
			$key = $basketItem->getId();
			$arBasketItems[$key] = $basketItem->getFieldValues();

			$items[$key]['ID'] = $key;
			$items[$key]['prod_id'] = $basketItem->getProductId();//$arBasketItems[$key]["PRODUCT_ID"];
			$items[$key]['price'] = $basketItem->getPrice();
			$items[$key]['quantity'] = $basketItem->getQuantity();

			$propertyCollection = $basketItem->getPropertyCollection();
			$propList = $propertyCollection->getPropertyValues();

			foreach ($propList as &$prop)
			{
				if ($prop['CODE'] == 'CATALOG.XML_ID' || $prop['CODE'] == 'PRODUCT.XML_ID' || $prop['CODE'] == 'SUM_OF_CHARGE')
					continue;
				$arBasketItems[$key]["PROPS"][] = array_filter($prop, array("CSaleBasketHelper", "filterFields"));
				if ($prop['CODE'] == 'delivery_time'){
                    $arBasketItems[$key]["DELIVERY_TIME"] = $prop["VALUE"];
                }
				$items[$key][$prop['CODE']] = $prop["VALUE"];
			}
		}
		$this->checkBasketItems($items);
		return $arBasketItems;
	}

	protected function checkBasketItems($items = []){
		global $DB, $USER, $APPLICATION;
		if (!CModule::IncludeModule("linemedia.auto")) {
			ShowError(GetMessage("LM_AUTO_MODULE_NOT_INSTALL"));
			return;
		}
		$arResult =& $this->arResult;
		$recalc = check_bitrix_sessid() && isset($_POST["RECALC"]) && $_POST["RECALC"] == 'Y';

		foreach ($items as $key => $val) {

			$sql = "SELECT * FROM `b_lm_products` WHERE `original_article` = '" . $val["original_article"] . "' AND `supplier_id` = '" . $val["supplier_id"] . "' AND `brand_title` = '" . $val["brand_title_original"] . "'";
			$db_res = $DB->Query($sql);
			$res = $db_res->Fetch();

			$part_obj = new LinemediaAutoPart($res['id'], $res);
			/**
			 * Посчитаем цену товара
			 */
			$price = new LinemediaAutoPrice($part_obj);
			$price->setUserID($USER->GetID());

			$price_calc = $price->calculate();

			if (!$res
				|| ($res['id'] != $val["prod_id"])
				|| ($res['price'] != $val["base_price"])
				|| ($res['quantity'] < $val["quantity"])
				|| round($price_calc,4) != $val['price'] //если цена в корзине отличается от расчётной
			) {
				if ($recalc) {
					CSaleBasket::Delete($val["ID"]);
					if ($res && $res['quantity'] > 0) {
						$arAddToBasket[0] = array(
							'part_id' => (int)$res['id'], // ID запчасти в локальной БД.
							'supplier_id' => (string)$res['supplier_id'], // ID поставщика. По нему можно также узнать, что запчасть лежит не в локальной БД, а в удалённом API.
							'quantity' => ($res['quantity'] < $val["quantity"]) ? $res['quantity'] : $val["quantity"], // Количество к заказу
							'additional' => array(
								'article' => (string)$res['article'],
								'brand_title' => (string)$res['brand_title'],
								'max_available_quantity' => (int)$res['quantity']
							),
						);

						$basket = new LinemediaAutoBasket();

						foreach ($arAddToBasket as $arAdditem) {
							$part_id = $arAdditem['part_id'];
							$spare = new LinemediaAutoPart($part_id);
							$supplier_id = ($arAdditem['supplier_id'] != '') ? $arAdditem['supplier_id'] : $spare->get('supplier_id');
							$quantity = ($arAdditem['quantity'] > 0) ? $arAdditem['quantity'] : 1;
							$additional = $arAdditem['additional'];
							$additional['max_available_quantity'] = abs($spare->get('quantity'));
							$basket_id = $basket->addItem($part_id, $supplier_id, $quantity, null, $additional);
						}
					} else {
						$this->addWarning(GetMessage("LM_AUTO_BASKET_CHECK_QUANTITY_ERR", ['#ARTICLE#'=>$val['article'], '#BRAND_TITLE#'=>$val['brand_title'], '#PART_TITLE#'=>$val["part_title"], '#SUPPLIER_TITLE#'=>$val["supplier_title"]]), 'CHECK_BASKET');
					}
				} else
					$arResult['products_not_checked'] = 'Y';
			}
		}
		if ($recalc && empty($arResult['WARNING']))
			LocalRedirect($APPLICATION->GetCurPage());
	}


	/**
	 * @param Sale\Order $order
	 * @return Sale\Shipment
	 */
	protected function getCurrentShipment(Sale\Order $order)
	{
		/** @var Sale\Shipment $shipment */
		$shipmentCollection = $order->getShipmentCollection();
		foreach ($shipmentCollection as $shipment)
		{
			if (!$shipment->isSystem())
				return $shipment;
		}
		return $shipmentCollection->createItem();
	}


	/**
	 * Set delivery data from shipment object and delivery services object to $this->arResult
	 * Execution of 'OnSaleComponentOrderOneStepDelivery' event
	 *
	 * @throws Main\NotSupportedException
	 */
	protected function obtainDelivery()
	{
		/** @var Sale\Order $order */
		$arResult =& $this->arResult;
		$order =& $this->order;
		$arStoreId = array();

		$shipment = $this->getCurrentShipment($order);
		$shipment->setField('CURRENCY', $order->getCurrency());

		$shipmentItemCollection = $shipment->getShipmentItemCollection();
		$shipment->setField('CURRENCY', $order->getCurrency());

		foreach ($order->getBasket() as $item)
		{
			/** @var Sale\ShipmentItem $shipmentItem */
			$shipmentItem = $shipmentItemCollection->createItem($item);
			$shipmentItem->setQuantity($item->getQuantity());
		}

		$this->arDeliveryServiceAll = Sale\Delivery\Services\Manager::getRestrictedObjectsList($shipment);

		if (!empty($this->arDeliveryServiceAll))
		{
			$bFirst = true;
			foreach ($this->arDeliveryServiceAll as $deliveryObj)
			{
				$arDelivery =& $this->arResult["DELIVERY"][$deliveryObj->getId()];

				$arDelivery['ID'] = $deliveryObj->getId();
				$arDelivery['NAME'] = $deliveryObj->isProfile() ? $deliveryObj->getNameWithParent() : $deliveryObj->getName();
				$arDelivery['OWN_NAME'] = $deliveryObj->getName();
				$arDelivery['DESCRIPTION'] = $deliveryObj->getDescription();
				$arDelivery['FIELD_NAME'] = 'DELIVERY_ID';
				$arDelivery["CURRENCY"] = $this->order->getCurrency();
				$arDelivery['SORT'] = $deliveryObj->getSort();
				$arDelivery['EXTRA_SERVICES'] = $deliveryObj->getExtraServices()->getItems();
				$arDelivery['STORE'] = Sale\Delivery\ExtraServices\Manager::getStoresList($deliveryObj->getId());

				if (intval($deliveryObj->getLogotip()) > 0)
					$arDelivery["LOGOTIP"] = CFile::GetFileArray($deliveryObj->getLogotip());

				if (!empty($arDelivery['STORE']) && is_array($arDelivery['STORE']))
				{
					foreach ($arDelivery['STORE'] as $val)
						$arStoreId[$val] = $val;
				}
				$arDelivery['CHECKED'] = ($this->request->get('DELIVERY_ID') == $arDelivery['ID']) || (empty($this->request->get('DELIVERY_ID')) && $bFirst);

				$buyerStore = $this->request->get('BUYER_STORE');
				if (!empty($buyerStore) && !empty($arDelivery['STORE']) && is_array($arDelivery['STORE']) && in_array($buyerStore, $arDelivery['STORE']))
				{
					$this->arUserResult['DELIVERY_STORE'] = $arDelivery["ID"];
				}
				$bFirst = false;
			}
		}

		$arResult["BUYER_STORE"] = (int) $this->request->get('BUYER_STORE'); //$shipment->getStoreId();

		$arStore = array();
		$dbList = CCatalogStore::GetList(
			array("SORT" => "DESC", "ID" => "DESC"),
			array("ACTIVE" => "Y", "ID" => $arStoreId, "ISSUING_CENTER" => "Y", "+SITE_ID" => SITE_ID),
			false,
			false,
			array("ID", "TITLE", "ADDRESS", "DESCRIPTION", "IMAGE_ID", "PHONE", "SCHEDULE", "GPS_N", "GPS_S", "ISSUING_CENTER", "SITE_ID", "XML_ID")
		);
		while ($arStoreTmp = $dbList->Fetch())
		{
			if ($arStoreTmp["IMAGE_ID"] > 0)
				$arStoreTmp["IMAGE_ID"] = CFile::GetFileArray($arStoreTmp["IMAGE_ID"]);
			else
				$arStoreTmp["IMAGE_ID"] = null;

			$arStore[$arStoreTmp["ID"]] = $arStoreTmp;
		}

		$arResult["STORE_LIST"] = $arStore;

		$arResult["DELIVERY_EXTRA"] = array();
		$deliveryExtra = $this->request->get('DELIVERY_EXTRA');
		if (is_array($deliveryExtra) && !empty($deliveryExtra[$this->arUserResult["DELIVERY_ID"]]))
			$arResult["DELIVERY_EXTRA"] = $deliveryExtra[$this->arUserResult["DELIVERY_ID"]];
	}

    protected function getCorpUserProfile($userId)
    {
        $profileID = ArusCorpClientsManager::getUserProfileId($userId);
        $haveProfileId = intval($profileID) > 0;
        if ($haveProfileId)
        {
            $dbUserPropsValues = CSaleOrderUserPropsValue::GetList(
                array('SORT' => 'ASC'),
                array(
                    'USER_PROPS_ID' => $profileID,
                ),
                false,
                false,
                array('ID','USER_ID','CODE','VALUE')
            );
            while ($propValue = $dbUserPropsValues->Fetch())
            {
                $profileProperties[$propValue['CODE']] = $propValue['VALUE'];
            }
        }
        $this->arResult["CORP_USER_PROFILE"] = $profileProperties;
    }

    public function executeComponent()
	{
		global $APPLICATION;
		$this->setFrameMode(false);
		$this->context = Main\Application::getInstance()->getContext();
		$this->checkSession = $this->arParams["DELIVERY_NO_SESSION"] == "N" || check_bitrix_sessid();
		$this->isRequestViaAjax = $this->request->isPost() && $this->request->get('via_ajax') == 'Y';
		$isAjaxRequest = $this->request["is_ajax_post"] == "Y";

		if ($isAjaxRequest)
			$APPLICATION->RestartBuffer();

		$this->action = $this->prepareAction();
		Sale\Compatible\DiscountCompatibility::stopUsageCompatible();
		$this->doAction($this->action);
		Sale\Compatible\DiscountCompatibility::revertUsageCompatible();

		if (!$isAjaxRequest)
		{
			CJSCore::Init(array('fx', 'popup', 'window', 'ajax', 'date'));
		}
		CAjax::Init();
		//is included in all cases for old template
		$this->includeComponentTemplate();

		if ($isAjaxRequest)
		{
			$APPLICATION->FinalActions();
			die();
		}
	}
}