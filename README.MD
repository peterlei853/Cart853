# Description 
Ue Pay OpenCart extension, OpenCart 3.0.2

# Useful URL
```
user: (Register yourself)
http://sntong.synology.me

user: admin  password: Mudar123
http://sntong.synology.me/admin/
```

# Uepay flow
1. use scan the qrcode in wechat app, open the url `index.php?route=extension/payment/ue_pay/payConfirm&order_id={order_id}&login_token={token}` in wechat.

2. In payConfirm

    2.1 use APPID to get wechat access token (CODE)

    2.2 use APPID, APPSECRET, CODE to get user OPENID

    2.3 use OPENID, ORDER_NO, AMOUNT, ... call uePay api get the wechat prepay data.

    2.4 use wechat prepay data render the parameters of the `getBrandWCPayRequest` method.

3. user press confirm.

4. TODO...


## related code
```
app/catalog/controller/extension/payment/ue_pay.php, index()->qrcode()->payConfirm()
```



# UePay Related Files

Admin:

- app/admin/controller/extension/payment/ue_pay.php
- app/admin/language/en-gb/extension/payment/ue_pay.php
- app/admin/view/template/extension/payment/ue_pay.twig

Catalog:

- app/catalog/controller/extension/payment/ue_pay.php
- app/catalog/language/en-gb/extension/payment/ue_pay.php
- app/catalog/model/extension/payment/ue_pay.php
- app/catalog/view/template/extension/payment/ue_pay.twig

Wechat PHP SDK:
- app/tmp/opencart-3.0.2.0/upload/system/storage/vendor/zoujingli/wechat-php-sdk/Wechat/

# Dev Tool
vscode + sftp extension