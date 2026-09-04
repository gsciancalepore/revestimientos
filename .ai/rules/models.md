---
paths:
  - 'app/Actions/PlaceOrderAction.php, app/Models/Order*.php'
---

# Models

## Pedidos: bcmath, snapshot, lock y limpieza post-commit
Montos siempre en centavos (int) con bcmath, nunca floats. OrderLine es snapshot desnormalizado independiente de Product. PlaceOrderAction valida activo/stock dentro de DB::transaction con lockForUpdate; ante fallo hace rollback sin side-effects. Cart::clear() solo tras COMMIT, fuera de la transacción. Total congelado: subtotal = Σ cantidad×precio_unitario, shipping de ShippingQuote (disponible ? costo : 0 — la ausencia de cotización no bloquea), total = subtotal + shipping.
