/**
 * evoCartForm
 *
 * formTpl for FormLister
 *
 * @category	chunk
 * @version 	1.0.0
 * @author	Artyom Kremen
 * @internal    @modx_category evoCart
 * @internal    @installset base
 * @lastupdate  09/04/2018
 * @overwrite   true
 */

[!FormLister?
	&redirectTo=`1`
	&to=`arty.volen@gmail.com`
	&subject=`Новый заказ с сайта`

	&debug=`-1`
	&formid=`orderForm`
	&prepareProcess=`prepareEvoCartForm`
	&protectSubmit=`0`
	&submitLimit=`0`
	&ccSender=`1`
	&errorClass=` has-error`
	&requiredClass=` has-warning`
	&rules=`{
	"name":{
                                                                "required":"Обязательно введите имя"
                                                        },
	}`
	&formTpl=`@CODE:
	<div class="js-ec-ifempty">
	<h3>Оформление заказа:</h3>
	<form method="POST" class="form-vertical js-ec-form" id="orderCartForm" action="[~[*id*]~]">
		<input type="hidden" name="formid" value="orderForm">
		<div class="form-group[+delivery.errorClass+][+delivery.requiredClass+]">
			<label for="delivery">Метод доставки</label>
			<select class="form-control fullwidth" name="delivery" id="delivery">
				<option value="Курьер" id="curier">Курьером</option>
				<option value="Самовывоз">Самовывоз</option>
			</select>
			<div class="form-control-feedback">[+delivery.error+]</div>
		</div>
		<div class="form-group[+address.errorClass+][+address.requiredClass+]" id="for_address">
			<label for="address">Адрес</label>
			<input type="text" class="form-control fullwidth" name="address" id="address" value="[+address.value+]">
			<div class="form-control-feedback">[+address.error+]</div>
		</div>
		<div class="form-group">
			<label for="message">Комментарий к заказу</label>
			<textarea class="form-control fullwidth" name="message" id="message" rows="3">[+message.value+]</textarea>
		</div>
		<button type="submit" class="btn btn-primary evoShop_submit">Оформить заказ</button>
	</form>
	</div>
	`
	&messagesOuterTpl=`@CODE: [+messages+]`
	&errorTpl=`@CODE: [+message+]`
	&successTpl=`@CODE:
	<div class="alert alert-success mt-3">
		<h3>Спасибо за ваш заказ!</h3>
		<p>Наш менеджер свяжется с вами в ближайшее время.</p>
	</div>`
	&reportTpl=`@CODE:
	<p>Имя: [+name+]</p>
	<p>Пользователь: [+username+]</p>
	<p>Email: [+email+]</p>
	<p>Телефон: [+phone+]</p>
	<p>Метод доставки: [+delivery.value+]</p>
	<p>Адрес: [+address.value+]</p>
	<p>Комментарий к заказу: [+message.value+]</p>
	[+evoCart_items+]
	`
	&ccSenderTpl=`@CODE:
	Вы оформили заказ на сайте:
	<p>Имя: [+name+]</p>
	<p>Email: [+email+]</p>
	<p>Телефон: [+phone+]</p>
	<p>Метод доставки: [+delivery.value+]</p>
	<p>Адрес: [+address.value+]</p>
	<p>Комментарий к заказу: [+message.value+]</p>
	[+evoCart_items+]
	`
!]