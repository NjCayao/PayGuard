# PayGuard — Plan de trabajo

**Periodo:** lunes 26 oct. al miércoles 4 nov. 2026 · **Entrega:** 4 nov., 9:00 p. m. (hora de Lima)
**Meta interna:** entregar el **martes 3 nov. por la noche** y dejar el miércoles solo como reserva.

## Forma de trabajo

- Cada día es una **entrega** con su propia rama (`dia-1-base`, `dia-2-...`).
- Antes de programar cada entrega: **auditoría corta de solo lectura** con Muse Code, conversamos y recién se arma el pedido de trabajo.
- Muse Code programa y hace commits y push en la rama. Nilson prueba en local, hace el merge a `main` y despliega.
- Una entrega a la vez: no se empieza la siguiente sin cerrar la anterior.
- Si un día se atrasa, se recorta alcance del panel antes que del flujo principal (captura → verificación → respuesta).

---

## Día 1 — Lun 26 oct. · Preparación y documentación
- [x] Cuenta en dev.meta.ai, tarjeta registrada, formulario de créditos enviado.
- [x] PRD.
- [ ] Plan de trabajo, base de datos, arquitectura y `AGENTS.md`.
- [ ] Repo privado en GitHub `payguard` con `.gitignore` correcto (sin `.env`).
- [ ] Laravel 13 instalado a mano en Laragon + React + Tailwind + Sanctum; primer commit.
- [ ] Subdominio `payguard.micaleth.com` creado en Hostinger.
- [ ] App de WhatsApp creada en developers.facebook.com (número de prueba, token, números de destino).
- [ ] Instalar Muse Code (sin usarlo hasta que lleguen los créditos).

## Día 2 — Mar 27 oct. · Base del sistema y servidor
- Modelo de datos completo (migraciones de todas las tablas).
- Registro e inicio de sesión del dueño (negocio + usuario).
- Panel: diseño base, menú y pantalla Cajeros (alta, activar, desactivar).
- Servidor: sitio Nginx, base de datos propia, HTTPS y primer despliegue en `payguard.micaleth.com`.
- Taller de Meta 11:00 a. m. (Agents 101).

## Día 3 — Mié 28 oct. · App Android y pagos recibidos
- API para la app: inicio de sesión del dispositivo y envío de notificaciones.
- Lector de notificaciones de Yape y Plin (reglas simples; texto original guardado).
- App Flutter: login, permiso de notificaciones, servicio en primer plano, envío con reintento, pantalla de estado.
- Panel: pantalla Pagos recibidos y estado del dispositivo.
- Taller de Meta 11:00 a. m. (Agents 102).

## Día 4 — Jue 29 oct. · WhatsApp
- Webhook de WhatsApp Cloud: verificación, firma, mensajes repetidos ignorados.
- Identificar al cajero por su número; responder "número no registrado" a desconocidos.
- Descargar imágenes y audios de WhatsApp y guardarlos.
- Enviar respuestas de texto.
- Solicitar la plantilla de alerta para el dueño.

## Día 5 — Vie 30 oct. · IA y verificación (el corazón)
- Cliente de Meta Model API (compatible con el SDK de OpenAI).
- Muse Spark: leer la captura y devolver JSON con esquema fijo.
- Motor de verificación: comparar con pagos recibidos (monto, nombre, hora, anti-reúso, misma imagen repetida).
- Resultados: verificado / aún no llega (revisión automática por 2 minutos) / no coincide, con motivo.
- Respuesta al cajero redactada en español claro.
- Taller de Meta 11:00 a. m. (Fireworks).

## Día 6 — Sáb 31 oct. · Voz, alertas y panel
- Notas de voz: Muse Voice Transcribe + Muse Spark para entender la consulta.
- Consultas por texto ("¿llegó un Yape de 50 de Juan?").
- Alerta al dueño cuando algo es sospechoso (plantilla o panel).
- Panel: Inicio (resumen del día), Verificaciones (con captura, datos leídos y motivo), Configuración.

## Día 7 — Dom 1 nov. · Pruebas reales y despliegue
- Pruebas de punta a punta con pagos reales propios de Yape y Plin y capturas editadas.
- Ajustes del motor de verificación según lo que se vea.
- Despliegue completo y pruebas en el servidor.
- Generar el APK de la app.

## Día 8 — Lun 2 nov. · Pulido y README
- Corrección de errores y textos de pantallas.
- Datos de demostración (sin datos reales de clientes).
- README en inglés: qué es, cómo funciona, modelos de Meta usados, cómo instalarlo y qué se construyó durante el hackathon.
- Revisión de seguridad: llaves fuera del repo, historial limpio.

## Día 9 — Mar 3 nov. · Video y entrega
- Guion del video de 3 minutos (español con subtítulos en inglés, o inglés).
- Grabación de pantalla: panel, app y WhatsApp funcionando.
- Repo en público y revisión final.
- **Enviar la entrega esta noche.**

## Mié 4 nov. · Reserva
- Solo correcciones de último minuto. Cierre: 9:00 p. m. hora de Lima.
