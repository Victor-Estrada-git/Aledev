<?php // test_db.php
require_once 'conexion_db.php';
if ($conexion) {
    echo "Conexión a la base de datos ($nombre_bd) exitosa!";
} else {
    echo "Falló la conexión a la base de datos.";
}
?>