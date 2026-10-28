class Strings {
  static const String appName = 'PayGuard';
  static const String brandPay = 'Pay';
  static const String brandGuard = 'Guard';
  static const String brandSlogan = 'Cada pago, verificado.';

  static const String emailLabel = 'Correo';
  static const String emailHint = 'tu@negocio.com';
  static const String passwordLabel = 'Contraseña';
  static const String passwordHint = 'Tu contraseña';
  static const String loginButton = 'Entrar';
  static const String loginBottom = 'Usa la misma cuenta del panel web';
  static const String emailRequired = 'Escribe tu correo.';
  static const String emailInvalid = 'Escribe un correo válido.';
  static const String passwordRequired = 'Escribe tu contraseña.';
  static const String serverLabel = 'Dirección del servidor';
  static const String serverInvalid = 'Escribe una dirección válida (http:// o https://).';

  static const String incompleteLabel = 'Configuración incompleta';
  static const String incompleteSubtitle = 'Toca cada paso pendiente para resolverlo.';
  static const String readyLabel = 'Activo';
  static const String readyTitle = 'Protegiendo tus pagos';
  static const String readySubtitle = 'Cada Yape que recibas llega a PayGuard al instante.';

  static String incompleteTitle(int missing) =>
      'Faltan $missing pasos para proteger tus pagos';

  static const String rowNotifications = 'Leer notificaciones';
  static const String rowNotificationsDetail = 'Para ver los Yapes que recibes';
  static const String actionActivate = 'Activar';
  static const String rowAlwaysOn = 'Funcionar siempre';
  static const String rowAlwaysOnDetail = 'Que Android no la duerma de noche';
  static const String actionAllow = 'Permitir';
  static const String rowConnected = 'Conectado al servidor';
  static const String rowSessionExpired = 'La sesión venció';
  static const String actionLogin = 'Entrar';
  static const String neverContacted = 'Sin contactar todavía';

  static const String restrictedTitle = 'Si el interruptor no se deja activar';
  static const String restrictedSteps =
      'En Ajustes > Apps > PayGuard, toca el menú y elige "Permitir ajustes restringidos". Después vuelve y activa el acceso.';

  static const String statToday = 'Hoy';
  static const String statPending = 'Pendientes';
  static const String statConnection = 'Conexión';

  static String paymentsToday(int count) => count == 1 ? '1 pago' : '$count pagos';

  static const String recentTitle = 'Últimos pagos';
  static const String seeAll = 'Ver todos';
  static const String recentEmpty = 'Cuando recibas Yapes, aparecen aquí.';
  static const String unparsedPayment = 'Pago sin leer';
  static const String codeLabel = 'Cód.';

  static const String historyTitle = 'Últimos pagos';
  static const String historyEmpty = 'Aún no hay pagos registrados.';
  static const String sessionExpiredForHistory =
      'La sesión venció. Vuelve atrás e inicia sesión de nuevo.';

  static const String accountTitle = 'Cuenta';
  static const String accountBusiness = 'Negocio';
  static const String accountDevice = 'Este celular';
  static const String accountPayments = 'Últimos pagos';
  static const String autostartTitle = 'Inicio automático';
  static const String autostartBody =
      'En Xiaomi, Huawei y otras marcas, permite el inicio automático de PayGuard para que siga funcionando tras reiniciar.';
  static const String logoutButton = 'Cerrar sesión';
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
