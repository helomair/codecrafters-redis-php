<?php
error_reporting(E_ALL);

spl_autoload_register(function ($namespace) {
    $namespace = str_replace("\\", "/", $namespace);
    $namespace = ltrim($namespace, "/");

    if ($lastBackSlash = strrpos($namespace, "/")) {
        $path = substr($namespace, 0, $lastBackSlash);
        $className = substr($namespace, $lastBackSlash + 1);
    }

    $path = (empty($path)) ? "$className.php" : $path . DIRECTORY_SEPARATOR . "$className.php";

    if (file_exists($path))
        require_once $path;
});
