<?php
/**
 * API: Dashboard Insights (series para gráficos)
 */

declare(strict_types=1);

require_once '../../src/Config/Database.php';
require_once '../../src/Config/ApiAuth.php';
require_once '../../src/Models/Dashboard.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-API-Timestamp, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit();
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        respond(['success' => false, 'error' => 'Método no permitido'], 405);
    }

    $validation = ApiAuth::validateRequest();
    if (!$validation['valid']) {
        respond([
            'success' => false,
            'error'   => 'Acceso no autorizado: ' . ($validation['error'] ?? 'Token inválido')
        ], 401);
    }

    $dashboard = new Dashboard();

    $months = isset($_GET['months']) && is_numeric($_GET['months']) ? (int)$_GET['months'] : 12;
    if ($months < 1 || $months > 24) {
        $months = 12;
    }

    $usuariosPorMes = $dashboard->getUsuariosPorMes($months);

    $alertsResumen = $dashboard->getResumenAlertas();
    $radarUsuarios = [
        ['categoria' => 'Sin finca', 'valor' => $alertsResumen['usuarios']['sin_finca'] ?? 0],
        ['categoria' => 'Inactivos 30d', 'valor' => $alertsResumen['usuarios']['inactivos_30d'] ?? 0],
        ['categoria' => 'Nunca logueados', 'valor' => $alertsResumen['usuarios']['nunca_logueados'] ?? 0],
        ['categoria' => 'Sin demografía', 'valor' => $alertsResumen['usuarios']['sin_demografia'] ?? 0],
    ];

    $areasSospechosas = $dashboard->getFincasAreaSospechosa();
    $radarFincas = [
        ['categoria' => 'Sin potreros', 'valor' => $alertsResumen['fincas']['sin_potreros'] ?? 0],
        ['categoria' => 'Sin actividad 30d', 'valor' => $alertsResumen['fincas']['sin_actividad_30d'] ?? 0],
        ['categoria' => 'Sin área calculada', 'valor' => $areasSospechosas['sin_area_calculada'] ?? 0],
        ['categoria' => 'Área < 0.5ha', 'valor' => $areasSospechosas['area_muy_pequeña'] ?? 0],
        ['categoria' => 'Área > 500ha', 'valor' => $areasSospechosas['area_muy_grande'] ?? 0],
    ];

    respond([
        'success' => true,
        'data'    => [
            'series' => [
                'usuarios' => $usuariosPorMes
            ],
            'radar' => [
                'usuarios' => $radarUsuarios,
                'fincas'   => $radarFincas
            ],
            'meta' => [
                'generated_at' => date('c'),
                'months'       => $months
            ]
        ]
    ]);
} catch (Throwable $e) {
    error_log('Error en dashboard-insights.php: ' . $e->getMessage());
    respond([
        'success' => false,
        'error'   => 'Error obteniendo insights del dashboard'
    ], 500);
}

