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

## Requisito previo: DNS (WSL2 + Windows)

En esta máquina, el DNS del ISP devolvía **NXDOMAIN para `trycloudflare.com`** — es un bloqueo común
por abuso de phishing. Verificar antes de pelear con el túnel:

```bash
getent hosts api.trycloudflare.com   # si falla, es esto
```

Hay que arreglarlo **en los dos lados**, porque WSL y Windows resuelven por separado. Windows es el
que necesita el browser.

**WSL** (una vez; `wsl.conf` evita que se regenere, `resolv.conf` toma efecto al instante, sin
reiniciar):

```bash
sudo tee -a /etc/wsl.conf > /dev/null <<'EOF'

[network]
generateResolvConf = false
EOF

sudo rm -f /etc/resolv.conf && sudo tee /etc/resolv.conf > /dev/null <<'EOF'
nameserver 1.1.1.1
nameserver 8.8.8.8
EOF
```

**Windows**, en PowerShell **como Administrador** (el adaptador en esta máquina es `Wi-Fi`):

```powershell
Set-DnsClientServerAddress -InterfaceAlias "Wi-Fi" -ServerAddresses 1.1.1.1,8.8.8.8
Clear-DnsClientCache
```

Para revertirlo: `Set-DnsClientServerAddress -InterfaceAlias "Wi-Fi" -ResetServerAddresses`.

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

## Limitación conocida

Sin webhook (hasta la Spec 08 fase 08.b), **un pago aprobado no mueve el pedido**: queda en
`PendingPayment` y el stock no se descuenta. Es el comportamiento correcto hoy, según la regla 128.
Lo que esta prueba valida es el tramo de ida: preferencia creada con el monto correcto, redirección,
y retorno a `/checkout/exito`.
