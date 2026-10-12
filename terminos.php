<?php
declare(strict_types=1);

$incrustado = isset($_GET['embed']) && $_GET['embed'] === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Términos, condiciones y política de privacidad de PETKO Vet & Agropecuario.">
    <title>PETKO | Términos, condiciones y privacidad</title>
    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --green-primary: #16a34a;
            --green-hover: #15803d;
            --green-glow: rgba(22, 163, 74, .25);
            --text-main: #0f172a;
            --text-muted: #64748b;
        }
        * { box-sizing: border-box; }
        html, body { width: 100%; min-height: 100%; margin: 0; }
        body {
            display: grid;
            min-height: 100vh;
            min-height: 100svh;
            place-items: center;
            padding: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, #f8fafc 0%, #f0fdf4 50%, #e0f2fe 100%);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
        }
        #videoFondo, .velo { position: fixed; inset: 0; width: 100%; height: 100%; }
        #videoFondo { z-index: 0; object-fit: cover; display: none; }
        .velo { z-index: 1; background: transparent; }
        .terms-card {
            position: relative;
            z-index: 2;
            display: flex;
            width: min(720px, 92%);
            max-height: calc(100vh - 40px);
            max-height: calc(100svh - 40px);
            flex-direction: column;
            padding: 28px 32px;
            border: 1px solid rgba(255, 255, 255, .9);
            border-radius: 18px;
            background: rgba(255, 255, 255, .85);
            color: var(--text-main);
            box-shadow: 0 20px 50px rgba(15, 23, 42, .08), 0 2px 6px rgba(15, 23, 42, .04);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        .brand-header { flex: 0 0 auto; margin-bottom: 17px; text-align: center; }
        .brand-logo { margin: 0 0 2px; color: #0f172a; font-family: 'Fredoka', sans-serif; font-size: 28px; font-weight: 700; letter-spacing: 0.04em; line-height: 1; }
        .brand-logo span { color: var(--green-primary); }
        .brand-subtitle { color: var(--text-muted); font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; }
        h2 { margin: 0 0 16px; color: var(--green-hover); font-size: clamp(19px, 3vw, 24px); line-height: 1.3; text-align: center; }
        .terms-content {
            min-height: 0;
            max-height: 60vh;
            overflow-y: auto;
            padding-right: 12px;
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.7;
            scrollbar-color: var(--green-primary) #f1f5f9;
            scrollbar-width: thin;
        }
        .terms-content::-webkit-scrollbar { width: 6px; }
        .terms-content::-webkit-scrollbar-thumb { background: var(--green-primary); border-radius: 10px; }
        .terms-content::-webkit-scrollbar-track { background: #f1f5f9; }
        .terms-content section { margin: 0 0 22px; }
        .terms-content h3 { margin: 0 0 8px; color: var(--green-hover); font-size: 15px; line-height: 1.45; }
        .terms-content p { margin: 0 0 9px; }
        .terms-content ul { margin: 0 0 10px; padding-left: 22px; }
        .terms-content li { margin-bottom: 5px; }
        .terms-footer { display: flex; justify-content: center; flex: 0 0 auto; margin-top: 18px; }
        .terms-button {
            min-height: 42px;
            padding: 10px 22px;
            border: 0;
            border-radius: 8px;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: #fff;
            cursor: pointer;
            font: inherit;
            font-weight: 750;
            text-decoration: none;
        }
        .terms-button:hover { box-shadow: 0 8px 25px rgba(22, 163, 74, .35); filter: brightness(1.04); }
        @media (max-width: 540px) {
            body { padding: 10px; }
            .terms-card { width: 100%; max-height: calc(100vh - 20px); max-height: calc(100svh - 20px); padding: 20px 18px; }
            .terms-content { max-height: 60vh; font-size: 13px; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
    <video id="videoFondo" autoplay muted loop playsinline aria-hidden="true">
        <source src="src/videoEJEMPLO/22.mp4" type="video/mp4">
    </video>
    <div class="velo" aria-hidden="true"></div>

    <main class="terms-card">
        <header class="brand-header">
            <h1 class="brand-logo">PET<span>KO</span></h1>
            <div class="brand-subtitle">Vet &amp; Agropecuario</div>
        </header>
        <h2>Términos, Condiciones y Política de Privacidad</h2>

        <div class="terms-content" tabindex="0" aria-label="Términos, condiciones y política de privacidad">
            <section>
                <h3>1. Aceptación y alcance de los servicios</h3>
                <p>PETKO es una plataforma de gestión y comercio orientada a actividades veterinarias y agropecuarias. Sus funciones pueden incluir un catálogo digital de productos, consulta de información y herramientas de gestión para usuarios, cajeros, gerentes y administradores.</p>
                <p>Al crear una cuenta, marcar la casilla de aceptación o utilizar las funciones de PETKO, la persona usuaria declara que ha leído y acepta estos Términos y la Política de Privacidad. Si no está de acuerdo, debe abstenerse de registrarse o utilizar la plataforma.</p>
            </section>

            <section>
                <h3>2. Cuentas de usuario, roles y autenticación</h3>
                <p>Para registrarse se requiere tener al menos 18 años —o la mayoría de edad aplicable en el lugar de residencia—, proporcionar información completa y verídica y disponer de una dirección de correo electrónico válida. La persona usuaria debe mantener sus datos actualizados.</p>
                <p>Las funciones disponibles dependen del rol asignado por PETKO. Los roles pueden incluir Usuario/Cliente, Cajero, Gerente y Administrador General. Los permisos de cada cuenta no deben transferirse ni utilizarse para acceder a funciones ajenas a sus responsabilidades.</p>
                <p>Si se ofrece inicio de sesión con Google Identity, PETKO recibe de Google los datos de perfil necesarios para identificar y crear la cuenta, que pueden incluir nombre, apellidos y correo electrónico, según los permisos otorgados. La autenticación de Google se rige también por las condiciones y la política de privacidad de Google.</p>
                <p>La persona usuaria es responsable de mantener confidenciales sus credenciales, cerrar sesión en dispositivos compartidos y notificar al operador de PETKO si sospecha de un acceso no autorizado.</p>
            </section>

            <section>
                <h3>3. Catálogo y productos agropecuarios y veterinarios</h3>
                <p>El catálogo puede mostrar medicamentos veterinarios, alimentos, productos e insumos agropecuarios. La información publicada tiene fines informativos y de comercialización; no sustituye una evaluación, diagnóstico, receta o indicación profesional.</p>
                <p>La adquisición y utilización de productos sujetos a receta, de uso especializado o que requieran criterio técnico debe realizarse con la supervisión de un profesional veterinario calificado y de acuerdo con las instrucciones del fabricante y la normativa aplicable. La persona usuaria es responsable del uso que dé a productos veterinarios o insumos agrícolas. PETKO no sustituye el consejo profesional ni asume responsabilidad por usos contrarios a las indicaciones o a la ley.</p>
                <p>La disponibilidad, los precios, las descripciones y las existencias del catálogo pueden cambiar y están sujetos a confirmación al momento de la compra. La publicación de un producto no garantiza su disponibilidad ni constituye una oferta irrevocable.</p>
            </section>

            <section>
                <h3>4. Política de privacidad y datos personales</h3>
                <p>Para operar la plataforma, PETKO puede tratar los datos que la persona usuaria proporciona o genera al utilizarla, por ejemplo:</p>
                <ul>
                    <li>Nombre, apellidos y dirección de correo electrónico.</li>
                    <li>Credenciales almacenadas como un hash de contraseña; PETKO no necesita guardar la contraseña en texto legible.</li>
                    <li>Rol asignado y datos de cuenta necesarios para habilitar funciones.</li>
                    <li>Datos técnicos y registros de actividad o accesos, en la medida en que las funciones y la configuración del sistema los recopilen.</li>
                </ul>
                <p>Estos datos se utilizan para crear y administrar cuentas, autenticar a las personas usuarias, proteger la plataforma, prestar sus funciones y atender solicitudes. PETKO no vende datos personales. No los comparte con terceros para sus propios fines sin consentimiento, excepto cuando sea necesario para prestar una función solicitada (por ejemplo, autenticación de Google), exista una obligación legal o resulte necesario para proteger la plataforma y sus usuarios. Cuando corresponda, esos proveedores tratarán los datos conforme a sus propias políticas.</p>
                <p>PETKO aplica medidas razonables para proteger la información y limitar su acceso a quienes lo necesiten para operar el servicio. Ningún sistema puede garantizar seguridad absoluta. Los datos se conservarán durante el tiempo necesario para los fines descritos o para cumplir obligaciones legales.</p>
                <p>La persona usuaria puede solicitar acceso, rectificación, cancelación o eliminación y oponerse al tratamiento, sujeto a la legislación aplicable, las obligaciones de conservación y la verificación de identidad. El ejercicio de estos derechos puede solicitarse al responsable de PETKO mediante el canal oficial de atención informado por la entidad operadora.</p>
            </section>

            <section>
                <h3>5. Cookies y sesiones persistentes («Recordarme»)</h3>
                <p>PETKO utiliza cookies esenciales para mantener la sesión y facilitar medidas de seguridad, incluida la protección de solicitudes mediante tokens CSRF. Estas cookies son necesarias para que ciertas funciones de autenticación operen correctamente.</p>
                <p>Si se selecciona «Recordarme», PETKO puede guardar en el navegador la cookie <code>kion_remember</code> para mantener el acceso hasta por 30 días. La cookie contiene un token aleatorio de acceso; la base de datos conserva su huella SHA-256 para validarlo, no la contraseña. La cookie se configura como HttpOnly y SameSite=Lax, y como Secure cuando la conexión HTTPS está detectada. El usuario puede eliminar cookies desde su navegador y revocar el acceso cerrando sesión; borrar la cookie puede impedir el reconocimiento automático de ese dispositivo.</p>
            </section>

            <section>
                <h3>6. Seguridad, suspensión y conducta de la persona usuaria</h3>
                <p>Está prohibido interferir con el funcionamiento de PETKO, intentar acceder a cuentas o datos ajenos, eludir controles de acceso, automatizar intentos de autenticación abusivos o explotar vulnerabilidades, incluidas técnicas como fuerza bruta, inyección SQL o falsificación de solicitudes (CSRF).</p>
                <p>El operador de PETKO puede limitar, suspender o inhabilitar cuentas inactivas, fraudulentas, comprometidas o utilizadas para vulnerar la seguridad, incumplir estos términos o infringir la ley. Cuando sea razonablemente posible, se informará a la persona afectada y se permitirá solicitar una revisión.</p>
            </section>

            <section>
                <h3>7. Propiedad intelectual y contacto</h3>
                <p>La marca PETKO, sus logotipos, el código fuente, el diseño visual y los contenidos propios de la plataforma están protegidos por los derechos de propiedad intelectual que correspondan. No se autoriza su reproducción o uso fuera de lo permitido por la ley o por autorización expresa de sus titulares.</p>
                <p>Para consultas, soporte o solicitudes relacionadas con datos personales, incluida la atención de derechos ARCO cuando sean aplicables, utiliza el canal oficial de contacto que la entidad operadora de PETKO publique en la plataforma. Para verificar una solicitud, podrán requerirse datos razonables que acrediten la identidad de la persona titular.</p>
                <p>Estos términos deben interpretarse conforme a la legislación aplicable al operador y a la persona usuaria. Las disposiciones imperativas de protección al consumidor y de datos personales prevalecen cuando correspondan.</p>
            </section>
        </div>

        <footer class="terms-footer">
            <?php if ($incrustado): ?>
                <button class="terms-button" id="closeAndAccept" type="button">Cerrar y aceptar</button>
            <?php else: ?>
                <a class="terms-button" href="registro.php">Volver al registro</a>
            <?php endif; ?>
        </footer>
    </main>
    <?php if ($incrustado): ?>
        <script>
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    window.parent.postMessage({ type: 'kion-terms-dismissed' }, window.location.origin);
                }
            });
            document.getElementById('closeAndAccept').addEventListener('click', () => {
                window.parent.postMessage({ type: 'kion-terms-accepted' }, window.location.origin);
            });
        </script>
    <?php endif; ?>
</body>
</html>
