import { CommonModule } from '@angular/common';
import { Component, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { CobranzaApiService } from '../../../core/services/cobranza-api.service';
import { BloqueEnvio, ClienteCartera, CrearBloqueRequest } from '../../../core/models/cobranza.models';

/**
 * Pantalla principal pedida por el negocio: administrar, por ruta y por
 * bloques, qué clientes con cartera vencida se van a contactar por
 * WhatsApp, antes de disparar el envío.
 */
@Component({
  selector: 'app-bloques-list',
  standalone: true,
  imports: [CommonModule, FormsModule, RouterLink],
  templateUrl: './bloques-list.component.html',
  styleUrl: './bloques-list.component.css',
})
export class BloquesListComponent {
  readonly idCompania = signal(19); // Biochem Systems
  readonly idRuta = signal<number | null>(null);
  readonly diasMinVencido = signal(1);

  readonly cartera = signal<ClienteCartera[]>([]);
  readonly bloques = signal<BloqueEnvio[]>([]);
  readonly clientesSeleccionados = signal<Set<number>>(new Set());
  readonly cargando = signal(false);

  nuevoBloqueNombre = '';
  nuevoBloqueIdPlantilla = 1;

  constructor(private readonly api: CobranzaApiService) {}

  buscarCartera(): void {
    this.cargando.set(true);
    this.api
      .obtenerCarteraVencida(this.idCompania(), this.idRuta() ?? undefined, this.diasMinVencido())
      .subscribe({
        next: (clientes) => {
          this.cartera.set(clientes);
          this.cargando.set(false);
        },
        error: () => this.cargando.set(false),
      });

    if (this.idRuta()) {
      this.api.listarBloquesPorRuta(this.idRuta()!).subscribe((b) => this.bloques.set(b));
    }
  }

  toggleCliente(idCliente: number): void {
    const seleccion = new Set(this.clientesSeleccionados());
    if (seleccion.has(idCliente)) {
      seleccion.delete(idCliente);
    } else {
      seleccion.add(idCliente);
    }
    this.clientesSeleccionados.set(seleccion);
  }

  crearBloqueConSeleccion(): void {
    if (!this.nuevoBloqueNombre || this.clientesSeleccionados().size === 0) {
      return;
    }
    const request: CrearBloqueRequest = {
      nombre: this.nuevoBloqueNombre,
      idCompania: this.idCompania(),
      idRuta: this.idRuta() ?? undefined,
      idPlantilla: this.nuevoBloqueIdPlantilla,
      diasMinVencido: this.diasMinVencido(),
    };
    this.api.crearBloque(request).subscribe((bloque) => {
      this.api.agregarClientes(bloque.idBloque, Array.from(this.clientesSeleccionados())).subscribe(() => {
        this.clientesSeleccionados.set(new Set());
        this.nuevoBloqueNombre = '';
        if (this.idRuta()) {
          this.api.listarBloquesPorRuta(this.idRuta()!).subscribe((b) => this.bloques.set(b));
        }
      });
    });
  }
}
