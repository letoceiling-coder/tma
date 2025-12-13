# Настройка символической ссылки для Storage на Production

## Проблема
Файлы из `storage/app/public` не доступны через веб-интерфейс (ошибка 403 Forbidden).

## Решение 1: Создать символическую ссылку (рекомендуется)

### На Linux/Unix сервере:
```bash
cd /path/to/project
php artisan storage:link
```

### На Windows сервере:
```bash
cd C:\path\to\project
php artisan storage:link
```

### Проверка:
```bash
ls -la public/storage
# Должна быть символическая ссылка на storage/app/public
```

## Решение 2: Настройка веб-сервера

### Для Nginx:
Добавьте в конфигурацию сервера:

```nginx
location /storage {
    alias /path/to/project/storage/app/public;
    try_files $uri =404;
    
    # Разрешить доступ к файлам
    location ~ \.(jpg|jpeg|png|gif|webp|pdf|doc|docx|txt|zip|rar|7z)$ {
        access_log off;
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
}
```

### Для Apache:
Убедитесь, что в `.htaccess` в `public/storage/` есть:

```apache
Options -Indexes
<IfModule mod_rewrite.c>
    RewriteEngine Off
</IfModule>

<FilesMatch "\.(jpg|jpeg|png|gif|webp|pdf|doc|docx|txt|zip|rar|7z)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

## Решение 3: Использование Laravel роута (уже настроено)

Если символическая ссылка не работает, файлы будут отдаваться через Laravel роут `/storage/{path}`.

**Проверка работы роута:**
- Откройте в браузере: `https://wows-in.ru/storage/support/attachments/test.png`
- Если файл существует, он должен загрузиться
- Если 404 - файл не найден
- Если 403 - проблема с правами доступа

## Права доступа

Убедитесь, что веб-сервер имеет права на чтение файлов:

```bash
# Linux/Unix
chmod -R 755 storage/app/public
chown -R www-data:www-data storage/app/public

# Проверка
ls -la storage/app/public/support/attachments/
```

## Отладка

Проверьте логи Laravel:
```bash
tail -f storage/logs/laravel.log | grep -i storage
```

Проверьте, что файлы действительно сохраняются:
```bash
ls -la storage/app/public/support/attachments/
```

## Автоматическое создание символической ссылки при деплое

Добавьте в скрипт деплоя:
```bash
php artisan storage:link --force
```

