# PayGuard — PRD (Documento de requisitos del producto)

**Versión:** 1.1 · **Fecha:** 27 oct. 2026 · **Autor:** Nilson Cayao
**Evento:** Meta Global AI Developer Hackathon — Track Indie Developer · Tema "Build for Your World"
**Construido desde cero durante el hackathon (26 oct. – 4 nov. 2026).**

---

## 1. Resumen

PayGuard es un asistente de WhatsApp que protege a los pequeños negocios de Latinoamérica de los **pagos falsos con billeteras digitales** (solo Yape en esta versión; Plin queda preparado para después).

El cajero reenvía a PayGuard la captura que le muestra el cliente (o le manda una nota de voz) y en segundos recibe la respuesta: **pago real** o **no coincide**, con el motivo. PayGuard no "adivina" mirando la imagen: **compara los datos de la captura con la notificación real** que llegó al celular del negocio, capturada por una app Android propia.

## 2. El problema

- En los pequeños negocios (bodegas, restaurantes, tiendas) se cobra con Yape y Plin. El cliente "demuestra" el pago mostrando o enviando una **captura de pantalla**.
- Las **capturas falsas o editadas** son una estafa muy común: el negocio entrega el producto y el dinero nunca llegó.
- El cajero no puede revisar el celular del dueño cada vez que alguien paga, y a veces el celular que recibe los pagos ni siquiera está en caja.
- Yape y Plin **no tienen una API pública** para verificar pagos. La única prueba real es la **notificación** que llega al celular del negocio.

## 3. Usuarios

| Usuario | Qué hace |
|---|---|
| **Dueño del negocio** | Crea la cuenta, instala la app en el celular que recibe los pagos, registra a sus cajeros y revisa todo desde el panel web. |
| **Cajero autorizado** | Consulta a PayGuard por WhatsApp desde su número registrado. No entra al panel. |
| **Cliente final** | No usa PayGuard. Solo muestra o envía su captura de pago como siempre. |

## 4. Cómo funciona (flujo principal)

1. **La app Android** (instalada en el celular del negocio que recibe los pagos) lee cada notificación de Yape y la envía al servidor: billetera, monto, nombre de quien pagó, código de seguridad y hora.
2. **El cliente paga** y muestra o envía su captura al cajero.
3. **El cajero reenvía la captura** al WhatsApp de PayGuard (o envía un audio: *"¿llegó un Yape de 50 soles de Juan?"*).
4. **PayGuard identifica el negocio** por el número del cajero (solo responde a números autorizados).
5. **Muse Spark lee la captura** y devuelve en JSON: billetera, monto, nombre del destinatario, fecha y hora, número de operación, código de seguridad y señales de posible edición.
   Si es audio, **Muse Voice Transcribe** lo convierte en texto y Muse Spark extrae la consulta.
6. **PayGuard busca la notificación real** que coincida. La captura muestra a quién se pagó, no quién pagó, así que el nombre de quien paga no se compara. Se compara: código de seguridad (si ambos lo tienen, debe coincidir; es la prueba más fuerte), mismo monto, hora cercana (ventana configurable, por defecto ±10 minutos), destinatario igual al titular Yape del negocio y número de operación no usado antes.
7. **Responde al cajero por WhatsApp** en lenguaje simple:
   - ✅ **Pago verificado:** *"Llegó S/ 50,00 de Juan P. por Yape a las 3:15 p. m."*
   - ⏳ **Aún no llega:** no hay notificación todavía; PayGuard vuelve a revisar durante 2 minutos y avisa el resultado.
   - ❌ **No coincide:** explica el motivo (no llegó ningún pago así, el monto es distinto, la captura ya se usó antes, la fecha es vieja o la imagen muestra señales de edición).
8. **Si es sospechoso**, PayGuard avisa también al dueño por WhatsApp y lo registra en el panel.

## 5. Alcance de la versión del hackathon

### 5.1 Panel web (dueño)
- Registro e inicio de sesión.
- **Inicio:** resumen del día (pagos recibidos, verificaciones, sospechosos).
- **Pagos recibidos:** lista de notificaciones capturadas por la app.
- **Verificaciones:** cada consulta con su captura, los datos leídos por la IA, el resultado y el motivo.
- **Cajeros:** agregar, activar o desactivar cajeros (nombre y número de WhatsApp).
- **Dispositivo:** estado de la app Android vinculada (conectada, última notificación recibida).
- **Configuración:** ventana de tiempo para comparar, número del dueño para alertas.

### 5.2 App Android (Flutter)
- Inicio de sesión con la cuenta del dueño.
- Permiso para leer notificaciones (solo de Yape).
- Envío inmediato de cada notificación al servidor, con reintento si no hay internet.
- Pantalla de estado: conectada o no y últimos pagos enviados.

### 5.3 Asistente de WhatsApp
- Recibe imágenes, audios y texto por la API oficial de WhatsApp Cloud.
- Responde solo a cajeros autorizados; a cualquier otro número le indica que no está registrado.
- Responde siempre en español, de forma corta y clara.

### 5.4 Fuera de alcance (después del hackathon)
- iPhone (iOS no permite leer notificaciones de otras apps).
- Otras billeteras y países (Nequi, Mercado Pago, etc.). El diseño queda listo para sumarlas.
- Conectar el número propio de cada negocio a WhatsApp.
- Planes de pago y cobros.

## 6. Uso de los modelos de Meta

| Modelo | Para qué |
|---|---|
| **Muse Spark** (Meta Model API) | Leer la captura (image understanding) y devolver datos en JSON con esquema fijo (structured output); detectar señales de edición; interpretar las consultas de texto o audio; redactar la respuesta al cajero. |
| **Muse Voice Transcribe** (Meta Model API) | Transcribir las notas de voz de los cajeros. |
| **Muse Code** | Agente de programación usado para construir el proyecto. |

**Regla clave:** la decisión final (real o no coincide) la toma la **comparación con la notificación real**, no solo la opinión del modelo sobre la imagen. La IA lee e interpreta; la verificación se basa en datos.

## 7. Tecnología

| Parte | Tecnología |
|---|---|
| Backend y API | Laravel 13 (PHP 8.3), Sanctum |
| Panel web | React + Tailwind (Vite) dentro del mismo proyecto |
| Base de datos | MySQL 8 |
| Colas | Cola de base de datos de Laravel + Supervisor |
| App móvil | Flutter (Android), en la carpeta `mobile/` del mismo repo |
| WhatsApp | WhatsApp Cloud API oficial (webhook) |
| IA | Meta Model API (Muse Spark, Muse Voice Transcribe), compatible con el SDK de OpenAI |
| Servidor | VPS Ubuntu 24.04 con Nginx · `payguard.micaleth.com` |

## 8. Requisitos importantes

- **Seguridad:** verificar la firma de cada mensaje que llega de WhatsApp; la app y el panel usan tokens; llaves solo en el `.env` (nunca en el repo).
- **Privacidad:** solo se guardan notificaciones de Yape; las demás se ignoran en el propio celular.
- **Velocidad:** respuesta al cajero en menos de 10 segundos en el caso normal.
- **Anti-reúso:** una notificación real solo puede validar una captura; si alguien reenvía la misma captura, se marca como sospechosa.
- **Sin marcas ajenas:** no se usan logos ni imágenes oficiales de Yape o Plin.
- **Datos de prueba:** solo pagos y capturas propios o inventados.

## 9. Riesgos y cómo se manejan

| Riesgo | Plan |
|---|---|
| Los mensajes que inicia PayGuard (alerta al dueño, resultado tras esperar) fuera de la ventana de 24 h requieren plantillas aprobadas por Meta. | Crear una plantilla de alerta al inicio; mientras se aprueba, las alertas también quedan en el panel. |
| El número de prueba de WhatsApp solo envía a pocos números registrados. | Suficiente para la demo (dueño y 1–2 cajeros). |
| El texto de las notificaciones de Yape/Plin puede variar. | Guardar siempre el texto original y leerlo con reglas simples; si no se entiende, usar Muse Spark como respaldo. |
| La notificación llega unos segundos después que la captura. | Estado "Aún no llega" con nueva revisión automática durante 2 minutos. |
| Android puede dormir la app. | Servicio en primer plano y pedir quitar la optimización de batería. |

## 10. Criterios de éxito (demo)

1. Un pago real por Yape enviado por el cajero → ✅ verificado en menos de 10 segundos.
2. Una captura editada (monto cambiado) → ❌ no coincide, con el motivo.
3. La misma captura reenviada por segunda vez → ❌ ya usada.
4. Una nota de voz preguntando por un pago → respuesta correcta.
5. Todo visible en el panel web y la app mostrando las notificaciones capturadas.

## 11. Entregables del hackathon

- Repositorio público con README en inglés que indique qué se construyó durante el evento.
- Video demo de 3 minutos.
- Sistema funcionando en `payguard.micaleth.com`.
