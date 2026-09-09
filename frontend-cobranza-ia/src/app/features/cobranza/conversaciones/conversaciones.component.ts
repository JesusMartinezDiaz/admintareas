import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { CobranzaApiService } from '../../../core/services/cobranza-api.service';
import { Conversacion, Mensaje } from '../../../core/models/cobranza.models';

/**
 * Bandeja de conversaciones que la IA escaló a revisión humana (cliente
 * no reconoce la deuda, pide hablar con alguien, o el agente no tuvo
 * suficiente confianza para seguir solo).
 */
@Component({
  selector: 'app-conversaciones',
  standalone: true,
  imports: [CommonModule],
  templateUrl: './conversaciones.component.html',
  styleUrl: './conversaciones.component.css',
})
export class ConversacionesComponent implements OnInit {
  readonly conversaciones = signal<Conversacion[]>([]);
  readonly seleccionada = signal<Conversacion | null>(null);
  readonly mensajes = signal<Mensaje[]>([]);

  constructor(private readonly api: CobranzaApiService) {}

  ngOnInit(): void {
    this.recargarLista();
  }

  recargarLista(): void {
    this.api.conversacionesPendientesAtencion().subscribe((c) => this.conversaciones.set(c));
  }

  seleccionar(conversacion: Conversacion): void {
    this.seleccionada.set(conversacion);
    this.api.mensajesDeConversacion(conversacion.idConversacion).subscribe((m) => this.mensajes.set(m));
  }

  cerrar(): void {
    const conv = this.seleccionada();
    if (!conv) return;
    this.api.cerrarConversacion(conv.idConversacion).subscribe(() => {
      this.seleccionada.set(null);
      this.recargarLista();
    });
  }
}
