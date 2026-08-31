<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Domain\Vbmapp\Catalog\CatalogCache;
use App\Models\Vbmapp\Item;
use App\Models\Vbmapp\MaterialPage;
use App\Models\Vbmapp\Stimulus;
use App\Support\ImageUploader;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Curadoria do acervo: é onde o recorte automático vira acervo confiável.
 *
 * O recorte acerta a maioria mas erra o bastante para não ir direto — junta
 * dois estímulos num só, corta figura pela metade. Aqui a psicóloga confirma
 * o rótulo, descarta o que não presta e sobe imagem quando falta.
 */
class StimulusCuration extends Component
{
    use WithFileUploads;

    public int $level = 1;

    public ?int $itemId = null;

    public array $rotulos = [];

    public ?string $salvoEm = null;

    public $novaImagem = null;

    public function mount(): void
    {
        $this->itemId ??= $this->marcos->first()?->id;
        $this->carregarRotulos();
    }

    /** Os marcos do nível que usam material de aplicação. */
    #[Computed]
    public function marcos(): Collection
    {
        return Item::with('area')
            ->where('level', $this->level)
            ->where('response_type', 'counter_stimuli')
            ->orderBy('area_id')->orderBy('position')
            ->get();
    }

    #[Computed]
    public function marco(): ?Item
    {
        return $this->itemId === null ? null : Item::with('area', 'stimuli')->find($this->itemId);
    }

    #[Computed]
    public function paginas(): Collection
    {
        $marco = $this->marco;

        if ($marco === null) {
            return collect();
        }

        return MaterialPage::where('level', $this->level)
            ->where('area_id', $marco->area_id)
            ->where('item_position', $marco->position)
            ->orderBy('page_number')
            ->get();
    }

    private function carregarRotulos(): void
    {
        $this->rotulos = $this->marco?->stimuli
            ->mapWithKeys(fn (Stimulus $e) => [$e->id => $e->label])
            ->all() ?? [];
    }

    public function selecionarMarco(int $itemId): void
    {
        $this->itemId = $itemId;
        unset($this->marco, $this->paginas);
        $this->carregarRotulos();
    }

    public function updatedRotulos(mixed $valor, string $chave): void
    {
        $estimulo = Stimulus::find((int) $chave);

        if ($estimulo === null) {
            return;
        }

        $estimulo->update(['label' => trim((string) $valor)]);
        $this->confirmar();
    }

    public function descartar(int $estimuloId): void
    {
        $estimulo = Stimulus::findOrFail($estimuloId);
        $estimulo->delete();

        unset($this->marco);
        $this->carregarRotulos();
        $this->confirmar();
    }

    public function subirImagem(): void
    {
        $this->validate([
            'novaImagem' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $marco = $this->marco;
        $caminho = app(ImageUploader::class)->store(
            $this->novaImagem, 'public', "vbmapp/estimulos/nivel-{$this->level}/manual",
        );

        Stimulus::create([
            'item_id' => $marco->id,
            'label' => '',
            'image_path' => $caminho,
            'source_page' => 0, // 0 = enviado à mão, não veio do recorte
            'position' => ($marco->stimuli->max('position') ?? 0) + 1,
        ]);

        $this->novaImagem = null;
        unset($this->marco);
        $this->carregarRotulos();
        $this->confirmar();
    }

    private function confirmar(): void
    {
        // O catálogo está em cache e acabou de mudar.
        CatalogCache::flush();
        $this->salvoEm = now()->format('H:i');
    }

    public function render()
    {
        return view('livewire.admin.stimulus-curation');
    }
}
