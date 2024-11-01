=== Migración de Medio de Pago Webpay Plus SOAP a REST de Transbank para WooCommerce ===
Contributors: AndresReyesDev
Donate link: https://andres.reyes.dev
Tags: woocommerce, payment-gateway, chile, webpay, transbank, webpay-plus, webpay-plus-rest, woocommerce, payment, gateway, khipu, flow, servipag, redcompra, onepay
Requires at least: 4.0
Tested up to: 5.6
Stable tag: 5.6
Requires PHP: 7.2+
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vende con las tarjetas de Webpay Plus en tu carro de compras con WooCommerce. Medio de Pago de Transbank.

== Description ==

Activa el medio de pago Transbank Webpay Plus REST en tu sitio web con WooCommerce. Este plugin tiene las siguientes mejoras:

1. Envía correos de respaldo al cliente y al comercio (como lo hace actualmente Webpay.cl). Esto permite que el comercio sepa al momento del pago y no deba esperar las 48 horas.
2. Guarda el registro de la venta (todo los datos) en la orden de compra. Por tanto, es posible que el cliente lo sepa y que el comercio tenga el respaldo.
3. Es compatible sólo con PHP 7.2, 7.3 y 7.4
4. Funciona en servidores Apache y Nginx
5. Mejora el código del plugin oficial Transbank.

Este plugin está basado en la versión desarrollada por [Transbank Developers](https://transbankdevelopers.cl/plugin/woocommerce/).

La marca y el logotipo de Transbank, Webpay Plus, Redcompra, OnePay son marcas registradas de [Transbank S.A.](https://www.transbank.cl) y son usados para fines meramente informativos dentro del plugin. Este plugin no tiene afiliación de ningún tipo con Transbank ni sus filiales.

== Installation ==

1. Sube los archivos a la carpeta `/wp-content/plugins/wc-transbank-webpay-plus-rest`, o instalalo directamente a través del panel de Wordpress Plugins.
2. Activalo a través de la pantalla de la sección de Plugins de Wordpress.
3. Para configurar el plugin debes ingresar a WooCommerce > Ajustes > Pagos > Webpay Plus Rest.
4. Realiza el paso a producción. Para más información ve a Preguntas Frecuentes.

== ¿OneClic para WooCommerce? ¿Captura Diferida para WooCommerce? ¿Webpay Plus Dolar? ==

Si deseas **OneClick para WooCommerce**, **Captura Diferida para WooCommerce** o **Webpay Plus Dólar para WooCommerce** este no es el plugin que buscas. Para usar dichos productos de Transbank dispongo de otros plugin desarrollados que ya funcionan en más de 50 sitios web. Valores, formas de trabajo y requerimientos técnicos puedo indicarlos vía mi [WhatsApp aquí](https://link.reyes.dev/webpay-plus-woocommerce)

== Frequently Asked Questions ==

= ¿Qué requisitos técnicos tiene este plugin? =

Nada especial. En la práctica si puedes instalar Wordpress y WooCommerce el plugin debería funcionar correctamente. Sin embargo, según la documentación oficial los módulos o extensiones son las siguientes:

* ext-curl
* ext-json
* ext-mbstring

= ¿Este plugin tiene costo? =

El usar el plugin no tiene costo y usarlo en tu web es gratis, sin embargo, Transbank exige que para poder vender con dinero real (Paso a Producción) realices un proceso técnico el cual puedo realizar yo.

Si deseas puedes escribirme a mi [WhatsApp aquí](https://link.reyes.dev/webpay-plus-woocommerce) para indicarte valores, plazos y contratar el servicio.

**Importante: No resuelvo dudas técnicas ni doy soporte en mi WhatsApp, para ello puedes usar el foro de [soporte acá](https://wordpress.org/support/plugin/wc-transbank-webpay-plus-rest/).**

= ¿Debo tener contrato con Transbank para poder usar el servicio? =

Si, es requisito tener contrato con Transbank y puedo orientarte en el proceso de contratación como parte del servicio anteriormente indicado.

= ¿Cuanto tiempo tarda el paso a producción? =

Son 4 etapas y la mitad dependen de Transbank. Usualmente el proceso tarda menos de 4 días hábiles.

= ¿Qué medios de pago quedan habilitados con este plugin? =

Depende del contrato que realices con Transbank, sin embargo, habitualmente son: Tarjetas de Crédito (Visa, Mastercard, Diners Club, American Express y Magna), Redcompra, OnePay y Redcompra Prepago.

= ¿Puedo recibir pagos en dólares? =

No, este plugin funciona sólo con pesos chilenos (esta es una restricción de Transbank). Si dispones de **Webpay Plus Dólar** lee más arriba.

= ¿Puedo recibir pagos con tarjetas de bancos extranjeros? =

No, este plugin funciona sólo con tarjetas emitidas por bancos chilenos..

== Changelog ==

= 2020.12.03 =
Versión Inicial