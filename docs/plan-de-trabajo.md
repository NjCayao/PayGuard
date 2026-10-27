# PayGuard — Plan de trabajo

**Periodo:** lunes 26 oct. al miércoles 4 nov. 2026 · **Entrega:** 4 nov., 9:00 p. m. (hora de Lima)
**Meta interna:** entregar el **martes 3 nov. por la noche** y dejar el miércoles solo como reserva.

## Forma de trabajo

- El trabajo se divide en **sprints**. Cada sprint tiene su propia rama (`sprint-N-nombre`).
- Antes de programar cada sprint: **auditoría corta de solo lectura** con Muse Code, conversamos las decisiones y recién se arma el pedido de trabajo.
- Muse Code programa y hace commits en la rama. Nilson hace el push de la rama, prueba en local, hace el merge a `main` y despliega.
- **Una sesión nueva de Muse Code por sprint.** Un sprint a la vez: no se empieza el siguiente sin cerrar el anterior.
- Si un sprint se atrasa, se recorta alcance del panel antes que del flujo principal (captura → verificación → respuesta).

---

## Sprint 0 — Preparación (26 oct.) · Cerrado
- Cuenta en dev.meta.ai, tarjeta registrada y **$150 de créditos** activos.
- Documentación: PRD, plan, base de datos, arquitectura y `AGENTS.md`.
- Repo `NjCayao/PayGuard` con Laravel 13, Sanctum, React y Tailwind.
- Subdominio `payguard.micaleth.com` apuntando al VPS.
- App de WhatsApp creada con número de prueba. Negocio **verificado** por Meta (27 oct.) y primer mensaje de prueba recibido.
- Muse Code instalado.

## Sprint 1 — Base del sistema (27 oct.) · En prueba
- Modelo de datos completo (6 tablas), registro e inicio de sesión del dueño, panel base y pantalla Cajeros. Rama `dia-2-base`.
- Pendiente: prueba local, merge y **primer despliegue** en `payguard.micaleth.com` (necesario para el webhook del Sprint 3).

## Sprint 2 — App Android y pagos recibidos (28 oct.)
- API para la app: inicio de sesión del dispositivo y envío de notificaciones.
- Lector de notificaciones de Yape y Plin (reglas simples; texto original guardado).
- App Flutter en `mobile/`: login, permiso de notificaciones, servicio en primer plano, envío con reintento, pantalla de estado.
- Panel: Pagos recibidos y Dispositivo.
- Taller de Meta 11:00 a. m. (Agents 102).

## Sprint 3 — WhatsApp (29 oct.)
- Token permanente de WhatsApp (usuario del sistema).
- Webhook de WhatsApp Cloud: verificación, firma y mensajes repetidos ignorados.
- Identificar al cajero por su número; responder "número no registrado" a desconocidos.
- Descargar imágenes y audios; enviar respuestas de texto.
- Solicitar la plantilla de alerta para el dueño.

## Sprint 4 — IA y verificación, el corazón (30 oct.)
- Cliente de Meta Model API (compatible con el SDK de OpenAI).
- Muse Spark: leer la captura y devolver JSON con esquema fijo.
- Motor de verificación: comparar con pagos recibidos (monto, nombre, hora, anti-reúso, misma imagen repetida).
- Resultados: verificado / aún no llega (revisión automática por 2 minutos) / no coincide, con motivo.
- Respuesta al cajero en español claro.
- Taller de Meta 11:00 a. m. (Fireworks).

## Sprint 5 — Voz, alertas y panel (31 oct.)
- Notas de voz: Muse Voice Transcribe + Muse Spark para entender la consulta.
- Consultas por texto ("¿llegó un Yape de 50 de Juan?").
- Alerta al dueño cuando algo es sospechoso (plantilla o panel).
- Panel: Inicio con resumen del día, Verificaciones (captura, datos leídos y motivo) y Configuración.

## Sprint 6 — Pruebas reales y despliegue (1 nov.)
- Pruebas de punta a punta con pagos reales propios de Yape y Plin y capturas editadas.
- Ajustes del motor según lo observado.
- Despliegue completo y pruebas en el servidor.
- APK de la app.

## Sprint 7 — Pulido y README (2 nov.)
- Corrección de errores y textos de pantallas.
- Datos de demostración (sin datos reales de clientes).
- README en inglés: qué es, cómo funciona, modelos de Meta usados, cómo instalarlo y qué se construyó durante el hackathon.
- Revisión de seguridad: llaves fuera del repo, historial limpio.

## Sprint 8 — Video y entrega (3 nov.)
- Guion del video de 3 minutos (español con subtítulos en inglés, o inglés).
- Grabación de pantalla: panel, app y WhatsApp funcionando.
- Repo en público y revisión final.
- **Enviar la entrega esta noche.**

## Reserva — Mié 4 nov.
- Solo correcciones de último minuto. Cierre: 9:00 p. m. hora de Lima.
