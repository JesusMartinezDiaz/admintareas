import { CommonModule } from '@angular/common';
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { CobranzaApiService } from '../../../core/services/cobranza-api.service';

interface Turno {
  autor: 'YO' | 'IA';
  texto: string;
}

@Component({
  selector: 'app-agente-ia-chat',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './agente-ia-chat.component.html',
  styleUrl: './agente-ia-chat.component.css',
})
export class AgenteIaChatComponent {
  readonly turnos = signal<Turno[]>([]);
  readonly enviando = signal(false);
  pregunta = '';

  constructor(private readonly api: CobranzaApiService) {}

  enviar(): void {
    const texto = this.pregunta.trim();
    if (!texto || this.enviando()) return;

    this.turnos.update((t) => [...t, { autor: 'YO', texto }]);
    this.pregunta = '';
    this.enviando.set(true);

    this.api.preguntarAgenteIa({ pregunta: texto }).subscribe({
      next: (resp) => {
        this.turnos.update((t) => [...t, { autor: 'IA', texto: resp.respuesta }]);
        this.enviando.set(false);
      },
      error: () => {
        this.turnos.update((t) => [...t, { autor: 'IA', texto: 'Ocurrió un error consultando la cartera.' }]);
        this.enviando.set(false);
      },
    });
  }
}
