# ADR-001 — Organización por dominios: arranque con 4 dominios

- **Estado**: aceptado (2026-08-05)
- **Contexto**: el plan original proponía 8+ dominios desde el día uno (Catalog,
  Products, Orders, Customers, Inventory, Payments, Repairs, Users). El dueño
  corrigió: varios no son dominios todavía, sino formas de mostrar o atributos de
  otros conceptos.

## Decisión

1. Se arranca con **cuatro dominios**: Products, Orders, Payments, Users.
2. **Catalog no es un dominio**: es la vista pública de Products.
3. **Inventory no se crea**: el stock (cajas) es atributo del producto.
4. **Customers no se crea**: el cliente anónimo solo aporta email y CP en el pedido.
5. **Shipping y Discounts** no se crean: viven dentro de Orders/Payments hasta que
   su complejidad lo justifique.
6. **Repairs queda fuera** del proyecto (decisión del dueño).
7. Los dominios no tienen carpetas propias: se expresan con prefijos y convenciones
   sobre la estructura Laravel estándar (ver `docs/arquitectura.md`).

## Consecuencias

- Menos estructura inicial, menos fricción en las primeras fases.
- Los nombres de clases/controladores llevan el prefijo del dominio (Product,
  Order, Payment, User) para que el mapa de dominio se lea en el código.
- Cuando un dominio crezca (tabla de movimientos, historial de clientes, etc.) se
  extrae con la frontera ya documentada (ver `docs/arquitectura.md` → "Fronteras
  futuras"); esa extracción generará un ADR propio.

## Alternativas

- **Módulos con carpetas por dominio desde el día uno**: descartado — agrega
  navegación y convenciones sin aportar valor con el tamaño actual (KISS/YAGNI).
- **Mantener Catalog/Inventory/Customers como dominios**: descartado — son
  atributos o vistas de los dominios reales; separarlos inventa abstracciones.
