# ADR-006 — Envíos: puerto + adaptador (implementación inicial interna)

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el costo de envío se calcula cuando el cliente ingresa su código
  postal. El dueño pidió "dejar un adaptador" porque el cálculo interno vs. una
  API externa se decide más adelante.

## Decisión

1. Se define un **puerto** (interfaz) en el dominio de envío, hoy dentro de Orders:

   ```php
   interface ShippingCalculator
   {
       public function quote(ShippingQuoteRequest $request): ShippingQuote;
   }
   ```

   `ShippingQuoteRequest` contiene: código postal, peso/cajas, m² y opciones de
   entrega. `ShippingQuote` devuelve el costo (centavos), disponibilidad y
   estimación de entrega.

2. **Implementación inicial interna**: `ManualShippingCalculator` con tarifas
   cargadas desde el admin (por CP o rango de CP / zona). Sin integraciones.
3. El cálculo se **resuelve desde el contenedor de servicios** (binding en
   `AppServiceProvider`), de modo que reemplazar la implementación por una API de
   cotización futura no toca ningún caso de uso ni vista.

## Consecuencias

- El dominio y las vistas dependen solo de la interfaz (dependency inversion).
- El manual/API de cotización se puede A/B probar o cambiar sin migraciones.
- Costo: una interfaz + un DTO de request/response — mínima, justificada por el
  requerimiento explícito del dueño.
- Las tarifas internas son datos de negocio (Spec 06): se definen formato, rangos
  y prioridad de match en esa spec.

## Alternativas

- **Sin puerto, función concreta**: descartado — el cambio a API obligaría a
  tocar Actions, controladores y vistas (acoplamiento que el dueño pidió evitar).
- **Puerto con implementación vacía/throw**: descartado — la impl. interna es
  viable desde el día uno y da valor real.
