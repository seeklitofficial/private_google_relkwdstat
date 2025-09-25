<?php

/**
 * Simple .env file loader
 * Loads environment variables from .env file
 */
class EnvLoader
{
    private static $loaded = false;
    private static $variables = [];

    /**
     * Load .env file and parse variables
     */
    public static function load($envPath = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = __DIR__ . '/../../.env';
        }

        if (!file_exists($envPath)) {
            throw new Exception('.env file not found at: ' . $envPath);
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }
                
                self::$variables[$key] = $value;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get environment variable
     */
    public static function get($key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return isset(self::$variables[$key]) ? self::$variables[$key] : $default;
    }

    /**
     * Get all loaded variables
     */
    public static function all()
    {
        if (!self::$loaded) {
            self::load();
        }

        return self::$variables;
    }
}
