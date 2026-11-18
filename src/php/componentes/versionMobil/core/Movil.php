<?php
declare(strict_types=1);

/**
 * KION · Motor de la versión móvil.
 *
 * No modifica ningún archivo original: ejecuta el original tal cual (misma lógica PHP,
 * mismas consultas, mismas sesiones) y, al final, inyecta en su HTML la capa móvil
 * (viewport, CSS y JS de "version mobil") y reescribe los enlaces/redirecciones que
 * apuntan a otros módulos para que el usuario siga dentro de la versión móvil.
 */
final class KionMovil
{
    /** módulo => archivo original */
    public const MODULOS = [
        'home'              => 'home.php',
        'catalogo'          => 'catalogo.php',
        'dashboard'         => 'dashboard.php',
        'dashboard_gerente' => 'dashboard_gerente.php',
        'dashboard_usuario' => 'dashboard_usuario.php',
    ];

    /** módulo => hoja de estilo específica */
    private const TIPOS = [
        'home'              => 'home',
        'catalogo'          => 'catalogo',
        'dashboard'         => 'dash',
        'dashboard_gerente' => 'dash',
        'dashboard_usuario' => 'usuario',
    ];

    private static array $ctx = [];
    private static ?array $cfg = null;

    /* ------------------------------------------------------------------ */
    /*  Configuración y localización de archivos                           */
    /* ------------------------------------------------------------------ */
    public static function config(): array
    {
        if (self::$cfg === null) {
            $c = require dirname(__DIR__) . '/config/kion_movil.php';
            self::$cfg = is_array($c) ? $c : [];
        }
        return self::$cfg;
    }

    public static function dirMovil(): string
    {
        return str_replace('\\', '/', (string) realpath(dirname(__DIR__)));
    }

    /** Busca el archivo original: configuración -> carpetas habituales -> búsqueda acotada. */
    public static function localizar(string $archivo): ?string
    {
        $rutaNormalizada = str_replace('\\', '/', $archivo);
        $nombreArchivo = basename($rutaNormalizada);
        if ($nombreArchivo !== $rutaNormalizada && is_file($archivo)) {
            $real = realpath($archivo);
            return $real === false ? null : str_replace('\\', '/', $real);
        }

        $cfg   = self::config();
        $clave = pathinfo($nombreArchivo, PATHINFO_FILENAME);
        $ruta  = $cfg['rutas'][$clave] ?? null;
        if (is_string($ruta) && is_file($ruta)) {
            return str_replace('\\', '/', (string) realpath($ruta));
        }

        $movil = self::dirMovil();
        $dir   = dirname($movil);
        $subs  = ['', 'modulos/home/', 'componentes/', 'modulos/', 'app/modulos/home/', 'app/componentes/'];
        $max   = max(1, (int) ($cfg['niveles_busqueda'] ?? 5));

        for ($i = 0, $d = $dir; $i < $max; $i++) {
            foreach ($subs as $s) {
                $c = $d . '/' . $s . $nombreArchivo;
                if (is_file($c)) {
                    return str_replace('\\', '/', (string) realpath($c));
                }
            }
            if (dirname($d) === $d) {
                break;
            }
            $d = dirname($d);
        }

        // Último recurso: búsqueda recursiva acotada.
        $raiz = $dir;
        for ($i = 0; $i < 2 && dirname($raiz) !== $raiz; $i++) {
            $raiz = dirname($raiz);
            $r = self::buscarRecursivo($raiz, $nombreArchivo, 0, $movil);
            if ($r !== null) {
                return $r;
            }
        }
        return null;
    }

    private static function buscarRecursivo(string $dir, string $archivo, int $nivel, string $movil): ?string
    {
        static $contador = 0;
        if ($nivel > 5 || $contador > 20000) {
            return null;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $it) {
            if ($it === '.' || $it === '..') {
                continue;
            }
            $contador++;
            if ($it === $archivo && is_file($dir . '/' . $it)) {
                return str_replace('\\', '/', (string) realpath($dir . '/' . $it));
            }
        }
        foreach ($items as $it) {
            if ($it === '.' || $it === '..' || in_array(strtolower($it), ['node_modules', 'vendor', '.git', 'version mobil', 'versionmobil'], true)) {
                continue;
            }
            $p = $dir . '/' . $it;
            if (is_dir($p) && str_replace('\\', '/', (string) realpath($p)) !== $movil) {
                $r = self::buscarRecursivo($p, $archivo, $nivel + 1, $movil);
                if ($r !== null) {
                    return $r;
                }
            }
        }
        return null;
    }

    /* ------------------------------------------------------------------ */
    /*  Utilidades de rutas / URL                                           */
    /* ------------------------------------------------------------------ */
    private static function partes(string $ruta): array
    {
        return array_values(array_filter(explode('/', str_replace('\\', '/', $ruta)), 'strlen'));
    }

    private static function igual(string $a, string $b): bool
    {
        return DIRECTORY_SEPARATOR === '\\' ? strcasecmp($a, $b) === 0 : $a === $b;
    }

    public static function relativo(string $desde, string $hasta): string
    {
        $a = self::partes($desde);
        $b = self::partes($hasta);
        $i = 0;
        $n = min(count($a), count($b));
        while ($i < $n && self::igual($a[$i], $b[$i])) {
            $i++;
        }
        return implode('/', array_merge(array_fill(0, count($a) - $i, '..'), array_slice($b, $i)));
    }

    public static function normalizar(string $path): string
    {
        $out = [];
        foreach (explode('/', $path) as $s) {
            if ($s === '' || $s === '.') {
                continue;
            }
            if ($s === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $s;
        }
        return '/' . implode('/', $out);
    }

    public static function codificar(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /* ------------------------------------------------------------------ */
    /*  Arranque                                                            */
    /* ------------------------------------------------------------------ */
    /**
     * @param string $clave módulo (home, catalogo, ...) o "p:<clave>" para páginas genéricas
     * @return string ruta del archivo original que el llamador debe incluir (en ámbito global)
     */
    public static function iniciar(string $clave): string
    {
        $generica = str_starts_with($clave, 'p:');
        $slug     = $generica ? substr($clave, 2) : $clave;
        $archivo  = $generica ? (self::config()['genericas'][$slug] ?? null) : (self::MODULOS[$slug] ?? null);

        if ($archivo === null) {
            http_response_code(404);
            exit('Módulo no disponible en la versión móvil.');
        }
        $origen = self::localizar($archivo);
        if ($origen === null) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            $seccionRuta = $generica ? 'genericas' : 'rutas';
            exit("KION móvil: no se encontró «{$archivo}». Define una ruta válida en versionMobil/config/kion_movil.php (clave '{$seccionRuta}', entrada '{$slug}').");
        }

        $script   = rawurldecode((string) ($_SERVER['SCRIPT_NAME'] ?? '/'));
        $movilUrl = rtrim(str_replace('\\', '/', dirname($script)), '/');
        $origDir  = self::normalizar($movilUrl . '/' . self::relativo(self::dirMovil(), dirname($origen)));
        $origPath = rtrim($origDir, '/') . '/' . basename($origen);

        self::$ctx = [
            'slug'     => $slug,
            'generica' => $generica,
            'tipo'     => $generica ? 'generic' : self::TIPOS[$slug],
            'movilUrl' => $movilUrl,   // /ruta/version mobil (sin codificar)
            'origPath' => $origPath,   // /ruta/modulos/home/dashboard.php (sin codificar)
            'selfPath' => $script,
        ];

        $GLOBALS['KION_MOBILE_RENDER'] = true;
        header_register_callback([self::class, 'cabeceras']);
        ob_start([self::class, 'filtrar']);
        return $origen;
    }

    /* ------------------------------------------------------------------ */
    /*  Redirecciones                                                       */
    /* ------------------------------------------------------------------ */
    private static function urlMovil(string $nombre, string $sufijo = ''): string
    {
        $base = self::codificar(self::$ctx['movilUrl']);
        if (isset(self::MODULOS[$nombre])) {
            return $base . '/' . $nombre . '.php' . $sufijo;
        }
        $extra = ($sufijo !== '' && $sufijo[0] === '?') ? '&' . substr($sufijo, 1) : $sufijo;
        return $base . '/pagina.php?p=' . rawurlencode($nombre) . $extra;
    }

    private static function nombreConocido(string $base): ?string
    {
        if (strcasecmp(pathinfo($base, PATHINFO_EXTENSION), 'php') !== 0) {
            return null;
        }
        $n = pathinfo($base, PATHINFO_FILENAME);
        if (isset(self::MODULOS[$n])) {
            return $n;
        }
        return isset(self::config()['genericas'][$n]) ? $n : null;
    }

    private static function mapearDestino(string $dest): ?string
    {
        $p = parse_url($dest);
        if ($p === false) {
            return null;
        }
        if (isset($p['scheme']) || isset($p['host'])) {
            $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
            $dh   = ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
            if ($dh === '' || strcasecmp($dh, $host) !== 0) {
                return null;
            }
        }
        $path = $p['path'] ?? '';
        if ($path === '') {
            return null;
        }
        $suf = (isset($p['query']) ? '?' . $p['query'] : '') . (isset($p['fragment']) ? '#' . $p['fragment'] : '');
        $abs = $path[0] === '/' ? $path : self::normalizar(dirname(self::$ctx['origPath']) . '/' . $path);

        $nombre = self::nombreConocido(basename($abs));
        if ($nombre !== null) {
            return self::urlMovil($nombre, $suf);
        }
        // Destino relativo a otro recurso: se convierte en ruta absoluta respecto al original.
        return $path[0] === '/' ? null : self::codificar($abs) . $suf;
    }

    /** header_register_callback: se ejecuta justo antes de enviar las cabeceras. */
    public static function cabeceras(): void
    {
        foreach (headers_list() as $h) {
            if (stripos($h, 'Location:') !== 0) {
                continue;
            }
            $dest  = trim(substr($h, 9));
            $nuevo = self::mapearDestino($dest);
            if ($nuevo !== null && $nuevo !== $dest) {
                header('Location: ' . $nuevo, true, http_response_code() ?: 302);
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Inyección en el HTML                                                */
    /* ------------------------------------------------------------------ */
    public static function filtrar(string $b, int $fase = 0): string
    {
        if ($b === '' || stripos($b, '</head>') === false || stripos($b, 'data-km=') !== false) {
            return $b;
        }
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) {
                return $b;
            }
        }

        // Primero se reescriben los enlaces del original; después se inyecta la capa móvil.
        $b = self::reescribirEnlaces($b);

        $c      = self::$ctx;
        $tipo   = $c['tipo'];
        $nonce  = preg_match('/\snonce="([^"]+)"/', $b, $m) ? ' nonce="' . $m[1] . '"' : '';
        $base   = self::codificar($c['movilUrl']);
        $assets = $base . '/assets';
        $dirFs  = dirname(__DIR__) . '/assets';
        $v      = static fn(string $f): string => '?v=' . (int) @filemtime($dirFs . '/' . $f);
        $color  = (string) (self::config()['color_tema'] ?? '#0a1f3d');

        // 1) Viewport propio (con áreas seguras) + <base> para conservar las rutas relativas del original
        $b = preg_replace('/<meta\s+name=["\']viewport["\'][^>]*>\s*/i', '', $b) ?? $b;
        $cabeza = '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
                . '<meta name="theme-color" content="' . htmlspecialchars($color, ENT_QUOTES) . '">'
                . '<meta name="mobile-web-app-capable" content="yes">'
                . '<base href="' . htmlspecialchars(self::codificar($c['origPath']), ENT_QUOTES) . '">';
        $b = preg_replace_callback('/<head(\s[^>]*)?>/i', static fn($m) => $m[0] . $cabeza, $b, 1) ?? $b;

        // 2) CSS y JS móviles (al final del <head> para ganar a los estilos originales)
        $css = '<link rel="stylesheet" href="' . $assets . '/css/base.css' . $v('css/base.css') . '"' . $nonce . '>'
             . '<link rel="stylesheet" href="' . $assets . '/css/' . $tipo . '.css' . $v('css/' . $tipo . '.css') . '"' . $nonce . '>'
             . '<script defer src="' . $assets . '/js/movil.js' . $v('js/movil.js') . '"' . $nonce . '></script>';
        $b = preg_replace_callback('#</head>#i', static fn() => $css . '</head>', $b, 1) ?? $b;

        // 3) Atributos de <html> y <body>
        $esc  = $base . '/entrada.php?m=' . rawurlencode($c['generica'] ? 'home' : $c['slug']) . '&vista=escritorio';
        $html = ' data-km="' . $tipo . '" data-km-mod="' . htmlspecialchars($c['slug'], ENT_QUOTES) . '"'
              . ' data-km-escritorio="' . htmlspecialchars($esc, ENT_QUOTES) . '"'
              . ' data-km-home="' . htmlspecialchars(self::urlMovil('home'), ENT_QUOTES) . '"';
        $b = preg_replace_callback('/<html\b/i', static fn($m) => $m[0] . $html, $b, 1) ?? $b;
        $b = preg_replace_callback('/<body\b([^>]*)>/i', static function ($m) use ($tipo) {
            $cls = 'km-body km-' . $tipo;
            if (preg_match('/\bclass=(["\'])/i', $m[1])) {
                return '<body' . preg_replace('/\bclass=(["\'])/i', 'class=$1' . $cls . ' ', $m[1], 1) . '>';
            }
            return '<body class="' . $cls . '"' . $m[1] . '>';
        }, $b, 1) ?? $b;

        return $b;
    }

    private static function reescribirEnlaces(string $b): string
    {
        $lista = array_unique(array_merge(
            array_keys(self::MODULOS),
            array_keys((array) (self::config()['genericas'] ?? []))
        ));
        usort($lista, static fn($x, $y) => strlen($y) <=> strlen($x));
        $nombres = implode('|', array_map(static fn($s) => preg_quote((string) $s, '/'), $lista));

        $esExterno = static fn(string $pre): bool => (bool) preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $pre);

        // a) href="?categoria=x" / action="?x"  ->  la misma página móvil
        $self  = self::codificar(self::$ctx['selfPath']);
        $esGen = self::$ctx['generica'];
        $slug  = self::$ctx['slug'];
        $b = preg_replace_callback('/\b(href|action)=(["\'])(\?[^"\']*)\2/i',
            static function ($m) use ($self, $esGen, $slug) {
                $q = $m[3];
                if ($esGen) {
                    $q = '?p=' . rawurlencode($slug) . (strlen($q) > 1 ? '&' . substr($q, 1) : '');
                }
                return $m[1] . '=' . $m[2] . $self . $q . $m[2];
            }, $b) ?? $b;

        // b) href/action hacia otros módulos  ->  su versión móvil
        $b = preg_replace_callback(
            '/\b(href|action)=(["\'])([^"\']*?)(?<![\w-])(' . $nombres . ')\.php([?#][^"\']*)?\2/i',
            static function ($m) use ($esExterno) {
                if ($esExterno($m[3])) {
                    return $m[0];
                }
                $n = self::nombreConocido($m[4] . '.php');
                return $n === null ? $m[0] : $m[1] . '=' . $m[2] . self::urlMovil($n, $m[5] ?? '') . $m[2];
            }, $b) ?? $b;

        // c) JS: location = '...', location.assign(...), window.open(...), || '...', "loginUrl":"..."
        $b = preg_replace_callback(
            '/(location(?:\.href)?\s*=\s*|location\.(?:assign|replace)\(\s*|window\.open\(\s*|\|\|\s*|"loginUrl"\s*:\s*)(["\'])([^"\'\\\\\r\n]*?)(?<![\w-])(' . $nombres . ')\.php([?#][^"\'\\\\\r\n]*)?\2/i',
            static function ($m) use ($esExterno) {
                if ($esExterno($m[3])) {
                    return $m[0];
                }
                $n = self::nombreConocido($m[4] . '.php');
                return $n === null ? $m[0] : $m[1] . $m[2] . self::urlMovil($n, $m[5] ?? '') . $m[2];
            }, $b) ?? $b;

        return $b;
    }
}
