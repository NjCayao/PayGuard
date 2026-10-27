# PayGuard — Guía de despliegue

Servidor: VPS Ubuntu 24.04 (el mismo de micaleth). Sitio: `payguard.micaleth.com`.
Carpeta: `/var/www/payguard`. Todo lo de PayGuard vive en archivos propios;
no se toca nada de micaleth, mitaller ni mimarket.

Requisitos previos en el servidor: Nginx, PHP 8.3-FPM con extensiones
`mysqlnd`, `mbstring`, `xml`, `curl` y `zip`, MySQL 8, Supervisor, Certbot y
Node.js 20+ (solo para compilar el panel).

## 1. Base de datos

```sql
CREATE DATABASE payguard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'payguard'@'localhost' IDENTIFIED BY 'PONER_CLAVE_SEGURA';
GRANT ALL PRIVILEGES ON payguard.* TO 'payguard'@'localhost';
FLUSH PRIVILEGES;
```

## 2. Código

```bash
cd /var/www
git clone https://github.com/NjCayao/PayGuard.git payguard
cd payguard
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

## 3. Archivo .env

Ajustar al menos estos valores (el resto queda como en `.env.example`):

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://payguard.micaleth.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=payguard
DB_USERNAME=payguard
DB_PASSWORD=PONER_CLAVE_SEGURA

SANCTUM_STATEFUL_DOMAINS=payguard.micaleth.com
```

Las llaves `META_MODEL_*` y `WHATSAPP_*` se llenan cuando se usen (Días 4-5).
Nunca se commitean: solo viven en este archivo.

```bash
php artisan migrate --force
sudo chown -R www-data:www-data storage bootstrap/cache
```

## 4. Nginx (sitio propio)

Archivo `/etc/nginx/sites-available/payguard`:

```nginx
server {
    listen 80;
    server_name payguard.micaleth.com;
    root /var/www/payguard/public;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Activar y recargar (no afecta a los otros sitios):

```bash
sudo ln -s /etc/nginx/sites-available/payguard /etc/nginx/sites-enabled/payguard
sudo nginx -t
sudo systemctl reload nginx
```

## 5. HTTPS con Let's Encrypt

```bash
sudo certbot --nginx -d payguard.micaleth.com
```

Certbot edita solo el bloque de PayGuard y deja la renovación automática.

## 6. Worker de cola (Supervisor)

Archivo `/etc/supervisor/conf.d/payguard-worker.conf`:

```ini
[program:payguard-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/payguard/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/payguard/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start payguard-worker:*
```

## 7. Tareas programadas (cron del scheduler)

```bash
sudo crontab -u www-data -e
```

Agregar esta línea:

```
* * * * * cd /var/www/payguard && php artisan schedule:run >> /dev/null 2>&1
```

## 8. Comprobar

- Abrir `https://payguard.micaleth.com/app` y crear una cuenta.
- Agregar un cajero y recargar: debe seguir ahí.
- `sudo supervisorctl status payguard-worker:*` muestra RUNNING.

## 9. Actualizar el sitio

```bash
cd /var/www/payguard
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci
npm run build
sudo supervisorctl restart payguard-worker:*
```
