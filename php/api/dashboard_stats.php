<?php
// php/api/dashboard_stats.php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Acceso no autorizado.', 'error_code' => 'UNAUTHORIZED']);
    exit;
}

require_once '../conexion_db.php';

try {
    // --- Contadores para Tarjetas (stats) ---
    $stmt_total_students = $conexion->query("SELECT COUNT(*) FROM alumnos");
    $totalStudents = $stmt_total_students->fetchColumn();

    $stmt_tutors_counts = $conexion->query("
        SELECT
            COUNT(*) as totalTutors,
            SUM(CASE WHEN estado_registro = 'pendiente' THEN 1 ELSE 0 END) as pendingTutors,
            SUM(CASE WHEN estado_registro = 'aprobado' THEN 1 ELSE 0 END) as approvedTutors,
            SUM(CASE WHEN estado_registro = 'rechazado' THEN 1 ELSE 0 END) as rejectedTutors
        FROM tutores
    ");
    $tutorCounts = $stmt_tutors_counts->fetch(PDO::FETCH_ASSOC);

    $stmt_total_categories = $conexion->query("SELECT COUNT(*) FROM categorias");
    $totalCategories = $stmt_total_categories->fetchColumn();

    $stmt_total_admins = $conexion->query("SELECT COUNT(*) FROM admins");
    $totalAdmins = $stmt_total_admins->fetchColumn();

    // --- Datos para Gráficos (charts) ---

    // 1. Estado de tutores (para gráfico de dona/pie)
    //    Los conteos ya los tenemos de $tutorCounts
    $tutorStatusChartData = [
        'aprobado' => intval($tutorCounts['approvedTutors'] ?? 0),
        'pendiente' => intval($tutorCounts['pendingTutors'] ?? 0),
        'rechazado' => intval($tutorCounts['rejectedTutors'] ?? 0)
    ];

    // 2. Registros mensuales (últimos 6 meses)
    $monthly_sql = "
        SELECT
            DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
            'alumnos' as tipo,
            COUNT(*) as count
        FROM alumnos
        WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')

        UNION ALL

        SELECT
            DATE_FORMAT(fecha_registro, '%Y-%m') as mes,
            'tutores' as tipo,
            COUNT(*) as count
        FROM tutores
        WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')

        ORDER BY mes ASC
    "; // ASC para que el gráfico de línea tenga sentido cronológico
    $stmt_monthly = $conexion->query($monthly_sql);
    $rawMonthlyData = $stmt_monthly->fetchAll(PDO::FETCH_ASSOC);

    $monthlyRegistrationsChartData = [];
    $tempMonthly = [];

    // Inicializar meses
    for ($i = 5; $i >= 0; $i--) {
        $monthKey = date('Y-m', strtotime("-$i month"));
        $tempMonthly[$monthKey] = ['mes' => $monthKey, 'alumnos' => 0, 'tutores' => 0];
    }

    foreach ($rawMonthlyData as $row) {
        if (isset($tempMonthly[$row['mes']])) {
            $tempMonthly[$row['mes']][$row['tipo']] = intval($row['count']);
        }
    }
    $monthlyRegistrationsChartData = array_values($tempMonthly);


    // --- Estadísticas Adicionales ---
    $stmt_career_dist = $conexion->query("SELECT carrera, COUNT(*) as count FROM alumnos GROUP BY carrera ORDER BY count DESC");
    $careerDistribution = $stmt_career_dist->fetchAll(PDO::FETCH_ASSOC);

    $stmt_exp_dist = $conexion->query("SELECT nivel_experiencia, COUNT(*) as count FROM tutores WHERE estado_registro = 'aprobado' GROUP BY nivel_experiencia ORDER BY count DESC");
    $experienceDistribution = $stmt_exp_dist->fetchAll(PDO::FETCH_ASSOC);

    // Actividad reciente (nuevos registros en las últimas 24 horas)
    $stmt_recent_activity = $conexion->query("
        SELECT 'alumno' as tipo, COUNT(*) as count FROM alumnos WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        UNION ALL
        SELECT 'tutor' as tipo, COUNT(*) as count FROM tutores WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $rawRecentActivity = $stmt_recent_activity->fetchAll(PDO::FETCH_ASSOC);
    $recentActivityData = ['newStudents24h' => 0, 'newTutors24h' => 0];
    foreach($rawRecentActivity as $activity) {
        if ($activity['tipo'] === 'alumno') $recentActivityData['newStudents24h'] = intval($activity['count']);
        if ($activity['tipo'] === 'tutor') $recentActivityData['newTutors24h'] = intval($activity['count']);
    }

    // Ensamblar respuesta
    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => [ // Para las tarjetas principales
            'totalStudents' => intval($totalStudents),
            'totalTutors' => intval($tutorCounts['totalTutors'] ?? 0),
            'approvedTutors' => intval($tutorCounts['approvedTutors'] ?? 0),
            'pendingTutors' => intval($tutorCounts['pendingTutors'] ?? 0),
            'rejectedTutors' => intval($tutorCounts['rejectedTutors'] ?? 0), // JS no lo usa directamente en tarjeta, pero sí para el chart
            'totalCategories' => intval($totalCategories),
            'totalAdmins' => intval($totalAdmins)
        ],
        'charts' => [
            'tutorStatus' => $tutorStatusChartData,
            'monthlyRegistrations' => $monthlyRegistrationsChartData,
            // Estos pueden ir en 'additionalStats' o directamente si el frontend los necesita para gráficos
            'careerDistribution' => $careerDistribution,
            'experienceDistribution' => $experienceDistribution
        ],
        'recentActivity' => $recentActivityData,
        'percentages' => [ // El script original tenía porcentajes, los mantengo si son útiles
            'approvedTutorsPercent' => ($tutorCounts['totalTutors'] ?? 0) > 0 ? round((($tutorCounts['approvedTutors'] ?? 0) / $tutorCounts['totalTutors']) * 100, 1) : 0,
            'pendingTutorsPercent' => ($tutorCounts['totalTutors'] ?? 0) > 0 ? round((($tutorCounts['pendingTutors'] ?? 0) / $tutorCounts['totalTutors']) * 100, 1) : 0,
            'rejectedTutorsPercent' => ($tutorCounts['totalTutors'] ?? 0) > 0 ? round((($tutorCounts['rejectedTutors'] ?? 0) / $tutorCounts['totalTutors']) * 100, 1) : 0
        ]
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("Error de BD en dashboard_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos.', 'error_code' => 'DATABASE_ERROR']);
} catch (Exception $e) {
    error_log("Error general en dashboard_stats.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: '. $e->getMessage(), 'error_code' => 'INTERNAL_SERVER_ERROR']);
}
?>