import { Component } from '@angular/core';
import { CobranzaIaService } from 'src/app/services/ventas/cobranza-ia/cobranza-ia.service';

interface TurnoChat {
  autor: 'YO' | 'IA';
  texto: string;
}

@Component({
  selector: 'app-agente-ia-chat-cobranza-ia',
  templateUrl: './agente-ia-chat-cobranza-ia.component.html',
  styleUrls: ['./agente-ia-chat-cobranza-ia.component.css'],
})
export class AgenteIaChatCobranzaIaComponent {
  turnos: TurnoChat[] = [];
  pregunta = '';
  enviando = false;

  constructor(private cobranzaIaService: CobranzaIaService) {}

  enviar(): void {
    const texto = (this.pregunta || '').trim();
    if (!texto || this.enviando) {
      return;
    }
    this.turnos.push({ autor: 'YO', texto });
    this.pregunta = '';
    this.enviando = true;

    this.cobranzaIaService.preguntarAgenteIa(texto).then((resp: any) => {
      this.turnos.push({ autor: 'IA', texto: resp.respuesta });
      this.enviando = false;
    }).catch(() => {
      this.turnos.push({ autor: 'IA', texto: 'Ocurrió un error consultando la cartera.' });
      this.enviando = false;
    });
  }
}
