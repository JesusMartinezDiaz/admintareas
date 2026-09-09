import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'bloques' },
  {
    path: 'bloques',
    loadComponent: () =>
      import('./features/cobranza/bloques-list/bloques-list.component').then((m) => m.BloquesListComponent),
  },
  {
    path: 'bloques/:idBloque',
    loadComponent: () =>
      import('./features/cobranza/bloque-detail/bloque-detail.component').then((m) => m.BloqueDetailComponent),
  },
  {
    path: 'conversaciones',
    loadComponent: () =>
      import('./features/cobranza/conversaciones/conversaciones.component').then((m) => m.ConversacionesComponent),
  },
  {
    path: 'agente-ia',
    loadComponent: () =>
      import('./features/cobranza/agente-ia-chat/agente-ia-chat.component').then((m) => m.AgenteIaChatComponent),
  },
];
