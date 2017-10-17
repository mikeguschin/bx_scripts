<?if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();
CJSCore::Init(array("popup"));
?>

<div id="hideBlock" style="display: none">
	<h1>Вниманию корпоративных клиентов!</h1>
	<h3>Для корректного оформления заказа Вам необходимо:</h3>

	<ul>
		<li>Определить кто выкупает заказ, юридическое лицо или физическое лицо.</li>
		<li>Указать информацию о клиенте из доступных.</li>
		<li>Указать контрагента из доступных.</li>
		<li>Указать способ получения заказа, доставка курьером или самовывоз</li>
		<li>Выбрать вариант оплаты, наличными или безналичный платеж.</li>
	</ul>
	<section class="description">
		<h3>Примечание.</h3>
		По заказам, оформленных на физическое лицо, мы предоставляем кассовый чек.
		Будьте внимательны при заполнении всех реквизитов. Заказы обрабатываются в автоматическом режиме.
		Для внесения исправлений, позвоните по общему номеру, указанному на сайте.
		Удачных покупок!
	</section>

	<button class="popup_close" onclick="oPopup.close()">Все понятно.</button>
</div>

<script>

	var oPopup = new BX.PopupWindow('call_feedback', window.body, {
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
	oPopup.setContent(BX('hideBlock'));
	BX.bindDelegate(
		document.body, 'click', {className: 'css_popup' },
		BX.proxy(function(e){
			if(!e)
				e = window.event;
			oPopup.show();
			return BX.PreventDefault(e);
		}, oPopup)
	);
</script>