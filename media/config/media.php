<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Настройки для управления медиа файлами
    |
    */

    /*
    |--------------------------------------------------------------------------
    | File Upload Settings
    |--------------------------------------------------------------------------
    */

    'upload' => [
        // Максимальный размер файла в килобайтах (по умолчанию 10 МБ)
        'max_size' => 10240, // 10 МБ в KB

        // Разрешенные типы файлов (MIME типы)
        'allowed_mime_types' => [
            // Изображения
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            'image/bmp',
            'image/tiff',
            
            // Видео
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/webm',
            'video/ogg',
            
            // Аудио
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/webm',
            
            // Документы
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
            
            // Архивы
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            
            // Текст
            'text/plain',
            'text/csv',
            'text/html',
            'text/css',
            'text/javascript',
            'application/json',
            'application/xml',
        ],

        // Разрешенные расширения файлов (альтернатива MIME типам)
        'allowed_extensions' => [
            // Изображения
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'tiff', 'ico',
            
            // Видео
            'mp4', 'mpeg', 'mov', 'avi', 'wmv', 'webm', 'ogg', 'flv',
            
            // Аудио
            'mp3', 'wav', 'ogg', 'webm', 'flac', 'aac',
            
            // Документы
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp',
            
            // Архивы
            'zip', 'rar', '7z', 'tar', 'gz',
            
            // Текст
            'txt', 'csv', 'html', 'css', 'js', 'json', 'xml', 'md',
        ],

        // Разрешить все типы файлов (если true, игнорируются allowed_mime_types и allowed_extensions)
        'allow_all_types' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination Settings
    |--------------------------------------------------------------------------
    */

    'pagination' => [
        // Количество файлов на странице по умолчанию
        'per_page_default' => 20,

        // Доступные варианты количества файлов на странице
        'per_page_options' => [10, 20, 30, 40, 50, 100],

        // Максимальное количество файлов на странице
        'per_page_max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    */

    'display' => [
        // Количество колонок в сетке по умолчанию
        'grid_columns' => [
            'default' => 5,
            'md' => 3,
            'lg' => 4,
            'xl' => 5,
        ],

        // Показывать превью для изображений
        'show_image_preview' => true,

        // Показывать превью для видео
        'show_video_preview' => true,

        // Размер превью изображений (в пикселях)
        'thumbnail_size' => [
            'width' => 300,
            'height' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Settings
    |--------------------------------------------------------------------------
    */

    'storage' => [
        // Диск для хранения файлов
        'disk' => 'public',

        // Базовая директория для загрузки
        'upload_path' => 'upload',

        // Путь к системным изображениям папок
        'folder_images_path' => 'img/system',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Требовать авторизацию для загрузки файлов
        'require_auth' => true,

        // Разрешить удаление файлов
        'allow_delete' => true,

        // Разрешить редактирование файлов
        'allow_edit' => true,

        // Разрешить перемещение файлов
        'allow_move' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Folder Settings
    |--------------------------------------------------------------------------
    */

    'folders' => [
        // Максимальная глубина вложенности папок
        'max_depth' => 10,

        // Разрешить создание папок
        'allow_create' => true,

        // Разрешить удаление папок
        'allow_delete' => true,

        // Минимальная длина названия папки
        'name_min_length' => 2,

        // Максимальная длина названия папки
        'name_max_length' => 100,
    ],
];

