---
paths:
  - database/factories/ProductFactory.php
---

# Factories

## ProductFactory: slug no sigue al name sobreescrito
El slug por defecto de la factory se deriva del nombre faker interno (`$name = fake()->words(3)`), no del atributo `name` que se sobreescribe. Si un test necesita nombre↔slug coherentes, pasar ambos explícitos: `Product::factory()->create(['name' => 'X', 'slug' => 'x'])`.
