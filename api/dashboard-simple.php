<?php

/**
 * Dashboard Simple - Sin dependencias
 */

// Configurar headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Test básico de conexión
    $host = '157.245.241.220';
    $port = '5432';
    $dbname = 'telegan';
    $username = 'rios';
    $password = '*gS&Di*ue.,RQ[E]X}.10QP8539';
    
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    $pdo = new PDO($dsn, $username, $password);
    
    // Test de consulta
    $sql = "SELECT COUNT(*) FROM v_usuarios_fincas";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $totalUsuarios = $stmt->fetchColumn();
    
    echo json_encode(array(
        'success' => true,
        'data' => array(
            'estadisticas' => array(
                'total_usuarios' => (int)$totalUsuarios,
                'total_fincas' => 0,
                'total_potreros' => 0,
                'total_registros_ganaderos' => 0,
                'timestamp' => date('Y-m-d H:i:s')
            ),
            'base_datos' => array(
                'conectado' => true,
                'version_postgresql' => 'PostgreSQL 14+',
                'timestamp' => date('Y-m-d H:i:s')
            )
        ),
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(array(
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ), JSON_UNESCAPED_UNICODE);
}
?>






