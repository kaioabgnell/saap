import './bootstrap';
import { registrarFilaSalvamento } from './fila-salvamento';
import { registrarGraficoMarcos } from './grafico-marcos';

// O Livewire 4 traz o próprio Alpine — iniciar outra instância aqui provocaria
// o aviso de "multiple instances of Alpine" e estado duplicado.
document.addEventListener('livewire:init', () => {
    registrarFilaSalvamento(window.Alpine, window.Livewire);
    registrarGraficoMarcos(window.Alpine);
});
