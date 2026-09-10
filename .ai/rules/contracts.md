---
paths:
  - 'app/Services/*Gateway*.php, app/Contracts/PaymentGateway.php'
---

# Contracts

## Pagos: paymentUrl null-vs-excepción y reintento POST
PaymentGateway::paymentUrl(Order): ?string — null significa "este medio no tiene URL" (transferencia); el fallo de un gateway con URL (MercadoPago: token ausente, 4xx/5xx, sin init_point) es excepción, nunca null. MercadoPagoGateway nunca retorna null (covarianza string). Reintento de pago siempre por POST explícito (checkout.mercadopago.retry, valida mercadopago + PendingPayment sino 403); GET /checkout/exito es solo lectura y nunca crea preferencias. Error de API → Order queda PendingPayment + redirect success with payment_error, sin 500.

## Pagos: el payload de la preferencia MP depende del entorno
`auto_return` se envía solo si la back_url es alcanzable desde internet (se descartan localhost, 127.0.0.1, ::1, sufijos .localhost/.local/.test y rangos IP privados o reservados): MercadoPago responde 400 invalid_auto_return en caso contrario y, como paymentUrl lanza ante error de API, MP quedaría inutilizable en local. Para probar MP en desarrollo hace falta un túnel (cloudflared) con APP_URL apuntando a la URL pública. El costo de envío viaja en shipments.cost (mode not_specified), nunca como ítem de items, y solo cuando shipping_cost_cents > 0: sin eso MercadoPago cobra el subtotal en vez del total del pedido.

## Servicios externos: los tests nunca alcanzan la red
Toda credencial de servicio externo se neutraliza en phpunit.xml (MERCADOPAGO_ACCESS_TOKEN/MERCADOPAGO_PUBLIC_KEY vacías), porque la suite hereda el .env del desarrollador. Los tests bindean un gateway explícito en el contenedor; nunca dependen de que el ambiente esté sin configurar para tomar el camino de error.
