<?php
/**
 * Panel de Administración del CV
 * Dany Josué Mínchez Monzón
 * 
 * Permite administrar todos los datos del CV de forma gráfica e interactiva.
 * Al guardar, actualiza data/cv_data.json, crea un respaldo automático y
 * regenera index.html listo para publicar en GitHub Pages.
 */

require_once __DIR__ . '/generate_cv_html.php';

$dataPath = __DIR__ . '/data/cv_data.json';
$backupDir = __DIR__ . '/data/backups';

if (!file_exists($backupDir)) {
    @mkdir($backupDir, 0777, true);
}

// -------------------------------------------------------------
// MANEJO DE PETICIONES AJAX / API
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? ($_GET['action'] ?? '');

    // 1. Guardar datos y regenerar index.html
    if ($action === 'save_data') {
        $rawJson = $_POST['data'] ?? '';
        if (empty($rawJson)) {
            echo json_encode(['success' => false, 'error' => 'No se recibieron datos para guardar.']);
            exit;
        }

        $decoded = json_decode($rawJson, true);
        if ($decoded === null) {
            echo json_encode(['success' => false, 'error' => 'Formato JSON inválido: ' . json_last_error_msg()]);
            exit;
        }

        // Crear respaldo automático con timestamp
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . "/cv_data_{$timestamp}.json";
        if (file_exists($dataPath)) {
            @copy($dataPath, $backupFile);
        }

        // Guardar JSON formateado
        $prettyJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($dataPath, $prettyJson) === false) {
            echo json_encode(['success' => false, 'error' => 'No se pudo escribir en ' . $dataPath]);
            exit;
        }

        // Regenerar index.html
        try {
            $newHtml = generate_cv_html($decoded);
            file_put_contents(__DIR__ . '/index.html', $newHtml);
        } catch (\Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => 'Datos guardados en JSON, pero falló la regeneración de index.html: ' . $e->getMessage()
            ]);
            exit;
        }

        // Limitar respaldos a los 25 más recientes
        $backups = glob($backupDir . "/cv_data_*.json");
        if ($backups && count($backups) > 25) {
            usort($backups, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            while (count($backups) > 25) {
                @unlink(array_shift($backups));
            }
        }

        echo json_encode([
            'success' => true,
            'message' => '¡Datos guardados e index.html regenerado exitosamente!',
            'backup' => basename($backupFile),
            'timestamp' => date('d/m/Y H:i:s')
        ]);
        exit;
    }

    // 2. Subir nuevo archivo (certificado, diploma o foto)
    if ($action === 'upload_file') {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al recibir el archivo o archivo vacío.']);
            exit;
        }

        $destType = $_POST['dest_type'] ?? 'certi'; // 'certi' o 'photo'
        $targetFolder = ($destType === 'photo') ? (__DIR__ . '/assets/images') : (__DIR__ . '/media/certi');
        if (!file_exists($targetFolder)) {
            @mkdir($targetFolder, 0777, true);
        }

        $origName = basename($_FILES['file']['name']);
        // Sanitizar manteniendo letras con tildes comunes o normalizando
        $sanitized = preg_replace('/[^\w\s\.-]/u', '_', $origName);
        $targetFile = $targetFolder . '/' . $sanitized;

        // Si ya existe y no se indicó sobreescribir, agregar sufijo
        if (file_exists($targetFile)) {
            $pathInfo = pathinfo($sanitized);
            $sanitized = $pathInfo['filename'] . '_' . time() . '.' . ($pathInfo['extension'] ?? '');
            $targetFile = $targetFolder . '/' . $sanitized;
        }

        if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            $relativeUrl = ($destType === 'photo') ? ('assets/images/' . $sanitized) : ('media/certi/' . $sanitized);
            echo json_encode([
                'success' => true,
                'filePath' => $relativeUrl,
                'fileName' => $sanitized,
                'message' => 'Archivo subido correctamente.'
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo mover el archivo subido al directorio destino.']);
        }
        exit;
    }

    // 3. Listar archivos existentes en media/certi/
    if ($action === 'list_cert_files') {
        $files = [];
        $scan = glob(__DIR__ . '/media/certi/*.*');
        if ($scan) {
            foreach ($scan as $f) {
                $files[] = [
                    'name' => basename($f),
                    'path' => 'media/certi/' . basename($f),
                    'size' => round(filesize($f) / 1024, 1) . ' KB'
                ];
            }
        }
        usort($files, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        echo json_encode(['success' => true, 'files' => $files]);
        exit;
    }

    // 4. Listar respaldos existentes
    if ($action === 'list_backups') {
        $backups = [];
        $scan = glob($backupDir . '/cv_data_*.json');
        if ($scan) {
            foreach ($scan as $b) {
                $backups[] = [
                    'filename' => basename($b),
                    'date' => date('d/m/Y H:i:s', filemtime($b)),
                    'size' => round(filesize($b) / 1024, 1) . ' KB'
                ];
            }
            usort($backups, function($a, $b) {
                return strcmp($b['filename'], $a['filename']); // más reciente primero
            });
        }
        echo json_encode(['success' => true, 'backups' => $backups]);
        exit;
    }

    // 5. Restaurar respaldo
    if ($action === 'restore_backup') {
        $filename = basename($_POST['filename'] ?? '');
        $restorePath = $backupDir . '/' . $filename;
        if (!file_exists($restorePath)) {
            echo json_encode(['success' => false, 'error' => 'El archivo de respaldo no existe.']);
            exit;
        }

        $content = file_get_contents($restorePath);
        $decoded = json_decode($content, true);
        if (!$decoded) {
            echo json_encode(['success' => false, 'error' => 'Respaldo corrupto o inválido.']);
            exit;
        }

        // Crear respaldo de seguridad antes de restaurar
        if (file_exists($dataPath)) {
            @copy($dataPath, $backupDir . '/cv_data_pre_restore_' . date('Y-m-d_H-i-s') . '.json');
        }

        file_put_contents($dataPath, $content);
        $newHtml = generate_cv_html($decoded);
        file_put_contents(__DIR__ . '/index.html', $newHtml);

        echo json_encode([
            'success' => true,
            'message' => 'Respaldo restaurado e index.html regenerado exitosamente.',
            'data' => $decoded
        ]);
        exit;
    }

    // 6. Consultar estado de Git
    if ($action === 'git_status') {
        $output = [];
        $code = 0;
        @exec('git status --porcelain 2>&1', $output, $code);
        $branchOut = [];
        @exec('git rev-parse --abbrev-ref HEAD 2>&1', $branchOut);
        $branch = $branchOut[0] ?? 'main';

        echo json_encode([
            'success' => ($code === 0),
            'branch' => $branch,
            'status' => implode("\n", $output),
            'has_changes' => !empty(array_filter($output))
        ]);
        exit;
    }

    // 7. Ejecutar Git commit y push
    if ($action === 'git_commit_push') {
        $msg = trim($_POST['message'] ?? 'Actualización de CV ' . date('d.m.Y H:i'));
        $escapedMsg = escapeshellarg($msg);
        
        $output = [];
        @exec("git add . 2>&1", $output);
        @exec("git commit -m $escapedMsg 2>&1", $output);
        $pushOutput = [];
        $code = 0;
        @exec("git push 2>&1", $pushOutput, $code);

        echo json_encode([
            'success' => ($code === 0),
            'output' => implode("\n", array_merge($output, $pushOutput)),
            'code' => $code
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Acción no reconocida.']);
    exit;
}

// -------------------------------------------------------------
// CARGA DE DATOS PARA LA INTERFAZ
// -------------------------------------------------------------
if (!file_exists($dataPath)) {
    // Si aún no existe cv_data.json, lo generamos a partir de index.html
    @include_once __DIR__ . '/parse_index_to_json.php';
}

$cvData = [];
if (file_exists($dataPath)) {
    $cvData = json_decode(file_get_contents($dataPath), true) ?: [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - CV Dany Josué Mínchez</title>
    <link rel="shortcut icon" href="media/bg/FOTO.webp">
    
    <!-- Google Fonts Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-light: #eff6ff;
            --primary-border: #bfdbfe;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-active: #3b82f6;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #ffffff;
            --bg-page: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --radius-md: 10px;
            --radius-lg: 16px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, .brand-title {
            font-family: 'Poppins', sans-serif;
        }

        /* SIDEBAR */
        .sidebar {
            width: 280px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 4px 0 20px rgba(0,0,0,0.15);
        }

        .sidebar-header {
            padding: 24px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.02);
        }

        .sidebar-header img {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--sidebar-active);
        }

        .sidebar-header-info h2 {
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.3px;
        }

        .sidebar-header-info p {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 500;
        }

        .sidebar-menu {
            list-style: none;
            padding: 16px 12px;
            flex-grow: 1;
        }

        .menu-category {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 700;
            color: #64748b;
            padding: 14px 12px 6px 12px;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
            margin-bottom: 2px;
        }

        .sidebar-menu li a:hover {
            background-color: var(--sidebar-hover);
            color: #fff;
        }

        .sidebar-menu li a.active {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(37,99,235,0.35);
        }

        .sidebar-menu li a i {
            width: 20px;
            text-align: center;
            font-size: 15px;
        }

        .sidebar-footer {
            padding: 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
            font-size: 11px;
            color: #64748b;
            text-align: center;
            background: rgba(0,0,0,0.15);
        }

        /* MAIN CONTENT */
        .main-wrapper {
            margin-left: 280px;
            flex-grow: 1;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            width: calc(100% - 280px);
        }

        /* TOPBAR */
        .topbar {
            height: 70px;
            background-color: #fff;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .topbar-title {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .topbar-title h1 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background-color: #dcfce7;
            color: #166534;
            transition: all 0.3s ease;
        }

        .status-badge.unsaved {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-badge .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: currentColor;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 18px;
            font-size: 13.5px;
            font-weight: 600;
            border-radius: 8px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            line-height: 1.2;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #fff;
            box-shadow: 0 2px 8px rgba(37,99,235,0.25);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: #fff;
            border-color: var(--border-color);
            color: var(--text-main);
        }

        .btn-outline:hover {
            background-color: #f1f5f9;
            border-color: #cbd5e1;
        }

        .btn-dark {
            background-color: #1e293b;
            color: #fff;
        }

        .btn-dark:hover {
            background-color: #0f172a;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        .btn-danger {
            background-color: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .btn-danger:hover {
            background-color: #dc2626;
            color: #fff;
        }

        /* PAGE BODY */
        .content-body {
            padding: 32px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .admin-section {
            display: none;
            animation: fadeIn 0.25s ease-in-out;
        }

        .admin-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-header {
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-header-info h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: -0.5px;
        }

        .section-header-info p {
            font-size: 14px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
            transition: all 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-title {
            font-size: 17px;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* FORM ELEMENTS */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-control {
            width: 100%;
            padding: 10px 14px;
            font-size: 14px;
            border: 1.5px solid var(--border-color);
            border-radius: 8px;
            background-color: #fff;
            color: var(--text-main);
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12);
        }

        textarea.form-control {
            min-height: 110px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-help {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* REORDERABLE ITEM CARDS */
        .item-card {
            background-color: #fff;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 18px 20px;
            margin-bottom: 14px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: all 0.2s ease;
            position: relative;
        }

        .item-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }

        .item-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .item-badge-title {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-grow: 1;
        }

        .drag-handle {
            color: #94a3b8;
            cursor: grab;
            font-size: 14px;
        }

        .item-card-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* TAG PILLS */
        .tags-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            padding: 12px;
            background-color: #f8fafc;
            border: 1px dashed var(--border-color);
            border-radius: 8px;
        }

        .tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #e2e8f0;
            color: #1e293b;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 500;
        }

        .tag-pill .remove-tag {
            cursor: pointer;
            color: #64748b;
            font-size: 14px;
        }

        .tag-pill .remove-tag:hover {
            color: #ef4444;
        }

        /* SEARCH BAR */
        .search-box {
            position: relative;
            max-width: 380px;
            width: 100%;
        }

        .search-box input {
            padding-left: 38px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        /* MODALS */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: all 0.25s ease;
        }

        .modal-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-box {
            background-color: #fff;
            border-radius: var(--radius-lg);
            width: 90%;
            max-width: 600px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: scale(0.95);
            transition: all 0.25s ease;
        }

        .modal-overlay.open .modal-box {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 18px;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
        }

        .modal-close:hover {
            color: var(--text-main);
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background-color: #f8fafc;
        }

        /* TOAST */
        .toast-container {
            position: fixed;
            bottom: 28px;
            right: 28px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            min-width: 300px;
            max-width: 420px;
            padding: 14px 18px;
            border-radius: 10px;
            background-color: #1e293b;
            color: #fff;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13.5px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        .toast.success { border-left: 4px solid var(--success); }
        .toast.error { border-left: 4px solid var(--danger); }
        .toast.warning { border-left: 4px solid var(--warning); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* STATS OVERVIEW CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: var(--shadow-sm);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-icon.blue { background-color: #eff6ff; color: #2563eb; }
        .stat-icon.green { background-color: #ecfdf5; color: #10b981; }
        .stat-icon.purple { background-color: #faf5ff; color: #a855f7; }
        .stat-icon.amber { background-color: #fffbeb; color: #f59e0b; }

        .stat-info .num {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            font-family: 'Outfit', sans-serif;
        }

        .stat-info .label {
            font-size: 12.5px;
            color: var(--text-muted);
            font-weight: 500;
        }

        /* SLIDER ROW */
        .slider-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .slider-row input[type="range"] {
            flex-grow: 1;
            accent-color: var(--primary);
        }

        .slider-val {
            min-width: 45px;
            text-align: right;
            font-weight: 700;
            color: var(--primary);
            font-size: 13.5px;
        }

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-wrapper {
                margin-left: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="<?= htmlspecialchars($cvData['personal_info']['photo'] ?? 'assets/images/FOTO.jpg') ?>" id="sidebarAvatar" alt="Avatar">
            <div class="sidebar-header-info">
                <h2><?= htmlspecialchars($cvData['personal_info']['name'] ?? 'Dany Josué Mínchez') ?></h2>
                <p>Administrador de CV</p>
            </div>
        </div>

        <ul class="sidebar-menu">
            <div class="menu-category">General</div>
            <li><a href="#dashboard" class="active" data-section="dashboard"><i class="fa-solid fa-chart-pie"></i> Resumen General</a></li>
            <li><a href="#personal" data-section="personal"><i class="fa-solid fa-id-card"></i> Datos Personales</a></li>
            <li><a href="#perfil" data-section="perfil"><i class="fa-solid fa-user-pen"></i> Perfil Profesional</a></li>

            <div class="menu-category">Trayectoria</div>
            <li><a href="#experiencia" data-section="experiencia"><i class="fa-solid fa-briefcase"></i> Experiencia Laboral</a></li>
            <li><a href="#educacion" data-section="educacion"><i class="fa-solid fa-graduation-cap"></i> Educación</a></li>
            <li><a href="#referencias" data-section="referencias"><i class="fa-solid fa-address-book"></i> Referencias</a></li>

            <div class="menu-category">Habilidades & Contacto</div>
            <li><a href="#habilidades" data-section="habilidades"><i class="fa-solid fa-code"></i> Habilidades Técnicas</a></li>
            <li><a href="#contacto" data-section="contacto"><i class="fa-solid fa-phone"></i> Contacto & Idiomas</a></li>

            <div class="menu-category">Acreditaciones</div>
            <li><a href="#certificados" data-section="certificados"><i class="fa-solid fa-certificate"></i> Certificados</a></li>
            <li><a href="#diplomados" data-section="diplomados"><i class="fa-solid fa-award"></i> Diplomados</a></li>
            <li><a href="#diplomas" data-section="diplomas"><i class="fa-solid fa-file-lines"></i> Diplomas</a></li>
            <li><a href="#talleres" data-section="talleres"><i class="fa-solid fa-screwdriver-wrench"></i> Talleres</a></li>
            <li><a href="#titulos" data-section="titulos"><i class="fa-solid fa-scroll"></i> Títulos Universitarios</a></li>

            <div class="menu-category">Sistema</div>
            <li><a href="#backups" data-section="backups"><i class="fa-solid fa-clock-rotate-left"></i> Respaldos & Git</a></li>
        </ul>

        <div class="sidebar-footer">
            CV Manager v2.0 • Local XAMPP
        </div>
    </aside>

    <!-- MAIN CONTAINER -->
    <div class="main-wrapper">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-title">
                <button class="btn btn-outline btn-sm d-lg-none" id="btnToggleSidebar" style="display:none;"><i class="fa-solid fa-bars"></i></button>
                <h1 id="currentSectionTitle">Resumen General</h1>
                <div class="status-badge" id="saveStatusBadge">
                    <span class="dot"></span>
                    <span id="saveStatusText">Todo guardado</span>
                </div>
            </div>

            <div class="topbar-actions">
                <a href="index.html" target="_blank" class="btn btn-outline" title="Ver cómo se ve el CV en vivo">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Previsualizar CV
                </a>
                <button class="btn btn-success" id="btnSaveAll" title="Guardar cambios y regenerar index.html (Ctrl + S)">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios
                </button>
            </div>
        </header>

        <!-- CONTENT BODY -->
        <main class="content-body">

            <!-- 1. DASHBOARD -->
            <section class="admin-section active" id="sec-dashboard">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Panel de Control del CV</h2>
                        <p>Gestiona cada sección de tu CV de manera visual y sin tocar código HTML.</p>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="openGitModal()"><i class="fa-brands fa-github"></i> Publicar a GitHub</button>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-briefcase"></i></div>
                        <div class="stat-info">
                            <div class="num" id="statExp">0</div>
                            <div class="label">Puestos Laborales</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon green"><i class="fa-solid fa-graduation-cap"></i></div>
                        <div class="stat-info">
                            <div class="num" id="statEdu">0</div>
                            <div class="label">Títulos y Grados</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon purple"><i class="fa-solid fa-certificate"></i></div>
                        <div class="stat-info">
                            <div class="num" id="statCert">0</div>
                            <div class="label">Certificados & Diplomas</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon amber"><i class="fa-solid fa-code"></i></div>
                        <div class="stat-info">
                            <div class="num" id="statSkills">0</div>
                            <div class="label">Habilidades Registradas</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-rocket"></i> Flujo de Trabajo Rápido</div>
                    </div>
                    <div style="font-size: 14px; line-height: 1.7; color: #475569;">
                        <ol style="padding-left: 20px; display: flex; flex-direction: column; gap: 8px;">
                            <li><strong>Edita tus datos:</strong> Usa el menú lateral para modificar cualquier dato (perfil, nuevos empleos, certificados, habilidades).</li>
                            <li><strong>Sube archivos con 1 clic:</strong> Puedes subir nuevos PDFs o imágenes a <code>media/certi/</code> directamente desde los formularios.</li>
                            <li><strong>Guarda los cambios:</strong> Presiona el botón verde <strong>"Guardar Cambios"</strong> (o presiona <code>Ctrl + S</code>). El sistema guarda en JSON y <strong>regenera automáticamente el archivo <code>index.html</code></strong>.</li>
                            <li><strong>Publica en GitHub Pages:</strong> Haz clic en el botón <strong>"Publicar a GitHub"</strong> para enviar los cambios a tu repositorio con un clic.</li>
                        </ol>
                    </div>
                </div>
            </section>

            <!-- 2. DATOS PERSONALES -->
            <section class="admin-section" id="sec-personal">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Información Personal & Encabezado</h2>
                        <p>Datos principales que aparecen en el encabezado del CV.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-user"></i> Datos Básicos</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group full-width">
                            <label>Nombre Completo</label>
                            <input type="text" class="form-control" id="p_name">
                        </div>

                        <div class="form-group">
                            <label>Título Profesional 1</label>
                            <input type="text" class="form-control" id="p_title1">
                        </div>

                        <div class="form-group">
                            <label>Título Profesional 2</label>
                            <input type="text" class="form-control" id="p_title2">
                        </div>

                        <div class="form-group">
                            <label>Correos Electrónicos</label>
                            <input type="text" class="form-control" id="p_emails">
                        </div>

                        <div class="form-group">
                            <label>Teléfono Principal / Celular</label>
                            <input type="text" class="form-control" id="p_phone">
                        </div>

                        <div class="form-group">
                            <label>Ruta de la Foto de Perfil</label>
                            <div style="display: flex; gap: 8px;">
                                <input type="text" class="form-control" id="p_photo">
                                <button class="btn btn-outline btn-sm" onclick="triggerPhotoUpload()"><i class="fa-solid fa-upload"></i> Subir Foto</button>
                                <input type="file" id="photoFileInput" accept="image/*" style="display:none;" onchange="handlePhotoUploaded(event)">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>URL de GitHub / CV Online</label>
                            <input type="text" class="form-control" id="p_git_url">
                        </div>

                        <div class="form-group">
                            <label>Texto Enlace GitHub</label>
                            <input type="text" class="form-control" id="p_git_label">
                        </div>
                    </div>
                </div>
            </section>

            <!-- 3. PERFIL PROFESIONAL -->
            <section class="admin-section" id="sec-perfil">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Perfil Profesional</h2>
                        <p>El resumen ejecutivo y trayectoria que se muestra en la sección "Perfil".</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-align-left"></i> Resumen de Perfil</div>
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" id="p_profile" style="min-height: 240px; font-size: 15px;"></textarea>
                        <div class="form-help">Escribe o ajusta la descripción de tu perfil profesional, trayectoria y metas.</div>
                    </div>
                </div>
            </section>

            <!-- 4. EXPERIENCIA LABORAL -->
            <section class="admin-section" id="sec-experiencia">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Experiencia Laboral</h2>
                        <p>Administra los puestos, empresas, funciones y tecnologías de tu historial laboral.</p>
                    </div>
                    <button class="btn btn-primary" onclick="addExperienceItem()"><i class="fa-solid fa-plus"></i> Agregar Empleo</button>
                </div>

                <div id="experienceList"></div>
            </section>

            <!-- 5. EDUCACIÓN -->
            <section class="admin-section" id="sec-educacion">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Educación</h2>
                        <p>Títulos, maestrías, licenciaturas, diplomados y educación académica.</p>
                    </div>
                    <button class="btn btn-primary" onclick="addEducationItem()"><i class="fa-solid fa-plus"></i> Agregar Educación</button>
                </div>

                <div id="educationList"></div>
            </section>

            <!-- 6. REFERENCIAS PERSONALES -->
            <section class="admin-section" id="sec-referencias">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Referencias Personales</h2>
                        <p>Contactos profesionales y personas de referencia.</p>
                    </div>
                    <button class="btn btn-primary" onclick="addReferenceItem()"><i class="fa-solid fa-plus"></i> Agregar Referencia</button>
                </div>

                <div id="referencesList"></div>
            </section>

            <!-- 7. HABILIDADES TÉCNICAS -->
            <section class="admin-section" id="sec-habilidades">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Habilidades & Herramientas</h2>
                        <p>Ajusta el porcentaje de dominio de cada habilidad o agrega nuevas herramientas.</p>
                    </div>
                    <button class="btn btn-primary" onclick="addSkillCategory()"><i class="fa-solid fa-folder-plus"></i> Nueva Categoría</button>
                </div>

                <div id="skillsCategoriesList"></div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-tags"></i> Otras Habilidades y Competencias (Badges)</div>
                    </div>
                    <div class="form-group">
                        <label>Agregar nueva habilidad:</label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" class="form-control" id="newOtherSkillInput" placeholder="Ej: DevOps, Kubernetes, etc." onkeydown="if(event.key==='Enter'){event.preventDefault();addOtherSkill();}">
                            <button class="btn btn-outline" onclick="addOtherSkill()"><i class="fa-solid fa-plus"></i> Agregar</button>
                        </div>
                    </div>
                    <div class="tags-container" id="othersSkillsTags"></div>
                </div>
            </section>

            <!-- 8. CONTACTO & IDIOMAS -->
            <section class="admin-section" id="sec-contacto">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Contacto Personal, Idiomas e Intereses</h2>
                        <p>Ubicación física, teléfonos secundarios, lenguajes hablados y aficiones.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-location-dot"></i> Contacto Personal</div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Teléfonos</label>
                            <input type="text" class="form-control" id="c_phone">
                        </div>
                        <div class="form-group">
                            <label>Nota del Teléfono (Ej: WhatsApp)</label>
                            <input type="text" class="form-control" id="c_phone_note">
                        </div>
                        <div class="form-group full-width">
                            <label>Dirección</label>
                            <input type="text" class="form-control" id="c_address">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-language"></i> Idiomas</div>
                        <button class="btn btn-outline btn-sm" onclick="addLanguageItem()"><i class="fa-solid fa-plus"></i> Agregar Idioma</button>
                    </div>
                    <div id="languagesList"></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-heart"></i> Intereses</div>
                    </div>
                    <div class="form-group">
                        <div style="display: flex; gap: 8px;">
                            <input type="text" class="form-control" id="newInterestInput" placeholder="Ej: Inteligencia Artificial, Ciclismo..." onkeydown="if(event.key==='Enter'){event.preventDefault();addInterest();}">
                            <button class="btn btn-outline" onclick="addInterest()"><i class="fa-solid fa-plus"></i> Agregar</button>
                        </div>
                    </div>
                    <div class="tags-container" id="interestsTags"></div>
                </div>
            </section>

            <!-- 9. CERTIFICADOS -->
            <section class="admin-section" id="sec-certificados">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Certificados</h2>
                        <p>Certificaciones profesionales emitidas por instituciones.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" class="form-control" placeholder="Buscar certificado..." oninput="filterCertList('certificates', this.value)">
                        </div>
                        <button class="btn btn-primary" onclick="openAddDocModal('certificates', 'Certificado')"><i class="fa-solid fa-plus"></i> Nuevo Certificado</button>
                    </div>
                </div>
                <div id="certificatesList"></div>
            </section>

            <!-- 10. DIPLOMADOS -->
            <section class="admin-section" id="sec-diplomados">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Diplomados</h2>
                        <p>Diplomados de posgrado y formación ejecutiva.</p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddDocModal('diplomados', 'Diplomado')"><i class="fa-solid fa-plus"></i> Nuevo Diplomado</button>
                </div>
                <div id="diplomadosList"></div>
            </section>

            <!-- 11. DIPLOMAS -->
            <section class="admin-section" id="sec-diplomas">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Diplomas</h2>
                        <p>Diplomas de cursos, charlas, conferencias y seminarios.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" class="form-control" placeholder="Buscar diploma..." oninput="filterCertList('diplomas', this.value)">
                        </div>
                        <button class="btn btn-primary" onclick="openAddDocModal('diplomas', 'Diploma')"><i class="fa-solid fa-plus"></i> Nuevo Diploma</button>
                    </div>
                </div>
                <div id="diplomasList"></div>
            </section>

            <!-- 12. TALLERES -->
            <section class="admin-section" id="sec-talleres">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Talleres</h2>
                        <p>Talleres prácticos, técnicos y de capacitación continua.</p>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <div class="search-box">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" class="form-control" placeholder="Buscar taller..." oninput="filterCertList('workshops', this.value)">
                        </div>
                        <button class="btn btn-primary" onclick="openAddDocModal('workshops', 'Taller')"><i class="fa-solid fa-plus"></i> Nuevo Taller</button>
                    </div>
                </div>
                <div id="workshopsList"></div>
            </section>

            <!-- 13. TÍTULOS Y RECONOCIMIENTOS -->
            <section class="admin-section" id="sec-titulos">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Títulos Universitarios & Reconocimientos</h2>
                        <p>Títulos profesionales oficiales y reconocimientos especiales.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-trophy"></i> Reconocimientos</div>
                        <button class="btn btn-outline btn-sm" onclick="addRecognitionItem()"><i class="fa-solid fa-plus"></i> Agregar</button>
                    </div>
                    <div id="recognitionsList"></div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-scroll"></i> Títulos Universitarios</div>
                        <button class="btn btn-outline btn-sm" onclick="openAddDocModal('university_titles', 'Título')"><i class="fa-solid fa-plus"></i> Agregar Título</button>
                    </div>
                    <div id="universityTitlesList"></div>
                </div>
            </section>

            <!-- 14. RESPALDOS & GIT -->
            <section class="admin-section" id="sec-backups">
                <div class="section-header">
                    <div class="section-header-info">
                        <h2>Respaldos del Sistema & Control de Versiones</h2>
                        <p>Historial de versiones guardadas, exportación / importación JSON y sincronización con GitHub.</p>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-download"></i> Exportar e Importar Datos</div>
                    </div>
                    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
                        Puedes descargar todo tu CV en un solo archivo JSON para guardarlo donde quieras o transferirlo a otra máquina.
                    </p>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="btn btn-outline" onclick="downloadJsonBackup()"><i class="fa-solid fa-file-arrow-down"></i> Descargar Respaldo JSON</button>
                        <button class="btn btn-outline" onclick="triggerImportJson()"><i class="fa-solid fa-file-arrow-up"></i> Cargar Respaldo JSON</button>
                        <input type="file" id="importJsonInput" accept=".json" style="display:none;" onchange="handleImportJson(event)">
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Respaldos Automáticos Recientes</div>
                        <button class="btn btn-outline btn-sm" onclick="loadBackupsList()"><i class="fa-solid fa-arrows-rotate"></i> Actualizar</button>
                    </div>
                    <div id="backupsListTable">
                        <p style="color: var(--text-muted); font-size: 14px;">Cargando lista de respaldos...</p>
                    </div>
                </div>
            </section>

        </main>
    </div>

    <!-- MODAL: AGREGAR / EDITAR DOCUMENTO (CERTIFICADO, DIPLOMA, TALLER) -->
    <div class="modal-overlay" id="docModal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 id="docModalTitle">Agregar Documento</h3>
                <button class="modal-close" onclick="closeDocModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="docModalTargetArray">
                <input type="hidden" id="docModalEditIndex" value="-1">

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Título o Nombre del Documento *</label>
                    <input type="text" class="form-control" id="docModalInputTitle" placeholder="Ej: Continuing Education Certificate in Digital Project Management">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Institución Emisora y Año</label>
                    <input type="text" class="form-control" id="docModalInputDetail" placeholder="Ej: MIU City University Miami 2025">
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Archivo Asociado (PDF o Imagen en media/certi/)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" class="form-control" id="docModalInputFile" placeholder="media/certi/NombreArchivo.pdf">
                        <button class="btn btn-outline btn-sm" type="button" onclick="openFilePicker()"><i class="fa-solid fa-folder-open"></i> Elegir</button>
                        <button class="btn btn-primary btn-sm" type="button" onclick="triggerDocUpload()"><i class="fa-solid fa-upload"></i> Subir</button>
                    </div>
                    <input type="file" id="docModalFileInput" accept=".pdf,.png,.jpg,.jpeg,.webp" style="display:none;" onchange="handleDocUploaded(event)">
                    <div class="form-help">Puedes escribir la ruta, seleccionarlo de los archivos existentes o subir uno nuevo directamente.</div>
                </div>

                <div class="form-group">
                    <label>Descripción / Observación (Opcional)</label>
                    <input type="text" class="form-control" id="docModalInputDesc" placeholder="Detalle adicional si aplica">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeDocModal()">Cancelar</button>
                <button class="btn btn-success" onclick="saveDocModalItem()"><i class="fa-solid fa-check"></i> Guardar en Lista</button>
            </div>
        </div>
    </div>

    <!-- MODAL: SELECTOR DE ARCHIVOS EN MEDIA/CERTI -->
    <div class="modal-overlay" id="filePickerModal">
        <div class="modal-box" style="max-width: 650px;">
            <div class="modal-header">
                <h3>Elegir Archivo Existente (media/certi/)</h3>
                <button class="modal-close" onclick="closeFilePicker()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <div class="search-box" style="margin-bottom: 16px; max-width: 100%;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" class="form-control" id="filePickerSearch" placeholder="Filtrar archivos..." oninput="filterFilePicker(this.value)">
                </div>
                <div id="filePickerList" style="max-height: 380px; overflow-y: auto; display: flex; flex-direction: column; gap: 6px;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeFilePicker()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- MODAL: GITHUB DEPLOY -->
    <div class="modal-overlay" id="gitModal">
        <div class="modal-box" style="max-width: 600px;">
            <div class="modal-header">
                <h3><i class="fa-brands fa-github"></i> Publicar a GitHub Pages</h3>
                <button class="modal-close" onclick="closeGitModal()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 16px;">
                    Al publicar, tus cambios se enviarán al repositorio GitHub para actualizar la página en vivo 
                    <a href="https://dminchez.github.io/cv_dminchez" target="_blank" style="color: var(--primary);">dminchez.github.io/cv_dminchez</a>.
                </p>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Mensaje del Commit</label>
                    <input type="text" class="form-control" id="gitCommitMsg" value="Actualización CV <?= date('d.m.Y H:i') ?>">
                </div>

                <div id="gitStatusBox" style="background-color: #0f172a; color: #f8fafc; font-family: monospace; font-size: 12.5px; padding: 14px; border-radius: 8px; max-height: 180px; overflow-y: auto; margin-bottom: 16px; white-space: pre-wrap;">
Consultando estado de Git...
                </div>

                <div style="background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 12px; font-size: 13px; color: #1e40af;">
                    <strong>Comandos que puedes ejecutar manualmente si lo prefieres:</strong>
                    <pre style="margin-top: 6px; font-family: monospace; background: rgba(255,255,255,0.7); padding: 8px; border-radius: 4px;">git add .
git commit -m "Actualización CV"
git push</pre>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeGitModal()">Cerrar</button>
                <button class="btn btn-primary" id="btnRunGitPush" onclick="executeGitCommitPush()"><i class="fa-solid fa-cloud-arrow-up"></i> Realizar Commit y Push</button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATIONS -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- INICIALIZACIÓN DE DATOS DESDE PHP -->
    <script>
        // Los datos se inyectan directamente desde el JSON
        let cvData = <?= json_encode($cvData, JSON_UNESCAPED_UNICODE) ?> || {};
        let isModified = false;
        let cachedCertFiles = [];

        // Inicializar la interfaz
        document.addEventListener('DOMContentLoaded', () => {
            initNavigation();
            renderAllSections();
            checkUrlHash();

            // Shortcut Ctrl + S para guardar
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    saveAllChanges();
                }
            });

            document.getElementById('btnSaveAll').addEventListener('click', saveAllChanges);
        });

        // -------------------------------------------------------------
        // NAVEGACIÓN Y VISTAS
        // -------------------------------------------------------------
        function initNavigation() {
            const links = document.querySelectorAll('.sidebar-menu a');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const secId = link.getAttribute('data-section');
                    switchSection(secId);
                });
            });
        }

        function switchSection(secId) {
            // Actualizar menú activo
            document.querySelectorAll('.sidebar-menu a').forEach(a => {
                a.classList.toggle('active', a.getAttribute('data-section') === secId);
            });

            // Mostrar la sección correspondiente
            document.querySelectorAll('.admin-section').forEach(sec => {
                sec.classList.remove('active');
            });
            const targetSec = document.getElementById('sec-' + secId);
            if (targetSec) {
                targetSec.classList.add('active');
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            // Actualizar título de topbar
            const activeLink = document.querySelector(`.sidebar-menu a[data-section="${secId}"]`);
            if (activeLink) {
                document.getElementById('currentSectionTitle').innerText = activeLink.textContent.trim();
            }

            // Si es la sección de respaldos, cargar lista
            if (secId === 'backups') {
                loadBackupsList();
            }
        }

        function checkUrlHash() {
            const hash = window.location.hash.replace('#', '');
            if (hash) {
                switchSection(hash);
            }
        }

        function markAsModified() {
            isModified = true;
            const badge = document.getElementById('saveStatusBadge');
            badge.classList.add('unsaved');
            document.getElementById('saveStatusText').innerText = 'Cambios sin guardar';
        }

        function markAsSaved() {
            isModified = false;
            const badge = document.getElementById('saveStatusBadge');
            badge.classList.remove('unsaved');
            document.getElementById('saveStatusText').innerText = 'Todo guardado';
        }

        // -------------------------------------------------------------
        // RENDERIZADO DE TODAS LAS SECCIONES
        // -------------------------------------------------------------
        function renderAllSections() {
            renderDashboardStats();
            renderPersonalInfo();
            renderProfile();
            renderExperience();
            renderEducation();
            renderReferences();
            renderSkills();
            renderContact();
            renderLanguages();
            renderInterests();
            renderCertificates();
            renderDiplomados();
            renderDiplomas();
            renderWorkshops();
            renderRecognitions();
            renderUniversityTitles();
        }

        // Estadísticas de dashboard
        function renderDashboardStats() {
            document.getElementById('statExp').innerText = (cvData.experience || []).length;
            document.getElementById('statEdu').innerText = (cvData.education || []).length;
            const totalDocs = (cvData.certificates || []).length + (cvData.diplomados || []).length + 
                              (cvData.diplomas || []).length + (cvData.workshops || []).length;
            document.getElementById('statCert').innerText = totalDocs;
            
            let totalSkills = (cvData.skills?.others || []).length;
            (cvData.skills?.categories || []).forEach(c => totalSkills += (c.items || []).length);
            document.getElementById('statSkills').innerText = totalSkills;
        }

        // 1. Personal Info
        function renderPersonalInfo() {
            const p = cvData.personal_info || {};
            document.getElementById('p_name').value = p.name || '';
            document.getElementById('p_title1').value = (p.titles && p.titles[0]) || '';
            document.getElementById('p_title2').value = (p.titles && p.titles[1]) || '';
            document.getElementById('p_emails').value = p.emails || '';
            document.getElementById('p_phone').value = p.phone || '';
            document.getElementById('p_photo').value = p.photo || '';
            document.getElementById('p_git_url').value = p.github_url || '';
            document.getElementById('p_git_label').value = p.github_label || '';

            // Escuchadores de cambio
            ['p_name', 'p_title1', 'p_title2', 'p_emails', 'p_phone', 'p_photo', 'p_git_url', 'p_git_label'].forEach(id => {
                document.getElementById(id).oninput = () => {
                    collectPersonalInfo();
                    markAsModified();
                };
            });
        }

        function collectPersonalInfo() {
            if (!cvData.personal_info) cvData.personal_info = {};
            cvData.personal_info.name = document.getElementById('p_name').value.trim();
            const t1 = document.getElementById('p_title1').value.trim();
            const t2 = document.getElementById('p_title2').value.trim();
            cvData.personal_info.titles = [t1, t2].filter(t => t.length > 0);
            cvData.personal_info.emails = document.getElementById('p_emails').value.trim();
            cvData.personal_info.phone = document.getElementById('p_phone').value.trim();
            cvData.personal_info.photo = document.getElementById('p_photo').value.trim();
            cvData.personal_info.github_url = document.getElementById('p_git_url').value.trim();
            cvData.personal_info.github_label = document.getElementById('p_git_label').value.trim();
            document.getElementById('sidebarAvatar').src = cvData.personal_info.photo || 'assets/images/FOTO.jpg';
        }

        // 2. Profile
        function renderProfile() {
            const el = document.getElementById('p_profile');
            el.value = cvData.profile || '';
            el.oninput = () => {
                cvData.profile = el.value.trim();
                markAsModified();
            };
        }

        // 3. Experience
        function renderExperience() {
            const container = document.getElementById('experienceList');
            container.innerHTML = '';
            const items = cvData.experience || [];

            items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'item-card';
                card.innerHTML = `
                    <div class="item-card-header">
                        <div class="item-badge-title">
                            <span class="badge" style="background-color:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:6px; font-weight:700;">#${index + 1}</span>
                            <strong style="font-size:16px;">${escapeHtml(item.position || 'Puesto')}</strong>
                            <span style="color:var(--text-muted); font-size:14px;">— ${escapeHtml(item.company || 'Empresa')}</span>
                        </div>
                        <div class="item-card-actions">
                            <button class="btn btn-outline btn-sm" onclick="moveItem('experience', ${index}, -1)" ${index === 0 ? 'disabled' : ''} title="Mover arriba"><i class="fa-solid fa-arrow-up"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="moveItem('experience', ${index}, 1)" ${index === items.length - 1 ? 'disabled' : ''} title="Mover abajo"><i class="fa-solid fa-arrow-down"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteItem('experience', ${index})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Puesto / Cargo</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.position || '')}" oninput="cvData.experience[${index}].position = this.value; markAsModified();">
                        </div>
                        <div class="form-group">
                            <label>Empresa / Institución</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.company || '')}" oninput="cvData.experience[${index}].company = this.value; markAsModified();">
                        </div>
                        <div class="form-group full-width">
                            <label>Período / Fechas</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.period || '')}" oninput="cvData.experience[${index}].period = this.value; markAsModified();">
                        </div>
                        <div class="form-group full-width">
                            <label>Descripción de Funciones y Logros</label>
                            <textarea class="form-control" style="min-height:90px;" oninput="cvData.experience[${index}].description = this.value; markAsModified();">${escapeHtml(item.description || '')}</textarea>
                        </div>
                        <div class="form-group full-width">
                            <label>Tecnologías Utilizadas (separadas por coma)</label>
                            <input type="text" class="form-control" value="${escapeHtml((item.technologies || []).join(', '))}" oninput="cvData.experience[${index}].technologies = this.value.split(',').map(s=>s.trim()).filter(Boolean); markAsModified();">
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
            renderDashboardStats();
        }

        function addExperienceItem() {
            if (!cvData.experience) cvData.experience = [];
            cvData.experience.unshift({
                position: 'Nuevo Puesto',
                company: 'Nombre de Empresa',
                period: 'Mes Año a la fecha',
                description: 'Descripción de las funciones desempeñadas...',
                technologies: []
            });
            renderExperience();
            markAsModified();
            showToast('Nuevo puesto agregado al inicio.', 'success');
        }

        // 4. Education
        function renderEducation() {
            const container = document.getElementById('educationList');
            container.innerHTML = '';
            const items = cvData.education || [];

            items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'item-card';
                card.innerHTML = `
                    <div class="item-card-header">
                        <div class="item-badge-title">
                            <span class="badge" style="background-color:#ecfdf5; color:#059669; padding:4px 10px; border-radius:6px; font-weight:700;">#${index + 1}</span>
                            <strong style="font-size:16px;">${escapeHtml(item.degree || 'Grado')}</strong>
                        </div>
                        <div class="item-card-actions">
                            <button class="btn btn-outline btn-sm" onclick="moveItem('education', ${index}, -1)" ${index === 0 ? 'disabled' : ''}><i class="fa-solid fa-arrow-up"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="moveItem('education', ${index}, 1)" ${index === items.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-arrow-down"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteItem('education', ${index})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Título / Grado Académico</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.degree || '')}" oninput="cvData.education[${index}].degree = this.value; markAsModified();">
                        </div>
                        <div class="form-group">
                            <label>Institución Educativa / Universidad</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.institution || '')}" oninput="cvData.education[${index}].institution = this.value; markAsModified();">
                        </div>
                        <div class="form-group full-width">
                            <label>Período / Años</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.period || '')}" oninput="cvData.education[${index}].period = this.value; markAsModified();">
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
            renderDashboardStats();
        }

        function addEducationItem() {
            if (!cvData.education) cvData.education = [];
            cvData.education.unshift({
                degree: 'Nuevo Grado / Título',
                institution: 'Institución o Universidad',
                period: 'Año - Año'
            });
            renderEducation();
            markAsModified();
            showToast('Nuevo registro educativo agregado.', 'success');
        }

        // 5. References
        function renderReferences() {
            const container = document.getElementById('referencesList');
            container.innerHTML = '';
            const items = cvData.references || [];

            items.forEach((item, index) => {
                const card = document.createElement('div');
                card.className = 'item-card';
                card.innerHTML = `
                    <div class="item-card-header">
                        <div class="item-badge-title">
                            <strong style="font-size:16px;">${escapeHtml(item.name || 'Referencia')}</strong>
                        </div>
                        <div class="item-card-actions">
                            <button class="btn btn-danger btn-sm" onclick="deleteItem('references', ${index})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Nombre Completo</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.name || '')}" oninput="cvData.references[${index}].name = this.value; markAsModified();">
                        </div>
                        <div class="form-group">
                            <label>Teléfono de Contacto</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.phone || '')}" oninput="cvData.references[${index}].phone = this.value; markAsModified();">
                        </div>
                        <div class="form-group full-width">
                            <label>Empresa / Cargo (Opcional)</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.company || '')}" oninput="cvData.references[${index}].company = this.value; markAsModified();">
                        </div>
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function addReferenceItem() {
            if (!cvData.references) cvData.references = [];
            cvData.references.push({
                name: 'Ing. Nombre Apellido',
                phone: 'Tel: 00000000',
                company: ''
            });
            renderReferences();
            markAsModified();
        }

        // 6. Skills
        function renderSkills() {
            const container = document.getElementById('skillsCategoriesList');
            container.innerHTML = '';
            const categories = cvData.skills?.categories || [];

            categories.forEach((cat, catIdx) => {
                const card = document.createElement('div');
                card.className = 'card';
                card.innerHTML = `
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fa-solid fa-layer-group"></i>
                            <input type="text" class="form-control" style="font-weight:700; width:auto; border:none; border-bottom:2px dashed #cbd5e1; border-radius:0; padding:2px 6px;" value="${escapeHtml(cat.category || '')}" oninput="cvData.skills.categories[${catIdx}].category = this.value; markAsModified();">
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-outline btn-sm" onclick="addSkillItem(${catIdx})"><i class="fa-solid fa-plus"></i> Añadir Habilidad</button>
                            <button class="btn btn-danger btn-sm" onclick="deleteCategory(${catIdx})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div id="skillsInCat_${catIdx}" style="display:flex; flex-direction:column; gap:12px;"></div>
                `;
                container.appendChild(card);

                const itemsBox = card.querySelector(`#skillsInCat_${catIdx}`);
                (cat.items || []).forEach((item, itemIdx) => {
                    const row = document.createElement('div');
                    row.className = 'slider-row';
                    row.innerHTML = `
                        <input type="text" class="form-control" style="max-width:320px;" value="${escapeHtml(item.name || '')}" oninput="cvData.skills.categories[${catIdx}].items[${itemIdx}].name = this.value; markAsModified();">
                        <input type="range" min="10" max="100" step="5" value="${item.level || 80}" oninput="updateSkillLevel(${catIdx}, ${itemIdx}, this.value)">
                        <div class="slider-val" id="skillVal_${catIdx}_${itemIdx}">${item.level || 80}%</div>
                        <button class="btn btn-danger btn-sm" onclick="deleteSkillItem(${catIdx}, ${itemIdx})"><i class="fa-solid fa-xmark"></i></button>
                    `;
                    itemsBox.appendChild(row);
                });
            });

            // Render others skills badges
            renderOthersSkillsTags();
            renderDashboardStats();
        }

        function updateSkillLevel(catIdx, itemIdx, val) {
            cvData.skills.categories[catIdx].items[itemIdx].level = parseInt(val, 10);
            document.getElementById(`skillVal_${catIdx}_${itemIdx}`).innerText = val + '%';
            markAsModified();
        }

        function addSkillCategory() {
            if (!cvData.skills) cvData.skills = { categories: [], others: [] };
            if (!cvData.skills.categories) cvData.skills.categories = [];
            const catName = prompt('Nombre de la nueva categoría (Ej: DevOps, Seguridad, Inteligencia Artificial):');
            if (catName && catName.trim()) {
                cvData.skills.categories.push({
                    category: catName.trim(),
                    items: [{ name: 'Habilidad 1', level: 85 }]
                });
                renderSkills();
                markAsModified();
            }
        }

        function deleteCategory(catIdx) {
            if (confirm(`¿Eliminar la categoría "${cvData.skills.categories[catIdx].category}" y todas sus habilidades?`)) {
                cvData.skills.categories.splice(catIdx, 1);
                renderSkills();
                markAsModified();
            }
        }

        function addSkillItem(catIdx) {
            cvData.skills.categories[catIdx].items.push({ name: 'Nueva Habilidad', level: 80 });
            renderSkills();
            markAsModified();
        }

        function deleteSkillItem(catIdx, itemIdx) {
            cvData.skills.categories[catIdx].items.splice(itemIdx, 1);
            renderSkills();
            markAsModified();
        }

        function renderOthersSkillsTags() {
            const container = document.getElementById('othersSkillsTags');
            container.innerHTML = '';
            const others = cvData.skills?.others || [];
            others.forEach((tag, idx) => {
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.innerHTML = `
                    <span>${escapeHtml(tag)}</span>
                    <span class="remove-tag" onclick="removeOtherSkill(${idx})">&times;</span>
                `;
                container.appendChild(pill);
            });
        }

        function addOtherSkill() {
            const inp = document.getElementById('newOtherSkillInput');
            const val = inp.value.trim();
            if (val) {
                if (!cvData.skills) cvData.skills = { categories: [], others: [] };
                if (!cvData.skills.others) cvData.skills.others = [];
                cvData.skills.others.push(val);
                inp.value = '';
                renderOthersSkillsTags();
                renderDashboardStats();
                markAsModified();
            }
        }

        function removeOtherSkill(idx) {
            cvData.skills.others.splice(idx, 1);
            renderOthersSkillsTags();
            renderDashboardStats();
            markAsModified();
        }

        // 7. Contact
        function renderContact() {
            const c = cvData.contact || {};
            document.getElementById('c_phone').value = c.phone || '';
            document.getElementById('c_phone_note').value = c.phone_note || '';
            document.getElementById('c_address').value = c.address || '';

            ['c_phone', 'c_phone_note', 'c_address'].forEach(id => {
                document.getElementById(id).oninput = () => {
                    if (!cvData.contact) cvData.contact = {};
                    cvData.contact.phone = document.getElementById('c_phone').value.trim();
                    cvData.contact.phone_note = document.getElementById('c_phone_note').value.trim();
                    cvData.contact.address = document.getElementById('c_address').value.trim();
                    markAsModified();
                };
            });
        }

        // 8. Languages
        function renderLanguages() {
            const container = document.getElementById('languagesList');
            container.innerHTML = '';
            (cvData.languages || []).forEach((item, idx) => {
                const row = document.createElement('div');
                row.style.cssText = 'display:flex; gap:12px; margin-bottom:8px; align-items:center;';
                row.innerHTML = `
                    <input type="text" class="form-control" placeholder="Idioma" value="${escapeHtml(item.language || '')}" oninput="cvData.languages[${idx}].language = this.value; markAsModified();">
                    <input type="text" class="form-control" placeholder="Nivel (Ej: Nativo, Intermedio)" value="${escapeHtml(item.level || '')}" oninput="cvData.languages[${idx}].level = this.value; markAsModified();">
                    <button class="btn btn-danger btn-sm" onclick="deleteLanguageItem(${idx})"><i class="fa-solid fa-trash"></i></button>
                `;
                container.appendChild(row);
            });
        }

        function addLanguageItem() {
            if (!cvData.languages) cvData.languages = [];
            cvData.languages.push({ language: 'Nuevo Idioma', level: 'Intermedio' });
            renderLanguages();
            markAsModified();
        }

        function deleteLanguageItem(idx) {
            cvData.languages.splice(idx, 1);
            renderLanguages();
            markAsModified();
        }

        // 9. Interests
        function renderInterests() {
            const container = document.getElementById('interestsTags');
            container.innerHTML = '';
            (cvData.interests || []).forEach((tag, idx) => {
                const pill = document.createElement('span');
                pill.className = 'tag-pill';
                pill.innerHTML = `
                    <span>${escapeHtml(tag)}</span>
                    <span class="remove-tag" onclick="removeInterest(${idx})">&times;</span>
                `;
                container.appendChild(pill);
            });
        }

        function addInterest() {
            const inp = document.getElementById('newInterestInput');
            const val = inp.value.trim();
            if (val) {
                if (!cvData.interests) cvData.interests = [];
                cvData.interests.push(val);
                inp.value = '';
                renderInterests();
                markAsModified();
            }
        }

        function removeInterest(idx) {
            cvData.interests.splice(idx, 1);
            renderInterests();
            markAsModified();
        }

        // 10. Document List Renderer (Certificados, Diplomados, Diplomas, Talleres, Títulos)
        function renderDocList(arrayName, containerId, labelSingular, filterQuery = '') {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            const items = cvData[arrayName] || [];

            const filtered = items.map((item, originalIndex) => ({ item, originalIndex }))
                .filter(({ item }) => {
                    if (!filterQuery) return true;
                    const q = filterQuery.toLowerCase();
                    return (item.title || '').toLowerCase().includes(q) || 
                           (item.detail || '').toLowerCase().includes(q);
                });

            if (filtered.length === 0) {
                container.innerHTML = `<p style="color:var(--text-muted); font-size:14px; padding:12px;">No hay elementos para mostrar.</p>`;
                return;
            }

            filtered.forEach(({ item, originalIndex }) => {
                const card = document.createElement('div');
                card.className = 'item-card';
                const fileHtml = item.file 
                    ? `<a href="${escapeHtml(item.file)}" target="_blank" style="color:var(--primary); font-size:13px;"><i class="fa-solid fa-file-pdf"></i> ${escapeHtml(item.file)}</a>` 
                    : `<span style="color:var(--text-muted); font-size:13px;"><i class="fa-solid fa-file-circle-xmark"></i> Sin archivo adjunto</span>`;

                card.innerHTML = `
                    <div class="item-card-header">
                        <div class="item-badge-title">
                            <span class="badge" style="background-color:#eff6ff; color:#2563eb; padding:4px 8px; border-radius:6px; font-weight:700;">#${originalIndex + 1}</span>
                            <div style="display:flex; flex-direction:column; gap:2px;">
                                <strong style="font-size:15px; color:var(--text-main);">${escapeHtml(item.title || '')}</strong>
                                <span style="font-size:13px; color:var(--text-muted);">${escapeHtml(item.detail || '')}</span>
                            </div>
                        </div>
                        <div class="item-card-actions">
                            <button class="btn btn-outline btn-sm" onclick="editDocModalItem('${arrayName}', ${originalIndex}, '${labelSingular}')" title="Editar"><i class="fa-solid fa-pen-to-square"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="moveItem('${arrayName}', ${originalIndex}, -1)" ${originalIndex === 0 ? 'disabled' : ''}><i class="fa-solid fa-arrow-up"></i></button>
                            <button class="btn btn-outline btn-sm" onclick="moveItem('${arrayName}', ${originalIndex}, 1)" ${originalIndex === items.length - 1 ? 'disabled' : ''}><i class="fa-solid fa-arrow-down"></i></button>
                            <button class="btn btn-danger btn-sm" onclick="deleteDocItem('${arrayName}', ${originalIndex})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                    <div style="border-top:1px dashed #e2e8f0; padding-top:10px; display:flex; justify-content:space-between; align-items:center;">
                        <div>${fileHtml}</div>
                        ${item.description ? `<div style="font-size:12.5px; color:#475569;">${escapeHtml(item.description)}</div>` : ''}
                    </div>
                `;
                container.appendChild(card);
            });
        }

        function filterCertList(arrayName, query) {
            const map = {
                'certificates': 'certificatesList',
                'diplomados': 'diplomadosList',
                'diplomas': 'diplomasList',
                'workshops': 'workshopsList',
                'university_titles': 'universityTitlesList'
            };
            renderDocList(arrayName, map[arrayName], arrayName, query);
        }

        function renderCertificates() { renderDocList('certificates', 'certificatesList', 'Certificado'); }
        function renderDiplomados() { renderDocList('diplomados', 'diplomadosList', 'Diplomado'); }
        function renderDiplomas() { renderDocList('diplomas', 'diplomasList', 'Diploma'); }
        function renderWorkshops() { renderDocList('workshops', 'workshopsList', 'Taller'); }
        function renderUniversityTitles() { renderDocList('university_titles', 'universityTitlesList', 'Título'); }

        // Reconocimientos
        function renderRecognitions() {
            const container = document.getElementById('recognitionsList');
            container.innerHTML = '';
            (cvData.recognitions || []).forEach((item, idx) => {
                const row = document.createElement('div');
                row.className = 'item-card';
                row.innerHTML = `
                    <div class="item-card-header">
                        <strong style="font-size:15px;">${escapeHtml(item.title || '')}</strong>
                        <button class="btn btn-danger btn-sm" onclick="deleteItem('recognitions', ${idx})"><i class="fa-solid fa-trash"></i></button>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Título del Reconocimiento</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.title || '')}" oninput="cvData.recognitions[${idx}].title = this.value; markAsModified();">
                        </div>
                        <div class="form-group">
                            <label>Detalle / Institución</label>
                            <input type="text" class="form-control" value="${escapeHtml(item.description || item.detail || '')}" oninput="cvData.recognitions[${idx}].description = this.value; markAsModified();">
                        </div>
                    </div>
                `;
                container.appendChild(row);
            });
        }

        function addRecognitionItem() {
            if (!cvData.recognitions) cvData.recognitions = [];
            cvData.recognitions.push({ title: 'Nuevo Reconocimiento', description: 'Entidad o motivo' });
            renderRecognitions();
            markAsModified();
        }

        // -------------------------------------------------------------
        // MODAL DE DOCUMENTO (AGREGAR / EDITAR)
        // -------------------------------------------------------------
        function openAddDocModal(arrayName, labelSingular) {
            document.getElementById('docModalTargetArray').value = arrayName;
            document.getElementById('docModalEditIndex').value = '-1';
            document.getElementById('docModalTitle').innerText = 'Nuevo ' + labelSingular;
            document.getElementById('docModalInputTitle').value = '';
            document.getElementById('docModalInputDetail').value = '';
            document.getElementById('docModalInputFile').value = '';
            document.getElementById('docModalInputDesc').value = '';
            document.getElementById('docModal').classList.add('open');
        }

        function editDocModalItem(arrayName, index, labelSingular) {
            const item = cvData[arrayName][index];
            document.getElementById('docModalTargetArray').value = arrayName;
            document.getElementById('docModalEditIndex').value = index;
            document.getElementById('docModalTitle').innerText = 'Editar ' + labelSingular;
            document.getElementById('docModalInputTitle').value = item.title || '';
            document.getElementById('docModalInputDetail').value = item.detail || '';
            document.getElementById('docModalInputFile').value = item.file || '';
            document.getElementById('docModalInputDesc').value = item.description || '';
            document.getElementById('docModal').classList.add('open');
        }

        function closeDocModal() {
            document.getElementById('docModal').classList.remove('open');
        }

        function saveDocModalItem() {
            const arrayName = document.getElementById('docModalTargetArray').value;
            const editIndex = parseInt(document.getElementById('docModalEditIndex').value, 10);
            const title = document.getElementById('docModalInputTitle').value.trim();
            const detail = document.getElementById('docModalInputDetail').value.trim();
            const file = document.getElementById('docModalInputFile').value.trim();
            const desc = document.getElementById('docModalInputDesc').value.trim();

            if (!title) {
                alert('El título del documento es obligatorio.');
                return;
            }

            if (!cvData[arrayName]) cvData[arrayName] = [];

            const docObj = {
                title: title,
                detail: detail,
                file: file,
                description: desc
            };

            if (editIndex >= 0) {
                cvData[arrayName][editIndex] = docObj;
                showToast('Elemento actualizado.', 'success');
            } else {
                cvData[arrayName].unshift(docObj);
                showToast('Nuevo elemento agregado a la lista.', 'success');
            }

            closeDocModal();
            markAsModified();

            // Re-render
            if (arrayName === 'certificates') renderCertificates();
            else if (arrayName === 'diplomados') renderDiplomados();
            else if (arrayName === 'diplomas') renderDiplomas();
            else if (arrayName === 'workshops') renderWorkshops();
            else if (arrayName === 'university_titles') renderUniversityTitles();
            renderDashboardStats();
        }

        function deleteDocItem(arrayName, index) {
            if (confirm('¿Estás seguro de eliminar este elemento?')) {
                cvData[arrayName].splice(index, 1);
                markAsModified();
                if (arrayName === 'certificates') renderCertificates();
                else if (arrayName === 'diplomados') renderDiplomados();
                else if (arrayName === 'diplomas') renderDiplomas();
                else if (arrayName === 'workshops') renderWorkshops();
                else if (arrayName === 'university_titles') renderUniversityTitles();
                renderDashboardStats();
            }
        }

        // Subir archivo desde el modal
        function triggerDocUpload() {
            document.getElementById('docModalFileInput').click();
        }

        function handleDocUploaded(e) {
            const file = e.target.files[0];
            if (!file) return;

            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('dest_type', 'certi');
            fd.append('file', file);

            showToast('Subiendo archivo...', 'warning');

            fetch('admin.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('docModalInputFile').value = data.filePath;
                    showToast('Archivo subido: ' + data.fileName, 'success');
                } else {
                    alert('Error al subir: ' + data.error);
                }
            })
            .catch(err => alert('Fallo de red: ' + err.message));
        }

        // Subir foto de perfil
        function triggerPhotoUpload() {
            document.getElementById('photoFileInput').click();
        }

        function handlePhotoUploaded(e) {
            const file = e.target.files[0];
            if (!file) return;

            const fd = new FormData();
            fd.append('action', 'upload_file');
            fd.append('dest_type', 'photo');
            fd.append('file', file);

            showToast('Subiendo foto de perfil...', 'warning');

            fetch('admin.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('p_photo').value = data.filePath;
                    document.getElementById('sidebarAvatar').src = data.filePath;
                    collectPersonalInfo();
                    markAsModified();
                    showToast('Foto de perfil actualizada.', 'success');
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(err => alert('Fallo de red: ' + err.message));
        }

        // -------------------------------------------------------------
        // SELECTOR DE ARCHIVOS EN MEDIA/CERTI
        // -------------------------------------------------------------
        function openFilePicker() {
            document.getElementById('filePickerModal').classList.add('open');
            if (cachedCertFiles.length === 0) {
                const fd = new FormData();
                fd.append('action', 'list_cert_files');
                fetch('admin.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            cachedCertFiles = res.files || [];
                            renderFilePickerList(cachedCertFiles);
                        }
                    });
            } else {
                renderFilePickerList(cachedCertFiles);
            }
        }

        function closeFilePicker() {
            document.getElementById('filePickerModal').classList.remove('open');
        }

        function renderFilePickerList(files) {
            const container = document.getElementById('filePickerList');
            container.innerHTML = '';
            files.forEach(f => {
                const item = document.createElement('div');
                item.style.cssText = 'padding:10px 14px; border:1px solid #e2e8f0; border-radius:8px; display:flex; justify-content:space-between; align-items:center; cursor:pointer; font-size:13px;';
                item.onmouseenter = () => item.style.backgroundColor = '#eff6ff';
                item.onmouseleave = () => item.style.backgroundColor = '#fff';
                item.innerHTML = `
                    <div style="display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-file" style="color:#2563eb;"></i>
                        <strong>${escapeHtml(f.name)}</strong>
                    </div>
                    <span style="color:#64748b; font-size:12px;">${f.size}</span>
                `;
                item.onclick = () => {
                    document.getElementById('docModalInputFile').value = f.path;
                    closeFilePicker();
                };
                container.appendChild(item);
            });
        }

        function filterFilePicker(q) {
            const query = q.toLowerCase();
            const filtered = cachedCertFiles.filter(f => f.name.toLowerCase().includes(query));
            renderFilePickerList(filtered);
        }

        // -------------------------------------------------------------
        // ACCIONES GLOBALES: MOVER Y ELIMINAR ELEMENTOS
        // -------------------------------------------------------------
        function moveItem(arrayName, index, direction) {
            const arr = cvData[arrayName];
            const targetIndex = index + direction;
            if (targetIndex < 0 || targetIndex >= arr.length) return;
            const temp = arr[index];
            arr[index] = arr[targetIndex];
            arr[targetIndex] = temp;
            markAsModified();

            if (arrayName === 'experience') renderExperience();
            else if (arrayName === 'education') renderEducation();
            else if (arrayName === 'certificates') renderCertificates();
            else if (arrayName === 'diplomas') renderDiplomas();
            else if (arrayName === 'workshops') renderWorkshops();
            else if (arrayName === 'university_titles') renderUniversityTitles();
        }

        function deleteItem(arrayName, index) {
            if (confirm('¿Eliminar este elemento de la lista?')) {
                cvData[arrayName].splice(index, 1);
                markAsModified();
                if (arrayName === 'experience') renderExperience();
                else if (arrayName === 'education') renderEducation();
                else if (arrayName === 'references') renderReferences();
                else if (arrayName === 'recognitions') renderRecognitions();
                renderDashboardStats();
            }
        }

        // -------------------------------------------------------------
        // GUARDAR CAMBIOS (AJAX -> PHP -> JSON + INDEX.HTML)
        // -------------------------------------------------------------
        function saveAllChanges() {
            collectPersonalInfo();
            cvData.profile = document.getElementById('p_profile').value.trim();

            const saveBtn = document.getElementById('btnSaveAll');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando...';

            const fd = new FormData();
            fd.append('action', 'save_data');
            fd.append('data', JSON.stringify(cvData));

            fetch('admin.php', {
                method: 'POST',
                body: fd
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Cambios';

                if (data.success) {
                    markAsSaved();
                    showToast(data.message, 'success');
                } else {
                    alert('Error al guardar: ' + data.error);
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Guardar Cambios';
                alert('Fallo de conexión al servidor local: ' + err.message);
            });
        }

        // -------------------------------------------------------------
        // GITHUB DEPLOY MODAL & EJECUCIÓN
        // -------------------------------------------------------------
        function openGitModal() {
            document.getElementById('gitModal').classList.add('open');
            const box = document.getElementById('gitStatusBox');
            box.innerText = 'Consultando estado de Git...';

            const fd = new FormData();
            fd.append('action', 'git_status');
            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        box.innerText = `Rama actual: ${res.branch}\n\nArchivos modificados:\n${res.status || 'No hay cambios pendientes por commitear.'}`;
                    } else {
                        box.innerText = `Git status output:\n${res.status || 'No se pudo obtener información de git.'}`;
                    }
                })
                .catch(err => {
                    box.innerText = 'Error al comunicar con Git: ' + err.message;
                });
        }

        function closeGitModal() {
            document.getElementById('gitModal').classList.remove('open');
        }

        function executeGitCommitPush() {
            const btn = document.getElementById('btnRunGitPush');
            const box = document.getElementById('gitStatusBox');
            const msg = document.getElementById('gitCommitMsg').value.trim();

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando a GitHub...';
            box.innerText += '\n\nEjecutando: git add . && git commit && git push...\nPor favor espera unos segundos...\n';

            const fd = new FormData();
            fd.append('action', 'git_commit_push');
            fd.append('message', msg);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Realizar Commit y Push';
                    box.innerText += '\nResultado:\n' + (res.output || 'Sin respuesta');
                    if (res.success) {
                        showToast('¡Cambios enviados a GitHub con éxito!', 'success');
                    } else {
                        showToast('Revisa la consola de Git.', 'warning');
                    }
                })
                .catch(err => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Realizar Commit y Push';
                    box.innerText += '\nError de ejecución: ' + err.message;
                });
        }

        // -------------------------------------------------------------
        // RESPALDOS & EXPORTACIÓN / IMPORTACIÓN
        // -------------------------------------------------------------
        function downloadJsonBackup() {
            collectPersonalInfo();
            const str = JSON.stringify(cvData, null, 2);
            const blob = new Blob([str], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `cv_dany_minchez_${new Date().toISOString().slice(0,10)}.json`;
            a.click();
            URL.revokeObjectURL(url);
            showToast('Archivo JSON descargado.', 'success');
        }

        function triggerImportJson() {
            document.getElementById('importJsonInput').click();
        }

        function handleImportJson(e) {
            const file = e.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (event) => {
                try {
                    const parsed = JSON.parse(event.target.result);
                    if (confirm('¿Deseas reemplazar todos los datos actuales con este respaldo JSON?')) {
                        cvData = parsed;
                        renderAllSections();
                        markAsModified();
                        showToast('Respaldo cargado. Guarda para aplicarlo a index.html', 'success');
                    }
                } catch (err) {
                    alert('Error: el archivo no es un JSON válido.');
                }
            };
            reader.readAsText(file);
        }

        function loadBackupsList() {
            const container = document.getElementById('backupsListTable');
            container.innerHTML = '<p style="color:var(--text-muted); font-size:14px;"><i class="fa-solid fa-spinner fa-spin"></i> Cargando respaldos...</p>';

            const fd = new FormData();
            fd.append('action', 'list_backups');
            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success && res.backups.length > 0) {
                        let html = `
                            <table style="width:100%; border-collapse:collapse; font-size:13.5px;">
                                <thead>
                                    <tr style="text-align:left; border-bottom:2px solid #e2e8f0; color:#64748b;">
                                        <th style="padding:10px 8px;">Archivo</th>
                                        <th style="padding:10px 8px;">Fecha</th>
                                        <th style="padding:10px 8px;">Tamaño</th>
                                        <th style="padding:10px 8px; text-align:right;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        res.backups.forEach(b => {
                            html += `
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:10px 8px; font-family:monospace; color:#1e293b;">${b.filename}</td>
                                    <td style="padding:10px 8px; color:#475569;">${b.date}</td>
                                    <td style="padding:10px 8px; color:#64748b;">${b.size}</td>
                                    <td style="padding:10px 8px; text-align:right;">
                                        <button class="btn btn-outline btn-sm" onclick="restoreBackup('${b.filename}')"><i class="fa-solid fa-rotate-left"></i> Restaurar</button>
                                    </td>
                                </tr>
                            `;
                        });
                        html += `</tbody></table>`;
                        container.innerHTML = html;
                    } else {
                        container.innerHTML = '<p style="color:var(--text-muted); font-size:14px;">No hay respaldos automáticos registrados todavía.</p>';
                    }
                })
                .catch(err => {
                    container.innerHTML = '<p style="color:red; font-size:14px;">Error al cargar respaldos: ' + err.message + '</p>';
                });
        }

        function restoreBackup(filename) {
            if (!confirm(`¿Restaurar la versión "${filename}"? Esta acción reemplazará los datos actuales y regenerará index.html.`)) {
                return;
            }

            const fd = new FormData();
            fd.append('action', 'restore_backup');
            fd.append('filename', filename);

            fetch('admin.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        cvData = res.data;
                        renderAllSections();
                        markAsSaved();
                        showToast(res.message, 'success');
                    } else {
                        alert('Error al restaurar: ' + res.error);
                    }
                })
                .catch(err => alert('Fallo de red: ' + err.message));
        }

        // -------------------------------------------------------------
        // UTILIDADES: TOAST Y ESCAPE HTML
        // -------------------------------------------------------------
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            const icon = type === 'success' ? 'circle-check' : (type === 'error' ? 'circle-xmark' : 'circle-info');
            toast.innerHTML = `<i class="fa-solid fa-${icon}"></i> <span>${escapeHtml(message)}</span>`;
            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        function escapeHtml(str) {
            if (typeof str !== 'string') return str;
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    </script>
</body>
</html>
