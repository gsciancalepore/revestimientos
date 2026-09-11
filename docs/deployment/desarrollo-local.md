# Desarrollo local — probar MercadoPago de punta a punta

Documenta cómo dejar el entorno local en condiciones de probar el checkout real contra MercadoPago,
y cómo devolverlo a su estado normal después. Escrito el 2026-09-10, la primera vez que la Spec 07.4
se verificó contra la API real.

**No hace falta para el desarrollo diario.** Solo para probar MercadoPago o, cuando exista, el
webhook de la Spec 08 fase 08.b.

## Por qué hace falta un túnel

MercadoPago rechaza la preferencia con `400 invalid_auto_return` cuando las `back_urls` no son
alcanzables desde internet, y `http://localhost:8080` no lo es. Como `paymentUrl()` lanza ante error
de API (regla 125), sin túnel **todo intento de pago cae en el `catch` de la regla 124** y devuelve
`payment_error`, sin que quede claro por qué.

El gateway envía `auto_return` solo cuando la back_url es pública (Spec 07.4, sincronía 2026-09-10),
así que sin túnel la preferencia **se crea igual** — pero el cliente nunca vuelve al sitio. Para
probar el flujo completo hace falta la URL pública.

El webhook de la Spec 08 va a necesitar lo mismo, y con más razón: MercadoPago tiene que poder
alcanzar el endpoint.

## Requisito previo: DNS (solo del lado de Windows)

En esta máquina, el DNS del ISP devolvía **NXDOMAIN para `trycloudflare.com`** — es un bloqueo común
por abuso de phishing. Verificar antes de pelear con el túnel:

```bash
getent hosts api.trycloudflare.com   # si falla, es esto
```

**Se arregla únicamente en Windows.** En PowerShell **como Administrador** (el adaptador en esta
máquina es `Wi-Fi`):

```powershell
Set-DnsClientServerAddress -InterfaceAlias "Wi-Fi" -ServerAddresses 1.1.1.1,8.8.8.8
Clear-DnsClientCache
```

Para revertirlo: `Set-DnsClientServerAddress -InterfaceAlias "Wi-Fi" -ResetServerAddresses`.

WSL no necesita nada: su `/etc/resolv.conf` generado apunta a `10.255.255.254`, que es el gateway de
la VM, y **ese gateway reenvía las consultas al resolver de Windows**. Arreglando Windows queda
arreglado WSL. Verificado el 2026-09-10: con el `resolv.conf` automático,
`getent hosts api.trycloudflare.com` resuelve sin problemas.

### ⚠️ No tocar `/etc/wsl.conf` — la versión anterior de este documento pedía hacerlo

Hasta el 2026-09-10 esta sección indicaba además agregar `[network] generateResolvConf = false` a
`/etc/wsl.conf` y escribir un `/etc/resolv.conf` a mano con `1.1.1.1` y `8.8.8.8`. **Era redundante
—el arreglo de Windows ya alcanzaba— y provocó dos problemas encadenados:**

1. **Dejó a WSL sin resolución para todo lo demás.** Con el `resolv.conf` fijo, el login de Claude
   Code falló por error de DNS. Cualquier servicio que dependa de la red desde WSL puede caer igual.
2. **Revertirlo obliga a un `wsl --shutdown`**, y ese reinicio deja los **puertos publicados de
   Docker rotos**: los contenedores levantan sanos y se hablan entre ellos, pero
   `http://localhost:8080` responde `ERR_EMPTY_RESPONSE` desde el navegador y *connection reset*
   incluso con `curl` desde adentro de WSL. Se arregla recreando los contenedores que publican
   puertos (ver `.ai/rules/general.md`).

Si el bloque llegó a agregarse, quitarlo, correr `wsl --shutdown`, y **contar con tener que recrear
los contenedores después**.

## Procedimiento

`cloudflared` está instalado en `~/.local/bin/cloudflared` (binario descargado de los releases de
Cloudflare, sin sudo). El *quick tunnel* no necesita cuenta.

1. **Levantar el túnel** — dejarlo corriendo en su propia terminal:

   ```bash
   ~/.local/bin/cloudflared tunnel --url http://localhost:8080
   ```

   Imprime una URL tipo `https://algo-random.trycloudflare.com`. **Cambia en cada arranque.**

2. **Apuntar `APP_URL` a esa URL** en `.env` y limpiar config:

   ```bash
   docker compose exec app php artisan config:clear
   ```

3. **Compilar los assets** y apagar el dev server de Vite:

   ```bash
   docker compose stop assets
   rm -f public/hot
   make npm-build
   ```

   Sin esto la página se ve **sin estilos**: Blade emite los assets como `http://localhost:5173`
   mientras la página se sirve por `https`, y el browser los bloquea por *mixed content*. Se pierde
   el hot reload mientras dure la prueba.

4. **Entrar por la URL del túnel**, nunca por `localhost`.

## Credenciales y cuentas de prueba

`MERCADOPAGO_ACCESS_TOKEN` y `MERCADOPAGO_PUBLIC_KEY` van en `.env` (ignorado por git). Se leen por
`config/services.php`, nunca por `env()` directo (regla 122).

**Los tests nunca tocan la API**: `phpunit.xml` fuerza esas credenciales vacías. Si se agrega otro
servicio externo, hay que neutralizarlo ahí también.

Para pagar en el sandbox, dos formas coherentes — **no se pueden mezclar**, MercadoPago corta con
*"Una de las partes con la que intentás hacer el pago es de prueba"*:

- **Como invitado** (la que funcionó): credenciales `TEST-` de la cuenta real + **sin iniciar sesión
  en MercadoPago**, pagando con tarjeta de prueba. Usar ventana de incógnito: si arrastrás la sesión
  de tu cuenta real, el botón de pagar **queda deshabilitado sin explicación**, porque comprador y
  vendedor no pueden ser la misma cuenta.
- **Con dos usuarios de prueba**: crear vendedor y comprador en el panel de MercadoPago, usar las
  credenciales **del vendedor de prueba**, y pagar logueado como el comprador de prueba.

Dato útil para diagnosticar: el prefijo del `preference_id` es el ID de la cuenta vendedora, y
coincide con el último segmento del access token.

## Volver al estado normal

```bash
# APP_URL=http://localhost:8080 en .env, después:
docker compose exec app php artisan config:clear
make npm-dev          # vuelve el hot reload
```

Y cerrar el túnel (Ctrl+C en su terminal).

## Webhook (Spec 08 fase 08.b)

El webhook es lo que hace que un pago aprobado mueva el pedido **solo**, sin que nadie mire el panel
de MercadoPago. Necesita el mismo túnel que el resto de esta guía, por la misma razón: MercadoPago
tiene que poder alcanzar la URL desde internet.

**La URL que va configurada en el panel** es la pública más la ruta del webhook:

```
https://<subdominio>.trycloudflare.com/webhook/mercadopago
```

El subdominio lo imprime `cloudflared` y **cambia en cada arranque**, así que hay que reconfigurarlo
en el panel cada vez que se levanta el túnel.

### Procedimiento

1. Levantar el túnel y apuntar `APP_URL`, igual que arriba (§Procedimiento, pasos 1 y 2).
2. En el panel de MercadoPago: **Tus integraciones → tu aplicación → Webhooks**. Pegar la URL,
   marcar el evento **Pagos** (`payment`) y guardar. Exige HTTPS: `localhost` no se acepta.
3. El panel genera ahí mismo una **clave secreta**. Va a `.env` como
   `MERCADOPAGO_WEBHOOK_SECRET` —es una credencial **distinta del access token**— y después
   `docker compose exec app php artisan config:clear`.
4. Configurar el webhook del **modo de prueba**, que es donde están las credenciales `TEST-`. El
   secreto de producción es otro.

Sin secreto configurado el endpoint responde **401 a todo**, a propósito (regla 154): preferible a
aceptar notificaciones sin verificar.

### Qué esperar al probar

- El botón **"Simular notificación"** del panel sirve para verificar firma y ruta, pero manda un
  `data.id` que la cuenta no conoce. La respuesta correcta ahí es **200** con
  `webhook.payment_not_found` en `audit_logs`, **no** un error: "no había nada que hacer" es un
  caso legítimo (reglas 153 y 156).
- Un **503** significa que la consulta a la API de MercadoPago falló, y es deliberado: hace que
  MercadoPago reintente en vez de perder el pago (regla 153).
- La prueba que vale es la real: pagar en el sandbox y ver el pedido pasar a `paid` con el stock
  descontado. Los tests cubren el webhook con dobles y **nunca** alcanzan la red, así que verde en
  la suite no es lo mismo que verificado — es exactamente lo que enseñó la 07.4, donde seis días de
  CI verde convivieron con MercadoPago cobrando el subtotal.

## Limitación conocida

La firma del webhook **no valida frescura del `ts`**, así que una notificación capturada es
reproducible. El impacto práctico es nulo por la idempotencia de la regla 152 —reproducirla no
descuenta stock dos veces— y la spec no lo exige; queda anotado por si alguna vez importa.
Lo que esta prueba valida es el tramo de ida: preferencia creada con el monto correcto, redirección,
y retorno a `/checkout/exito`.
