import { Component, OnInit } from '@angular/core';
import { Router } from '@angular/router';
import { NgxSpinnerService } from 'ngx-spinner';
import Swal from 'sweetalert2';
import { CobranzaIaService } from 'src/app/services/ventas/cobranza-ia/cobranza-ia.service';

/**
 * Pantalla principal de "Cobranza IA": el administrador filtra la
 * cartera vencida de una ruta, selecciona clientes (o los agrega todos)
 * y crea un bloque de envío de WhatsApp con una plantilla, tamaño de
 * lote e intervalo entre lotes.
 */
@Component({
  selector: 'app-bloques-cobranza-ia',
  templateUrl: './bloques-cobranza-ia.component.html',
  styleUrls: ['./bloques-cobranza-ia.component.css'],
})
export class BloquesCobranzaIaComponent implements OnInit {
  idCompania = 19; // Biochem Systems (20 = All 4 Pet's)
  idRuta: number = null;
  diasMinVencido = 1;

  cartera: any[] = [];
  bloques: any[] = [];
  seleccionados: { [idCliente: number]: boolean } = {};
  cargando = false;

  nuevoBloqueNombre = '';
  nuevoBloqueIdPlantilla: number = null;
  loteTamanio = 25;
  intervaloSegundosLote = 60;

  constructor(private cobranzaIaService: CobranzaIaService, private spinner: NgxSpinnerService, private router: Router) {}

  ngOnInit(): void {}

  buscarCarteraVencida(): void {
    if (!this.idCompania) {
      Swal.fire('Filtros incompletos', 'Selecciona la compañía para continuar.', 'warning');
      return;
    }
    this.spinner.show();
    this.cargando = true;
    this.cobranzaIaService.obtenerCarteraVencida(this.idCompania, this.idRuta, this.diasMinVencido)
      .then((res: any[]) => {
        this.cartera = res || [];
        this.seleccionados = {};
        this.spinner.hide();
        this.cargando = false;
      })
      .catch((err) => {
        console.error(err);
        this.spinner.hide();
        this.cargando = false;
        Swal.fire('Error', 'No se pudo obtener la cartera vencida.', 'error');
      });

    if (this.idRuta) {
      this.cobranzaIaService.listarBloquesPorRuta(this.idRuta).then((res: any[]) => (this.bloques = res || []));
    } else {
      this.bloques = [];
    }
  }

  get totalSeleccionados(): number {
    return Object.values(this.seleccionados).filter((v) => v).length;
  }

  toggleCliente(idCliente: number): void {
    this.seleccionados[idCliente] = !this.seleccionados[idCliente];
  }

  crearBloqueConSeleccion(): void {
    const idsCliente = Object.keys(this.seleccionados)
      .filter((id) => this.seleccionados[+id])
      .map((id) => +id);

    if (!this.nuevoBloqueNombre || !this.nuevoBloqueIdPlantilla || idsCliente.length === 0) {
      Swal.fire('Datos incompletos', 'Captura nombre, plantilla y selecciona al menos un cliente.', 'warning');
      return;
    }

    const request = {
      nombre: this.nuevoBloqueNombre,
      idCompania: this.idCompania,
      idRuta: this.idRuta,
      idPlantilla: this.nuevoBloqueIdPlantilla,
      loteTamanio: this.loteTamanio,
      intervaloSegundosLote: this.intervaloSegundosLote,
      diasMinVencido: this.diasMinVencido,
    };

    this.spinner.show();
    this.cobranzaIaService.crearBloque(request).then((bloque: any) => {
      this.cobranzaIaService.agregarClientes(bloque.idBloque, idsCliente).then(() => {
        this.spinner.hide();
        Swal.fire('Bloque creado', `Se creó el bloque "${bloque.nombre}" con ${idsCliente.length} cliente(s).`, 'success');
        this.nuevoBloqueNombre = '';
        this.seleccionados = {};
        this.router.navigate(['dashboard/ventas/cobranza-ia/bloques', bloque.idBloque]);
      });
    }).catch((err) => {
      console.error(err);
      this.spinner.hide();
      Swal.fire('Error', 'No se pudo crear el bloque.', 'error');
    });
  }

  irABloque(idBloque: number): void {
    this.router.navigate(['dashboard/ventas/cobranza-ia/bloques', idBloque]);
  }
}
