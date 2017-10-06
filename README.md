# VirtueMart plugin for Payme

## Установка

#### Требования

- Joomla 3.7.0
- Virtuemart 3.0.10
- Регистрация в кабинете поставщика [Paycom](http://paycom.uz/)

#### Настройка БД MySQL

1.1	Заходим в панель управления phpMyAdmin

![phpMyAdmin](images/1.jpg)

1.2	Открывем таблицу «___virtuemart_orders»

![virtuemart_orders](images/2.jpg)

1.3	Переходим во вкладку «Структура» и вводим в поле «добавить» цифру 3, затем нажимаем «Вперед»

![image3](images/3.jpg)

1.4	В открывшемся окне заполняем имена полей «create_time», «perform_time», «cancel_time» а так же тип «BIGINT». Нажимаем «Сохранить»

![image4](images/4.jpg)

1.5	В таблицу добавились 3 столбца

![image5](images/5.jpg)

#### Установка модуля

2.1 Перейти на вкладку установки расширений Extensions->Manage->Install

![image6](images/6.jpg)

2.2 Перейти на вкладку Upload Package File и указать (или перетащить)  архив virtuemart-gateway-payme.zip

![image7](images/7.jpg)

2.3 Успешная установка сопровождается сообщением

![image7](images/8.jpg)

#### Настройка плагина

3.1 Переходим Virtuemart->Payment Methods

![image9](images/9.jpg)

3.2 Нажимаем кнопку «New» и заполняем поля настройки метода оплаты:
Payment Name: Payme;
Self Alias: payme;
Published: yes;
Payment Method: Payme;
Description заполняется опционально
Нажимаем кнопку «Save»

![image10](images/10.jpg)

3.3 Переходим во вкладку «Configuration» и заполняем MERCHANT_ID и SECRET_KEY. Нажимаем «SAVE»
![image11](images/11.jpg)

3.4 Настройка модуля завершена
![image12](images/12.jpg)

**Важно! В файле /plugins/vmpayment/payme/payme.php в строках 108 и 148 заменяем “7” на ID метода Payme (на скрине выше последний столбик – цифра “8”)**




