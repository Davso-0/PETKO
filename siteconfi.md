# Configuración de PETKO para `petko.site.je`

Guía para pasar la instalación local de XAMPP al subdominio alojado en InfinityFree. Haz primero una copia de seguridad de los archivos y de la base local.

## 1. Preparar la carpeta del sitio

1. En el panel de InfinityFree, confirma que `petko.site.je` apunte a la carpeta raíz del sitio (normalmente `htdocs`).
2. Sube el contenido del proyecto directamente a esa carpeta. Ahí deben quedar `index.php`, `inicioSesion.php`, `google-callback.php`, `src/`, `docs/` y `vendor/`. No pongas todo dentro de una carpeta adicional, a menos que el subdominio esté configurado para apuntar a ella.
3. Sube también `vendor/`: Composer la excluye de Git, pero la aplicación la necesita para PHPMailer, Dotenv y la verificación de Google. Si tienes acceso a Composer en el hosting, puedes generarla en la raíz con `composer install --no-dev --optimize-autoloader`; si no, sube la carpeta `vendor/` generada en tu instalación local.
4. Conserva exactamente las mayúsculas de los nombres de archivos y carpetas, en particular `versionMobil`. El hosting distingue mayúsculas y minúsculas aunque Windows no lo haga.
5. Comprueba en el panel que el sitio use PHP 8.1 o superior y tenga habilitados PDO MySQL y cURL. El sitio también necesita HTTPS activo.

## 2. Copiar la base de datos local

No uses como copia completa `docs/base_datos/kion.sql`: es un volcado base y no representa necesariamente las tablas, roles ni datos que ya usa tu proyecto local. El `README.md` también menciona `petko.sql`, pero ese archivo no está en la carpeta actual. `migracion_notificaciones.sql` puede fallar si se ejecuta sobre una base que ya tiene sus columnas.

1. En `http://localhost/phpmyadmin`, selecciona la base que realmente usa tu instalación local (en la configuración predeterminada del proyecto se llama `kion`).
2. Usa **Exportar** y guarda una copia SQL con **estructura y datos**.
3. En InfinityFree, crea una base de datos y un usuario desde el panel. Copia exactamente el host, nombre de la base, usuario y contraseña que muestra el panel; el host remoto de MySQL no necesariamente es `localhost`.
4. Abre phpMyAdmin desde el panel de InfinityFree, selecciona la base recién creada e importa el archivo exportado.
5. Verifica que se copiaron los datos necesarios y las tablas que usa tu versión actual. En particular, el rol `Usuario` debe existir en `roles`; el código lo necesita para registrar clientes y crear cuentas con Google.

Importa primero en una base vacía. No vuelvas a importar el volcado sobre datos de producción sin respaldo: podrías duplicar o reemplazar información.

## 3. Crear la configuración privada `.env`

Crea un archivo llamado `.env` en la raíz del proyecto del hosting, junto a `index.php`. Parte de `.env.example`, pero reemplaza cada valor de ejemplo. No subas credenciales reales a Git ni las compartas públicamente.

```env
KION_DB_HOST=HOST_MYSQL_INDICADO_POR_INFINITYFREE
KION_DB_PORT=3306
KION_DB_NAME=NOMBRE_COMPLETO_DE_LA_BASE
KION_DB_USER=USUARIO_COMPLETO_DE_LA_BASE
KION_DB_PASSWORD=CONTRASENA_DE_LA_BASE

KION_SMTP_HOST=smtp.gmail.com
KION_SMTP_PORT=587
KION_SMTP_ENCRYPTION=tls
KION_SMTP_USER=petkosupport@gmail.com
KION_SMTP_PASSWORD=CONTRASENA_DE_APLICACION
KION_MAIL_FROM=petkosupport@gmail.com
KION_MAIL_FROM_NAME="PETKO Support"

GOOGLE_CLIENT_ID=tu-client-id.apps.googleusercontent.com
```

- Usa los datos de MySQL del panel, incluidos los prefijos que InfinityFree agregue al nombre de la base o del usuario. No uses `root` ni la contraseña vacía de XAMPP.
- Para Gmail, usa `petkosupport@gmail.com` como cuenta SMTP y dirección `From`; `KION_SMTP_PASSWORD` debe ser una contraseña de aplicación de esa cuenta, no su contraseña normal. Si usas otro proveedor, sustituye host, puerto, cifrado y credenciales por los valores de ese proveedor.
- No configures `KION_DEV_MODE=true` en el hosting. El selector público de demostración está limitado en el servidor a las tres cuentas demo configuradas.
- `.env.example` contiene valores de ejemplo; no es la configuración activa. PHP carga `.env` desde la raíz.

### Proteger `.env`

Si existe un `.htaccess` en la raíz, agrega estas reglas sin borrar las que ya tenga. Si no existe, crea uno:

```apache
<FilesMatch "^\.env">
    Require all denied
</FilesMatch>
```

Después prueba `https://petko.site.je/.env` en el navegador. Debe responder **403** o **404**, nunca mostrar el contenido. Si no puedes garantizar que el archivo no sea descargable, no guardes secretos en una carpeta pública: consulta al hosting cómo definir variables de entorno fuera del directorio público.

## 4. Configurar Google Sign-In

El inicio de sesión usa **Google Identity Services**: el navegador envía un `credential` a `google-callback.php`, y el servidor lo verifica con `GOOGLE_CLIENT_ID`.

1. En [Google Cloud Console — Credenciales](https://console.cloud.google.com/apis/credentials), abre o crea un cliente OAuth de tipo **Aplicación web**.
2. En **Orígenes de JavaScript autorizados**, agrega:

   ```text
   https://petko.site.je
   http://localhost
   ```

   Agrega `https://www.petko.site.je` solamente si también vas a servir el sitio desde `www`. Son orígenes, no rutas: no incluyas `/inicioSesion.php`.
3. Copia el ID del cliente (termina en `.apps.googleusercontent.com`) en `GOOGLE_CLIENT_ID` dentro de `.env`.
4. En `inicioSesion.php`, el JavaScript del botón también declara un ID de cliente directamente. Asegúrate de que ese valor sea idéntico al de `.env`; reemplaza el ID de esa línea si estás usando otro cliente OAuth.
5. No necesitas configurar un **URI de redirección** para este flujo. No pongas `GOOGLE_CLIENT_SECRET` en el proyecto ni en el navegador: el servidor solo necesita el ID público del cliente.
6. Si la pantalla de consentimiento sigue en modo de prueba, agrega como usuario de prueba la cuenta de Google con la que vas a validar el login.

El formulario actual envía la credencial a `google-callback.php`. El archivo `login-google.php` es otro endpoint y no es el que invoca ese formulario.

## 5. Dominio, rutas y correo

- Usa siempre `https://petko.site.je` para probar el sitio. No reemplaces todas las apariciones de `localhost` del código: se usan para detectar el entorno local y habilitar funciones de desarrollo; en el dominio público no deben activarse.
- El callback principal construye sus enlaces usando el dominio de la solicitud. Confirma que el navegador y el hosting estén usando HTTPS para que las sesiones y enlaces trabajen con el dominio seguro.
- Los correos de bienvenida de Google, activación de personal y recuperación de contraseña usan SMTP (`KION_SMTP_*`). Configura y prueba SMTP desde el hosting para confirmar que los tres flujos puedan entregar mensajes.
- No publiques `probar-correo.php` como herramienta web: está limitado a ejecución por consola. Si tienes acceso a CLI, puede usarse para probar SMTP con `KION_TEST_EMAIL` configurado o indicando un destinatario al ejecutar el script.

## 6. Pruebas después de subir

- [ ] `https://petko.site.je` y `https://petko.site.je/inicioSesion.php` cargan sin errores PHP.
- [ ] La URL `/.env` no revela la configuración.
- [ ] El catálogo y las páginas que consultan MySQL cargan; si no, revisa los datos de conexión y los registros de errores PHP del hosting.
- [ ] No aparece el acceso de demostración en la página de inicio de sesión.
- [ ] Google Sign-In funciona con un usuario de prueba y la cuenta aparece en la base de datos con el rol `Usuario`.
- [ ] Los correos SMTP de bienvenida/activación y la recuperación de contraseña se prueban por separado.
- [ ] Las páginas móviles abren con las rutas que conservan las mayúsculas de `versionMobil`.
