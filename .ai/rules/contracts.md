---
paths:
  - 'app/Services/*Gateway*.php, app/Contracts/PaymentGateway.php'
---

# Contracts

## Pagos: paymentUrl null-vs-excepción y reintento POST
PaymentGateway::paymentUrl(Order): ?string — null significa "este medio no tiene URL" (transferencia); el fallo de un gateway con URL (MercadoPago: token ausente, 4xx/5xx, sin init_point) es excepción, nunca null. MercadoPagoGateway nunca retorna null (covarianza string). Reintento de pago siempre por POST explícito (checkout.mercadopago.retry, valida mercadopago + PendingPayment sino 403); GET /checkout/exito es solo lectura y nunca crea preferencias. Error de API → Order queda PendingPayment + redirect success with payment_error, sin 500.
