<?php
// src/Core/Paths.php
namespace App\Core;

/**
 * Централизованное управление путями приложения
 */
class Paths
{
    // Базовые пути
    const BASE_PATH = '/var/www/www-root/data/site/vdestor.ru';
    const PUBLIC_PATH = self::BASE_PATH . '/public';
    const SRC_PATH = self::BASE_PATH . '/src';
    const CONFIG_PATH = '/etc/vdestor/config';
    const LOG_PATH = '/var/www/www-root/data/logs';
    
    // Относительные пути для URL
    const ASSETS_URL = '/assets/dist';
    const IMAGES_URL = '/images';
    const UPLOADS_URL = '/uploads';
    
    /**
     * Получить полный путь к файлу
     */
    public static function get(string $type, string $path = ''): string
    {
        $basePath = match($type) {
            'base' => self::BASE_PATH,
            'public' => self::PUBLIC_PATH,
            'src' => self::SRC_PATH,
            'config' => self::CONFIG_PATH,
            'log' => self::LOG_PATH,
            'views' => self::SRC_PATH . '/views',
            'controllers' => self::SRC_PATH . '/Controllers',
            'services' => self::SRC_PATH . '/Services',
            default => self::BASE_PATH
        };
        
        return $basePath . ($path ? '/' . ltrim($path, '/') : '');
    }
    
    /**
     * Получить URL для ассетов
     */
    public static function asset(string $path): string
    {
        return self::ASSETS_URL . '/' . ltrim($path, '/');
    }
    
    /**
     * Проверить существование пути
     */
    public static function exists(string $type, string $path = ''): bool
    {
        return file_exists(self::get($type, $path));
    }
}