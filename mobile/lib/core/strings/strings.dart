class Strings {
  static const String appName = 'PayGuard';

  static const String loginTitle = 'Iniciar sesión';
  static const String loginSubtitle = 'Usa tu cuenta de dueño de PayGuard.';
  static const String emailLabel = 'Correo';
  static const String emailHint = 'tucorreo@ejemplo.com';
  static const String passwordLabel = 'Contraseña';
  static const String loginButton = 'Entrar';
  static const String emailRequired = 'Escribe tu correo.';
  static const String emailInvalid = 'Escribe un correo válido.';
  static const String passwordRequired = 'Escribe tu contraseña.';
  static const String serverLabel = 'Dirección del servidor';
  static const String serverInvalid = 'Escribe una dirección válida (http:// o https://).';

  static const String statusTitle = 'Estado';
  static const String statusReady = 'Lista para recibir pagos.';
  static const String statusNotReady = 'Te faltan pasos para activar la app.';
  static const String notificationsTitle = 'Leer notificaciones';
  static const String notificationsOk = 'La app puede leer las notificaciones de Yape.';
  static const String notificationsMissing = 'Sin este permiso no se capturan los pagos.';
  static const String notificationsButton = 'Abrir ajustes';
  static const String restrictedTitle = 'Si el interruptor no se deja activar';
  static const String restrictedSteps =
      '1. Abre Ajustes del celular.\n2. Entra a Apps y busca PayGuard.\n3. Toca el menú (tres puntos) y elige "Permitir ajustes restringidos".\n4. Vuelve y activa el acceso a las notificaciones.';
  static const String batteryTitle = 'Sin optimización de batería';
  static const String batteryOk = 'La app puede trabajar con la pantalla apagada.';
  static const String batteryMissing = 'Obligatorio: sin esto Android detiene la app.';
  static const String batteryButton = 'Pedir exención';
  static const String sessionTitle = 'Sesión';
  static const String sessionActive = 'Sesión iniciada.';
  static const String sessionMissing = 'No has iniciado sesión.';
  static const String sessionInvalid = 'La sesión venció. Inicia sesión de nuevo.';
  static const String sessionButton = 'Iniciar sesión';
  static const String connectionButton = 'Probar conexión';
  static const String connectionOk = 'Conexión correcta.';
  static const String queueTitle = 'Pagos enviados';
  static const String queueEmpty = 'Aún no se envía ningún pago.';
  static const String queuePending = 'pagos por enviar';
  static const String queueLastSent = 'Último envío';
  static const String queueNever = 'ninguno todavía';
  static const String queueSendNow = 'Enviar ahora';
  static const String queueSeeAll = 'Ver últimos pagos';
  static const String autostartTitle = 'Inicio automático';
  static const String autostartBody =
      'En Xiaomi, Huawei y otras marcas, permite el inicio automático de PayGuard para que siga funcionando tras reiniciar.';
  static const String autostartButton = 'Abrir ajustes de la app';
  static const String refreshButton = 'Actualizar';

  static const String historyTitle = 'Últimos pagos';
  static const String historyEmpty = 'Aún no hay pagos enviados desde este celular.';

  static const String logoutTitle = 'Cerrar sesión';
  static const String logoutConfirm =
      'Se borran los pagos pendientes de envío y este celular deja de enviar pagos. ¿Seguro?';
  static const String logoutCancel = 'Cancelar';
  static const String logoutConfirmButton = 'Sí, cerrar sesión';

  static const String genericError = 'Algo salió mal. Inténtalo de nuevo.';
  static const String noConnection = 'Sin conexión al servidor. Revisa tu internet.';
  static const String unexpectedResponse = 'Respuesta inesperada del servidor.';
  static const String retry = 'Reintentar';
}
