<?php
declare(strict_types=1);

function petkoProcesarSubidaImagen(?array $file, string $urlTexto = ''): ?string
{
    $urlTexto = trim($urlTexto);
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($file === null || $error === UPLOAD_ERR_NO_FILE) {
        return $urlTexto !== '' ? $urlTexto : null;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $limite = (string)ini_get('upload_max_filesize');
        $mensajes = [
            UPLOAD_ERR_INI_SIZE => 'La imagen supera el límite del servidor (' . $limite . ').',
            UPLOAD_ERR_FORM_SIZE => 'La imagen supera el límite permitido por el formulario.',
            UPLOAD_ERR_PARTIAL => 'La imagen se recibió incompleta. Intenta subirla de nuevo.',
            UPLOAD_ERR_NO_TMP_DIR => 'El servidor no tiene configurada una carpeta temporal para cargas.',
            UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo guardar la imagen.',
            UPLOAD_ERR_EXTENSION => 'Una extensión de PHP detuvo la carga de la imagen.',
        ];
        throw new RuntimeException($mensajes[$error] ?? 'No se pudo subir la imagen.');
    }

    $tmpName = $file['tmp_name'] ?? '';
    if (!is_string($tmpName) || $tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('El archivo de imagen no se recibió correctamente.');
    }
    if ((int)($file['size'] ?? 0) <= 0) {
        throw new RuntimeException('La imagen está vacía o no se pudo leer.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new RuntimeException('No está disponible la validación de imágenes en el servidor.');
    }
    try {
        $mime = finfo_file($finfo, $tmpName);
    } finally {
        finfo_close($finfo);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new RuntimeException('El archivo debe ser una imagen JPG, PNG, WEBP o GIF.');
    }

    $uploadDir = __DIR__ . '/../../img/productos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('No se pudo crear la carpeta de imágenes del catálogo.');
    }
    if (!is_writable($uploadDir)) {
        throw new RuntimeException('La carpeta de imágenes del catálogo no tiene permisos de escritura.');
    }

    $documentRootPath = (string)($_SERVER['DOCUMENT_ROOT'] ?? '');
    $documentRoot = $documentRootPath !== '' ? realpath($documentRootPath) : false;
    $realUploadDir = realpath($uploadDir);
    if ($documentRoot === false || $realUploadDir === false) {
        throw new RuntimeException('No se pudo resolver la ruta pública de las imágenes.');
    }

    $rootPath = rtrim(str_replace('\\', '/', $documentRoot), '/');
    $uploadPath = str_replace('\\', '/', $realUploadDir);
    $rootPrefix = $rootPath . '/';
    $insideDocumentRoot = DIRECTORY_SEPARATOR === '\\'
        ? strncasecmp($uploadPath, $rootPrefix, strlen($rootPrefix)) === 0
        : strncmp($uploadPath, $rootPrefix, strlen($rootPrefix)) === 0;
    if (!$insideDocumentRoot) {
        throw new RuntimeException('La carpeta de imágenes debe estar dentro de la raíz pública del sitio.');
    }

    $nombreArchivo = 'prod_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($tmpName, $uploadDir . DIRECTORY_SEPARATOR . $nombreArchivo)) {
        throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos de la carpeta.');
    }

    $rutaPublica = substr($uploadPath, strlen($rootPath));
    return '/' . trim($rutaPublica, '/') . '/' . rawurlencode($nombreArchivo);
}
