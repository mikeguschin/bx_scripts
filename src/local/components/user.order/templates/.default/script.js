/**
 * Created by m.gushhin on 04.07.2017.
 */
// PDD = Preferred Delivery Date
/** Ключ - день недели (начиная с 0)
 * значение - время в формате HH:MI
 * @type {{1: string, 2: string, 3: string}}
 */
function isPickupCheck(){
    var deliveryIdPickup = 'ID_DELIVERY_ID_109';
    var result = document.getElementById(deliveryIdPickup).checked || 0;
    return result;

}

function fullOrderValue(){
    var radioValue = '';
    var fullOrderSelector = 'FULL_ORDER';
    var fullOrderRadios = document.getElementsByName(fullOrderSelector);
    for (var i = 0, lenght = fullOrderRadios.length; i < lenght; i++) {
        if (fullOrderRadios[i].checked) {
            radioValue = fullOrderRadios[i].value;
        }
    }
    return radioValue;
}

function fullOrderCheck(){
    var result;
    var fullOrderSelector = 'FULL_ORDER';
    var fullOrderRadios = document.getElementsByName(fullOrderSelector),
        radioValue = 'N';
    for (var i = 0, lenght = fullOrderRadios.length; i < lenght; i++) {
        if (fullOrderRadios[i].checked) {
            radioValue = fullOrderRadios[i].value;
        }
    }
    if (radioValue == "Y"){
        result = true;
    }
    else{
        result = false;
    }
    return result;
}

function checkDateAvailable(dateToCheck, allowedDatesJSON){
    var result = false;
    dateToCheck.setHours(23);
    dateToCheck.setMinutes(59);
    try{
        if (allowedDatesJSON.length == 0){
            throw new Error;
        }
        var today = new Date();
        var allowedDates = [];
        JSON.parse(allowedDatesJSON, function(key, value) {
            allowedDates[key] = value;
        });
        if (allowedDates.length != 0){
            result = true;
        }
        allowedDates.forEach(function (item, i, allowedDates) {
            //Номер проверяемого дня совпал с текущим
            if (dateToCheck.getDay() == i){
                result = false;
            }
            if (result == false){
                var checkDate = new Date(dateToCheck.getFullYear(), dateToCheck.getMonth(), dateToCheck.getDate(), item.split(":")[0], item.split(":")[1]);
                if (checkDate < today){
                    result = true;
                }
            }
        });
        return result;
    }
    catch (e){
        return result;
    }
}
function clickFullOrderInput(value){
    if (value == "Y"){
        document.getElementById("FULL_ORDER_YES").checked = true;
    }
    else{
        document.getElementById("FULL_ORDER_NO").checked = true;
    }
}
function initPDDInput(allowedDatesJSON, deliveryDateInputId, DeliveryHoursAdd){
    if (!isPickupCheck()){
        // var id = $('.' + deliveryDateInputSelector).attr('id');
        var id = deliveryDateInputId;
        var today = new Date();
        var newToday = today;
        if (DeliveryHoursAdd > 0) {
            var newToday = today.setHours(today.getHours() + DeliveryHoursAdd);
        }
        if (timepicker !== undefined && timepicker.isObject){
            timepicker.destroy();
        }
        // var allowedDatesJSON = '{"1":"15:00","2":"15:00","3":"11:16","5":"13:00"}';
        var timepicker = new Pikaday({
            field: document.getElementById(id),
            firstDay: 1,
            minDate: new Date(newToday),
            yearRange: [today.getFullYear(),today.getFullYear()+1],
            autoClose: false,
            format: 'DD.MM.YYYY',
            keyboardInput: false,
            i18n: {
                previousMonth : 'Пред. месяц',
                nextMonth     : 'След. месяц',
                months        : ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'],
                weekdays      : ['Воскресенье','Понедельник','Вторник','Среда','Четверг','Пятница','Суббота'],
                weekdaysShort : ['ВС','ПН','ВТ','СР','ЧТ','ПТ','СБ']
            },
            disableDayFn: function(dateToCheck){
                return checkDateAvailable(dateToCheck, allowedDatesJSON);
            }
        });

    }
};