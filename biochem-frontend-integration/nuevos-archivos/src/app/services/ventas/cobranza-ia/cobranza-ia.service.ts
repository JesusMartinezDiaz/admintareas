import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from 'src/environments/environment';
import { GeneralService } from 'src/app/utils/general.service';

/**
 * Cliente del backend "cobranza-ia" (agente virtual de cobranza por
 * WhatsApp). Sigue el mismo patrón que EstadoCuentaService: promesas
 * envolviendo HttpClient, y GeneralService.getParams para query params.
 * El interceptor global (AuthInterceptor) ya agrega el header
 * Authorization con el token de sessionStorage, así que aquí no hace
 * falta hacerlo manualmente.
 */
@Injectable({
  providedIn: 'root',
})
export class CobranzaIaService {
  private baseUrl: string = environment.cobranzaIA;

  constructor(private http: HttpClient, private general: GeneralService) {}

  // ---------- Cartera / bloques ----------

  obtenerCarteraVencida(idCompania: number, idRuta?: number, diasMinVencido: number = 1): Promise<any> {
    const paramsData: { param: string; value: any }[] = [
      { param: 'idCompania', value: idCompania },
      { param: 'diasMinVencido', value: diasMinVencido },
    ];
    if (idRuta) {
      paramsData.push({ param: 'idRuta', value: idRuta });
    }
    const params = this.general.getParams(paramsData);
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/bloques/cartera-vencida`, { params })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  listarBloquesPorRuta(idRuta: number): Promise<any> {
    const params = this.general.getParams([{ param: 'idRuta', value: idRuta }]);
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/bloques`, { params })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  obtenerBloque(idBloque: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/bloques/${idBloque}`)
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  listarClientesDeBloque(idBloque: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/bloques/${idBloque}/clientes`)
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  crearBloque(request: any, cveUsuario?: string): Promise<any> {
    const params = cveUsuario ? this.general.getParams([{ param: 'cveUsuario', value: cveUsuario }]) : undefined;
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/bloques`, request, { params })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  agregarClientes(idBloque: number, idsCliente: number[], autocompletarDesdeRuta: boolean = false): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/bloques/${idBloque}/clientes`, { idsCliente, autocompletarDesdeRuta })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  quitarCliente(idBloque: number, idCliente: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.delete(`${this.baseUrl}/bloques/${idBloque}/clientes/${idCliente}`)
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  iniciarEnvio(idBloque: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/bloques/${idBloque}/iniciar`, {})
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  pausarEnvio(idBloque: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/bloques/${idBloque}/pausar`, {})
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  // ---------- Conversaciones ----------

  conversacionesPendientesAtencion(): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/conversaciones/pendientes-atencion`)
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  mensajesDeConversacion(idConversacion: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.get(`${this.baseUrl}/conversaciones/${idConversacion}/mensajes`)
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  tomarConversacion(idConversacion: number, idAgente: number): Promise<any> {
    const params = this.general.getParams([{ param: 'idAgente', value: idAgente }]);
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/conversaciones/${idConversacion}/tomar`, {}, { params })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  cerrarConversacion(idConversacion: number): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/conversaciones/${idConversacion}/cerrar`, {})
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }

  // ---------- Agente de datos (chat admin) ----------

  preguntarAgenteIa(pregunta: string, cveUsuario?: string): Promise<any> {
    return new Promise((resolve, reject) => {
      this.http.post(`${this.baseUrl}/agente-ia/chat`, { pregunta, cveUsuario })
        .subscribe((response) => resolve(response), (error) => reject(error));
    });
  }
}
