import { HttpClient } from '@angular/common/http';
import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  BloqueCliente,
  BloqueEnvio,
  ChatAdminRequest,
  ChatAdminResponse,
  ClienteCartera,
  Conversacion,
  CrearBloqueRequest,
  Mensaje,
} from '../models/cobranza.models';

@Injectable({ providedIn: 'root' })
export class CobranzaApiService {
  private readonly baseUrl = environment.apiBaseUrl;

  constructor(private readonly http: HttpClient) {}

  obtenerCarteraVencida(idCompania: number, idRuta?: number, diasMinVencido = 1): Observable<ClienteCartera[]> {
    const params: Record<string, string> = { idCompania: String(idCompania), diasMinVencido: String(diasMinVencido) };
    if (idRuta) {
      params['idRuta'] = String(idRuta);
    }
    return this.http.get<ClienteCartera[]>(`${this.baseUrl}/bloques/cartera-vencida`, { params });
  }

  listarBloquesPorRuta(idRuta: number): Observable<BloqueEnvio[]> {
    return this.http.get<BloqueEnvio[]>(`${this.baseUrl}/bloques`, { params: { idRuta: String(idRuta) } });
  }

  obtenerBloque(idBloque: number): Observable<BloqueEnvio> {
    return this.http.get<BloqueEnvio>(`${this.baseUrl}/bloques/${idBloque}`);
  }

  listarClientesDeBloque(idBloque: number): Observable<BloqueCliente[]> {
    return this.http.get<BloqueCliente[]>(`${this.baseUrl}/bloques/${idBloque}/clientes`);
  }

  crearBloque(request: CrearBloqueRequest, cveUsuario?: string): Observable<BloqueEnvio> {
    const params: Record<string, string> = {};
    if (cveUsuario) {
      params['cveUsuario'] = cveUsuario;
    }
    return this.http.post<BloqueEnvio>(`${this.baseUrl}/bloques`, request, { params });
  }

  agregarClientes(idBloque: number, idsCliente: number[], autocompletarDesdeRuta = false): Observable<BloqueCliente[]> {
    return this.http.post<BloqueCliente[]>(`${this.baseUrl}/bloques/${idBloque}/clientes`, {
      idsCliente,
      autocompletarDesdeRuta,
    });
  }

  quitarCliente(idBloque: number, idCliente: number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/bloques/${idBloque}/clientes/${idCliente}`);
  }

  iniciarEnvio(idBloque: number): Observable<BloqueEnvio> {
    return this.http.post<BloqueEnvio>(`${this.baseUrl}/bloques/${idBloque}/iniciar`, {});
  }

  pausarEnvio(idBloque: number): Observable<BloqueEnvio> {
    return this.http.post<BloqueEnvio>(`${this.baseUrl}/bloques/${idBloque}/pausar`, {});
  }

  preguntarAgenteIa(request: ChatAdminRequest): Observable<ChatAdminResponse> {
    return this.http.post<ChatAdminResponse>(`${this.baseUrl}/agente-ia/chat`, request);
  }

  conversacionesPendientesAtencion(): Observable<Conversacion[]> {
    return this.http.get<Conversacion[]>(`${this.baseUrl}/conversaciones/pendientes-atencion`);
  }

  mensajesDeConversacion(idConversacion: number): Observable<Mensaje[]> {
    return this.http.get<Mensaje[]>(`${this.baseUrl}/conversaciones/${idConversacion}/mensajes`);
  }

  tomarConversacion(idConversacion: number, idAgente: number): Observable<Conversacion> {
    return this.http.post<Conversacion>(
      `${this.baseUrl}/conversaciones/${idConversacion}/tomar`,
      {},
      { params: { idAgente: String(idAgente) } },
    );
  }

  cerrarConversacion(idConversacion: number): Observable<Conversacion> {
    return this.http.post<Conversacion>(`${this.baseUrl}/conversaciones/${idConversacion}/cerrar`, {});
  }
}
