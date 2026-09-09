export interface ClienteCartera {
  idCliente: number;
  nombreCliente: string;
  nombreComercial?: string;
  idRuta?: number;
  nombreRuta?: string;
  idAgente?: number;
  saldoTotal?: number;
  diasVencidoMax?: number;
  telefonoWhatsapp?: string;
}

export type EstatusBloque = 'BORRADOR' | 'PROGRAMADO' | 'EN_PROCESO' | 'PAUSADO' | 'COMPLETADO' | 'CANCELADO';

export interface BloqueEnvio {
  idBloque: number;
  nombre: string;
  idCompania: number;
  idRuta?: number;
  idPlantilla: number;
  tipoEnvio: string;
  estatus: EstatusBloque;
  loteTamanio: number;
  intervaloSegundosLote: number;
  diasMinVencido: number;
  fechaCreacion: string;
}

export interface CrearBloqueRequest {
  nombre: string;
  idCompania: number;
  idRuta?: number;
  idPlantilla: number;
  tipoEnvio?: string;
  loteTamanio?: number;
  intervaloSegundosLote?: number;
  diasMinVencido?: number;
}

export type EstatusBloqueCliente =
  | 'PENDIENTE' | 'ENVIADO' | 'ENTREGADO' | 'LEIDO' | 'RESPONDIDO' | 'ERROR' | 'EXCLUIDO';

export interface BloqueCliente {
  idBloqueCliente: number;
  idBloque: number;
  idCliente: number;
  saldoSnapshot?: number;
  diasVencidoSnapshot?: number;
  ordenEnvio: number;
  estatus: EstatusBloqueCliente;
  motivoExclusion?: string;
  fechaEnvio?: string;
}

export type EstatusConversacion = 'ABIERTA' | 'ESPERANDO_CLIENTE' | 'ESPERANDO_REVISION_HUMANA' | 'CERRADA';

export interface Conversacion {
  idConversacion: number;
  idCliente: number;
  telefonoE164: string;
  estatus: EstatusConversacion;
  requiereAtencionHumana: boolean;
  saldoAlAbrir?: number;
  idAgenteAsignado?: number;
  fechaApertura: string;
  fechaUltimaActividad: string;
}

export type DireccionMensaje = 'ENTRANTE' | 'SALIENTE';

export interface Mensaje {
  idMensaje: number;
  idConversacion: number;
  direccion: DireccionMensaje;
  tipoContenido: string;
  contenidoTexto?: string;
  urlMedia?: string;
  generadoPor: 'SISTEMA' | 'IA' | 'AGENTE_HUMANO' | 'CLIENTE';
  fechaCreacion: string;
}

export interface ChatAdminRequest {
  pregunta: string;
  cveUsuario?: string;
}

export interface ChatAdminResponse {
  respuesta: string;
  idConsultaLog: number;
}
