# PROJECT PRINCIPLES

Principios innegociables del proyecto. Toda decisión de diseño, de código o de
proceso debe poder justificarse frente a estas reglas. Si una decisión las
contradice, se revisa la decisión.

## Las diez reglas

1. **Nunca programar sin una Spec aprobada.**
   Todo desarrollo comienza en `docs/specs/`. Si no hay spec aprobada, no hay código.

2. **Nunca asumir reglas de negocio.**
   Si una regla no está especificada, se pregunta. Está prohibido inventar comportamiento.

3. **Toda funcionalidad comienza con un test.**
   TDD: red → green → refactor. Ninguna funcionalidad se implementa sin tests.

4. **Todo cambio importante genera un ADR.**
   Las decisiones arquitectónicas se registran en `docs/adr/` con su contexto, la
   decisión y sus consecuencias.

5. **Preferir simplicidad antes que flexibilidad.**
   La alternativa más simple que satisface los requisitos actuales es la correcta.
   (YAGNI, KISS)

6. **El código debe ser legible antes que inteligente.**
   Nombres expresivos, métodos cortos, cero "clever code". El código se lee más
   veces de las que se escribe.

7. **No optimizar sin medir.**
   Primero claridad, después medir, y solo entonces optimizar.

8. **No agregar abstracciones innecesarias.**
   Cada capa, interfaz o clase extra debe demostrar que vale su peso.

9. **El dominio manda sobre la tecnología.**
   Las reglas de negocio viven en el dominio, nunca en el framework ni en la UI.

10. **Toda decisión debe poder explicarse dentro de un año.**
    Si dentro de un año nadie puede reconstruir el porqué de una decisión, esa
    decisión está mal documentada.

## Convención de commits

Commits con formato Conventional Commits y mensaje en español.

| Tipo | Uso |
|---|---|
| `feat` | Nueva funcionalidad (spec aprobada) |
| `fix` | Corrección de bug |
| `chore` | Infraestructura, dependencias, scaffolding, CI |
| `docs` | Specs, ADRs, arquitectura, roadmap y demás documentación |
| `refactor` | Refactor sin cambio de comportamiento |
| `test` | Agregar o ajustar tests |
| `perf` | Optimización (con medición previa) |

Formato: `tipo(ámbito): descripción` (el ámbito es opcional).

Ejemplos:

```
feat(products): crear producto
fix(checkout): validar stock al generar el pedido
docs(specs): aprobar spec 05 reglas del carrito
chore: configurar CI en GitHub Actions
```
