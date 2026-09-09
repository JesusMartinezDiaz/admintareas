import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { NgxSpinnerService } from 'ngx-spinner';
import Swal from 'sweetalert2';
import { CobranzaIaService } from 'src/app/services/ventas/cobranza-ia/cobranza-ia.service';

@Component({
  selector: 'app-bloque-detalle-cobranza-ia',
  templateUrl: './bloque-detalle-cobranza-ia.component.html',
  styleUrls: ['./bloque-detalle-cobranza-ia.component.css'],
})
export class BloqueDetalleCobranzaIaComponent implements OnInit {
  idBloque: number;
  bloque: any = null;
  clientes: any[] = [];

  constructor(
    private route: ActivatedRoute,
    private cobranzaIaService: CobranzaIaService,
    private spinner: NgxSpinnerService,
  ) {}

  ngOnInit(): void {
    this.idBloque = +this.route.snapshot.paramMap.get('idBloque');
    this.recargar();
  }

  recargar(): void {
    this.spinner.show();
    this.cobranzaIaService.obtenerBloque(this.idBloque).then((b) => {
      this.bloque = b;
      this.spinner.hide();
    }).catch(() => this.spinner.hide());

    this.cobranzaIaService.listarClientesDeBloque(this.idBloque).then((c: any[]) => (this.clientes = c || []));
  }

  contadorPorEstatus(estatus: string): number {
    return this.clientes.filter((c) => c.estatus === estatus).length;
  }

  iniciar(): void {
    this.spinner.show();
    this.cobranzaIaService.iniciarEnvio(this.idBloque).then(() => {
      this.spinner.hide();
      this.recargar();
    }).catch(() => {
      this.spinner.hide();
      Swal.fire('Error', 'No se pudo iniciar el envío del bloque.', 'error');
    });
  }

  pausar(): void {
    this.spinner.show();
    this.cobranzaIaService.pausarEnvio(this.idBloque).then(() => {
      this.spinner.hide();
      this.recargar();
    }).catch(() => {
      this.spinner.hide();
      Swal.fire('Error', 'No se pudo pausar el bloque.', 'error');
    });
  }

  quitar(idCliente: number): void {
    Swal.fire({
      title: '¿Quitar cliente del bloque?',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, quitar',
      cancelButtonText: 'Cancelar',
    }).then((result) => {
      if (result.value) {
        this.cobranzaIaService.quitarCliente(this.idBloque, idCliente).then(() => this.recargar());
      }
    });
  }
}
