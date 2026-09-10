import { Component, OnInit } from '@angular/core';
import { NgxSpinnerService } from 'ngx-spinner';
import Swal from 'sweetalert2';
import { CobranzaIaService } from 'src/app/services/ventas/cobranza-ia/cobranza-ia.service';

/**
 * Bandeja de conversaciones que el agente de IA escaló a revisión
 * humana (cliente no reconoce la deuda, pide hablar con alguien, o la
 * IA no tuvo suficiente confianza para seguir sola).
 */
@Component({
  selector: 'app-conversaciones-cobranza-ia',
  templateUrl: './conversaciones-cobranza-ia.component.html',
  styleUrls: ['./conversaciones-cobranza-ia.component.css'],
})
export class ConversacionesCobranzaIaComponent implements OnInit {
  conversaciones: any[] = [];
  seleccionada: any = null;
  mensajes: any[] = [];

  constructor(private cobranzaIaService: CobranzaIaService, private spinner: NgxSpinnerService) {}

  ngOnInit(): void {
    this.recargarLista();
  }

  recargarLista(): void {
    this.spinner.show();
    this.cobranzaIaService.conversacionesPendientesAtencion().then((c: any[]) => {
      this.conversaciones = c || [];
      this.spinner.hide();
    }).catch(() => this.spinner.hide());
  }

  seleccionar(conversacion: any): void {
    this.seleccionada = conversacion;
    this.cobranzaIaService.mensajesDeConversacion(conversacion.idConversacion).then((m: any[]) => (this.mensajes = m || []));
  }

  cerrar(): void {
    if (!this.seleccionada) {
      return;
    }
    Swal.fire({
      title: '¿Cerrar esta conversación?',
      type: 'question',
      showCancelButton: true,
      confirmButtonText: 'Sí, cerrar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.value) {
        this.cobranzaIaService.cerrarConversacion(this.seleccionada.idConversacion).then(() => {
          this.seleccionada = null;
          this.mensajes = [];
          this.recargarLista();
        });
      }
    });
  }
}
