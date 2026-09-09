import { CommonModule } from '@angular/common';
import { Component, OnInit, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { CobranzaApiService } from '../../../core/services/cobranza-api.service';
import { BloqueCliente, BloqueEnvio } from '../../../core/models/cobranza.models';

@Component({
  selector: 'app-bloque-detail',
  standalone: true,
  imports: [CommonModule, RouterLink],
  templateUrl: './bloque-detail.component.html',
  styleUrl: './bloque-detail.component.css',
})
export class BloqueDetailComponent implements OnInit {
  readonly bloque = signal<BloqueEnvio | null>(null);
  readonly clientes = signal<BloqueCliente[]>([]);
  private idBloque!: number;

  constructor(
    private readonly route: ActivatedRoute,
    private readonly api: CobranzaApiService,
  ) {}

  ngOnInit(): void {
    this.idBloque = Number(this.route.snapshot.paramMap.get('idBloque'));
    this.recargar();
  }

  recargar(): void {
    this.api.obtenerBloque(this.idBloque).subscribe((b) => this.bloque.set(b));
    this.api.listarClientesDeBloque(this.idBloque).subscribe((c) => this.clientes.set(c));
  }

  iniciar(): void {
    this.api.iniciarEnvio(this.idBloque).subscribe(() => this.recargar());
  }

  pausar(): void {
    this.api.pausarEnvio(this.idBloque).subscribe(() => this.recargar());
  }

  quitar(idCliente: number): void {
    this.api.quitarCliente(this.idBloque, idCliente).subscribe(() => this.recargar());
  }

  contadorPorEstatus(estatus: string): number {
    return this.clientes().filter((c) => c.estatus === estatus).length;
  }
}
