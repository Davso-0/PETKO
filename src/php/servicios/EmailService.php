<?php
declare(strict_types=1);

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

final class EmailService
{
    private static bool $entornoCargado = false;

    public static function enviarActivacion(
        string $destinatario,
        string $nombre,
        string $rol,
        string $sucursal,
        string $urlActivacion
    ): bool {
        $asunto = 'Activa tu cuenta de ' . $rol . ' en PETKO';
        $nombreSeguro = self::escape($nombre);
        $rolSeguro = self::escape($rol);
        $correoSeguro = self::escape($destinatario);
        $sucursalSeguro = self::escape($sucursal);
        $urlSeguro = self::escape($urlActivacion);
        $urlTerminos = self::urlTerminosDesdeActivacion($urlActivacion);
        $urlTerminosSeguro = self::escape($urlTerminos);
        $html = self::plantilla(
            '<p style="margin:0 0 16px;">Hola <strong>' . $nombreSeguro . '</strong>, tu cuenta como <strong>' . $rolSeguro . '</strong> está lista para activarse.</p>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:20px 0;background-color:#F3F6F8;border:1px solid #D9E2EA;">'
            . '<tr><td style="padding:10px;font-weight:bold;color:#5A6B7D;">Correo</td><td style="padding:10px;">' . $correoSeguro . '</td></tr>'
            . '<tr><td style="padding:10px;font-weight:bold;color:#5A6B7D;">Rol</td><td style="padding:10px;">' . $rolSeguro . '</td></tr>'
            . '<tr><td style="padding:10px;font-weight:bold;color:#5A6B7D;">Sucursal</td><td style="padding:10px;">' . $sucursalSeguro . '</td></tr></table>'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;margin:22px 0;background-color:#ECFDF5;border:1px solid #BBF7D0;border-left:4px solid #22C55E;">'
            . '<tr><td style="padding:16px 18px;color:#14532D;font-size:14px;line-height:1.5;"><strong style="display:block;margin-bottom:6px;font-size:16px;">Antes de establecer tu contraseña</strong>Revisa y acepta los Términos, Condiciones y Política de Privacidad para completar la activación de tu cuenta.</td></tr>'
            . '<tr><td style="padding:0 18px 16px;"><a href="' . $urlTerminosSeguro . '" style="display:inline-block;background-color:#FFFFFF;border:1px solid #16A34A;color:#166534;text-decoration:none;padding:11px 16px;border-radius:6px;font-weight:bold;font-size:14px;">Leer Términos y Condiciones</a></td></tr></table>'
            . '<p style="margin:0 0 20px;">Establece tu propia contraseña para activar la cuenta. Este enlace es personal y expira en 24 horas.</p>'
            . '<p style="text-align:center;margin:26px 0;"><a href="' . $urlSeguro . '" style="display:inline-block;background-color:#22C55E;color:#FFFFFF;text-decoration:none;padding:14px 22px;border-radius:6px;font-weight:bold;">Establecer mi contraseña y activar cuenta</a></p>',
            $urlTerminos,
            false
        );
        return self::enviar($destinatario, $nombre, $asunto, $html);
    }

    public static function enviarBienvenidaGoogle(string $destinatario, string $nombre, string $urlSistema): bool
    {
        $html = self::plantilla(
            '<p style="margin:0 0 16px;">Hola <strong>' . self::escape($nombre) . '</strong>, ¡bienvenido/a a PETKO!</p>'
            . '<p style="margin:0 0 20px;">Tu cuenta se creó mediante Google. Ahora puedes consultar el catálogo y gestionar tus operaciones desde un solo lugar.</p>'
            . '<p style="text-align:center;margin:26px 0;"><a href="' . self::escape($urlSistema) . '" style="display:inline-block;background-color:#22C55E;color:#FFFFFF;text-decoration:none;padding:14px 22px;border-radius:6px;font-weight:bold;">Explorar Catálogo / Sistema</a></p>'
        );
        return self::enviar($destinatario, $nombre, '¡Bienvenido/a a PETKO!', $html);
    }

    public static function enviarRecuperacion(string $destinatario, string $nombre, string $urlRecuperacion): bool
    {
        $html = self::plantilla(
            '<p style="margin:0 0 16px;">Hola <strong>' . self::escape($nombre) . '</strong>, recibimos una solicitud para restablecer la contraseña de tu cuenta.</p>'
            . '<p style="margin:0 0 20px;">El enlace es de un solo uso y vence en una hora. Si no solicitaste este cambio, puedes ignorar este mensaje.</p>'
            . '<p style="text-align:center;margin:26px 0;"><a href="' . self::escape($urlRecuperacion) . '" style="display:inline-block;background-color:#22C55E;color:#FFFFFF;text-decoration:none;padding:14px 22px;border-radius:6px;font-weight:bold;">Restablecer contraseña</a></p>',
            null,
            false
        );
        return self::enviar($destinatario, $nombre, 'Restablece tu contraseña - PETKO', $html);
    }

    public static function enviarCodigoVerificacion(string $destinatario, string $nombre, string $codigo): bool
    {
        $codigoSeguro = self::escape($codigo);
        $html = self::plantilla(
            '<p style="margin:0 0 16px;">Hola <strong>' . self::escape($nombre) . '</strong>, usa este código para verificar tu correo y continuar con la creación de tu cuenta:</p>'
            . '<p style="margin:24px 0;text-align:center;font-size:30px;font-weight:bold;letter-spacing:8px;color:#0B2545;">' . $codigoSeguro . '</p>'
            . '<p style="margin:0 0 16px;">El código vence en 10 minutos y solo puede utilizarse una vez. Si no solicitaste una cuenta, ignora este mensaje.</p>',
            null,
            false
        );
        return self::enviar($destinatario, $nombre, 'Verifica tu correo - PETKO', $html);
    }

    public static function urlAplicacion(): string
    {
        self::cargarEntorno();
        $urlConfigurada = self::configuracion('KION_APP_URL');
        if ($urlConfigurada !== null) {
            $partes = parse_url($urlConfigurada);
            if (!is_array($partes)
                || !isset($partes['scheme'], $partes['host'])
                || !in_array(strtolower($partes['scheme']), ['http', 'https'], true)
                || array_key_exists('user', $partes)
                || array_key_exists('pass', $partes)
                || array_key_exists('query', $partes)
                || array_key_exists('fragment', $partes)
                || !preg_match('/\A[a-zA-Z0-9.-]+\z/', $partes['host'])
                || (isset($partes['port']) && ($partes['port'] < 1 || $partes['port'] > 65535))) {
                throw new RuntimeException('KION_APP_URL debe ser una URL base HTTP o HTTPS válida.');
            }
            if (strtolower($partes['scheme']) !== 'https'
                && !in_array(strtolower($partes['host']), ['localhost', '127.0.0.1'], true)) {
                throw new RuntimeException('KION_APP_URL debe usar HTTPS fuera del entorno local.');
            }

            return rtrim($urlConfigurada, '/');
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $esHostLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && preg_match('/\A(?:localhost|127\.0\.0\.1)(?::\d{1,5})?\z/i', (string)$host) === 1;
        if (!$esHostLocal) {
            throw new RuntimeException('Configura KION_APP_URL para generar enlaces de correo fuera del entorno local.');
        }

        $protocolo = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $rutaAplicacion = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        return $protocolo . '://' . $host . $rutaAplicacion;
    }

    public static function enviarPrueba(string $destinatario): bool
    {
        if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('La dirección de prueba no es válida.');
        }

        $enviado = self::enviar(
            $destinatario,
            'Destinatario de prueba',
            'Prueba SMTP - PETKO',
            self::plantilla('<p>Este mensaje confirma que el SMTP de PETKO está configurado correctamente.</p>')
        );

        if (!$enviado) {
            throw new RuntimeException('PHPMailer no pudo enviar el correo de prueba. Revisa el error_log de PHP.');
        }

        return true;
    }

    private static function enviar(string $destinatario, string $nombre, string $asunto, string $html): bool
    {
        $mail = null;
        try {
            self::cargarEntorno();

            $host = self::configuracionRequerida('KION_SMTP_HOST');
            $usuario = self::configuracionRequerida('KION_SMTP_USER');
            $contrasena = self::configuracionRequerida('KION_SMTP_PASSWORD');
            $correoEmisor = self::configuracionRequerida('KION_MAIL_FROM');
            $puerto = self::puertoSMTP();
            $cifrado = self::configuracion('KION_SMTP_ENCRYPTION') ?: PHPMailer::ENCRYPTION_STARTTLS;
            $nombreEmisor = self::configuracion('KION_MAIL_FROM_NAME') ?: 'PETKO Vet & Agropecuario';

            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $usuario;
            $mail->Password = $contrasena;
            $mail->SMTPSecure = $cifrado;
            $mail->Port = $puerto;
            $mail->Timeout = 10;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($correoEmisor, $nombreEmisor);
            $mail->addAddress($destinatario, $nombre);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $html;
            return $mail->send();
        } catch (Throwable $exception) {
            $errorInfo = $mail instanceof PHPMailer ? $mail->ErrorInfo : '';
            error_log(
                'PETKO EmailService: ' . ($errorInfo !== '' ? $errorInfo : $exception->getMessage())
            );
            return false;
        }
    }

    private static function cargarEntorno(): void
    {
        if (self::$entornoCargado) {
            return;
        }

        self::$entornoCargado = true;
        $ruta = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($ruta)) {
            return;
        }

        Dotenv::createMutable(dirname(__DIR__, 3))->safeLoad();
    }

    private static function configuracion(string $nombre): ?string
    {
        $valor = getenv($nombre);
        if ($valor === false || trim($valor) === '') {
            $valor = $_ENV[$nombre] ?? $_SERVER[$nombre] ?? null;
        }
        if ($valor === null || $valor === false || trim((string)$valor) === '') {
            return null;
        }

        return trim((string)$valor);
    }

    private static function configuracionRequerida(string $nombre): string
    {
        $valor = self::configuracion($nombre);
        if ($valor === null) {
            throw new RuntimeException(
                'Falta la variable de entorno ' . $nombre . '. Configúrala en el entorno del servidor o en .env.'
            );
        }

        return $valor;
    }

    private static function puertoSMTP(): int
    {
        $valor = self::configuracion('KION_SMTP_PORT') ?: '587';
        $puerto = filter_var($valor, FILTER_VALIDATE_INT);
        if ($puerto === false || $puerto < 1 || $puerto > 65535) {
            throw new RuntimeException('KION_SMTP_PORT debe ser un número entre 1 y 65535.');
        }

        return $puerto;
    }

    private static function plantilla(string $contenido, ?string $urlTerminos = null, bool $mostrarEnlaceTerminos = true): string
    {
        $urlTerminos = $urlTerminos ?? self::baseUrl() . '/terminos.php';
        return '<!doctype html><html lang="es"><body style="margin:0;background-color:#F3F6F8;color:#142033;font-family:Arial,sans-serif;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;background-color:#F3F6F8;padding:24px 0;"><tr><td align="center">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="border-collapse:collapse;max-width:600px;background-color:#FFFFFF;border-radius:8px;box-shadow:0 2px 10px rgba(7,26,50,.12);overflow:hidden;">'
            . '<tr><td style="padding:22px;background-color:#0B2545;color:#FFFFFF;font-size:26px;font-weight:bold;">PET<span style="color:#22C55E;">KO</span></td></tr>'
            . '<tr><td style="padding:28px 26px;font-size:15px;line-height:1.55;">' . $contenido . '</td></tr>'
            . '<tr><td style="padding:18px 26px;background-color:#071A32;color:#FFFFFF;font-size:12px;line-height:1.5;">Soporte: soporte@vetko.com<br>&copy; ' . date('Y') . ' PETKO. Todos los derechos reservados.' . ($mostrarEnlaceTerminos ? '<br><a href="' . self::escape($urlTerminos) . '" style="color:#22C55E;">Consulta nuestros Términos y Condiciones</a>' : '') . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    private static function urlTerminosDesdeActivacion(string $urlActivacion): string
    {
        $partes = parse_url($urlActivacion);
        if (is_array($partes) && isset($partes['scheme'], $partes['host'], $partes['path'])) {
            $rutaTerminos = preg_replace(
                '~\/activar-cuenta\.php$~',
                '/terminos.php',
                (string)$partes['path']
            );
            if (is_string($rutaTerminos)) {
                $puerto = isset($partes['port']) ? ':' . $partes['port'] : '';
                $parametros = ['origen' => 'activacion'];
                if (isset($partes['query'])) {
                    parse_str($partes['query'], $consulta);
                    if (isset($consulta['token']) && is_string($consulta['token']) && preg_match('/\A[a-f0-9]{64}\z/', $consulta['token'])) {
                        $parametros['token'] = $consulta['token'];
                    }
                }
                return $partes['scheme'] . '://' . $partes['host'] . $puerto . $rutaTerminos . '?' . http_build_query($parametros);
            }
        }

        return self::baseUrl() . '/terminos.php';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
