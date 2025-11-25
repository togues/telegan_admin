<?php

/**
 * Modelo para el Dashboard principal
 * Maneja las estadísticas y datos del panel de control
 * PHP Vanilla - Sin frameworks, sin namespaces
 */

// Incluir dependencias
require_once __DIR__ . '/../Config/Database.php';
class Dashboard
{
    /**
     * Obtener total de usuarios registrados
     */
    public function getTotalUsuarios()
    {
        try {
            $sql = "SELECT COUNT(*) FROM usuario";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener total de usuarios: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener usuarios activos
     */
    public function getUsuariosActivos()
    {
        try {
            $sql = "SELECT COUNT(*) FROM usuario WHERE activo = TRUE";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios activos: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener total de fincas activas
     */
    public function getTotalFincas()
    {
        try {
            $sql = "SELECT COUNT(*) FROM finca WHERE estado = 'ACTIVA'";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener total de fincas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener total de potreros activos
     */
    public function getTotalPotreros()
    {
        try {
            $sql = "SELECT COUNT(*) FROM potrero WHERE estado = 'ACTIVO'";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener total de potreros: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener total de registros ganaderos
     */
    public function getTotalRegistrosGanaderos()
    {
        try {
            $sql = "SELECT COUNT(*) FROM registro_ganadero";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener total de registros ganaderos: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Usuarios registrados por mes (últimos N meses)
     */
    public function getUsuariosPorMes($meses = 12)
    {
        try {
            $sql = "
                SELECT
                    TO_CHAR(date_trunc('month', fecha_registro), 'YYYY-MM') AS mes,
                    COUNT(*) AS total
                FROM usuario
                WHERE fecha_registro >= date_trunc('month', CURRENT_DATE) - INTERVAL '{$meses} months'
                GROUP BY 1
                ORDER BY 1 ASC
            ";
            return Database::fetchAll($sql);
        } catch (Exception $e) {
            error_log('Error al obtener usuarios por mes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fincas creadas por mes
     */
    public function getFincasPorMes($meses = 12)
    {
        try {
            $sql = "
                SELECT
                    TO_CHAR(date_trunc('month', fecha_creacion), 'YYYY-MM') AS mes,
                    COUNT(*) AS total
                FROM finca
                WHERE fecha_creacion >= date_trunc('month', CURRENT_DATE) - INTERVAL '{$meses} months'
                GROUP BY 1
                ORDER BY 1 ASC
            ";
            return Database::fetchAll($sql);
        } catch (Exception $e) {
            error_log('Error al obtener fincas por mes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Registro ganadero por mes
     */
    public function getRegistrosPorMes($meses = 12)
    {
        try {
            $sql = "
                SELECT
                    TO_CHAR(date_trunc('month', fecha_registro), 'YYYY-MM') AS mes,
                    COUNT(*) AS total
                FROM registro_ganadero
                WHERE fecha_registro >= date_trunc('month', CURRENT_DATE) - INTERVAL '{$meses} months'
                GROUP BY 1
                ORDER BY 1 ASC
            ";
            return Database::fetchAll($sql);
        } catch (Exception $e) {
            error_log('Error al obtener registros por mes: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Registro ganadero por día (últimos N días)
     */
    public function getRegistrosPorDia($dias = 30)
    {
        try {
            $sql = "
                SELECT
                    TO_CHAR(date_trunc('day', fecha_registro), 'YYYY-MM-DD') AS dia,
                    COUNT(*) AS total
                FROM registro_ganadero
                WHERE fecha_registro >= CURRENT_DATE - INTERVAL '{$dias} days'
                GROUP BY 1
                ORDER BY 1 ASC
            ";
            return Database::fetchAll($sql);
        } catch (Exception $e) {
            error_log('Error al obtener registros por día: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fincas creadas por día (últimos N días)
     */
    public function getFincasPorDia($dias = 30)
    {
        try {
            $sql = "
                SELECT
                    TO_CHAR(date_trunc('day', fecha_creacion), 'YYYY-MM-DD') AS dia,
                    COUNT(*) AS total
                FROM finca
                WHERE fecha_creacion >= CURRENT_DATE - INTERVAL '{$dias} days'
                GROUP BY 1
                ORDER BY 1 ASC
            ";
            return Database::fetchAll($sql);
        } catch (Exception $e) {
            error_log('Error al obtener fincas por día: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtener usuarios administradores de finca
     */
    public function getUsuariosAdministradores()
    {
        try {
            $sql = "SELECT COUNT(*) FROM usuario_finca WHERE rol = 'ADMIN'";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios administradores: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener usuarios colaboradores
     */
    public function getUsuariosColaboradores()
    {
        try {
            $sql = "SELECT COUNT(*) FROM usuario_finca WHERE rol = 'COLABORADOR'";
            return (int) Database::fetchColumn($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios colaboradores: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtener estadísticas completas del dashboard
     */
    public function getEstadisticasCompletas()
    {
        return array(
            'total_usuarios' => $this->getTotalUsuarios(),
                'usuarios_activos' => $this->getUsuariosActivos(),
                'total_fincas' => $this->getTotalFincas(),
                'total_potreros' => $this->getTotalPotreros(),
                'total_registros_ganaderos' => $this->getTotalRegistrosGanaderos(),
                'usuarios_administradores' => $this->getUsuariosAdministradores(),
                'usuarios_colaboradores' => $this->getUsuariosColaboradores(),
                'timestamp' => date('Y-m-d H:i:s')
        );
    }

    /**
     * Obtener datos operativos administrativos
     */
    public function getDatosOperativos()
    {
        return array(
            'usuarios' => array(
                'total' => $this->getTotalUsuarios(),
                'activos' => $this->getUsuariosActivos(),
                'administradores' => $this->getUsuariosAdministradores(),
                'colaboradores' => $this->getUsuariosColaboradores()
            ),
            'fincas' => array(
                'total_activas' => $this->getTotalFincas()
            ),
            'potreros' => array(
                'total_activos' => $this->getTotalPotreros()
            ),
            'registros' => array(
                'total' => $this->getTotalRegistrosGanaderos()
            ),
            'timestamp' => date('Y-m-d H:i:s')
        );
    }

    /**
     * Verificar conexión a la base de datos
     */
    public function verificarConexion()
    {
        try {
            $sql = "SELECT 1";
            Database::fetchColumn($sql);
            return true;
        } catch (Exception $e) {
            error_log("Error de conexión a BD: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener información de la base de datos
     */
    public function getInfoBaseDatos()
    {
        try {
            $sql = "SELECT version() as version_postgresql";
            $version = Database::fetch($sql);
            
            return array(
                'conectado' => true,
                'version_postgresql' => isset($version['version_postgresql']) ? $version['version_postgresql'] : 'Desconocida',
                'timestamp' => date('Y-m-d H:i:s')
            );
        } catch (Exception $e) {
            return array(
                'conectado' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            );
        }
    }

    /**
     * ===================================
     * ALERTAS CRÍTICAS PARA ADMIN
     * ===================================
     */

    /**
     * Usuarios registrados SIN ninguna finca
     */
    public function getUsuariosSinFinca()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT u.id_usuario) as total_usuarios_sin_finca
                FROM usuario u
                LEFT JOIN usuario_finca uf ON u.id_usuario = uf.id_usuario
                WHERE uf.id_usuario IS NULL
                  AND u.activo = TRUE
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios sin finca: " . $e->getMessage());
            return array('total_usuarios_sin_finca' => 0);
        }
    }

    /**
     * Usuarios INACTIVOS (sin sesión en 30+ días)
     */
    public function getUsuariosInactivos()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as usuarios_inactivos_30dias,
                    COUNT(CASE WHEN ultima_sesion < CURRENT_DATE - INTERVAL '60 days' THEN 1 END) as usuarios_inactivos_60dias,
                    COUNT(CASE WHEN ultima_sesion < CURRENT_DATE - INTERVAL '90 days' THEN 1 END) as usuarios_inactivos_90dias
                FROM usuario
                WHERE activo = TRUE
                  AND ultima_sesion IS NOT NULL
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios inactivos: " . $e->getMessage());
            return array(
                'usuarios_inactivos_30dias' => 0,
                'usuarios_inactivos_60dias' => 0,
                'usuarios_inactivos_90dias' => 0
            );
        }
    }

    /**
     * Usuarios registrados pero NUNCA iniciaron sesión
     */
    public function getUsuariosNuncaLogueados()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as usuarios_nunca_logueados
                FROM usuario
                WHERE ultima_sesion IS NULL
                  AND activo = TRUE
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios nunca logueados: " . $e->getMessage());
            return array('usuarios_nunca_logueados' => 0);
        }
    }

    /**
     * Fincas activas SIN potreros
     */
    public function getFincasSinPotreros()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as fincas_sin_potreros
                FROM finca f
                LEFT JOIN potrero p ON f.id_finca = p.id_finca
                WHERE f.estado = 'ACTIVA'
                  AND p.id_potrero IS NULL
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener fincas sin potreros: " . $e->getMessage());
            return array('fincas_sin_potreros' => 0);
        }
    }

    /**
     * Fincas sin actividad reciente (sin registros ganaderos)
     */
    public function getFincasSinActividad()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(DISTINCT f.id_finca) as fincas_sin_actividad_30dias
                FROM finca f
                INNER JOIN potrero pt ON f.id_finca = pt.id_finca
                LEFT JOIN registro_ganadero rg ON pt.id_potrero = rg.id_potrero 
                    AND rg.fecha_registro >= CURRENT_DATE - INTERVAL '30 days'
                WHERE f.estado = 'ACTIVA'
                  AND rg.id_registro IS NULL
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener fincas sin actividad: " . $e->getMessage());
            return array('fincas_sin_actividad_30dias' => 0);
        }
    }

    /**
     * Usuarios sin datos demográficos completos
     */
    public function getUsuariosSinDemografia()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(*) as usuarios_sin_demografia
                FROM usuario u
                LEFT JOIN demografia_usuario d ON u.id_usuario = d.id_usuario
                WHERE u.activo = TRUE
                  AND (d.id_demografia IS NULL 
                       OR d.genero IS NULL 
                       OR d.edad IS NULL 
                       OR d.grupo_etnico IS NULL)
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener usuarios sin demografía: " . $e->getMessage());
            return array('usuarios_sin_demografia' => 0);
        }
    }

    /**
     * Fincas con área sospechosa o sin calcular
     */
    public function getFincasAreaSospechosa()
    {
        try {
            $sql = "
                SELECT 
                    COUNT(CASE WHEN area_hectareas IS NULL THEN 1 END) as sin_area_calculada,
                    COUNT(CASE WHEN area_hectareas < 0.5 THEN 1 END) as area_muy_pequeña,
                    COUNT(CASE WHEN area_hectareas > 500 THEN 1 END) as area_muy_grande
                FROM finca
                WHERE estado = 'ACTIVA'
            ";
            
            return Database::fetch($sql);
        } catch (Exception $e) {
            error_log("Error al obtener fincas con área sospechosa: " . $e->getMessage());
            return array(
                'sin_area_calculada' => 0,
                'area_muy_pequeña' => 0,
                'area_muy_grande' => 0
            );
        }
    }

    /**
     * Obtener resumen ejecutivo de alertas (widget compacto)
     */
    public function getResumenAlertas()
    {
        try {
            $alertas = array(
                'usuarios' => array(
                    'sin_finca' => $this->getUsuariosSinFinca()['total_usuarios_sin_finca'] ?? 0,
                    'inactivos_30d' => $this->getUsuariosInactivos()['usuarios_inactivos_30dias'] ?? 0,
                    'nunca_logueados' => $this->getUsuariosNuncaLogueados()['usuarios_nunca_logueados'] ?? 0,
                    'sin_demografia' => $this->getUsuariosSinDemografia()['usuarios_sin_demografia'] ?? 0
                ),
                'fincas' => array(
                    'sin_potreros' => $this->getFincasSinPotreros()['fincas_sin_potreros'] ?? 0,
                    'sin_actividad_30d' => $this->getFincasSinActividad()['fincas_sin_actividad_30dias'] ?? 0
                ),
                'calidad' => array(
                    'sin_demografia' => $this->getUsuariosSinDemografia()['usuarios_sin_demografia'] ?? 0,
                    'areas_sospechosas' => ($this->getFincasAreaSospechosa()['sin_area_calculada'] ?? 0) + 
                                         ($this->getFincasAreaSospechosa()['area_muy_pequeña'] ?? 0) + 
                                         ($this->getFincasAreaSospechosa()['area_muy_grande'] ?? 0)
                ),
                'timestamp' => date('Y-m-d H:i:s')
            );

            return $alertas;
        } catch (Exception $e) {
            error_log("Error al obtener resumen de alertas: " . $e->getMessage());
            return array(
                'usuarios' => array('sin_finca' => 0, 'inactivos_30d' => 0, 'nunca_logueados' => 0, 'sin_demografia' => 0),
                'fincas' => array('sin_potreros' => 0, 'sin_actividad_30d' => 0),
                'calidad' => array('sin_demografia' => 0, 'areas_sospechosas' => 0),
                'timestamp' => date('Y-m-d H:i:s')
            );
        }
    }
}
