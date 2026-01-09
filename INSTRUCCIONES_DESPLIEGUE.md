# 🚀 INSTRUCCIONES DE DESPLIEGUE - FUTBOLISTA INVERSOR
## Despliegue seguro desde Git a Hostinger

---

## 🔒 SEGURIDAD: Uso de Variables de Entorno

**✅ IMPLEMENTADO:** El proyecto ahora usa un archivo `.env` para las credenciales SMTP, que **NO se sube a Git**.

**Archivos relacionados:**
- `.env` - Contiene credenciales reales (NO en Git, ya en .gitignore)
- `.env.example` - Plantilla sin credenciales (SÍ se sube a Git)
- `process-form.php` - Lee automáticamente desde `.env`

---

## ✅ CAMBIOS IMPLEMENTADOS (Completado)

### Archivos Modificados:
- ✅ **index.html** - Correcciones de seguridad
  - Enlaces `javascript:;` reemplazados por `#`
  - Protección `rel="noopener noreferrer"` en enlaces externos
  - Formulario configurado con validación HTML5
  - iframe de Vimeo con permisos reducidos
  - Botón "Comprar" del curso Basic conectado a Teachable

### Archivos Creados:
- ✅ **.htaccess** - Headers de seguridad HTTP completos
- ✅ **robots.txt** - Control de indexación
- ✅ **.well-known/security.txt** - RFC 9116
- ✅ **process-form.php** - Backend del formulario usando variables de entorno
- ✅ **.env** - Credenciales SMTP (NO en Git)
- ✅ **.env.example** - Plantilla para credenciales
- ✅ **.gitignore** - Actualizado para excluir archivos sensibles

---

## 📋 PASOS PARA DESPLEGAR DESDE GIT A HOSTINGER

### MÉTODO 1: Despliegue Automático desde Git (RECOMENDADO)

#### PASO 0: Subir código a Git

1. **Crear commit con los cambios:**
   ```bash
   git add .
   git commit -m "feat: Security audit and Teachable integration"
   git push origin main
   ```

   **IMPORTANTE:** El archivo `.env` NO se subirá (está en .gitignore) ✅

#### PASO 1: Configurar Git en Hostinger

1. Accede al panel de Hostinger: https://hpanel.hostinger.com
2. Ve a: **Sitios web** > **Tu dominio** > **Git**
3. Conecta tu repositorio:
   - **Repositorio:** URL de tu repositorio Git
   - **Rama:** main
   - **Directorio:** public_html (o el directorio de tu sitio)
4. Haz clic en **Conectar**
5. Hostinger clonará automáticamente tu repositorio

#### PASO 2: Crear archivo .env en Hostinger

**IMPORTANTE:** Como `.env` no está en Git, debes crearlo manualmente en el servidor.

**Opción A: Usando File Manager de Hostinger**
1. Ve a: **Archivos** > **File Manager**
2. Navega a tu directorio del sitio (public_html)
3. Haz clic en **Nuevo archivo**
4. Nombre: `.env`
5. Copia el contenido desde tu archivo local `.env` o usa `.env.example` como referencia:
   ```
   SMTP_HOST=smtp.hostinger.com
   SMTP_PORT=465
   SMTP_ENCRYPTION=ssl
   SMTP_USERNAME=info@futbolistainversor.com
   SMTP_PASSWORD=Jorg190202!
   SMTP_FROM_EMAIL=info@futbolistainversor.com
   SMTP_FROM_NAME=Formulario Web
   CONTACT_EMAIL=info@futbolistainversor.com
   CONTACT_NAME=Futbolista Inversor
   ```
6. Guarda el archivo

**Opción B: Usando SSH**
```bash
ssh tu_usuario@tu_dominio.com
cd public_html
nano .env
# Pega el contenido anterior
# Guarda con Ctrl+X, luego Y, luego Enter
```

#### PASO 3: Instalar PHPMailer en Hostinger

**Opción A: Usando Composer (Recomendado)**
```bash
# Conecta por SSH a tu hosting Hostinger
ssh tu_usuario@tu_dominio.com

# Navega al directorio de tu sitio web
cd public_html

# Instala PHPMailer con Composer
composer require phpmailer/phpmailer
```

**Opción B: Instalación manual**
1. Descarga PHPMailer desde: https://github.com/PHPMailer/PHPMailer/releases
2. Extrae la carpeta `PHPMailer` en tu servidor
3. Actualiza la línea 141 de `process-form.php` con la ruta correcta:
   ```php
   require 'PHPMailer/src/PHPMailer.php';
   require 'PHPMailer/src/SMTP.php';
   require 'PHPMailer/src/Exception.php';
   ```

### PASO 4: Activar PHPMailer en process-form.php

**Si usaste despliegue desde Git, el archivo ya está en el servidor.**

1. Abre el archivo `process-form.php` en Hostinger (File Manager o SSH)
2. **Descomenta las líneas 169-240** (elimina `/*` al inicio y `*/` al final)
3. **Comenta las líneas 242-280** (la sección "Opción 2: mail() de PHP")

El archivo leerá automáticamente las credenciales desde `.env`:
- ✅ Servidor, puerto, usuario y contraseña desde variables de entorno
- ✅ No hay contraseñas hardcodeadas en el código
- ✅ Fácil actualizar cambiando solo el archivo .env

---

### MÉTODO 2: Subir archivos manualmente (Alternativa)

**Si prefieres no usar Git, puedes subir los archivos por FTP:**

**Archivos que debes subir:**
```
/public_html/
├── index.html (modificado)
├── .htaccess (nuevo)
├── robots.txt (nuevo)
├── process-form.php (nuevo)
├── .env (nuevo - IMPORTANTE: crear manualmente)
├── .well-known/
│   └── security.txt (nuevo)
└── assets/ (carpeta existente)
```

**Métodos de subida:**
- **FTP/SFTP:** Usar FileZilla o Cyberduck
- **File Manager de Hostinger:** Panel de control > Archivos

**⚠️ IMPORTANTE:** No olvides crear el archivo `.env` con tus credenciales.

---

### PASO 5: Verificar permisos de archivos

```bash
# En tu servidor Hostinger, ejecuta:
chmod 644 index.html
chmod 644 .htaccess
chmod 644 robots.txt
chmod 755 process-form.php
chmod 644 .env
chmod 644 .well-known/security.txt
```

**IMPORTANTE:** El archivo `.env` debe tener permisos 644 para que PHP pueda leerlo, pero no debe ser accesible desde web.

### PASO 6: Probar el formulario de contacto

1. Abre tu sitio web: https://futbolistainversor.com
2. Ve a la sección "Contacto"
3. Completa el formulario con datos de prueba
4. Verifica que recibas el email en info@futbolistainversor.com

**Si hay errores:**
- Revisa los logs en `form_errors.log` (se crea automáticamente)
- Verifica las credenciales SMTP en el panel de Hostinger
- Comprueba que PHPMailer esté instalado correctamente

---

## ⚠️ TAREAS PENDIENTES

### 1. Configurar cursos Premium y Deluxe en Teachable

**Cursos pendientes:**
- Premium (895 €)
- Deluxe (1295 €)

**Cuando tengas los enlaces:**
1. Abre `index.html`
2. Busca las líneas **932** y **954**
3. Reemplaza:
   ```html
   <!-- Línea 932 - Premium -->
   <a href="#" class="btn btn-inverse btn-theme btn-block">Comprar</a>

   <!-- Cambiar por: -->
   <a href="https://jorge-alonso-s-school.teachable.com/purchase?product_id=XXXXX"
      target="_blank" rel="noopener noreferrer"
      class="btn btn-inverse btn-theme btn-block">Comprar</a>
   ```

### 2. Verificar certificado SSL

1. Abre tu sitio: https://futbolistainversor.com
2. Verifica el candado verde en el navegador
3. El .htaccess forzará redirección automática HTTP → HTTPS

### 3. Ejecutar auditorías de seguridad

**Después del despliegue, verifica:**

1. **Mozilla Observatory**
   - URL: https://observatory.mozilla.org/
   - Objetivo: Grado A o A+

2. **SecurityHeaders.com**
   - URL: https://securityheaders.com/
   - Objetivo: Grado A

3. **SSL Labs**
   - URL: https://www.ssllabs.com/ssltest/
   - Objetivo: Grado A o A+

4. **Google PageSpeed Insights**
   - URL: https://pagespeed.web.dev/
   - Verifica rendimiento y mejores prácticas

---

## 🔒 SEGURIDAD POST-DESPLIEGUE

### Cambiar contraseña SMTP (Recomendado después del despliegue)

Si deseas cambiar la contraseña por seguridad:

1. **En el panel de Hostinger:**
   - Ve a Emails > info@futbolistainversor.com
   - Cambia la contraseña

2. **Actualiza SOLO el archivo .env:**
   ```bash
   # Edita el archivo .env en el servidor
   SMTP_PASSWORD=NUEVA_CONTRASEÑA_AQUÍ
   ```

   ✅ **Ventaja:** No necesitas tocar el código PHP, solo actualizar el archivo .env

### Proteger archivos sensibles

El archivo `.htaccess` ya protege:
- ✅ Archivos .env, .log, .sql
- ✅ Directorio .git
- ✅ Directorio .venv_FI
- ✅ process-form.php (bloqueado en robots.txt)

### Monitorear logs

- **Logs de formulario:** `form_errors.log` (creado automáticamente)
- **Rate limiting:** `rate_limit.json` (creado automáticamente)
- **Logs Apache/Nginx:** Panel de Hostinger > Logs

---

## 📊 CHECKLIST DE DESPLIEGUE

Marca cada tarea al completarla:

- [ ] PHPMailer instalado en Hostinger
- [ ] PHPMailer activado en process-form.php (líneas descomentadas)
- [ ] Archivos subidos vía FTP/File Manager
- [ ] Permisos de archivos configurados (755/644)
- [ ] Certificado SSL activo (HTTPS funcionando)
- [ ] Formulario de contacto probado y funcionando
- [ ] Email de prueba recibido en info@futbolistainversor.com
- [ ] Botón "Comprar" del curso Basic redirige a Teachable
- [ ] Auditoría Mozilla Observatory ejecutada (Objetivo: A/A+)
- [ ] Auditoría SecurityHeaders ejecutada (Objetivo: A)
- [ ] Auditoría SSL Labs ejecutada (Objetivo: A/A+)
- [ ] robots.txt accesible: https://futbolistainversor.com/robots.txt
- [ ] security.txt accesible: https://futbolistainversor.com/.well-known/security.txt
- [ ] Headers de seguridad activos (verificar con SecurityHeaders.com)

---

## 🐛 TROUBLESHOOTING

### El formulario no envía emails

**Problema:** Error 500 o formulario no funciona

**Soluciones:**
1. Verifica que PHPMailer esté instalado:
   ```bash
   ls vendor/phpmailer/phpmailer/
   ```
2. Revisa `form_errors.log` para ver el error exacto
3. Verifica credenciales SMTP en el panel de Hostinger
4. Comprueba que el puerto 465 esté abierto (SSL)

### Headers de seguridad no activos

**Problema:** SecurityHeaders.com muestra F o D

**Soluciones:**
1. Verifica que `.htaccess` esté subido correctamente
2. Comprueba que Apache tenga `mod_headers` activo
3. Contacta soporte de Hostinger si es necesario

### Certificado SSL no funciona

**Problema:** Navegador muestra advertencia de seguridad

**Soluciones:**
1. En el panel de Hostinger: SSL > Activar SSL gratuito
2. Espera 10-15 minutos para propagación
3. Limpia caché del navegador

### Rate limiting muy estricto

**Problema:** Usuarios bloqueados por envíos

**Soluciones:**
1. Abre `process-form.php` línea 47
2. Cambia `$maxAttempts = 5;` a un valor mayor (ej: 10)
3. Cambia `$timeWindow = 900;` (15 min) si es necesario

---

## 📞 SOPORTE

**Email:** info@futbolistainversor.com
**Soporte Hostinger:** https://www.hostinger.com/support
**Tutorial PHPMailer:** https://www.hostinger.com/es/tutoriales/enviar-emails-usando-php-mail

---

## 📝 NOTAS IMPORTANTES

1. **Contraseña SMTP:** Por seguridad, considera cambiarla después del despliegue
2. **NIF en Aviso Legal:** Es obligatorio por ley LSSI - NO eliminar
3. **Backup:** Guarda una copia local antes de subir cambios
4. **Git:** Considera hacer commit de los cambios (sin subir process-form.php al repositorio público)

---

**Última actualización:** 2026-01-08
**Estado:** ✅ Listo para despliegue
**Auditoría:** Completada
