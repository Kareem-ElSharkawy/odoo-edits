<?php
/**
 * ═══════════════════════════════════════════════════════════════════════
 * ERP Autoloader
 * ═══════════════════════════════════════════════════════════════════════
 * 
 * Autoloader for ERP classes
 * 
 * @version 2.0
 * @author Simaat Development Team
 * @date 2026-01-31
 */

spl_autoload_register(function ($className) {
    // ERP classes namespace
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
    
    // Map class names to file paths
    $classMap = [
        'ERP_Interface' => $baseDir . 'Interface' . DIRECTORY_SEPARATOR . 'ERP_Interface.php',
        'ERP_Factory' => $baseDir . 'Factory' . DIRECTORY_SEPARATOR . 'ERP_Factory.php',
    ];
    
    // Check if class is in our map
    if (isset($classMap[$className])) {
        $file = $classMap[$className];
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
    
    // Try to find class in src directory structure
    $className = str_replace('\\', DIRECTORY_SEPARATOR, $className);
    $file = $baseDir . $className . '.php';
    
    if (file_exists($file)) {
        require_once $file;
        return;
    }
    
    // Try in Systems subdirectory
    $file = $baseDir . 'Systems' . DIRECTORY_SEPARATOR . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
        return;
    }
});





